/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

class CWidgetHostOverview extends CWidget {
  onInitialize() {
    this.rendered = false;
    // State for interface tickers
    this.interfaceTicker = new Map();
    // State for percent tickers
    this.percentTicker = new Map();
    // Previous settled values for direction arrows
    this.prevValues = new Map();
    // Sparkline state
    this.sparklineState = {
      open: false,
      metricKey: null,
      itemRef: null,
      fallbackTitle: '',
      period: '1h',
      data: null,
      message: ''
    };
    this.itemMap = {};
    this._sparklineDrawState = null;
    this._sparklineSnapshot = null;
    this._sparklineUpdatesPaused = false;
    this._sparklineAbortController = null;
    this._sparklineKeydownHandler = null;
    this._sparklineRedrawRaf = null;
    this._sparklineRequestId = 0;
    this._sparklineResizeObserver = null;
  }

  onDeactivate() {
    this.closeSparkline();
  }

  onResize() {
    this._scheduleSparklineRedraw();
  }

  onDestroy() {
    this._cancelSparklineRequest();
    this._removeSparklineKeydownHandler();
    if (this._sparklineResizeObserver) {
      this._sparklineResizeObserver.disconnect();
      this._sparklineResizeObserver = null;
    }
    if (this._sparklineRedrawRaf) {
      cancelAnimationFrame(this._sparklineRedrawRaf);
      this._sparklineRedrawRaf = null;
    }
    this._resumeSparklineUpdating();
  }

  // Compare new value to previous and return direction arrow
  getArrow(key, newValue) {
    const prev = this.prevValues.get(key);
    this.prevValues.set(key, newValue);
    if (prev === undefined) return null;
    if (newValue > prev) return 'up';
    if (newValue < prev) return 'down';
    return null;
  }

  // Toggle a class on the container div if the header is hidden
  updateViewModeAttr() {
    try {
      if (!this._body) return;
      const container = this._body.querySelector("#container");
      if (!container) return;
      if (typeof this.getViewMode === "function") {
        const mode = this.getViewMode();
        container.classList.toggle("hidden_header", mode);
      }
    } catch (_) {}
  }

  // Set width and color for a fill element
  updateFillWidth(element, percent, fields = null) {
    if (!element) return;
    const p = Number(percent);
    const targetWidth = `${p}%`;
    element.style.width = targetWidth;

    // Apply color directly from config fields if available
    if (!fields) return;

    if (fields["color_scheme"] == "1") {
      element.style.backgroundColor = `#${fields["fill_color"]}`;
    } else {
      const highThreshold = Number(fields["th_num_1"]);
      const mediumThreshold = Number(fields["th_num_2"]);
      if (p > highThreshold) {
        element.style.backgroundColor = `#${fields["th_color_1"]}`;
      } else if (p > mediumThreshold) {
        element.style.backgroundColor = `#${fields["th_color_2"]}`;
      } else {
        element.style.backgroundColor = `#${fields["th_color_3"]}`;
      }
    }
  }

  // Widget lifecycle
  setContents(response) {
    // Render the skeleton once then update values
    if (!this.rendered) {
      super.setContents(response);
      this.rendered = true;
      // Ensure the container reflects the current view mode
      this.updateViewModeAttr();
      // Attach sparkline click handlers
      this.attachSparklineHandlers();
      this._setupSparklineResizeObserver();
    }

    // Store item map for sparkline lookups
    this._fields = response.config || this._fields || {};
    if (response.item_map) {
      this.itemMap = response.item_map;
    }
    // Store disk/partition item names from row data
    if (response.disks) {
      for (const row of response.disks) {
        if (row.item_name) this.itemMap[`disk:${row.name}`] = row.item_name;
      }
    }
    if (response.partitions) {
      for (const row of response.partitions) {
        if (row.item_name) this.itemMap[`partition:${row.name}`] = row.item_name;
      }
    }
    if (Array.isArray(response.interfaces)) {
      for (const row of response.interfaces) {
        if (row.key && row.item_ref) {
          this.itemMap[`iface:${row.key}`] = row.item_ref;
        }
      }
    }

    // If config is present update values
    if (response.config && Object.keys(response.config).length > 0) {
      this.updateValues(response);
    }
  }

