/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

class CWidgetHostOverview extends CWidget {

  // Metric IDs — must match WidgetForm::METRIC_* constants in PHP
  static METRIC_CPU        = 0;
  static METRIC_RAM        = 1;
  static METRIC_LOAD       = 2;
  static METRIC_SWAP       = 3;
  static METRIC_INTERFACES = 4;
  static METRIC_DISKS      = 5;
  static METRIC_PARTITIONS = 6;

  onInitialize() {
    this.rendered = false;
    // State for interface tickers
    this.interfaceTicker = new Map();
    // State for percent tickers
    this.percentTicker = new Map();
    // Previous settled values for direction arrows
    this.prevValues = new Map();
    this.itemMap = {};
    this._sparkline = new HostOverviewSparkline({
      getBody: () => this._body,
      getFields: () => this._fields || {},
      getItemRef: (metricKey) => this.itemMap[metricKey],
      getWidgetRoot: () => this._body?.closest('.dashboard-widget-host_overview')
        || this._target
        || this._body,
      formatBps: (bps) => this._formatBps(bps),
      pauseUpdating: () => {
        if (typeof this._pauseUpdating !== 'function') {
          return false;
        }

        this._pauseUpdating();
        return true;
      },
      resumeUpdating: () => {
        if (typeof this._resumeUpdating === 'function') {
          this._resumeUpdating();
        }
      },
    });
  }

  onDeactivate() {
    this._sparkline?.close();
  }

  onResize() {
    this._sparkline?.scheduleRedraw();
  }

  onDestroy() {
    this._sparkline?.destroy();
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
      this._sparkline?.attach();
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

    this.updateViewModeAttr();

    const fields = response.config;
    const enabledMetrics = new Set(fields["metrics_show"] || []);
    const badgeData = response.badge_data || {};

    this._updateHostBadges(badgeData);
    this._updateUptimeBadges(badgeData);
    this._updateFreshnessBadges(badgeData, fields);
    this._updateMaintenanceBadges(badgeData);
    this._updateProblemBadges(badgeData);
    this._updateSingleMetrics(response, fields, enabledMetrics);

    if (enabledMetrics.has(CWidgetHostOverview.METRIC_INTERFACES)) {
      this._updateInterfaces(response.interfaces, fields);
    }

    if (enabledMetrics.has(CWidgetHostOverview.METRIC_DISKS)) {
      this._updateMetricRows(".disks-data", response.disks, "disks", fields);
    }

    if (enabledMetrics.has(CWidgetHostOverview.METRIC_PARTITIONS)) {
      this._updateMetricRows(".partitions-data", response.partitions, "partitions", fields);
    }
  }

