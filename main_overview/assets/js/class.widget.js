/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

class CWidgetMainOverview extends CWidget {

  static DYNAMIC_BADGE_CLASSES = [
    'empty',
    'freshness-warn',
    'freshness-stale',
    'problems-severity',
    'problems-info',
    'problems-warning',
    'problems-average',
    'problems-high',
    'problems-disaster',
  ];

  static TRAFFIC_LIGHT_CLASSES = ['main-overview-light--green', 'main-overview-light--yellow', 'main-overview-light--red'];

  onInitialize() {
    this.rendered = false;
    this._runtimeFields = this._fields || {};
    this._layoutSignature = '';
    this._multiDetailHostId = null;
    this._overviewClickRoots = new Set();
    this._handleOverviewClick = (event) => this._onOverviewClick(event);
    this._sparklineOverviewContext = null;
    this._multiNavBound = null;
    this._multiNavBackEl = null;
    this._onMultiNavClick = (event) => this._handleMultiNavClick(event);
    this._onMultiNavKey = (event) => this._handleMultiNavKey(event);
    this._onMultiBackClick = (event) => this._handleMultiBackClick(event);
    this.valueTicker = new Map();
    this.prevValues = new Map();

    this._sparkline = new MainOverviewSparkline({
      getBody: () => this._body,
      getOverlayRoot: () => this._getSparklineRoot(),
      getOverviewRoot: () => this._sparklineOverviewContext || this._getFirstOverviewRoot(),
      getWidgetRoot: () => this._getWidgetRoot(),
      getFields: () => this._getRuntimeFields(),
      onClose: () => {
        this._sparklineOverviewContext = null;
      },
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
      formatBps: (bps) => this._formatBps(bps),
      hasSquareCorners: () => this._getWidgetRoot()?.classList.contains('square-corners') ?? false,
    });
  }

  onActivate() {
    this._attachOverview();
    this._attachMultiNavigation();
    this._sparkline?.scheduleRedraw();
  }

  onDeactivate() {
    this._sparkline?.close();
    for (const root of this._overviewClickRoots) {
      root.removeEventListener('click', this._handleOverviewClick);
    }

    this._overviewClickRoots.clear();
    this._detachMultiNavigation();
    this._resetAnimationState();
  }

  onClearContents() {
    this._sparkline?.close();
    for (const root of this._overviewClickRoots) {
      root.removeEventListener('click', this._handleOverviewClick);
    }

    this._overviewClickRoots.clear();
    this._detachMultiNavigation();
    this._resetAnimationState();
    this._sparklineOverviewContext = null;
  }

  onResize() {
    this._sparkline?.scheduleRedraw();
  }

  onDestroy() {
    for (const root of this._overviewClickRoots) {
      root.removeEventListener('click', this._handleOverviewClick);
    }

    this._overviewClickRoots.clear();
    this._detachMultiNavigation();
    this._resetAnimationState();
    this._sparkline?.destroy();
  }

  _getWidgetRoot() {
    return this._body?.closest('.dashboard-widget-main_overview')
      || this._target
      || this._body;
  }

  _getFirstOverviewRoot() {
    return this._body?.querySelector('[data-main-overview-role="overview"]') || null;
  }

  _getSparklineRoot() {
    return this._body?.querySelector('[data-main-overview-role="sparkline"]') || null;
  }

  _isHiddenHeaderMode() {
    if (typeof this.getViewMode !== 'function') {
      return false;
    }

    const mode = this.getViewMode();

    if (typeof ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER !== 'undefined') {
      return mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;
    }

    return Boolean(mode);
  }

  updateViewModeAttr() {
    try {
      if (!this._body || typeof this.getViewMode !== 'function') {
        return;
      }

      const root = this._getWidgetRoot();
      if (root) {
        root.classList.toggle('hidden_header', this._isHiddenHeaderMode());
      }
    } catch (_) {}
  }

  _syncRootModifiers(fields) {
    const root = this._getWidgetRoot();
    if (!root) {
      return;
    }

    root.classList.toggle('square-corners', String(fields?.corners) === '1');
    root.classList.toggle('problems-pulse-enabled', String(fields?.problems_pulse) === '1');
    this._syncThemeColors(fields, root);
  }