  // Update values in DOM
  updateValues(response) {
    if (!response || !response.config) return;

    // Keep container view mode in sync for styling
    this.updateViewModeAttr();

    const fields = response.config;

    // Helper for percent ticker
    const clampPercent = (n) => {
      const v = Math.round(Number(n) || 0);
      return Math.max(0, Math.min(100, v));
    };

    const renderTextWithArrow = (textEl, labelText, arrowDir) => {
      if (!textEl) return;
      textEl.innerHTML = '';
      textEl.textContent = labelText;
      if (arrowDir) {
        const span = document.createElement('span');
        span.className = `dir-arrow dir-${arrowDir}`;
        textEl.appendChild(document.createTextNode(' '));
        textEl.appendChild(span);
      }
    };

    const startPercentTicker = (key, name, toPercent, textEl, arrowDir = null) => {
      const state = this.percentTicker.get(key) || { value: 0, rafId: null };
      if (state.rafId) cancelAnimationFrame(state.rafId);
      const from = Number(state.value) || 0;
      const to = clampPercent(toPercent);
      const start = performance.now();
      const dur = 500;
      const step = (now) => {
        const t = Math.min(1, (now - start) / dur);
        const ease = 1 - Math.pow(1 - t, 3);
        const cur = Math.round(from + (to - from) * ease);
        const text = name ? `${name} — ${cur}%` : `${cur}%`;
        renderTextWithArrow(textEl, text, arrowDir);
        state.value = cur;
        if (t < 1) {
          state.rafId = requestAnimationFrame(step);
        } else {
          state.value = to;
          state.rafId = null;
          const finalText = name ? `${name} — ${to}%` : `${to}%`;
          renderTextWithArrow(textEl, finalText, arrowDir);
        }
      };
      state.rafId = requestAnimationFrame(step);
      this.percentTicker.set(key, state);
    };

    const setSingleMetricNoData = (fill, text) => {
      const bar = fill ? fill.closest('.bar') : null;
      const data = bar ? bar.closest('.data') : null;
      if (bar) bar.style.display = 'none';
      if (data) data.classList.add('no-bar');
      if (text) text.textContent = 'No data';
    };

    const setSingleMetricVisible = (fill) => {
      const bar = fill ? fill.closest('.bar') : null;
      const data = bar ? bar.closest('.data') : null;
      if (bar) bar.style.display = '';
      if (data) data.classList.remove('no-bar');
    };

    const setMultiMetricNoData = (subcell, labelName) => {
      if (!subcell) return;
      const bar = subcell.querySelector('.bar');
      const text = subcell.querySelector('.text');

      subcell.classList.add('no-bar');
      if (bar) bar.style.display = 'none';
      if (text) text.textContent = `${labelName} — No data`;
    };

    const setMultiMetricVisible = (subcell) => {
      if (!subcell) return;
      const bar = subcell.querySelector('.bar');

      subcell.classList.remove('no-bar');
      if (bar) bar.style.display = '';
    };
    const cancelTickerState = (map, key) => {
      const state = map.get(key);

      if (state?.rafId) {
        cancelAnimationFrame(state.rafId);
      }

      map.delete(key);
    };
    const createMultiMetricCell = (key, label) => {
      const cell = document.createElement('div');
      const bar = document.createElement('div');
      const fill = document.createElement('div');
      const text = document.createElement('span');

      cell.className = 'cell';
      cell.setAttribute('data-key', key);
      cell.setAttribute('data-label', label);

      bar.className = 'bar';
      fill.className = 'fill';
      text.className = 'text';

      bar.appendChild(fill);
      cell.appendChild(bar);
      cell.appendChild(text);

      return cell;
    };
    const reconcileMultiMetricRows = (container, rows, {
      getKey,
      getLabel,
      onRemoveKey = () => {},
    }) => {
      const normalizedRows = [];
      const seenKeys = new Set();

      for (const row of rows ?? []) {
        const key = String(getKey(row) ?? '').trim();

        if (key === '' || seenKeys.has(key)) {
          continue;
        }

        seenKeys.add(key);
        normalizedRows.push({
          ...row,
          key,
          label: String(getLabel(row, key) ?? key),
        });
      }

      const naIndicator = container.querySelector('.na-indicator');
      const existingCells = new Map();

      container.querySelectorAll('.cell[data-key]').forEach((cell) => {
        const key = cell.getAttribute('data-key');

        if (key !== null) {
          existingCells.set(key, cell);
        }
      });

      if (normalizedRows.length === 0) {
        existingCells.forEach((cell, key) => {
          onRemoveKey(key);
          cell.remove();
        });

        if (!naIndicator) {
          const na = document.createElement('span');
          na.className = 'na-indicator';
          na.textContent = 'No data';
          container.appendChild(na);
        }

        return [];
      }

      if (naIndicator) {
        naIndicator.remove();
      }

      const desiredKeys = new Set(normalizedRows.map((row) => row.key));
      existingCells.forEach((cell, key) => {
        if (!desiredKeys.has(key)) {
          onRemoveKey(key);
          cell.remove();
          existingCells.delete(key);
        }
      });

      return normalizedRows.map((row) => {
        const cell = existingCells.get(row.key) ?? createMultiMetricCell(row.key, row.label);

        cell.setAttribute('data-key', row.key);
        cell.setAttribute('data-label', row.label);
        container.appendChild(cell);

        return {
          ...row,
          cell,
        };
      });
    };

    const metrics = fields["metrics_show"] || [];
    const hasMetric = (id) => metrics.includes(id);
    const badgeData = response.badge_data || {};
    const getBadgePayload = (badge) => {
      const index = badge?.getAttribute("data-badge-index");

      if (index === null || index === undefined) {
        return null;
      }

      return badgeData[index] ?? null;
    };
    const setBadgeText = (badgeText, text) => {
      if (!badgeText) return;
      badgeText.classList.remove("badge-parts");
      badgeText.textContent = text;
    };
    const setBadgeParts = (badgeText, parts) => {
      if (!badgeText) return;

      badgeText.classList.add("badge-parts");
      badgeText.innerHTML = "";

      parts.forEach((part, index) => {
        if (index > 0) {
          const separator = document.createElement("span");
          separator.className = "badge-dot-separator";
          badgeText.appendChild(separator);
        }

        const partSpan = document.createElement("span");
        partSpan.textContent = part;
        badgeText.appendChild(partSpan);
      });
    };

    // Host info badges
    for (const badge of this._body.querySelectorAll(".host-badge")) {
      const hostText = badge.querySelector(".badge-text");
      if (!hostText) continue;

      const payload = getBadgePayload(badge);
      const hostname =
        payload && Object.prototype.hasOwnProperty.call(payload, "hostname")
          ? payload.hostname
          : null;

      if (hostname) {
        hostText.textContent = hostname;
        hostText.style.fontStyle = '';
      } else {
        hostText.textContent = 'Hostname missing';
        hostText.style.fontStyle = 'italic';
      }
    }

    for (const badge of this._body.querySelectorAll(".uptime-badge")) {
      const badgeText = badge.querySelector(".badge-text");
      if (badgeText) {
        const payload = getBadgePayload(badge);
        const uptime =
          payload && Object.prototype.hasOwnProperty.call(payload, "uptime")
            ? payload.uptime
            : null;

        badgeText.textContent = uptime || '—';
      }
    }

    for (const badge of this._body.querySelectorAll(".freshness-badge")) {
      const badgeText = badge.querySelector(".badge-text");
      if (!badgeText) continue;

      const payload = getBadgePayload(badge);
      const freshness =
        payload && Object.prototype.hasOwnProperty.call(payload, "freshness")
          ? payload.freshness
          : null;
      const secs = Number(freshness);
      const warnThreshold = Math.max(
        0,
        Number(fields["freshness_warn"]) || 60
      );
      const staleThreshold = Math.max(
        warnThreshold,
        Number(fields["freshness_stale"]) || 300
      );

      badge.classList.remove('freshness-warn', 'freshness-stale');
      if (Number.isFinite(secs)) {
        const label = secs < 60
          ? `${secs}s ago`
          : `${Math.floor(secs / 60)}m ago`;
        let cls = null;
        if (secs >= staleThreshold) {
          cls = 'freshness-stale';
        } else if (secs >= warnThreshold) {
          cls = 'freshness-warn';
        }
        badgeText.textContent = label;
        if (cls) {
          badge.classList.add(cls);
        }
      } else {
        badgeText.textContent = '—';
      }
    }

    for (const badge of this._body.querySelectorAll(".maintenance-badge")) {
      const badgeText = badge.querySelector(".badge-text");
      if (!badgeText) continue;

      const payload = getBadgePayload(badge);
      const status =
        payload && Object.prototype.hasOwnProperty.call(payload, "status")
          ? Number(payload.status)
          : 0;

      badge.classList.remove("maintenance-active", "is-hidden");

      if (status === 1) {
        badge.classList.add("maintenance-active");
        badge.style.display = "";
        badgeText.textContent = "Maintenance";
      } else {
        badge.classList.add("is-hidden");
        badge.style.display = "none";
        badgeText.textContent = "";
      }
    }

    const problemBadges = this._body.querySelectorAll(".problems-badge");
    if (problemBadges.length > 0) {
      const colorClasses = ['problems-info', 'problems-warning',
        'problems-average', 'problems-high', 'problems-disaster'];
      const sevClassMap = {
        0: 'problems-info', 1: 'problems-info',
        2: 'problems-warning', 3: 'problems-average',
        4: 'problems-high', 5: 'problems-disaster',
      };

      for (const badge of problemBadges) {
        const payload = getBadgePayload(badge);
        const p =
          payload && Object.prototype.hasOwnProperty.call(payload, "problems")
            ? payload.problems
            : null;
        const badgeText = badge.querySelector(".badge-text");

        badge.classList.remove(...colorClasses);

        if (!p) {
          badge.style.display = '';
          if (badgeText) {
            setBadgeText(badgeText, 'Problems —');
          }
          continue;
        }

        const total = Number(p.total) || 0;

        if (total === 0) {
          badge.style.display = 'none';
          continue;
        }

        const maxSev = Number(p.max_severity);

        badge.style.display = '';
        if (badgeText) {
          setBadgeText(badgeText, `${total} problems`);
        }

        badge.classList.add(sevClassMap[maxSev] || 'problems-info');
      }
    }

    // Single-metric bars: [metricId, key, responseKey, fillSelector, textSelector]
    const singleMetrics = [
      [0, 'cpu',  'cpu',          '.cpu',  '.cpu-text'],
      [1, 'ram',  'ram',          '.ram',  '.ram-text'],
      [2, 'load', 'load_percent', '.load', '.load-text'],
      [3, 'swap', 'swap',         '.swap', '.swap-text'],
    ];

    for (const [metricId, key, responseKey, fillSel, textSel] of singleMetrics) {
      if (!hasMetric(metricId)) continue;
      const fill = this._body.querySelector(fillSel);
      const text = this._body.querySelector(textSel);
      if (response[responseKey] == null) {
        setSingleMetricNoData(fill, text);
      } else {
        setSingleMetricVisible(fill);
        const value = Number(response[responseKey]);
        this.updateFillWidth(fill, value, fields);
        const arrowDir = this.getArrow(key, value);
        if (text) startPercentTicker(key, null, value, text, arrowDir);
      }
    }

    // Helper to update a list of rows within a container
    const updateRows = (containerSelector, rows, keyPrefix, {
      getKey = (row) => row.key ?? row.name,
      getLabel = (row, key) => row.label ?? row.name ?? key,
    } = {}) => {
      const container = this._body.querySelector(containerSelector);
      if (!container) return;
      const boundRows = reconcileMultiMetricRows(container, rows, {
        getKey,
        getLabel,
        onRemoveKey: (key) => {
          const stateKey = `${keyPrefix}:${key}`;
          cancelTickerState(this.percentTicker, stateKey);
          this.prevValues.delete(stateKey);
        },
      });

      for (const row of boundRows) {
        const subcell = row.cell;
        const text = subcell.querySelector(".text");
        const fill = subcell.querySelector(".fill");
        const labelName = row.label;
        const percent = row.percent === null ? null : Number(row.percent);

        if (percent === null || Number.isNaN(percent)) {
          setMultiMetricNoData(subcell, labelName);
          continue;
        }

        setMultiMetricVisible(subcell);
        if (fill) this.updateFillWidth(fill, Number(row.percent), fields);
        if (text) {
          const arrowKey = `${keyPrefix}:${row.key}`;
          const arrowDir = this.getArrow(arrowKey, percent);
          startPercentTicker(
            arrowKey,
            labelName,
            percent,
            text,
            arrowDir
          );
        }
      }
    };

    // Interfaces ticker implementation
    const updateInterfaces = (interfaces) => {
      const container = this._body.querySelector(".interfaces-data");
      if (!container) return;
      const rows = Array.isArray(interfaces)
        ? interfaces
        : Object.entries(interfaces || {}).map(([key, item]) => ({
            ...item,
            key,
            label: key,
          }));
      const boundRows = reconcileMultiMetricRows(container, rows, {
        getKey: (row) => row.key,
        getLabel: (row, key) => row.label ?? key,
        onRemoveKey: (key) => {
          cancelTickerState(this.interfaceTicker, key);
          this.prevValues.delete(`iface:${key}`);
        },
      });

      const startTicker = (row) => {
        const key = row.key ?? '';
        const label = row.label ?? key;
        const bps = row.bps ?? null;
        const percent = row.percent ?? null;
        const sub = row.cell;
        const text = sub.querySelector(".text");
        const fill = sub.querySelector(".fill");

        if (bps === null || bps === undefined) {
          setMultiMetricNoData(sub, String(label));
          return;
        }

        const state = this.interfaceTicker.get(key) || {
          // Start from 0 on first render so bitrate ticks up
          value: 0,
          rafId: null,
        };
        if (state.rafId) cancelAnimationFrame(state.rafId);

        const from = Number(state.value) || 0;
        const to = Number(bps) || 0;
        const start = performance.now();
        const dur = 700;
        const arrowDir = this.getArrow(`iface:${key}`, to);

        setMultiMetricVisible(sub);
        if (fill && Number.isFinite(Number(percent))) {
          this.updateFillWidth(fill, Number(percent), fields);
        }

        const step = (now) => {
          const t = Math.min(1, (now - start) / dur);
          const ease = 1 - Math.pow(1 - t, 3);
          const cur = from + (to - from) * ease;
          renderTextWithArrow(text, `${label} — ${this._formatBps(cur)}`, arrowDir);
          state.value = cur;
          if (t < 1) {
            state.rafId = requestAnimationFrame(step);
          } else {
            state.value = to;
            state.rafId = null;
            renderTextWithArrow(text, `${label} — ${this._formatBps(to)}`, arrowDir);
          }
        };
        state.rafId = requestAnimationFrame(step);
        this.interfaceTicker.set(key, state);
      };

      for (const row of boundRows) {
        startTicker(row);
      }
    };

    // Interfaces (ticker)
    if (hasMetric(4)) {
      updateInterfaces(response.interfaces);
    }

    // Disks
    if (hasMetric(5)) {
      updateRows(".disks-data", response.disks, "disks");
    }

    // Partitions
    if (hasMetric(6)) {
      updateRows(".partitions-data", response.partitions, "partitions");
    }
  }

