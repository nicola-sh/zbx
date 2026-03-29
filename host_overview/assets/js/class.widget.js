/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

class CWidgetHostOverview extends CWidget {

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

  onInitialize() {
    this.rendered = false;
    this._runtimeFields = this._fields || {};
    this._layoutSignature = '';
    this._attachedOverviewRoot = null;
    this._handleOverviewClick = (event) => this._onOverviewClick(event);
    this.valueTicker = new Map();
    this.prevValues = new Map();

    this._sparkline = new HostOverviewSparkline({
      getBody: () => this._body,
      getOverlayRoot: () => this._getSparklineRoot(),
      getOverviewRoot: () => this._getOverviewRoot(),
      getWidgetRoot: () => this._getWidgetRoot(),
      getFields: () => this._getRuntimeFields(),
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

  onDeactivate() {
    this._sparkline?.close();
  }

  onResize() {
    this._sparkline?.scheduleRedraw();
  }

  onDestroy() {
    if (this._attachedOverviewRoot) {
      this._attachedOverviewRoot.removeEventListener('click', this._handleOverviewClick);
      this._attachedOverviewRoot = null;
    }

    this._resetAnimationState();
    this._sparkline?.destroy();
  }

  _getWidgetRoot() {
    return this._body?.closest('.dashboard-widget-host_overview')
      || this._target
      || this._body;
  }

  _getOverviewRoot() {
    return this._body?.querySelector('[data-host-overview-role="overview"]') || null;
  }

  _getSparklineRoot() {
    return this._body?.querySelector('[data-host-overview-role="sparkline"]') || null;
  }

  updateViewModeAttr() {
    try {
      if (!this._body || typeof this.getViewMode !== 'function') {
        return;
      }

      const root = this._getWidgetRoot();
      if (root) {
        root.classList.toggle('hidden_header', this.getViewMode());
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
  }

  setContents(response) {
    const nextLayoutSignature = response.layout_signature ?? '';
    const requiresFullRender = !this.rendered
      || this.isFieldsReferredDataUpdated()
      || nextLayoutSignature !== this._layoutSignature;

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
    this._sparkline?.attach();
    this._syncRootModifiers(this._getRuntimeFields());
    this._layoutSignature = nextLayoutSignature;

    if (response.values) {
      this.updateValues(response.values);
    }
  }

  _attachOverview() {
    const root = this._getOverviewRoot();

    if (this._attachedOverviewRoot === root) {
      return;
    }

    if (this._attachedOverviewRoot) {
      this._attachedOverviewRoot.removeEventListener('click', this._handleOverviewClick);
      this._resetAnimationState();
    }

    if (root) {
      root.addEventListener('click', this._handleOverviewClick);
    }

    this._attachedOverviewRoot = root;
  }

  updateValues(values) {
    const root = this._getOverviewRoot();
    if (!root || !values) {
      return;
    }

    this._patchBadges(root, values.badges || {});
    this._patchCells(root, values.cells || {});
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

    if ((cellValue.state ?? 'ok') === 'empty' || display.value == null) {
      this._cancelTicker(cellId);
      this.prevValues.delete(cellId);
      this._setMetricCellEmpty(cellEl, display.value_text ?? 'No data');

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

  _setMetricCellEmpty(cellEl, valueText) {
    cellEl.classList.add('is-empty');

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
      ...CWidgetHostOverview.DYNAMIC_BADGE_CLASSES,
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
    const root = this._getOverviewRoot();
    if (!root) {
      return;
    }

    const bar = event.target.closest('.metric-bar');
    if (!bar || !root.contains(bar)) {
      return;
    }

    const cellEl = bar.closest('[data-cell-id]');
    const metric = this._readSparklineMetric(cellEl);

    if (!metric) {
      return;
    }

    event.preventDefault();
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
    return this._runtimeFields || this._fields || {};
  }

  _formatBps(bps) {
    if (bps >= 1e9) return (bps / 1e9).toFixed(bps % 1e9 === 0 ? 0 : 1) + ' Gbps';
    if (bps >= 1e6) return (bps / 1e6).toFixed(bps % 1e6 === 0 ? 0 : 1) + ' Mbps';
    if (bps >= 1e3) return (bps / 1e3).toFixed(bps % 1e3 === 0 ? 0 : 1) + ' Kbps';
    return Math.round(bps) + ' bps';
  }
}