  _normalizeThemeHex(value, fallback) {
    let hex = String(value ?? fallback).trim().replace(/^#/, '');

    if (!/^[0-9a-fA-F]{6}$/.test(hex)) {
      hex = String(fallback).replace(/^#/, '');
    }

    return `#${hex.toUpperCase()}`;
  }

  _hexToRgba(hex, alpha) {
    const h = hex.replace(/^#/, '');
    const r = Number.parseInt(h.slice(0, 2), 16);
    const g = Number.parseInt(h.slice(2, 4), 16);
    const b = Number.parseInt(h.slice(4, 6), 16);

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  _syncThemeColors(fields, root = null) {
    const el = root || this._getWidgetRoot();

    if (!el || !fields) {
      return;
    }

    const green = this._normalizeThemeHex(fields.th_color_3, '4C9F38');
    const yellow = this._normalizeThemeHex(fields.th_color_2, 'FF851B');
    const red = this._normalizeThemeHex(fields.th_color_1, 'FF4136');
    const solid = this._normalizeThemeHex(fields.fill_color, '458ADC');

    const set = (name, value) => el.style.setProperty(name, value);

    set('--ho-color-green', green);
    set('--ho-color-yellow', yellow);
    set('--ho-color-red', red);
    set('--ho-color-solid', solid);
    set('--sparkline-active-color', solid);
    set('--ho-freshness-warn-bg', this._hexToRgba(yellow, 0.38));
    set('--ho-freshness-stale-bg', this._hexToRgba(red, 0.38));
    set('--ho-maintenance-bg', this._hexToRgba(solid, 0.32));
    set('--ho-problems-info-bg', this._hexToRgba(solid, 0.34));
    set('--ho-problems-warning-bg', this._hexToRgba(yellow, 0.4));
    set('--ho-problems-average-bg', this._hexToRgba(yellow, 0.48));
    set('--ho-problems-high-bg', this._hexToRgba(red, 0.42));
    set('--ho-problems-disaster-bg', this._hexToRgba(red, 0.52));
  }

  setContents(response) {
    const nextLayoutSignature = response.layout_signature ?? '';
    const requiresFullRender = !this.rendered
      || this.isFieldsReferredDataUpdated()
      || nextLayoutSignature !== this._layoutSignature;
    const previousDetailHostId = this._captureCurrentDetailHostId();

    this._multiHost = Boolean(response.multi_host);

    if (response.config && Object.keys(response.config).length > 0) {
      this._runtimeFields = response.config;
    }

    if (requiresFullRender) {
      this._sparkline?.close();

      if (this.rendered) {
        this.clearContents();
      }

      super.setContents(response);
      this.rendered = true;
    }

    this.updateViewModeAttr();
    this._attachOverview();
    this._attachMultiNavigation();

    if (response.multi_host) {
      if (requiresFullRender) {
        this._restoreMultiHostView(previousDetailHostId);
      }
      else if (this._multiDetailHostId && !this._hasHostDetailPanel(this._multiDetailHostId)) {
        this._showMultiListView();
      }
    }
    else {
      this._multiDetailHostId = null;
    }

    this._sparkline?.attach();
    this._syncRootModifiers(this._getRuntimeFields());
    this._layoutSignature = nextLayoutSignature;

    if (response.values) {
      this.updateValues(response.values);
    }
  }

  _overviewRootsEqual(next) {
    if (this._overviewClickRoots.size !== next.size) {
      return false;
    }

    for (const node of next) {
      if (!this._overviewClickRoots.has(node)) {
        return false;
      }
    }

    return true;
  }

  _attachOverview() {
    const next = new Set(this._body?.querySelectorAll('[data-main-overview-role="overview"]') || []);

    for (const node of this._overviewClickRoots) {
      if (!next.has(node)) {
        node.removeEventListener('click', this._handleOverviewClick);
      }
    }

    for (const node of next) {
      if (!this._overviewClickRoots.has(node)) {
        node.addEventListener('click', this._handleOverviewClick);
      }
    }

    if (!this._overviewRootsEqual(next)) {
      this._resetAnimationState();
    }

    this._overviewClickRoots = next;
  }

  _attachMultiNavigation() {
    const root = this._body?.querySelector('[data-main-overview-multi="1"]');

    if (!root) {
      this._detachMultiNavigation();

      return;
    }

    if (this._multiNavBound === root) {
      return;
    }

    this._detachMultiNavigation();
    this._multiNavBound = root;
    root.addEventListener('click', this._onMultiNavClick);
    root.addEventListener('keydown', this._onMultiNavKey);

    const back = root.querySelector('[data-main-overview-back]');

    if (back) {
      back.addEventListener('click', this._onMultiBackClick);
      this._multiNavBackEl = back;
    }
  }

  _detachMultiNavigation() {
    if (!this._multiNavBound) {
      return;
    }

    this._multiNavBound.removeEventListener('click', this._onMultiNavClick);
    this._multiNavBound.removeEventListener('keydown', this._onMultiNavKey);

    if (this._multiNavBackEl) {
      this._multiNavBackEl.removeEventListener('click', this._onMultiBackClick);
      this._multiNavBackEl = null;
    }

    this._multiNavBound = null;
  }

  _handleMultiNavClick(event) {
    const summary = event.target.closest('[data-main-overview-nav]');

    if (!summary || !this._multiNavBound?.contains(summary)) {
      return;
    }

    if (event.target.closest('.metric-bar')) {
      return;
    }

    if (event.target.closest('a[href]')) {
      return;
    }

    if (event.target.closest('.js-badge-host-menu, [data-hintbox], .menu-popup')) {
      return;
    }

    event.preventDefault();
    const hostid = summary.getAttribute('data-main-overview-nav');

    if (hostid) {
      this._openMultiDetail(hostid);
    }
  }

  _handleMultiNavKey(event) {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    const summary = event.target.closest('[data-main-overview-nav]');

    if (!summary || !this._multiNavBound?.contains(summary)) {
      return;
    }

    event.preventDefault();
    const hostid = summary.getAttribute('data-main-overview-nav');

    if (hostid) {
      this._openMultiDetail(hostid);
    }
  }

  _handleMultiBackClick(event) {
    event.preventDefault();
    this._showMultiListView();
  }

  _openMultiDetail(hostid) {
    const root = this._getWidgetRoot();
    const multi = this._body?.querySelector('[data-main-overview-multi="1"]');

    if (!root || !multi || !hostid) {
      return;
    }

    root.classList.add('main-overview--multi-detail');
    multi.querySelector('.main-overview-multi-list-view')?.setAttribute('hidden', 'hidden');
    multi.querySelector('.main-overview-multi-detail-view')?.removeAttribute('hidden');

    for (const panel of multi.querySelectorAll('[data-host-detail-panel]')) {
      const match = panel.getAttribute('data-host-detail-panel') === hostid;

      if (match) {
        panel.removeAttribute('hidden');
      }
      else {
        panel.setAttribute('hidden', 'hidden');
      }
    }

    this._multiDetailHostId = hostid;
    this._sparkline?.scheduleRedraw();
  }

  _showMultiListView() {
    const root = this._getWidgetRoot();
    const multi = this._body?.querySelector('[data-main-overview-multi="1"]');

    if (!multi) {
      return;
    }

    root?.classList.remove('main-overview--multi-detail');
    multi.querySelector('.main-overview-multi-detail-view')?.setAttribute('hidden', 'hidden');
    multi.querySelector('.main-overview-multi-list-view')?.removeAttribute('hidden');

    for (const panel of multi.querySelectorAll('[data-host-detail-panel]')) {
      panel.setAttribute('hidden', 'hidden');
    }

    this._multiDetailHostId = null;
    this._sparkline?.close();
    this._sparkline?.scheduleRedraw();
  }

  _captureCurrentDetailHostId() {
    if (!this._multiHost) {
      return null;
    }

    const multi = this._body?.querySelector('[data-main-overview-multi="1"]');
    if (!multi) {
      return this._multiDetailHostId;
    }

    const panel = multi.querySelector('[data-host-detail-panel]:not([hidden])');
    const hostid = panel?.getAttribute('data-host-detail-panel');

    return hostid ? hostid : this._multiDetailHostId;
  }

  _hasHostDetailPanel(hostid) {
    if (!hostid) {
      return false;
    }

    const multi = this._body?.querySelector('[data-main-overview-multi="1"]');
    if (!multi) {
      return false;
    }

    return Boolean(multi.querySelector(`[data-host-detail-panel="${this._escapeSelector(hostid)}"]`));
  }

  _restoreMultiHostView(previousDetailHostId) {
    const detailHostId = this._multiDetailHostId || previousDetailHostId;

    if (detailHostId && this._hasHostDetailPanel(detailHostId)) {
      this._openMultiDetail(detailHostId);
      return;
    }

    this._showMultiListView();
  }

  _escapeSelector(value) {
    const s = String(value);

    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
      return CSS.escape(s);
    }

    return s.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  _applyTrafficLight(element, light) {
    if (!element) {
      return;
    }

    const next = typeof light === 'string' ? light.toLowerCase().trim() : 'green';
    const safe = ['green', 'yellow', 'red'].includes(next) ? next : 'green';

    element.classList.remove(...CWidgetMainOverview.TRAFFIC_LIGHT_CLASSES);
    element.classList.add(`main-overview-light--${safe}`);
    element.setAttribute('title', safe);
    element.setAttribute('aria-label', safe);
  }

  updateValues(values) {
    if (!values) {
      return;
    }

    if (this._multiHost && values.hosts) {
      this._patchMultiHostValues(values.hosts);

      return;
    }

    const root = this._getFirstOverviewRoot();

    if (!root) {
      return;
    }

    this._patchBadges(root, values.badges || {});
    this._patchCells(root, values.cells || {});
  }

  _patchMultiHostValues(hostsMap) {
    const root = this._body?.querySelector('[data-main-overview-multi="1"]');

    if (!root) {
      return;
    }

    for (const [hostid, payload] of Object.entries(hostsMap)) {
      const summary = root.querySelector(`[data-main-overview-nav="${this._escapeSelector(hostid)}"]`);
      const lightEl = summary?.querySelector('.main-overview-light');

      if (lightEl && payload?.light) {
        this._applyTrafficLight(lightEl, payload.light);
      }

      const summaryToolbar = summary?.querySelector('.main-overview-multi-summary-toolbar');

      if (summaryToolbar) {
        this._patchBadges(summaryToolbar, payload?.badges || {});
      }

      const panel = root.querySelector(`[data-host-detail-panel="${this._escapeSelector(hostid)}"]`);
      const overview = panel?.querySelector('[data-main-overview-role="overview"]');

      if (!overview) {
        continue;
      }

      this._patchBadges(overview, payload?.badges || {});
      this._patchCells(overview, payload?.cells || {});
    }
  }

  _patchBadges(root, badgeValues) {
    for (const badgeEl of root.querySelectorAll('[data-badge-id]')) {
      const badgeId = badgeEl.getAttribute('data-badge-id');
      const badgeValue = badgeId ? badgeValues[badgeId] : null;

      if (!badgeValue) {
        badgeEl.hidden = true;
        continue;
      }

      badgeEl.hidden = Boolean(badgeValue.hidden);
      this._applyStateClasses(badgeEl, badgeValue.state_classes);

      const badgeText = badgeEl.querySelector('.badge-text');
      if (badgeText) {
        badgeText.textContent = badgeValue.text ?? '';
      }
    }
  }

  _patchCells(root, cellValues) {
    const seenCellIds = new Set();

    for (const cellEl of root.querySelectorAll('[data-cell-id]')) {
      const cellId = cellEl.getAttribute('data-cell-id');
      const cellValue = cellId ? cellValues[cellId] : null;

      if (!cellId) {
        continue;
      }

      if (!cellValue) {
        this._cancelTicker(cellId);
        this.prevValues.delete(cellId);
        cellEl.hidden = true;
        continue;
      }

      seenCellIds.add(cellId);
      cellEl.hidden = false;
      this._patchCell(cellEl, cellId, cellValue);
    }

    for (const cellId of Array.from(this.valueTicker.keys())) {
      if (!seenCellIds.has(cellId)) {
        this._cancelTicker(cellId);
        this.prevValues.delete(cellId);
      }
    }
  }

  _patchCell(cellEl, cellId, cellValue) {
    const display = cellValue.display ?? {};
    const textEl = cellEl.querySelector('.metric-value-link');
    const barEl = cellEl.querySelector('.metric-bar');
    const fillEl = cellEl.querySelector('.metric-fill');

    const emptyStates = new Set(['empty', 'missing', 'ambiguous']);
    const isEmptyState = emptyStates.has(cellValue.state ?? '');

    if (isEmptyState || display.value == null) {
      this._cancelTicker(cellId);
      this.prevValues.delete(cellId);
      const emptyLabel = display.text || display.empty_text || display.value_text || 'No data';
      this._setMetricCellEmpty(cellEl, emptyLabel, cellValue.state_reason ?? '');

      if (fillEl) {
        fillEl.style.width = '0%';
      }

      return;
    }

    this._setMetricCellVisible(cellEl);
    this._applyBarState(fillEl, barEl, cellValue.bar ?? {});

    if (!textEl) {
      return;
    }

    const numericValue = Number(display.value);
    if (!Number.isFinite(numericValue)) {
      this._renderTextWithArrow(textEl, display.value_text ?? display.text ?? '', null);
      return;
    }

    const arrowDir = this._getArrow(cellId, numericValue);
    this._startTicker(cellId, display, textEl, arrowDir);
  }

  _applyBarState(fillEl, barEl, barModel) {
    if (!barEl || !fillEl) {
      return;
    }

    const percent = Number(barModel.percent);
    fillEl.style.width = Number.isFinite(percent)
      ? `${Math.max(0, Math.min(100, percent))}%`
      : '0%';

    if (barModel.color) {
      fillEl.style.backgroundColor = barModel.color;
    }
    else {
      fillEl.style.removeProperty('background-color');
    }
  }

  _setMetricCellEmpty(cellEl, valueText, stateReason = '') {
    cellEl.classList.add('is-empty');

    if (stateReason) {
      cellEl.setAttribute('title', stateReason);
    }
    else {
      cellEl.removeAttribute('title');
    }

    const barEl = cellEl.querySelector('.metric-bar');
    if (barEl) {
      barEl.hidden = true;
    }

    const textEl = cellEl.querySelector('.metric-value-link');
    if (textEl) {
      textEl.classList.add('empty');
      this._renderTextWithArrow(textEl, valueText, null);
    }
  }

  _setMetricCellVisible(cellEl) {
    cellEl.classList.remove('is-empty');

    const barEl = cellEl.querySelector('.metric-bar');
    if (barEl) {
      barEl.hidden = false;
    }

    const textEl = cellEl.querySelector('.metric-value-link');
    if (textEl) {
      textEl.classList.remove('empty');
    }
  }

  _startTicker(cellId, display, textEl, arrowDir) {
    const state = this.valueTicker.get(cellId) || { value: 0, rafId: null };
    if (state.rafId) {
      cancelAnimationFrame(state.rafId);
    }

    const kind = display.kind ?? 'percent';
    const from = Number(state.value) || 0;
    const to = Number(display.value) || 0;
    const duration = kind === 'interface' ? 700 : 500;
    const start = performance.now();

    const step = (now) => {
      const t = Math.min(1, (now - start) / duration);
      const ease = 1 - Math.pow(1 - t, 3);
      const current = from + (to - from) * ease;

      this._renderTextWithArrow(textEl, this._formatAnimatedDisplayValue(display, current), arrowDir);

      state.value = current;

      if (t < 1) {
        state.rafId = requestAnimationFrame(step);
        return;
      }

      state.value = to;
      state.rafId = null;
      this._renderTextWithArrow(textEl, display.value_text ?? display.text ?? '', arrowDir);
    };

    state.rafId = requestAnimationFrame(step);
    this.valueTicker.set(cellId, state);
  }

  _formatAnimatedDisplayValue(display, value) {
    switch (display.kind) {
      case 'load':
        return this._formatLoadValue(value);

      case 'interface':
        return this._formatBps(value);

      case 'percent':
      default:
        return `${this._clampPercent(value)}%`;
    }
  }

  _formatLoadValue(value) {
    const rounded = Math.round((Number(value) || 0) * 100) / 100;
    return Number.isInteger(rounded) ? rounded.toFixed(0) : rounded.toFixed(2);
  }

  _clampPercent(value) {
    const rounded = Math.round(Number(value) || 0);
    return Math.max(0, Math.min(100, rounded));
  }

  _renderTextWithArrow(textEl, valueText, arrowDir) {
    if (!textEl) {
      return;
    }

    textEl.innerHTML = '';

    const label = document.createElement('span');
    label.className = 'metric-value-label';
    label.textContent = valueText;
    textEl.appendChild(label);

    if (!arrowDir) {
      return;
    }

    textEl.appendChild(this._createTrendArrow(arrowDir));
  }

  _createTrendArrow(arrowDir) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('xmlns', ns);
    svg.setAttribute('width', '24');
    svg.setAttribute('height', '24');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2.5');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.classList.add('dir-arrow');

    svg.classList.add(`dir-${arrowDir}`);
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');

    for (const d of ['m5 12 7-7 7 7', 'M12 19V5']) {
      const path = document.createElementNS(ns, 'path');
      path.setAttribute('d', d);
      svg.appendChild(path);
    }

    return svg;
  }

  _applyStateClasses(element, classes) {
    const previous = (element.dataset.hostOverviewStateClasses || '')
      .split(' ')
      .filter(Boolean);

    const toRemove = [...new Set([
      ...CWidgetMainOverview.DYNAMIC_BADGE_CLASSES,
      ...previous,
    ])];

    if (toRemove.length > 0) {
      element.classList.remove(...toRemove);
    }

    const next = Array.isArray(classes) ? classes.filter(Boolean) : [];
    if (next.length > 0) {
      element.classList.add(...next);
    }

    element.dataset.hostOverviewStateClasses = next.join(' ');
  }

  _getArrow(key, newValue) {
    const prev = this.prevValues.get(key);
    this.prevValues.set(key, newValue);

    if (prev === undefined) {
      return null;
    }
    if (newValue > prev) {
      return 'up';
    }
    if (newValue < prev) {
      return 'down';
    }

    return null;
  }

  _cancelTicker(cellId) {
    const state = this.valueTicker.get(cellId);
    if (state?.rafId) {
      cancelAnimationFrame(state.rafId);
    }

    this.valueTicker.delete(cellId);
  }

  _resetAnimationState() {
    for (const state of this.valueTicker.values()) {
      if (state?.rafId) {
        cancelAnimationFrame(state.rafId);
      }
    }

    this.valueTicker.clear();
    this.prevValues.clear();
  }

  _onOverviewClick(event) {
    const bar = event.target.closest('.metric-bar');

    if (!bar) {
      return;
    }

    const root = bar.closest('[data-main-overview-role="overview"]');

    if (!root || !this._body?.contains(root) || !root.contains(bar)) {
      return;
    }

    const cellEl = bar.closest('[data-cell-id]');
    const metric = this._readSparklineMetric(cellEl);

    if (!metric) {
      return;
    }

    event.preventDefault();
    this._sparklineOverviewContext = root;
    this._sparkline?.open(metric);
  }

  _readSparklineMetric(cellEl) {
    const cellId = cellEl?.getAttribute('data-cell-id');
    const rawSpec = cellEl?.getAttribute('data-sparkline-spec');

    if (!cellId || !rawSpec) {
      return null;
    }

    try {
      const spec = JSON.parse(rawSpec);

      if (!spec?.item_ref || (!spec.item_ref.itemid && !spec.item_ref.name)) {
        return null;
      }

      return {
        cellId,
        spec,
        color: this._readSparklineColor(cellEl),
      };
    }
    catch (_) {
      return null;
    }
  }

  _readSparklineColor(cellEl) {
    const fillEl = cellEl?.querySelector('.metric-fill');
    const color = fillEl ? getComputedStyle(fillEl).backgroundColor.trim() : '';

    return color !== '' ? color : null;
  }

  _getRuntimeFields() {
    const base = this._runtimeFields || this._fields || {};
    const ctx = this._sparklineOverviewContext;

    const configJson = ctx?.dataset?.mainOverviewConfig ?? ctx?.dataset?.hostOverviewConfig;

    if (configJson) {
      try {
        const parsed = JSON.parse(configJson);

        if (parsed && typeof parsed === 'object') {
          return {...base, ...parsed};
        }
      }
      catch (_) {}
    }

    return base;
  }

  _formatBps(bps) {
    if (bps >= 1e9) return (bps / 1e9).toFixed(bps % 1e9 === 0 ? 0 : 1) + ' Gbps';
    if (bps >= 1e6) return (bps / 1e6).toFixed(bps % 1e6 === 0 ? 0 : 1) + ' Mbps';
    if (bps >= 1e3) return (bps / 1e3).toFixed(bps % 1e3 === 0 ? 0 : 1) + ' Kbps';
    return Math.round(bps) + ' bps';
  }
}