  // Attach click handlers to bars for sparkline overlay
  attachSparklineHandlers() {
    if (!this._body) return;

    this._body.addEventListener('click', (e) => {
      const closeBtn = e.target.closest('.js-sparkline-close');
      if (closeBtn) {
        e.preventDefault();
        this.closeSparkline();
        return;
      }

      const backdrop = e.target.closest('.sparkline-backdrop');
      if (backdrop) {
        e.preventDefault();
        this.closeSparkline();
        return;
      }

      const periodBtn = e.target.closest('.sparkline-periods button[data-period]');
      if (periodBtn) {
        e.preventDefault();
        const period = periodBtn.getAttribute('data-period');
        if (!period || this.sparklineState.itemRef === null) {
          return;
        }

        this.sparklineState.period = period;
        this._updateSparklineChrome();
        this.fetchSparkline(this.sparklineState.itemRef, period);
        return;
      }

      const bar = e.target.closest('.bar');
      if (!bar) {
        return;
      }

      const overlay = this._body.querySelector('.sparkline-overlay');
      if (overlay && overlay.contains(bar)) {
        return;
      }

      const metric = this._getSparklineMetricFromBar(bar);
      if (!metric) {
        return;
      }

      e.preventDefault();
      this.toggleSparkline(metric.key, metric.title);
    });

    // Canvas seeker (hover) handlers
    const canvas = this._body.querySelector('.sparkline-canvas');
    if (canvas) {
      canvas.addEventListener('mousemove', (e) => this._onSparklineMouseMove(e));
      canvas.addEventListener('mouseleave', () => this._onSparklineMouseLeave());
    }
  }

