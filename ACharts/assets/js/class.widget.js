/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

class CWidgetACharts extends CWidget {

  static ACTION_HISTORY = 'widget.acharts.history';

  static CHART_TYPE_LINE = 0;
  static CHART_TYPE_AREA = 1;
  static CHART_TYPE_BAR  = 2;

  onInitialize() {
    this.rendered = false;
    this._layoutSignature = '';
    this._chart = null;
    this._fetchAbort = null;
    this._fetchGeneration = 0;
    this._resizeObserver = null;
    this._resizeRaf = null;
    this._config = null;
  }

  onActivate() {
    this._scheduleChartResize();
  }

  onDeactivate() {
    this._invalidatePendingLoads();
    this._abortFetch();
    this._cancelScheduledResize();
  }

  onClearContents() {
    this._invalidatePendingLoads();
    this._abortFetch();
    this._destroyChart();
    this._cancelScheduledResize();
    this._resizeObserver?.disconnect();
    this._resizeObserver = null;
  }

  onResize() {
    this._scheduleChartResize();
  }

  onDestroy() {
    this.onClearContents();
  }

  _getWidgetRoot() {
    return this._body?.closest('.dashboard-widget-acharts')
      || this._target
      || this._body;
  }

  _getCanvas() {
    return this._body?.querySelector('.a-charts-canvas') || null;
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
    }
    catch (_) {}
  }

  setContents(response) {
    const nextLayoutSignature = response.layout_signature ?? '';
    const requiresFullRender = !this.rendered
      || this.isFieldsReferredDataUpdated()
      || nextLayoutSignature !== this._layoutSignature;

    if (response.config && Object.keys(response.config).length > 0) {
      this._config = response.config;
    }

    if (requiresFullRender) {
      if (this.rendered) {
        this.clearContents();
      }

      super.setContents(response);
      this.rendered = true;
      this._layoutSignature = nextLayoutSignature;
      this._observeCanvas();
    }

    this.updateViewModeAttr();

    const hasSeries = Array.isArray(this._config?.series) && this._config.series.length > 0;

    if (!response.empty && hasSeries) {
      this._loadAndRender();
    }
    else {
      this._invalidatePendingLoads();
      this._abortFetch();
      this._destroyChart();
    }
  }

  _observeCanvas() {
    const wrap = this._body?.querySelector('.a-charts-canvas-wrap');

    if (!wrap || typeof ResizeObserver === 'undefined') {
      return;
    }

    this._resizeObserver?.disconnect();
    this._resizeObserver = new ResizeObserver(() => this._scheduleChartResize());
    this._resizeObserver.observe(wrap);
  }

  _scheduleChartResize() {
    if (this._resizeRaf !== null) {
      return;
    }

    this._resizeRaf = requestAnimationFrame(() => {
      this._resizeRaf = null;
      this._chart?.resize();
    });
  }

  async _loadAndRender() {
    const root = this._getWidgetRoot();
    const generation = ++this._fetchGeneration;

    root?.classList.add('is-loading');

    try {
      const payload = await this._fetchHistory();

      if (generation !== this._fetchGeneration) {
        return;
      }

      this._renderChart(payload);
    }
    catch (error) {
      if (generation !== this._fetchGeneration || error?.name === 'AbortError') {
        return;
      }

      this._showError(error?.message || 'Could not load chart data.');
    }
    finally {
      if (generation === this._fetchGeneration) {
        root?.classList.remove('is-loading');
      }
    }
  }

  async _fetchHistory() {
    this._abortFetch();

    const config = this._config || {};
    const series = Array.isArray(config.series) ? config.series : [];

    if (series.length === 0) {
      return { datasets: [] };
    }

    const controller = new AbortController();
    this._fetchAbort = controller;

    const curl = new Curl('zabbix.php');
    curl.setArgument('action', CWidgetACharts.ACTION_HISTORY);

    const payload = {
      period: String(config.period || '3h'),
      series: JSON.stringify(series.map((entry) => ({
        key: entry.key,
        label: entry.label,
        hostid: entry.hostid,
        host_name: entry.host_name,
        itemid: entry.itemid,
        item_name: entry.item_name,
        value_type: entry.value_type,
        units: entry.units,
      }))),
    };

    if (config.hostid) {
      payload.hostid = String(config.hostid);
    }

    try {
      const response = await fetch(curl.getUrl(), {
        method: 'POST',
        signal: controller.signal,
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });

      return await this._parseHistoryResponse(response);
    }
    finally {
      if (this._fetchAbort === controller) {
        this._fetchAbort = null;
      }
    }
  }

  async _parseHistoryResponse(response) {
    const raw = await response.text();

    if (raw === '') {
      throw new Error('The chart endpoint returned an empty response.');
    }

    let parsed;

    try {
      parsed = JSON.parse(raw);
    }
    catch (_error) {
      throw new Error('Could not read the chart response.');
    }

    if ('error' in parsed) {
      const messages = Array.isArray(parsed.error?.messages)
        ? parsed.error.messages.filter(Boolean)
        : [];

      throw new Error(messages[0] ?? 'Could not load chart data.');
    }

    return parsed;
  }

  _abortFetch() {
    if (this._fetchAbort) {
      this._fetchAbort.abort();
      this._fetchAbort = null;
    }
  }

  _invalidatePendingLoads() {
    this._fetchGeneration += 1;
    this._getWidgetRoot()?.classList.remove('is-loading');
  }

  _cancelScheduledResize() {
    if (this._resizeRaf !== null) {
      cancelAnimationFrame(this._resizeRaf);
      this._resizeRaf = null;
    }
  }

  _renderChart(payload) {
    const canvas = this._getCanvas();

    if (!canvas || typeof Chart === 'undefined') {
      return;
    }

    const config = this._config || {};
    const theme = this._readTheme();
    const datasets = this._buildChartDatasets(payload, config, theme);

    if (datasets.length === 0) {
      this._destroyChart();
      this._showError('No data for the selected series.');

      return;
    }

    const chartType = this._resolveChartType(config.chart_type);
    const legendPosition = this._resolveLegendPosition(config.legend_position);

    const chartConfig = {
      type: chartType,
      data: {
        datasets,
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            display: legendPosition !== 'hidden',
            position: legendPosition === 'bottom' ? 'bottom' : 'top',
            labels: {
              color: theme.text,
              boxWidth: 12,
              boxHeight: 12,
              usePointStyle: true,
            },
          },
          tooltip: {
            callbacks: {
              title: (items) => this._formatTooltipTitle(items[0]?.raw?.x),
              label: (context) => {
                const label = context.dataset.label || '';
                const value = context.parsed.y;
                const units = String(context.dataset.units || '').trim();
                const formatted = this._formatValue(value);

                return units !== ''
                  ? `${label}: ${formatted} ${units}`
                  : `${label}: ${formatted}`;
              },
            },
          },
        },
        scales: {
          x: {
            type: 'time',
            time: {
              tooltipFormat: 'PPpp',
            },
            grid: {
              display: String(config.show_grid) === '1',
              color: theme.grid,
            },
            ticks: {
              color: theme.muted,
              maxRotation: 0,
              autoSkip: true,
              maxTicksLimit: 8,
            },
          },
          y: {
            beginAtZero: true,
            stacked: String(config.chart_stacked) === '1' && chartType !== 'line',
            grid: {
              display: String(config.show_grid) === '1',
              color: theme.grid,
            },
            ticks: {
              color: theme.muted,
              callback: (value) => this._formatValue(value),
            },
          },
        },
      },
    };

    this._destroyChart();
    this._chart = new Chart(canvas, chartConfig);
  }

  _buildChartDatasets(payload, config, theme) {
    const chartType = this._resolveChartType(config.chart_type);
    const chartTypeId = Number(config.chart_type);
    const fillUnderLine = chartTypeId === CWidgetACharts.CHART_TYPE_AREA
      || (chartTypeId === CWidgetACharts.CHART_TYPE_LINE && String(config.chart_fill) === '1');
    const stacked = String(config.chart_stacked) === '1';
    const seriesByKey = new Map((config.series || []).map((entry) => [entry.key, entry]));
    const datasets = [];

    for (const dataset of payload.datasets || []) {
      if (dataset.missing || !Array.isArray(dataset.points) || dataset.points.length === 0) {
        continue;
      }

      const meta = seriesByKey.get(dataset.key) || {};
      const color = this._colorFromHex(meta.color || '458ADC');
      const isLine = chartType === 'line';

      datasets.push({
        label: dataset.label || meta.label || dataset.key,
        key: dataset.key,
        units: dataset.units || meta.units || '',
        data: dataset.points.map((point) => ({
          x: (point.t || 0) * 1000,
          y: point.v,
        })),
        borderColor: color.border,
        backgroundColor: chartType === 'bar' ? color.fill : (fillUnderLine ? color.fill : color.border),
        borderWidth: isLine ? 2 : 1,
        pointRadius: 0,
        pointHoverRadius: 3,
        tension: 0.25,
        fill: chartType === 'bar' ? false : fillUnderLine,
        stack: stacked && chartType !== 'line' ? 'stack0' : undefined,
      });
    }

    return datasets;
  }

  _resolveChartType(value) {
    const numeric = Number(value);

    switch (numeric) {
      case CWidgetACharts.CHART_TYPE_AREA:
        return 'line';
      case CWidgetACharts.CHART_TYPE_BAR:
        return 'bar';
      default:
        return 'line';
    }
  }

  _resolveLegendPosition(value) {
    switch (Number(value)) {
      case 1:
        return 'bottom';
      case 2:
        return 'hidden';
      default:
        return 'top';
    }
  }

  _readTheme() {
    const root = this._getWidgetRoot();
    const styles = root ? getComputedStyle(root) : null;

    return {
      text: styles?.getPropertyValue('--charts-text')?.trim() || '#1f2c33',
      muted: styles?.getPropertyValue('--charts-muted')?.trim() || '#768d99',
      grid: styles?.getPropertyValue('--charts-grid')?.trim() || 'rgba(118, 141, 153, 0.25)',
    };
  }

  _colorFromHex(hex) {
    const normalized = String(hex || '').replace('#', '').trim();

    if (!/^[0-9A-Fa-f]{6}$/.test(normalized)) {
      return {
        border: 'rgb(69, 138, 220)',
        fill: 'rgba(69, 138, 220, 0.18)',
      };
    }

    const r = parseInt(normalized.slice(0, 2), 16);
    const g = parseInt(normalized.slice(2, 4), 16);
    const b = parseInt(normalized.slice(4, 6), 16);

    return {
      border: `rgb(${r}, ${g}, ${b})`,
      fill: `rgba(${r}, ${g}, ${b}, 0.22)`,
    };
  }

  _formatValue(value) {
    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
      return '—';
    }

    if (Math.abs(numeric) >= 100) {
      return `${Math.round(numeric)}`;
    }

    if (Math.abs(numeric) >= 10) {
      return numeric.toFixed(1);
    }

    return numeric.toFixed(2);
  }

  _formatTooltipTitle(rawX) {
    if (!rawX) {
      return '';
    }

    try {
      return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
      }).format(new Date(rawX));
    }
    catch (_) {
      return String(rawX);
    }
  }

  _showError(message) {
    const stage = this._body?.querySelector('.a-charts-stage');

    if (!stage) {
      return;
    }

    let banner = stage.querySelector('.a-charts-error');

    if (!banner) {
      banner = document.createElement('div');
      banner.className = 'a-charts-error';
      banner.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--charts-muted);text-align:center;padding:12px;pointer-events:none;';
      stage.appendChild(banner);
    }

    banner.textContent = message;
  }

  _destroyChart() {
    if (this._chart) {
      this._chart.destroy();
      this._chart = null;
    }

    this._body?.querySelector('.a-charts-error')?.remove();
  }
}
