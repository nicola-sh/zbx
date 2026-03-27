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
  static BADGE_FRESHNESS_STATE_CLASSES = ['freshness-warn', 'freshness-stale'];
  static BADGE_PROBLEM_STATE_CLASSES = [
    'problems-info',
    'problems-warning',
    'problems-average',
    'problems-high',
    'problems-disaster',
  ];
  static BADGE_PROBLEM_SEVERITY_CLASS_MAP = {
    0: 'problems-info',
    1: 'problems-info',
    2: 'problems-warning',
    3: 'problems-average',
    4: 'problems-high',
    5: 'problems-disaster',
  };

  onInitialize() {
    this.rendered = false;
    this._runtimeFields = this._fields || {};
    // State for interface tickers
    this.interfaceTicker = new Map();
    // State for percent tickers
    this.percentTicker = new Map();
    // Previous settled values for direction arrows
    this.prevValues = new Map();
    this.itemMap = {};
    this._sparkline = new HostOverviewSparkline({
      getBody: () => this._body,
      getFields: () => this._getRuntimeFields(),
      getItemRef: (metricKey) => this.itemMap[metricKey],
      getWidgetRoot: () => this._getWidgetRoot(),
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

  // Get the widget root element (.dashboard-widget-host_overview)
  _getWidgetRoot() {
    return this._body?.closest('.dashboard-widget-host_overview')
      || this._target
      || this._body;
  }

  // Toggle hidden_header class on the widget root when the header is hidden
  updateViewModeAttr() {
    try {
      if (!this._body) return;
      if (typeof this.getViewMode !== "function") return;
      const root = this._getWidgetRoot();
      if (root) root.classList.toggle("hidden_header", this.getViewMode());
    } catch (_) {}
  }

  // Sync config-driven modifier classes to the widget root (once on first render)
  _syncRootModifiers(fields) {
    const root = this._getWidgetRoot();
    if (!root) return;
    root.classList.toggle('square-corners', String(fields['corners']) === '1');
    root.classList.toggle('problems-pulse-enabled', String(fields['problems_pulse']) === '1');
  }

  // Set width and color for a fill element
  updateFillWidth(element, percent, fields = null, metricKey = null) {
    if (!element) return;
    const p = Number(percent);
    const targetWidth = `${p}%`;
    element.style.width = targetWidth;

    // Apply color directly from config fields if available
    if (!fields) return;

    if (fields["color_scheme"] == "1") {
      element.style.backgroundColor = `#${fields["fill_color"]}`;
    } else {
      const thresholdMetric = this._getThresholdMetricKey(metricKey);
      const highThreshold = this._getThresholdValue(fields, thresholdMetric, 1);
      const mediumThreshold = this._getThresholdValue(fields, thresholdMetric, 2);

      if (p > highThreshold) {
        element.style.backgroundColor = `#${fields["th_color_1"]}`;
      } else if (p > mediumThreshold) {
        element.style.backgroundColor = `#${fields["th_color_2"]}`;
      } else {
        element.style.backgroundColor = `#${fields["th_color_3"]}`;
      }
    }
  }

  _getThresholdMetricKey(metricKey) {
    if (!metricKey) {
      return null;
    }

    if (metricKey === 'iface' || metricKey.startsWith('iface:')) {
      return 'iface';
    }

    if (metricKey === 'disk' || metricKey.startsWith('disk:')) {
      return 'disk';
    }

    if (metricKey === 'partition' || metricKey.startsWith('partition:')) {
      return 'partition';
    }

    return metricKey;
  }

  _getThresholdValue(fields, metricKey, level) {
    const metricField = metricKey ? fields[`th_${metricKey}_${level}`] : undefined;
    const fallbackField = fields[`th_num_${level}`];
    const rawValue = metricField !== undefined && metricField !== null && metricField !== ''
      ? metricField
      : fallbackField;
    const value = Number(rawValue);

    return Number.isFinite(value) ? value : 0;
  }

  // Widget lifecycle
  setContents(response) {
    // When the override host changes, re-render from scratch
    if (this.isFieldsReferredDataUpdated()) {
      this.rendered = false;
      this.clearContents();
    }

    // Render the skeleton once then update values
    if (!this.rendered) {
      super.setContents(response);
      this.rendered = true;
      // Ensure the container reflects the current view mode
      this.updateViewModeAttr();
      this._sparkline?.attach();
    }

    // Keep the original widget fields intact so foreign references are not
    // replaced with resolved runtime values and accidentally saved as static IDs.
    if (response.config && Object.keys(response.config).length > 0) {
      this._runtimeFields = response.config;
    }

    this._syncRootModifiers(this._getRuntimeFields());
    if (response.item_map) {
      this.itemMap = response.item_map;
    }
    // Store disk/partition item names from row data
    if (response.disks) {
      for (const row of response.disks) {
        if (row.item_ref) {
          this.itemMap[`disk:${row.name}`] = row.item_ref;
        } else if (row.item_name) {
          this.itemMap[`disk:${row.name}`] = row.item_name;
        }
      }
    }
    if (response.partitions) {
      for (const row of response.partitions) {
        if (row.item_ref) {
          this.itemMap[`partition:${row.name}`] = row.item_ref;
        } else if (row.item_name) {
          this.itemMap[`partition:${row.name}`] = row.item_name;
        }
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

    this._updateBadges(badgeData, fields);
    this._updateSingleMetrics(response, fields, enabledMetrics);

    if (enabledMetrics.has(CWidgetHostOverview.METRIC_INTERFACES)) {
      this._updateInterfaces(response.interfaces, fields);
    }

    if (enabledMetrics.has(CWidgetHostOverview.METRIC_DISKS)) {
      this._updateMetricRows(".disks-data", response.disks, "disk", fields);
    }

    if (enabledMetrics.has(CWidgetHostOverview.METRIC_PARTITIONS)) {
      this._updateMetricRows(".partitions-data", response.partitions, "partition", fields);
    }
  }

  _updateBadges(badgeData, fields) {
    const warnThreshold = Math.max(0, Number(fields["freshness_warn"]) || 60);
    const staleThreshold = Math.max(
      warnThreshold,
      Number(fields["freshness_stale"]) || 300
    );

    this._forEachBadge(".host-badge", badgeData, (_badge, payload, badgeText) => {
      if (!badgeText) {
        return;
      }

      const hostname = String(this._getBadgeValue(payload, "hostname", "") || '').trim();
      this._setBadgeText(badgeText, hostname || 'Hostname missing');
    });

    this._forEachBadge(".uptime-badge", badgeData, (_badge, payload, badgeText) => {
      if (!badgeText) {
        return;
      }

      this._setBadgeText(badgeText, this._getBadgeValue(payload, "uptime", "—") || '—');
    });

    this._forEachBadge(".freshness-badge", badgeData, (badge, payload, badgeText) => {
      if (!badgeText) {
        return;
      }

      const secs = Number(this._getBadgeValue(payload, "freshness"));

      badge.classList.remove(...CWidgetHostOverview.BADGE_FRESHNESS_STATE_CLASSES);
      if (!Number.isFinite(secs)) {
        this._setBadgeText(badgeText, '—');
        return;
      }

      this._setBadgeText(
        badgeText,
        secs < 60 ? `${secs}s ago` : `${Math.floor(secs / 60)}m ago`
      );

      if (secs >= staleThreshold) {
        badge.classList.add('freshness-stale');
      } else if (secs >= warnThreshold) {
        badge.classList.add('freshness-warn');
      }
    });

    this._forEachBadge(".tags-badge", badgeData, (_badge, payload, badgeText) => {
      if (!badgeText) {
        return;
      }

      const badgeTags = this._getBadgeValue(payload, "tags", []);
      const tags = Array.isArray(badgeTags)
        ? badgeTags
        : [];

      const text = tags
        .map((tag) => {
          const tagName = String(tag?.tag ?? '').trim();
          const tagValue = String(tag?.value ?? '').trim();

          if (tagName === '') {
            return '';
          }

          return tagValue === '' ? tagName : `${tagName}: ${tagValue}`;
        })
        .filter((tag) => tag !== '')
        .join(' • ');

      this._setBadgeText(badgeText, text);
    });

    this._forEachBadge(".maintenance-badge", badgeData, (badge, payload, badgeText) => {
      const status = Number(this._getBadgeValue(payload, "status", 0));
      const isActive = status === 1;

      badge.classList.toggle("maintenance", isActive);
      this._setBadgeHidden(badge, !isActive);

      if (badgeText) {
        this._setBadgeText(badgeText, isActive ? "Maintenance" : "");
      }
    });

    this._forEachBadge(".problems-badge", badgeData, (badge, payload, badgeText) => {
      const problems = this._getBadgeValue(payload, "problems");

      badge.classList.remove("problems-severity");
      badge.classList.remove(...CWidgetHostOverview.BADGE_PROBLEM_STATE_CLASSES);

      if (!problems) {
        this._setBadgeHidden(badge, false);
        if (badgeText) {
          this._setBadgeText(badgeText, 'Problems —');
        }
        return;
      }

      const total = Number(problems.total) || 0;
      if (total === 0) {
        this._setBadgeHidden(badge, true);
        return;
      }

      this._setBadgeHidden(badge, false);
      if (badgeText) {
        this._setBadgeText(badgeText, `${total} problems`);
      }

      const maxSeverity = Number(problems.max_severity);
      badge.classList.add("problems-severity");
      badge.classList.add(CWidgetHostOverview.BADGE_PROBLEM_SEVERITY_CLASS_MAP[maxSeverity] || 'problems-info');
    });
  }

  _forEachBadge(selector, badgeData, callback) {
    for (const badge of this._body.querySelectorAll(selector)) {
      callback(badge, this._getBadgePayload(badge, badgeData), badge.querySelector(".badge-text"));
    }
  }

  _getBadgeValue(payload, key, fallback = null) {
    if (!payload || !Object.prototype.hasOwnProperty.call(payload, key)) {
      return fallback;
    }

    return payload[key];
  }

  _updateSingleMetrics(response, fields, enabledMetrics) {
    const singleMetrics = [
      [CWidgetHostOverview.METRIC_CPU,  'cpu',  'cpu',  'percent'],
      [CWidgetHostOverview.METRIC_RAM,  'ram',  'ram',  'percent'],
      [CWidgetHostOverview.METRIC_LOAD, 'load', 'load', 'load'],
      [CWidgetHostOverview.METRIC_SWAP, 'swap', 'swap', 'percent'],
    ];

    for (const [metricId, key, responseKey, mode] of singleMetrics) {
      if (!enabledMetrics.has(metricId)) {
        continue;
      }

      const cell = this._getMetricCell(key);
      if (!cell) {
        continue;
      }

      const fill = cell.querySelector('.metric-fill');
      const text = this._getMetricTextElement(cell);
      this._syncMetricLinks(cell, key);

      if (response[responseKey] == null) {
        this._setMetricCellNoData(cell);
        continue;
      }

      this._setMetricCellVisible(cell);
      const value = Number(response[responseKey]);

      if (mode === 'load') {
        this.updateFillWidth(fill, this._getLoadBarPercent(value, fields), fields, key);
        const arrowDir = this.getArrow(key, value);
        this._startLoadTicker(key, value, text, arrowDir);
        continue;
      }

      this.updateFillWidth(fill, value, fields, key);
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
      const text = this._getMetricTextElement(subcell);
      const fill = subcell.querySelector(".metric-fill");
      const labelName = row.label;
      const percent = row.percent === null ? null : Number(row.percent);
      const metricKey = `${keyPrefix}:${row.key}`;

      this._syncMetricLinks(subcell, metricKey, row.item_ref);

      if (percent === null || Number.isNaN(percent)) {
        this._setMetricCellNoData(subcell, `${labelName} — No data`);
        continue;
      }

      this._setMetricCellVisible(subcell);
      this.updateFillWidth(fill, Number(row.percent), fields, metricKey);

      const arrowDir = this.getArrow(metricKey, percent);
      this._startPercentTicker(metricKey, labelName, percent, text, arrowDir);
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
    const text = this._getMetricTextElement(subcell);
    const fill = subcell.querySelector(".metric-fill");
    const metricKey = `iface:${key}`;

    this._syncMetricLinks(subcell, metricKey, row.item_ref);

    if (bps === null || bps === undefined) {
      this._setMetricCellNoData(subcell, `${label} — No data`);
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
    const arrowDir = this.getArrow(metricKey, to);

    this._setMetricCellVisible(subcell);
    if (Number.isFinite(Number(percent))) {
      this.updateFillWidth(fill, Number(percent), fields, metricKey);
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
    for (const cell of container.querySelectorAll('.metric-cell[data-key]')) {
      const key = cell.getAttribute('data-key');
      if (key !== null) {
        existingCells.set(key, cell);
      }
    }

    const removedKeys = [];
    const emptyState = Array.from(container.children).find((child) =>
      child.classList?.contains('metric-empty')
    ) || null;

    if (normalizedRows.length === 0) {
      for (const [key, cell] of existingCells) {
        removedKeys.push(key);
        cell.remove();
      }

      if (!emptyState) {
        const placeholder = document.createElement('span');
        placeholder.className = 'metric-empty';
        placeholder.textContent = 'No data';
        container.appendChild(placeholder);
      }

      return {
        boundRows: [],
        removedKeys,
      };
    }

    if (emptyState) {
      emptyState.remove();
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
      const cell = existingCells.get(row.key) || this._createMetricCell(row.key, row.label);
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

  _createMetricCell(key, label) {
    const cell = document.createElement('div');
    const bar = document.createElement('div');
    const fill = document.createElement('div');
    const text = document.createElement('a');

    cell.className = 'metric-cell';
    cell.setAttribute('data-key', key);
    cell.setAttribute('data-label', label);

    bar.className = 'metric-bar';
    fill.className = 'metric-fill';
    text.className = 'metric-text metric-link metric-value-link js-metric-link';

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

  _startLoadTicker(key, toValue, textEl, arrowDir = null) {
    const state = this.percentTicker.get(key) || { value: 0, rafId: null };
    if (state.rafId) {
      cancelAnimationFrame(state.rafId);
    }

    const from = Number(state.value) || 0;
    const to = Number(toValue) || 0;
    const start = performance.now();
    const duration = 500;

    const step = (now) => {
      const t = Math.min(1, (now - start) / duration);
      const ease = 1 - Math.pow(1 - t, 3);
      const current = from + (to - from) * ease;

      this._renderTextWithArrow(textEl, this._formatLoadValue(current), arrowDir);
      state.value = current;

      if (t < 1) {
        state.rafId = requestAnimationFrame(step);
        return;
      }

      state.value = to;
      state.rafId = null;
      this._renderTextWithArrow(textEl, this._formatLoadValue(to), arrowDir);
    };

    state.rafId = requestAnimationFrame(step);
    this.percentTicker.set(key, state);
  }

  _getLoadBarPercent(loadValue, fields) {
    const loadCeiling = Number(fields?.load_high) || 0;

    if (loadCeiling <= 0) {
      return 0;
    }

    return this._clampPercent((Number(loadValue) / loadCeiling) * 100);
  }

  _clampPercent(value) {
    const rounded = Math.round(Number(value) || 0);
    return Math.max(0, Math.min(100, rounded));
  }

  _formatLoadValue(value) {
    const rounded = Math.round((Number(value) || 0) * 100) / 100;

    return Number.isInteger(rounded) ? rounded.toFixed(0) : rounded.toFixed(2);
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

  _getMetricCell(metricKey) {
    if (!metricKey || !this._body) {
      return null;
    }

    for (const cell of this._body.querySelectorAll('.metric-cell[data-metric-key]')) {
      if (cell.getAttribute('data-metric-key') === metricKey) {
        return cell;
      }
    }

    return null;
  }

  _getMetricTextElement(cell) {
    if (!cell) {
      return null;
    }

    return cell.querySelector('.metric-text, .metric-empty');
  }

  _getLinkTarget() {
    return String(this._getRuntimeFields()?.open_links_same_window) === '1' ? '_self' : '_blank';
  }

  _syncLinkTarget(linkEl) {
    if (!linkEl) {
      return;
    }

    const target = this._getLinkTarget();
    linkEl.setAttribute('target', target);

    if (target === '_blank') {
      linkEl.setAttribute('rel', 'noopener');
      return;
    }

    linkEl.removeAttribute('rel');
  }

  _syncMetricLinks(cell, metricKey, itemRef = undefined) {
    if (!cell) {
      return;
    }

    if (metricKey) {
      cell.setAttribute('data-metric-key', metricKey);
    }

    const row = cell.closest('.metric-row');
    const labelLink = row?.querySelector('.metric-label .js-metric-link');
    const valueLink = cell.querySelector('.js-metric-link');

    this._syncMetricLink(labelLink, metricKey, itemRef);

    if (valueLink && valueLink !== labelLink) {
      this._syncMetricLink(valueLink, metricKey, itemRef);
    }
  }

  _syncMetricLink(linkEl, metricKey, itemRef = undefined) {
    if (!linkEl) {
      return;
    }

    if (metricKey) {
      linkEl.setAttribute('data-metric-key', metricKey);
    }

    const latestDataUrl = this._buildMetricLatestDataUrl(
      itemRef !== undefined ? itemRef : this.itemMap[metricKey]
    );

    if (latestDataUrl) {
      linkEl.setAttribute('href', latestDataUrl);
      linkEl.setAttribute('title', 'Open latest data');
      this._syncLinkTarget(linkEl);
      linkEl.classList.remove('is-disabled');
      return;
    }

    linkEl.removeAttribute('href');
    linkEl.removeAttribute('title');
    linkEl.removeAttribute('target');
    linkEl.removeAttribute('rel');
    linkEl.classList.add('is-disabled');
  }

  _buildMetricLatestDataUrl(itemRef) {
    const hostid = (this._getRuntimeFields()?.hostid || [])[0];

    if (!hostid) {
      return null;
    }

    let itemName = '';

    if (typeof itemRef === 'string') {
      itemName = itemRef.trim();
    } else if (itemRef && typeof itemRef === 'object' && itemRef.name != null) {
      itemName = String(itemRef.name).trim();
    }

    if (!itemName) {
      return null;
    }

    const params = new URLSearchParams({
      action: 'latest.view',
      name: itemName,
      filter_set: '1',
    });
    params.append('hostids[]', String(hostid));

    return `zabbix.php?${params.toString()}`;
  }

  _getRuntimeFields() {
    return this._runtimeFields || this._fields || {};
  }

  _setMetricCellNoData(cell, textValue = 'No data') {
    if (!cell) {
      return;
    }

    const bar = cell.querySelector('.metric-bar');
    const text = this._getMetricTextElement(cell);

    cell.classList.add('is-empty');
    if (bar) {
      bar.hidden = true;
    }
    if (text) {
      text.classList.remove('metric-text');
      text.classList.add('metric-empty');
      text.textContent = textValue;
    }
  }

  _setMetricCellVisible(cell) {
    if (!cell) {
      return;
    }

    const bar = cell.querySelector('.metric-bar');
    const text = this._getMetricTextElement(cell);
    cell.classList.remove('is-empty');
    if (bar) {
      bar.hidden = false;
    }
    if (text) {
      text.classList.remove('metric-empty');
      text.classList.add('metric-text');
    }
  }

  _getBadgePayload(badge, badgeData) {
    const index = badge?.getAttribute("data-badge-index");
    if (index === null || index === undefined) {
      return null;
    }

    return badgeData[index] ?? null;
  }

  _setBadgeHidden(badge, hidden) {
    if (!badge) {
      return;
    }

    badge.hidden = hidden;
    badge.classList.remove("is-hidden");
  }

  _setBadgeText(badgeText, text) {
    if (!badgeText) {
      return;
    }

    badgeText.classList.remove("badge-parts");
    badgeText.classList.remove("is-placeholder");
    badgeText.textContent = text;
  }

  _formatBps(bps) {
    if (bps >= 1e9) return (bps / 1e9).toFixed(bps % 1e9 === 0 ? 0 : 1) + ' Gbps';
    if (bps >= 1e6) return (bps / 1e6).toFixed(bps % 1e6 === 0 ? 0 : 1) + ' Mbps';
    if (bps >= 1e3) return (bps / 1e3).toFixed(bps % 1e3 === 0 ? 0 : 1) + ' Kbps';
    return Math.round(bps) + ' bps';
  }
}