  toggleSparkline(metricKey, fallbackTitle) {
    if (this.sparklineState.open && this.sparklineState.metricKey === metricKey) {
      this.closeSparkline();
      return;
    }

    const itemRef = this._normalizeSparklineRef(this.itemMap[metricKey]);
    if (!itemRef || (!itemRef.itemid && !itemRef.name)) return;

    this.sparklineState.open = true;
    this.sparklineState.metricKey = metricKey;
    this.sparklineState.itemRef = itemRef;
    this.sparklineState.fallbackTitle = fallbackTitle;
    this.sparklineState.data = null;
    this.sparklineState.message = 'Loading...';
    this._pauseSparklineUpdating();
    this._updateSparklineChrome();

    const root = this._getWidgetRoot();
    if (root) {
      root.classList.add('sparkline-open');
    }

    const overlay = this._body.querySelector('.sparkline-overlay');
    if (overlay) {
      overlay.style.backgroundColor = this._getSparklineSurfaceColor();
      overlay.classList.add('visible');
    }

    const backdrop = this._body.querySelector('.sparkline-backdrop');
    if (backdrop) {
      backdrop.classList.add('visible');
    }

    const container = this._body.querySelector('#container');
    if (container) container.classList.add('sparkline-active');
    this._bindSparklineKeydown();
    this._scheduleSparklineRedraw();
    this.fetchSparkline(itemRef, this.sparklineState.period);
  }

  closeSparkline() {
    if (!this.sparklineState.open && !this._sparklineUpdatesPaused) {
      this._removeSparklineKeydownHandler();
      return;
    }

    this._cancelSparklineRequest();

    this.sparklineState.open = false;
    this.sparklineState.metricKey = null;
    this.sparklineState.itemRef = null;
    this.sparklineState.fallbackTitle = '';
    this.sparklineState.data = null;
    this.sparklineState.message = '';
    this._sparklineDrawState = null;
    this._sparklineSnapshot = null;
    this._setSparklineYLabelsVisible(false);

    if (this._sparklineRedrawRaf) {
      cancelAnimationFrame(this._sparklineRedrawRaf);
      this._sparklineRedrawRaf = null;
    }

    const overlay = this._body.querySelector('.sparkline-overlay');
    if (overlay) {
      overlay.classList.remove('visible');
    }

    const backdrop = this._body.querySelector('.sparkline-backdrop');
    if (backdrop) {
      backdrop.classList.remove('visible');
    }

    const root = this._getWidgetRoot();
    if (root) {
      root.classList.remove('sparkline-open');
    }

    const container = this._body.querySelector('#container');
    if (container) container.classList.remove('sparkline-active');

    this._removeSparklineKeydownHandler();
    this._resumeSparklineUpdating();
  }

  _getWidgetRoot() {
    return this._body?.closest('.dashboard-widget-host_overview')
      || this._target
      || this._body;
  }

  _setupSparklineResizeObserver() {
    if (this._sparklineResizeObserver || typeof ResizeObserver === 'undefined') {
      return;
    }

    const target = this._getWidgetRoot();
    if (!target) {
      return;
    }

    this._sparklineResizeObserver = new ResizeObserver(() => {
      this._scheduleSparklineRedraw();
    });
    this._sparklineResizeObserver.observe(target);
  }