  _updateHostBadges(badgeData) {
    for (const badge of this._body.querySelectorAll(".host-badge")) {
      const hostText = badge.querySelector(".badge-text");
      if (!hostText) {
        continue;
      }

      const payload = this._getBadgePayload(badge, badgeData);
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
  }

  _updateUptimeBadges(badgeData) {
    for (const badge of this._body.querySelectorAll(".uptime-badge")) {
      const badgeText = badge.querySelector(".badge-text");
      if (!badgeText) {
        continue;
      }

      const payload = this._getBadgePayload(badge, badgeData);
      const uptime =
        payload && Object.prototype.hasOwnProperty.call(payload, "uptime")
          ? payload.uptime
          : null;

      badgeText.textContent = uptime || '—';
    }
  }

  _updateFreshnessBadges(badgeData, fields) {
    const warnThreshold = Math.max(0, Number(fields["freshness_warn"]) || 60);
    const staleThreshold = Math.max(
      warnThreshold,
      Number(fields["freshness_stale"]) || 300
    );

    for (const badge of this._body.querySelectorAll(".freshness-badge")) {
      const badgeText = badge.querySelector(".badge-text");
      if (!badgeText) {
        continue;
      }

      const payload = this._getBadgePayload(badge, badgeData);
      const freshness =
        payload && Object.prototype.hasOwnProperty.call(payload, "freshness")
          ? payload.freshness
          : null;
      const secs = Number(freshness);

      badge.classList.remove('freshness-warn', 'freshness-stale');

      if (!Number.isFinite(secs)) {
        badgeText.textContent = '—';
        continue;
      }

      badgeText.textContent = secs < 60
        ? `${secs}s ago`
        : `${Math.floor(secs / 60)}m ago`;

      if (secs >= staleThreshold) {
        badge.classList.add('freshness-stale');
      } else if (secs >= warnThreshold) {
        badge.classList.add('freshness-warn');
      }
    }
  }

  _updateMaintenanceBadges(badgeData) {
    for (const badge of this._body.querySelectorAll(".maintenance-badge")) {
      const badgeText = badge.querySelector(".badge-text");
      if (!badgeText) {
        continue;
      }

      const payload = this._getBadgePayload(badge, badgeData);
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
  }

  _updateProblemBadges(badgeData) {
    const problemBadges = this._body.querySelectorAll(".problems-badge");
    if (problemBadges.length === 0) {
      return;
    }

    const colorClasses = ['problems-info', 'problems-warning',
      'problems-average', 'problems-high', 'problems-disaster'];
    const severityClassMap = {
      0: 'problems-info',
      1: 'problems-info',
      2: 'problems-warning',
      3: 'problems-average',
      4: 'problems-high',
      5: 'problems-disaster',
    };

    for (const badge of problemBadges) {
      const payload = this._getBadgePayload(badge, badgeData);
      const problems =
        payload && Object.prototype.hasOwnProperty.call(payload, "problems")
          ? payload.problems
          : null;
      const badgeText = badge.querySelector(".badge-text");

      badge.classList.remove(...colorClasses);

      if (!problems) {
        badge.style.display = '';
        if (badgeText) {
          this._setBadgeText(badgeText, 'Problems —');
        }
        continue;
      }

      const total = Number(problems.total) || 0;
      if (total === 0) {
        badge.style.display = 'none';
        continue;
      }

      badge.style.display = '';
      if (badgeText) {
        this._setBadgeText(badgeText, `${total} problems`);
      }

      const maxSeverity = Number(problems.max_severity);
      badge.classList.add(severityClassMap[maxSeverity] || 'problems-info');
    }
  }

  _updateSingleMetrics(response, fields, enabledMetrics) {
    const singleMetrics = [
      [CWidgetHostOverview.METRIC_CPU,  'cpu',  'cpu',          '.cpu',  '.cpu-text'],
      [CWidgetHostOverview.METRIC_RAM,  'ram',  'ram',          '.ram',  '.ram-text'],
      [CWidgetHostOverview.METRIC_LOAD, 'load', 'load_percent', '.load', '.load-text'],
      [CWidgetHostOverview.METRIC_SWAP, 'swap', 'swap',         '.swap', '.swap-text'],
    ];

    for (const [metricId, key, responseKey, fillSelector, textSelector] of singleMetrics) {
      if (!enabledMetrics.has(metricId)) {
        continue;
      }

      const fill = this._body.querySelector(fillSelector);
      const text = this._body.querySelector(textSelector);

      if (response[responseKey] == null) {
        this._setSingleMetricNoData(fill, text);
        continue;
      }

      this._setSingleMetricVisible(fill);
      const value = Number(response[responseKey]);
      this.updateFillWidth(fill, value, fields);
      const arrowDir = this.getArrow(key, value);
      this._startPercentTicker(key, null, value, text, arrowDir);
    }
  }

  _updateMetricRows(containerSelector, rows, keyPrefix, fields, options = {}) {
    const container = this._body.querySelector(containerSelector);
    if (!container) {
      return;
    }

    const getKey = options.getKey || this._getMultiMetricRowKey;
    const getLabel = options.getLabel || this._getMultiMetricRowLabel;
    const { boundRows, removedKeys } = this._reconcileMultiMetricRows(container, rows, {
      getKey,
      getLabel,
    });

    for (const key of removedKeys) {
      const stateKey = `${keyPrefix}:${key}`;
      this._cancelTickerState(this.percentTicker, stateKey);
      this.prevValues.delete(stateKey);
    }

    for (const row of boundRows) {
      const subcell = row.cell;
      const text = subcell.querySelector(".text");
      const fill = subcell.querySelector(".fill");
      const labelName = row.label;
      const percent = row.percent === null ? null : Number(row.percent);

      if (percent === null || Number.isNaN(percent)) {
        this._setMultiMetricNoData(subcell, labelName);
        continue;
      }

      this._setMultiMetricVisible(subcell);
      this.updateFillWidth(fill, Number(row.percent), fields);

      const arrowKey = `${keyPrefix}:${row.key}`;
      const arrowDir = this.getArrow(arrowKey, percent);
      this._startPercentTicker(arrowKey, labelName, percent, text, arrowDir);
    }
  }

  _updateInterfaces(interfaces, fields) {
    const container = this._body.querySelector(".interfaces-data");
    if (!container) {
      return;
    }

    const rows = this._normalizeInterfaceRows(interfaces);
    const { boundRows, removedKeys } = this._reconcileMultiMetricRows(container, rows, {
      getKey: this._getInterfaceRowKey,
      getLabel: this._getInterfaceRowLabel,
    });

    for (const key of removedKeys) {
      this._cancelTickerState(this.interfaceTicker, key);
      this.prevValues.delete(`iface:${key}`);
    }

    for (const row of boundRows) {
      this._startInterfaceTicker(row, fields);
    }
  }

  _startInterfaceTicker(row, fields) {
    const key = row.key ?? '';
    const label = row.label ?? key;
    const bps = row.bps ?? null;
    const percent = row.percent ?? null;
    const subcell = row.cell;
    const text = subcell.querySelector(".text");
    const fill = subcell.querySelector(".fill");

    if (bps === null || bps === undefined) {
      this._setMultiMetricNoData(subcell, String(label));
      return;
    }

    const state = this.interfaceTicker.get(key) || {
      value: 0,
      rafId: null,
    };
    if (state.rafId) {
      cancelAnimationFrame(state.rafId);
    }

    const from = Number(state.value) || 0;
    const to = Number(bps) || 0;
    const start = performance.now();
    const duration = 700;
    const arrowDir = this.getArrow(`iface:${key}`, to);

    this._setMultiMetricVisible(subcell);
    if (Number.isFinite(Number(percent))) {
      this.updateFillWidth(fill, Number(percent), fields);
    }

    const step = (now) => {
      const t = Math.min(1, (now - start) / duration);
      const ease = 1 - Math.pow(1 - t, 3);
      const current = from + (to - from) * ease;

      this._renderTextWithArrow(text, `${label} — ${this._formatBps(current)}`, arrowDir);
      state.value = current;

      if (t < 1) {
        state.rafId = requestAnimationFrame(step);
        return;
      }

      state.value = to;
      state.rafId = null;
      this._renderTextWithArrow(text, `${label} — ${this._formatBps(to)}`, arrowDir);
    };

    state.rafId = requestAnimationFrame(step);
    this.interfaceTicker.set(key, state);
  }

  _normalizeInterfaceRows(interfaces) {
    if (Array.isArray(interfaces)) {
      return interfaces;
    }

    const rows = [];
    for (const [key, item] of Object.entries(interfaces || {})) {
      rows.push({
        ...item,
        key,
        label: key,
      });
    }

    return rows;
  }

  _reconcileMultiMetricRows(container, rows, options = {}) {
    const getKey = options.getKey || this._getMultiMetricRowKey;
    const getLabel = options.getLabel || this._getMultiMetricRowLabel;
    const normalizedRows = [];
    const seenKeys = new Set();

    for (const row of rows ?? []) {
      const key = String(getKey.call(this, row) ?? '').trim();
      if (key === '' || seenKeys.has(key)) {
        continue;
      }

      seenKeys.add(key);
      normalizedRows.push({
        ...row,
        key,
        label: String(getLabel.call(this, row, key) ?? key),
      });
    }

    const existingCells = new Map();
    for (const cell of container.querySelectorAll('.cell[data-key]')) {
      const key = cell.getAttribute('data-key');
      if (key !== null) {
        existingCells.set(key, cell);
      }
    }

    const removedKeys = [];
    const naIndicator = container.querySelector('.na-indicator');

    if (normalizedRows.length === 0) {
      for (const [key, cell] of existingCells) {
        removedKeys.push(key);
        cell.remove();
      }

      if (!naIndicator) {
        const emptyState = document.createElement('span');
        emptyState.className = 'na-indicator';
        emptyState.textContent = 'No data';
        container.appendChild(emptyState);
      }

      return {
        boundRows: [],
        removedKeys,
      };
    }

    if (naIndicator) {
      naIndicator.remove();
    }

    const desiredKeys = new Set();
    for (const row of normalizedRows) {
      desiredKeys.add(row.key);
    }

    for (const [key, cell] of existingCells) {
      if (desiredKeys.has(key)) {
        continue;
      }

      removedKeys.push(key);
      cell.remove();
      existingCells.delete(key);
    }

    const boundRows = [];
    for (const row of normalizedRows) {
      const cell = existingCells.get(row.key) || this._createMultiMetricCell(row.key, row.label);
      cell.setAttribute('data-key', row.key);
      cell.setAttribute('data-label', row.label);
      container.appendChild(cell);

      boundRows.push({
        ...row,
        cell,
      });
    }

    return {
      boundRows,
      removedKeys,
    };
  }

  _createMultiMetricCell(key, label) {
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
  }

  _getMultiMetricRowKey(row) {
    return row.key ?? row.name;
  }

  _getMultiMetricRowLabel(row, key) {
    return row.label ?? row.name ?? key;
  }

  _getInterfaceRowKey(row) {
    return row.key;
  }

  _getInterfaceRowLabel(row, key) {
    return row.label ?? key;
  }

  _cancelTickerState(map, key) {
    const state = map.get(key);
    if (state?.rafId) {
      cancelAnimationFrame(state.rafId);
    }

    map.delete(key);
  }

  _startPercentTicker(key, name, toPercent, textEl, arrowDir = null) {
    const state = this.percentTicker.get(key) || { value: 0, rafId: null };
    if (state.rafId) {
      cancelAnimationFrame(state.rafId);
    }

    const from = Number(state.value) || 0;
    const to = this._clampPercent(toPercent);
    const start = performance.now();
    const duration = 500;

    const step = (now) => {
      const t = Math.min(1, (now - start) / duration);
      const ease = 1 - Math.pow(1 - t, 3);
      const current = Math.round(from + (to - from) * ease);
      const text = name ? `${name} — ${current}%` : `${current}%`;

      this._renderTextWithArrow(textEl, text, arrowDir);
      state.value = current;

      if (t < 1) {
        state.rafId = requestAnimationFrame(step);
        return;
      }

      state.value = to;
      state.rafId = null;
      const finalText = name ? `${name} — ${to}%` : `${to}%`;
      this._renderTextWithArrow(textEl, finalText, arrowDir);
    };

    state.rafId = requestAnimationFrame(step);
    this.percentTicker.set(key, state);
  }

  _clampPercent(value) {
    const rounded = Math.round(Number(value) || 0);
    return Math.max(0, Math.min(100, rounded));
  }

  _renderTextWithArrow(textEl, labelText, arrowDir) {
    if (!textEl) {
      return;
    }

    textEl.innerHTML = '';
    textEl.textContent = labelText;

    if (!arrowDir) {
      return;
    }

    const span = document.createElement('span');
    span.className = `dir-arrow dir-${arrowDir}`;
    textEl.appendChild(document.createTextNode(' '));
    textEl.appendChild(span);
  }

  _setSingleMetricNoData(fill, text) {
    const bar = fill ? fill.closest('.bar') : null;
    const data = bar ? bar.closest('.data') : null;

    if (bar) {
      bar.style.display = 'none';
    }
    if (data) {
      data.classList.add('no-bar');
    }
    if (text) {
      text.textContent = 'No data';
    }
  }

  _setSingleMetricVisible(fill) {
    const bar = fill ? fill.closest('.bar') : null;
    const data = bar ? bar.closest('.data') : null;

    if (bar) {
      bar.style.display = '';
    }
    if (data) {
      data.classList.remove('no-bar');
    }
  }

  _setMultiMetricNoData(subcell, labelName) {
    if (!subcell) {
      return;
    }

    const bar = subcell.querySelector('.bar');
    const text = subcell.querySelector('.text');

    subcell.classList.add('no-bar');
    if (bar) {
      bar.style.display = 'none';
    }
    if (text) {
      text.textContent = `${labelName} — No data`;
    }
  }

  _setMultiMetricVisible(subcell) {
    if (!subcell) {
      return;
    }

    const bar = subcell.querySelector('.bar');
    subcell.classList.remove('no-bar');
    if (bar) {
      bar.style.display = '';
    }
  }

  _getBadgePayload(badge, badgeData) {
    const index = badge?.getAttribute("data-badge-index");
    if (index === null || index === undefined) {
      return null;
    }

    return badgeData[index] ?? null;
  }

  _setBadgeText(badgeText, text) {
    if (!badgeText) {
      return;
    }

    badgeText.classList.remove("badge-parts");
    badgeText.textContent = text;
  }

  _formatBps(bps) {
    if (bps >= 1e9) return (bps / 1e9).toFixed(bps % 1e9 === 0 ? 0 : 1) + ' Gbps';
    if (bps >= 1e6) return (bps / 1e6).toFixed(bps % 1e6 === 0 ? 0 : 1) + ' Mbps';
    if (bps >= 1e3) return (bps / 1e3).toFixed(bps % 1e3 === 0 ? 0 : 1) + ' Kbps';
    return Math.round(bps) + ' bps';
  }
}