  _scheduleSparklineRedraw() {
    if (!this.sparklineState.open) {
      return;
    }

    if (this._sparklineRedrawRaf) {
      cancelAnimationFrame(this._sparklineRedrawRaf);
    }

    this._sparklineRedrawRaf = requestAnimationFrame(() => {
      this._sparklineRedrawRaf = null;
      this._redrawSparkline();
    });
  }

  _redrawSparkline() {
    if (!this.sparklineState.open) {
      return;
    }

    const overlay = this._body?.querySelector('.sparkline-overlay');
    if (!overlay || !overlay.classList.contains('visible')) {
      return;
    }

    const data = this.sparklineState.data;
    if (data) {
      this.drawSparkline(
        data.points,
        data.min,
        data.max,
        data.timeFrom,
        data.timeTill,
        data.gapThresholdFloor
      );
      return;
    }

    this.drawSparklineMessage(this.sparklineState.message || 'Loading...');
  }

  _cancelSparklineRequest() {
    if (this._sparklineAbortController) {
      this._sparklineAbortController.abort();
      this._sparklineAbortController = null;
    }
  }

  _bindSparklineKeydown() {
    if (this._sparklineKeydownHandler) {
      return;
    }

    this._sparklineKeydownHandler = (e) => {
      if (e.key === 'Escape' && this.sparklineState.open) {
        e.preventDefault();
        this.closeSparkline();
      }
    };

    document.addEventListener('keydown', this._sparklineKeydownHandler);
  }

  _removeSparklineKeydownHandler() {
    if (!this._sparklineKeydownHandler) {
      return;
    }

    document.removeEventListener('keydown', this._sparklineKeydownHandler);
    this._sparklineKeydownHandler = null;
  }

  _updateSparklineChrome() {
    const titleEl = this._body?.querySelector('.sparkline-title');
    if (titleEl) {
      titleEl.textContent = this._getSparklineTitle(
        this.sparklineState.itemRef,
        this.sparklineState.fallbackTitle
      );
    }

    const fields = this._fields || {};
    const fillColor = fields.fill_color ? `#${fields.fill_color}` : '#458ADC';

    this._body?.querySelectorAll('.sparkline-periods button').forEach((btn) => {
      btn.style.setProperty('--sparkline-active-color', fillColor);
      btn.classList.toggle('active', btn.getAttribute('data-period') === this.sparklineState.period);
    });
  }

  _getSparklineMetricFromBar(bar) {
    const cell = bar.closest('.cell[data-key]');
    if (cell) {
      const name = cell.getAttribute('data-key');
      const label = cell.getAttribute('data-label') || name;
      if (!name) {
        return null;
      }

      if (cell.closest('.interfaces-data')) {
        return { key: `iface:${name}`, title: label };
      }
      if (cell.closest('.disks-data')) {
        return { key: `disk:${name}`, title: label };
      }
      if (cell.closest('.partitions-data')) {
        return { key: `partition:${name}`, title: label };
      }

      return null;
    }

    const fill = bar.querySelector('.fill');
    if (!fill) {
      return null;
    }

    if (fill.classList.contains('cpu')) {
      return { key: 'cpu', title: 'Processor' };
    }
    if (fill.classList.contains('ram')) {
      return { key: 'ram', title: 'Memory' };
    }
    if (fill.classList.contains('load')) {
      return { key: 'load', title: 'Load' };
    }
    if (fill.classList.contains('swap')) {
      return { key: 'swap', title: 'Swap' };
    }

    return null;
  }

  _getSparklineSurfaceColor() {
    let el = this._getWidgetRoot();

    while (el) {
      const color = getComputedStyle(el).backgroundColor;
      if (color && color !== 'rgba(0, 0, 0, 0)' && color !== 'transparent') {
        return color;
      }
      el = el.parentElement;
    }

    return 'rgb(255, 255, 255)';
  }

  _pauseSparklineUpdating() {
    if (this._sparklineUpdatesPaused) {
      return;
    }

    if (typeof this._pauseUpdating === 'function') {
      this._pauseUpdating();
      this._sparklineUpdatesPaused = true;
    }
  }

  _resumeSparklineUpdating() {
    if (!this._sparklineUpdatesPaused) {
      return;
    }

    if (typeof this._resumeUpdating === 'function') {
      this._resumeUpdating();
    }

    this._sparklineUpdatesPaused = false;
  }

  fetchSparkline(itemRef, period) {
    const normalizedRef = this._normalizeSparklineRef(itemRef);

    if (!normalizedRef || (!normalizedRef.itemid && !normalizedRef.name)) {
      this.sparklineState.data = null;
      this.sparklineState.message = 'Item not found';
      this._scheduleSparklineRedraw();
      return;
    }

    this.sparklineState.itemRef = normalizedRef;
    this.sparklineState.period = period;
    this.sparklineState.data = null;
    this.sparklineState.message = 'Loading...';
    this._updateSparklineChrome();
    this._scheduleSparklineRedraw();

    this._cancelSparklineRequest();
    const requestId = ++this._sparklineRequestId;
    const controller = new AbortController();
    this._sparklineAbortController = controller;
    this._fetchSparklineAsync(
      normalizedRef,
      period,
      this.sparklineState.metricKey,
      requestId,
      controller.signal
    );
  }

  async _fetchSparklineAsync(itemRef, period, metricKey, requestId, signal) {
    try {
      let itemid = itemRef.itemid || null;
      let valueType = itemRef.value_type != null ? parseInt(itemRef.value_type) : NaN;
      let resolvedRef = { ...itemRef };

      if (!itemid || Number.isNaN(valueType)) {
        const hostid = (this._fields.hostid || [])[0];
        if (!hostid) {
          if (requestId === this._sparklineRequestId && this.sparklineState.open) {
            this.sparklineState.data = null;
            this.sparklineState.message = 'No host';
            this._scheduleSparklineRedraw();
          }
          return;
        }

        // Fallback path for item refs that only carry a name.
        const items = await this._apiCall('item.get', {
          output: ['itemid', 'name', 'value_type'],
          hostids: [hostid],
          search: { name: itemRef.name },
          limit: 1,
        }, signal);

        if (!items || items.length === 0) {
          if (requestId === this._sparklineRequestId && this.sparklineState.open) {
            this.sparklineState.data = null;
            this.sparklineState.message = 'Item not found';
            this._scheduleSparklineRedraw();
          }
          return;
        }

        itemid = items[0].itemid;
        valueType = parseInt(items[0].value_type);
        resolvedRef = {
          itemid,
          name: items[0].name ?? itemRef.name,
          value_type: valueType
        };
      }

      const periods = { '1h': 3600, '3h': 10800, '6h': 21600, '12h': 43200, '1d': 86400, '3d': 259200, '1w': 604800, '2w': 1209600 };
      const seconds = periods[period] || 43200;
      const timeTill = Math.floor(Date.now() / 1000);
      const timeFrom = timeTill - seconds;

      const { points: sparklinePoints, gapThresholdFloor } =
        await this._loadSparklinePoints(itemid, valueType, timeFrom, timeTill, seconds, signal);
      let points = sparklinePoints;

      // Invert swap values when configured (free % -> used %).
      if (metricKey === 'swap' && this._fields.item_swap_invert == 1) {
        points = points.map(p => ({ t: p.t, v: 100 - p.v }));
      }

      // Downsample to ~200 points
      if (points.length > 200) {
        const stride = points.length / 200;
        const ds = [];
        for (let i = 0; i < 200; i++) {
          ds.push(points[Math.floor(i * stride)]);
        }
        ds.push(points[points.length - 1]);
        points = ds;
      }

      // Compute min/max
      let min = Infinity, max = -Infinity;
      for (const p of points) {
        if (p.v < min) min = p.v;
        if (p.v > max) max = p.v;
      }
      if (points.length === 0) { min = 0; max = 0; }

      // For interface metrics, use configured capacity as max Y.
      if (metricKey?.startsWith('iface:')) {
        const fields = this._fields || {};
        const high = parseInt(fields.interfaces_high) || 0;
        const unit = parseInt(fields.interfaces_unit) || 0;
        if (high > 0) {
          const factors = { 2: 1e9, 1: 1e6, 0: 1e3 };
          max = high * (factors[unit] || 1e3);
        }
      }

      // For load metrics, use configured load_high as max Y.
      if (metricKey === 'load') {
        const loadHigh = parseFloat((this._fields || {}).load_high) || 0;
        if (loadHigh > 0) max = loadHigh;
      }

      const result = { points, min, max, timeFrom, timeTill, gapThresholdFloor };
      if (requestId === this._sparklineRequestId
          && this.sparklineState.open
          && this.sparklineState.period === period) {
        this.sparklineState.itemRef = resolvedRef;
        this.sparklineState.data = result;
        this.sparklineState.message = '';
        this._updateSparklineChrome();
        this._scheduleSparklineRedraw();
      }
    } catch (e) {
      if (e?.name === 'AbortError') {
        return;
      }

      if (requestId === this._sparklineRequestId && this.sparklineState.open) {
        this.sparklineState.data = null;
        this.sparklineState.message = 'Error loading data';
        this._scheduleSparklineRedraw();
      }
    } finally {
      if (this._sparklineAbortController?.signal === signal) {
        this._sparklineAbortController = null;
      }
    }
  }

  _normalizeSparklineRef(itemRef) {
    if (!itemRef) {
      return null;
    }

    if (typeof itemRef === 'string') {
      return {
        itemid: null,
        name: itemRef,
        value_type: null,
      };
    }

    if (typeof itemRef === 'object') {
      return {
        itemid: itemRef.itemid ?? null,
        name: itemRef.name ?? null,
        value_type: itemRef.value_type ?? null,
      };
    }

    return null;
  }

  _getSparklineTitle(ref, fallbackTitle = '') {
    return (ref && ref.name) ? ref.name : fallbackTitle;
  }

  async _apiCall(method, params, signal = undefined) {
    const resp = await fetch('api_jsonrpc.php', {
      method: 'POST',
      signal,
      headers: { 'Content-Type': 'application/json-rpc' },
      body: JSON.stringify({
        jsonrpc: '2.0',
        method: method,
        params: params,
        id: 1,
      }),
    });
    const data = await resp.json();
    if (data.error) throw new Error(data.error.data || data.error.message || 'API error');
    return data.result;
  }

  async _loadSparklinePoints(itemid, valueType, timeFrom, timeTill, seconds, signal) {
    const supportsTrends = valueType === 0 || valueType === 3;
    const useTrendBlend = supportsTrends && seconds > 43200;

    if (!useTrendBlend) {
      let points = await this._fetchHistory(itemid, valueType, timeFrom, timeTill, { signal });

      if (points.length === 0 && supportsTrends) {
        points = await this._fetchTrends(itemid, valueType, timeFrom, timeTill, signal);
      }

      return {
        points,
        gapThresholdFloor: 300,
      };
    }

    // For longer periods, use trends for broad coverage and a recent history tail
    // so the chart keeps its newest samples instead of truncating the right edge.
    const [trendPoints, historyTail] = await Promise.all([
      this._fetchTrends(itemid, valueType, timeFrom, timeTill, signal),
      this._fetchHistory(itemid, valueType, timeFrom, timeTill, {
        sortorder: 'DESC',
        limit: 500,
        signal,
      }),
    ]);

    if (historyTail.length > 0) {
      const historyStart = historyTail[0].t;

      return {
        points: trendPoints.filter(p => p.t < historyStart).concat(historyTail),
        gapThresholdFloor: 3600,
      };
    }

    if (trendPoints.length > 0) {
      return {
        points: trendPoints,
        gapThresholdFloor: 3600,
      };
    }

    return {
      points: await this._fetchHistory(itemid, valueType, timeFrom, timeTill, { signal }),
      gapThresholdFloor: 300,
    };
  }

  async _fetchHistory(itemid, valueType, timeFrom, timeTill, options = {}) {
    const sortorder = options.sortorder || 'ASC';
    const limit = options.limit || 500;
    const signal = options.signal;
    const records = await this._apiCall('history.get', {
      output: ['value', 'clock'],
      history: valueType,
      itemids: [itemid],
      time_from: timeFrom,
      time_till: timeTill,
      sortfield: 'clock',
      sortorder,
      limit,
    }, signal);

    const points = records.map(r => ({ t: parseInt(r.clock), v: parseFloat(r.value) }));

    return sortorder === 'DESC'
      ? points.sort((a, b) => a.t - b.t)
      : points;
  }

  async _fetchTrends(itemid, valueType, timeFrom, timeTill, signal = undefined) {
    const records = await this._apiCall('trend.get', {
      output: ['value_avg', 'clock'],
      history: valueType,
      itemids: [itemid],
      time_from: timeFrom,
      time_till: timeTill,
      limit: 500,
    }, signal);
    return records.map(r => ({ t: parseInt(r.clock), v: parseFloat(r.value_avg) }))
      .sort((a, b) => a.t - b.t);
  }

  // Prepare the sparkline canvas for drawing: reset size, scale for DPR, clear.
  // Returns { canvas, ctx, dpr, w, h } in physical pixels, or null if no canvas.
  _prepareCanvas() {
    const canvas = this._body.querySelector('.sparkline-canvas');
    if (!canvas) return null;
    canvas.style.width = '';
    canvas.style.height = '';
    const dpr = window.devicePixelRatio || 1;
    const cssW = canvas.clientWidth || canvas.getBoundingClientRect().width || 300;
    const cssH = canvas.clientHeight || canvas.getBoundingClientRect().height || 100;
    const w = Math.round(cssW * dpr);
    const h = Math.round(cssH * dpr);
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, w, h);
    return { canvas, ctx, dpr, w, h };
  }

  drawSparklineMessage(msg) {
    const c = this._prepareCanvas();
    if (!c) return;
    this._setSparklineYLabelsVisible(false);
    const { ctx, dpr, w, h } = c;
    ctx.scale(dpr, dpr);
    const cssW = Math.round(w / dpr);
    const cssH = Math.round(h / dpr);
    ctx.fillStyle = 'rgba(128,128,128,0.5)';
    ctx.font = '10px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(msg, cssW / 2, cssH / 2);
    this._sparklineDrawState = null;
    this._sparklineSnapshot = null;
  }

  drawSparkline(points, min, max, timeFrom, timeTill, gapThresholdFloor = 300) {
    const metricKey = this.sparklineState.metricKey || '';
    const isInterface = metricKey.startsWith('iface:');
    const isLoad = metricKey === 'load';

    if (!points || points.length === 0) {
      this.drawSparklineMessage('No data');
      return;
    }

    min = 0;
    if (!isInterface && !isLoad) {
      max = Math.max(100, max);
    }
    max = Math.max(max, 1);
    this._updateYLabels(max, isInterface, isLoad);

    const c = this._prepareCanvas();
    if (!c) return;
    const { canvas, ctx, dpr, w: drawW, h: drawH } = c;

    // Use the full requested period window for x-axis mapping
    // so sparse data only fills the portion of canvas it covers
    const tMin = timeFrom || points[0].t;
    const tMax = timeTill || points[points.length - 1].t;
    const tRange = tMax - tMin || 1;

    // Map points to canvas coordinates in physical pixels.
    const coords = points.map(p => ({
      x: Math.round(((p.t - tMin) / tRange) * drawW),
      y: Math.round((1 - (p.v - min) / (max - min)) * drawH),
    }));

    // Draw horizontal grid lines at 25%, 50%, 75%.
    ctx.beginPath();
    ctx.strokeStyle = 'rgba(128, 128, 128, 0.2)';
    const gridLW = Math.round(dpr);
    ctx.lineWidth = gridLW;
    const gridOffset = gridLW % 2 === 1 ? 0.5 : 0;
    for (const frac of [0.25, 0.5, 0.75]) {
      const y = Math.round((1 - frac) * drawH) + gridOffset;
      ctx.moveTo(0, y);
      ctx.lineTo(drawW, y);
    }
    ctx.stroke();

    // Fill color from config
    const fields = this._fields || {};
    const fillColor = fields.fill_color ? `#${fields.fill_color}` : '#458ADC';

    // Split coords into continuous segments at large gaps.
    const intervals = [];
    for (let i = 1; i < points.length; i++) {
      intervals.push(points[i].t - points[i - 1].t);
    }
    intervals.sort((a, b) => a - b);
    const medianInterval = intervals.length > 0 ? intervals[Math.floor(intervals.length / 2)] : 0;
    const gapThreshold = Math.max(medianInterval * 3, gapThresholdFloor);

    const segments = [];
    let seg = [0];
    for (let i = 1; i < coords.length; i++) {
      if (points[i].t - points[i - 1].t > gapThreshold) {
        segments.push(seg);
        seg = [i];
      } else {
        seg.push(i);
      }
    }
    segments.push(seg);

    // Draw filled area and stroke in a single pass per segment.
    ctx.strokeStyle = fillColor;
    ctx.lineWidth = Math.round(1.5 * dpr);
    for (const s of segments) {
      if (s.length === 0) continue;
      // Build the line path once
      const path = new Path2D();
      path.moveTo(coords[s[0]].x, coords[s[0]].y);
      for (let k = 1; k < s.length; k++) {
        path.lineTo(coords[s[k]].x, coords[s[k]].y);
      }
      // Stroke the line
      ctx.stroke(path);
      // Extend path to form closed fill area
      path.lineTo(coords[s[s.length - 1]].x, drawH);
      path.lineTo(coords[s[0]].x, drawH);
      path.closePath();
      ctx.fillStyle = fillColor + '26';
      ctx.fill(path);
    }

    this._sparklineDrawState = { points, coords, min, max, tMin, tMax, tRange, drawW, drawH, fillColor, isInterface, isLoad };
    this._sparklineSnapshot = ctx.getImageData(0, 0, canvas.width, canvas.height);
    this._setSparklineYLabelsVisible(true);
  }

  _onSparklineMouseMove(e) {
    const state = this._sparklineDrawState;
    if (!state) return;

    const canvas = this._body.querySelector('.sparkline-canvas');
    if (!canvas) return;

    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    const mouseX = (e.clientX - rect.left) * dpr;

    let nearest = 0;
    let minDist = Math.abs(state.coords[0].x - mouseX);
    for (let i = 1; i < state.coords.length; i++) {
      const dist = Math.abs(state.coords[i].x - mouseX);
      if (dist < minDist) {
        minDist = dist;
        nearest = i;
      }
    }

    const ctx = canvas.getContext('2d');
    if (this._sparklineSnapshot) {
      ctx.putImageData(this._sparklineSnapshot, 0, 0);
    }

    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);

    const sx = Math.round(state.coords[nearest].x);
    const sy = Math.round(state.coords[nearest].y);
    const drawH = state.drawH;
    const drawW = state.drawW;
    const fillColor = state.fillColor;
    const fontSize = Math.round(12 * dpr);
    const dotR = Math.round(3 * dpr);

    ctx.beginPath();
    ctx.setLineDash([Math.round(4 * dpr), Math.round(3 * dpr)]);
    ctx.strokeStyle = fillColor + '66';
    ctx.lineWidth = Math.round(1 * dpr);
    ctx.moveTo(sx + 0.5, 0);
    ctx.lineTo(sx + 0.5, drawH);
    ctx.stroke();
    ctx.setLineDash([]);

    ctx.beginPath();
    ctx.arc(sx, sy, dotR, 0, Math.PI * 2);
    ctx.fillStyle = fillColor;
    ctx.fill();

    const valueText = this._formatSeekerValue(state.points[nearest].v, state);
    const timeText = this._formatSeekerTime(state.points[nearest].t, state.tRange);

    const textColor = 'rgba(255,255,255,0.95)';
    const boxBg = 'rgba(0,0,0,0.8)';
    const pad = Math.round(5 * dpr);
    const boxH = Math.round(18 * dpr);
    const gap = Math.round(10 * dpr);
    const cornerR = (this._fields || {}).corners == 1 ? 0 : Math.round(3 * dpr);
    const inset = Math.round(3 * dpr);

    ctx.font = `${fontSize}px sans-serif`;
    const valMetrics = ctx.measureText(valueText);
    const valW = Math.round(valMetrics.width + pad * 2);
    const flipRight = sx + gap + valW > drawW - inset;
    const valX = Math.round(Math.max(inset, Math.min(flipRight ? sx - gap - valW : sx + gap, drawW - valW - inset)));
    const valY = Math.round(Math.max(inset, Math.min(sy - boxH / 2, drawH - boxH - inset)));

    ctx.fillStyle = boxBg;
    this._roundRect(ctx, valX, valY, valW, boxH, cornerR);
    ctx.fill();

    ctx.fillStyle = textColor;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';
    ctx.fillText(valueText, valX + pad, valY + Math.round((boxH - fontSize) / 2) + Math.round(dpr));

    const timeMetrics = ctx.measureText(timeText);
    const timeW = Math.round(timeMetrics.width + pad * 2);
    let timeX = Math.round(sx - timeW / 2);
    timeX = Math.max(inset, Math.min(timeX, drawW - timeW - inset));
    const timeTopY = inset;
    const timeBotY = Math.round(drawH - boxH - inset);
    const timeRight = timeX + timeW;
    const valRight = valX + valW;
    const horizOverlap = timeX < valRight && timeRight > valX;
    const vertOverlapTop = timeTopY < valY + boxH && (timeTopY + boxH) > valY;
    const timeY = (horizOverlap && vertOverlapTop) ? timeBotY : timeTopY;

    ctx.fillStyle = boxBg;
    this._roundRect(ctx, timeX, timeY, timeW, boxH, cornerR);
    ctx.fill();

    ctx.fillStyle = textColor;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';
    ctx.fillText(timeText, timeX + pad, timeY + Math.round((boxH - fontSize) / 2) + Math.round(dpr));

    ctx.restore();
  }

  _onSparklineMouseLeave() {
    const state = this._sparklineDrawState;
    if (!state) return;

    const canvas = this._body.querySelector('.sparkline-canvas');
    if (canvas && this._sparklineSnapshot) {
      canvas.getContext('2d').putImageData(this._sparklineSnapshot, 0, 0);
    }
  }

  _formatSeekerValue(value, state) {
    if (state.isInterface) {
      return this._formatBps(value);
    }
    if (state.isLoad) {
      return value % 1 === 0 ? value.toFixed(0) : value.toFixed(2);
    }
    return value.toFixed(1) + '%';
  }

  _formatSeekerTime(timestamp, tRange) {
    const date = new Date(timestamp * 1000);
    const pad = (n) => String(n).padStart(2, '0');
    const hhmm = `${pad(date.getHours())}:${pad(date.getMinutes())}`;
    if (tRange > 86400) {
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return `${months[date.getMonth()]} ${date.getDate()} ${hhmm}`;
    }
    return hhmm;
  }

  _roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r);
    ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    ctx.lineTo(x + r, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
  }

  _updateYLabels(max, isInterface, isLoad) {
    const labels = this._body?.querySelector('.sparkline-y-labels');
    if (!labels) return;
    const spans = labels.querySelectorAll('span');
    if (spans.length < 3) return;

    if (isInterface) {
      spans[0].textContent = this._formatBps(max);
      spans[1].textContent = this._formatBps(max * 0.5);
      spans[2].textContent = '0';
    } else if (isLoad) {
      spans[0].textContent = max % 1 === 0 ? max.toFixed(0) : max.toFixed(1);
      spans[1].textContent = (max * 0.5) % 1 === 0 ? (max * 0.5).toFixed(0) : (max * 0.5).toFixed(1);
      spans[2].textContent = '0';
    } else {
      spans[0].textContent = '100%';
      spans[1].textContent = '50%';
      spans[2].textContent = '0%';
    }
  }

  _setSparklineYLabelsVisible(visible) {
    const labels = this._body?.querySelector('.sparkline-y-labels');
    if (labels) {
      labels.style.visibility = visible ? '' : 'hidden';
    }
  }

  _formatBps(bps) {
    if (bps >= 1e9) return (bps / 1e9).toFixed(bps % 1e9 === 0 ? 0 : 1) + ' Gbps';
    if (bps >= 1e6) return (bps / 1e6).toFixed(bps % 1e6 === 0 ? 0 : 1) + ' Mbps';
    if (bps >= 1e3) return (bps / 1e3).toFixed(bps % 1e3 === 0 ? 0 : 1) + ' Kbps';
    return Math.round(bps) + ' bps';
  }
}
