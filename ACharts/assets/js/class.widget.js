/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

const CROSSHAIR_PLUGIN_ID = 'aChartsCrosshair';
  let chartDefaultsApplied = false;

  const crosshairPlugin = {
    id: CROSSHAIR_PLUGIN_ID,
    afterDraw(chart) {
      const active = chart.tooltip?.getActiveElements?.() || chart.tooltip?._active || [];

      if (!active.length) {
        return;
      }

      const theme = chart.$aChartsTheme;

      if (!theme) {
        return;
      }

      const { ctx, chartArea, tooltip } = chart;
      const x = tooltip?.caretX;

      if (!Number.isFinite(x) || !chartArea) {
        return;
      }

      ctx.save();
      ctx.beginPath();
      ctx.moveTo(x, chartArea.top);
      ctx.lineTo(x, chartArea.bottom);
      ctx.lineWidth = 1;
      ctx.strokeStyle = theme.crosshair;
      ctx.setLineDash([3, 3]);
      ctx.stroke();
      ctx.restore();
    },
  };

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
      this._applyGlobalChartDefaults();
    }

    onActivate() {
      this._detectTheme();
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

    onRefresh() {
      if (!this.rendered || !this._config) {
        return;
      }

      const hasSeries = Array.isArray(this._config.series) && this._config.series.length > 0;

      if (hasSeries) {
        this._loadAndRender();
      }
    }

    onDestroy() {
      this.onClearContents();
    }

  static _registerPlugins() {
    if (typeof Chart === 'undefined') {
      return;
    }

    const registry = Chart.registry?.plugins;

    if (registry && !registry.get(CROSSHAIR_PLUGIN_ID)) {
      Chart.register(crosshairPlugin);
    }
  }

  _applyGlobalChartDefaults() {
    CWidgetACharts._registerPlugins();

      if (chartDefaultsApplied || typeof Chart === 'undefined') {
        return;
      }

      chartDefaultsApplied = true;

      Chart.defaults.font.family = '"Roboto", "Helvetica Neue", Arial, sans-serif';
      Chart.defaults.font.size = 11;
      Chart.defaults.color = '#768d99';
      Chart.defaults.plugins.legend.labels.boxWidth = 10;
      Chart.defaults.plugins.legend.labels.boxHeight = 10;
      Chart.defaults.plugins.legend.labels.padding = 12;
      Chart.defaults.plugins.legend.labels.usePointStyle = true;
      Chart.defaults.plugins.legend.labels.pointStyle = 'rectRounded';
      Chart.defaults.plugins.tooltip.enabled = true;
      Chart.defaults.plugins.tooltip.mode = 'index';
      Chart.defaults.plugins.tooltip.intersect = false;
      Chart.defaults.plugins.tooltip.position = 'nearest';
      Chart.defaults.plugins.tooltip.cornerRadius = 4;
      Chart.defaults.plugins.tooltip.padding = 10;
      Chart.defaults.plugins.tooltip.titleFont = { weight: '600', size: 11 };
      Chart.defaults.plugins.tooltip.bodyFont = { size: 11 };
      Chart.defaults.plugins.tooltip.displayColors = true;
      Chart.defaults.plugins.tooltip.boxPadding = 4;
      Chart.defaults.elements.line.borderCapStyle = 'round';
      Chart.defaults.elements.line.borderJoinStyle = 'round';
      Chart.defaults.elements.point.radius = 0;
      Chart.defaults.elements.point.hoverRadius = 4;
      Chart.defaults.elements.point.hitRadius = 12;
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

    _detectTheme() {
      const root = this._getWidgetRoot();

      if (!root) {
        return;
      }

      const surface = getComputedStyle(root).getPropertyValue('--ui-bg-color-page').trim();
      const isDark = surface !== '' && this._isDarkColor(surface);

      root.dataset.theme = isDark ? 'dark' : 'light';
    }

    _isDarkColor(cssColor) {
      const probe = document.createElement('span');
      probe.style.color = cssColor;
      document.body.appendChild(probe);
      const rgb = getComputedStyle(probe).color;
      probe.remove();

      const match = rgb.match(/(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);

      if (!match) {
        return false;
      }

      const luminance = (0.299 * Number(match[1]) + 0.587 * Number(match[2]) + 0.114 * Number(match[3])) / 255;

      return luminance < 0.45;
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
        this._detectTheme();
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

      const range = this._resolveTimeRange(config);

      const payload = {
        period: range.period,
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

      if (Array.isArray(config.hostids) && config.hostids.length > 0) {
        payload.hostids = JSON.stringify(config.hostids);
      }
      else if (config.hostid) {
        payload.hostid = String(config.hostid);
      }

      if (range.time_from != null && range.time_till != null) {
        payload.time_from = range.time_from;
        payload.time_till = range.time_till;
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

    _resolveTimeRange(config) {
      const useDashboard = String(config.use_dashboard_time) === '1';

      if (useDashboard) {
        const range = this._readDashboardTimeRange();

        if (range) {
          return {
            period: 'dashboard',
            time_from: range.from,
            time_till: range.till,
          };
        }
      }

      return {
        period: String(config.period || '3h'),
        time_from: null,
        time_till: null,
      };
    }

    _readDashboardTimeRange() {
      const candidates = [
        () => this._dashboard?.getSharedTimePeriod?.(),
        () => this._dashboard?.sharedTimePeriod,
        () => window.dashboard?.getSharedTimePeriod?.(),
        () => window.dashboard?.sharedTimePeriod,
      ];

      for (const read of candidates) {
        try {
          const period = read();

          if (!period) {
            continue;
          }

          const from = Number(period.from ?? period.time_from ?? period.ts_from);
          const till = Number(period.to ?? period.time_till ?? period.ts_to);

          if (Number.isFinite(from) && Number.isFinite(till) && till > from) {
            return { from: Math.floor(from), till: Math.floor(till) };
          }
        }
        catch (_) {
          // try next source
        }
      }

      return null;
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
      const chartType = this._resolveChartType(config.chart_type);
      const datasets = this._buildChartDatasets(payload, config, chartType);
      const missingNotes = (payload.datasets || [])
        .filter((dataset) => dataset.missing)
        .map((dataset) => dataset.missing_reason || dataset.label || dataset.key)
        .filter(Boolean);

      if (datasets.length === 0) {
        this._destroyChart();
        const detail = missingNotes.length > 0 ? missingNotes.join('; ') : '';
        this._showError(detail !== ''
          ? `No chart data: ${detail}`
          : 'No data for the selected series.');

        return;
      }

      this._clearError();

      const options = this._buildChartOptions(config, chartType, theme, payload);

      if (this._chart && this._chart.config.type === chartType) {
        this._chart.$aChartsTheme = theme;
        this._chart.data.datasets = datasets;
        Object.assign(this._chart.options, options);
        this._chart.update('none');
        return;
      }

      this._destroyChart();
      this._chart = new Chart(canvas, {
        type: chartType,
        data: { datasets },
        options,
        plugins: [crosshairPlugin],
      });
      this._chart.$aChartsTheme = theme;
    }

    _buildChartOptions(config, chartType, theme, payload) {
      const legendPosition = this._resolveLegendPosition(config.legend_position);
      const showGrid = String(config.show_grid) === '1';
      const stacked = String(config.chart_stacked) === '1' && chartType !== 'line';
      const timeMin = payload.timeFrom != null ? payload.timeFrom * 1000 : undefined;
      const timeMax = payload.timeTill != null ? payload.timeTill * 1000 : undefined;

      return {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        layout: {
          padding: {
            top: legendPosition === 'top' ? 2 : 6,
            right: 8,
            bottom: legendPosition === 'bottom' ? 2 : 6,
            left: 4,
          },
        },
        interaction: {
          mode: 'index',
          intersect: false,
          axis: 'x',
        },
        plugins: {
          legend: {
            display: legendPosition !== 'hidden',
            position: legendPosition === 'bottom' ? 'bottom' : 'top',
            align: 'start',
            labels: {
              color: theme.text,
              font: { size: 11, weight: '500' },
              usePointStyle: true,
              pointStyle: 'rectRounded',
              boxWidth: 10,
              boxHeight: 10,
              padding: 14,
            },
          },
          tooltip: {
            backgroundColor: theme.tooltipBg,
            titleColor: theme.tooltipText,
            bodyColor: theme.tooltipText,
            footerColor: theme.tooltipText,
            borderColor: theme.border,
            borderWidth: 1,
            padding: 10,
            caretPadding: 6,
            callbacks: {
              title: (items) => this._formatTooltipTitle(items[0]?.parsed?.x ?? items[0]?.raw?.x),
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
          decimation: {
            enabled: chartType === 'line',
            algorithm: 'lttb',
            samples: 320,
          },
        },
        scales: {
          x: {
            type: 'time',
            min: timeMin,
            max: timeMax,
            bounds: 'data',
            grid: {
              display: false,
              drawBorder: false,
            },
            border: {
              display: true,
              color: theme.border,
            },
            ticks: {
              color: theme.muted,
              maxRotation: 0,
              autoSkip: true,
              maxTicksLimit: 7,
              font: { size: 10 },
            },
            time: {
              displayFormats: {
                minute: 'HH:mm',
                hour: 'HH:mm',
                day: 'dd MMM',
                week: 'dd MMM',
                month: 'MMM yyyy',
              },
              tooltipFormat: 'PPpp',
            },
          },
          y: {
            beginAtZero: chartType === 'bar',
            grace: chartType === 'bar' ? '2%' : '8%',
            stacked,
            grid: {
              display: showGrid,
              color: theme.grid,
              drawBorder: false,
            },
            border: {
              display: false,
            },
            ticks: {
              color: theme.muted,
              maxTicksLimit: 6,
              font: { size: 10 },
              callback: (value) => this._formatAxisValue(value),
            },
          },
        },
      };
    }

    _buildChartDatasets(payload, config, chartType) {
      const chartTypeId = Number(config.chart_type);
      const fillUnderLine = chartTypeId === CWidgetACharts.CHART_TYPE_AREA
        || (chartTypeId === CWidgetACharts.CHART_TYPE_LINE && String(config.chart_fill) === '1');
      const stacked = String(config.chart_stacked) === '1';
      const seriesByKey = new Map((config.series || []).map((entry) => [entry.key, entry]));
      const datasets = [];
      const isLine = chartType === 'line';

      for (const dataset of payload.datasets || []) {
        if (dataset.missing || !Array.isArray(dataset.points) || dataset.points.length === 0) {
          continue;
        }

        const meta = seriesByKey.get(dataset.key) || {};
        const hex = meta.color || '458ADC';
        const color = this._colorFromHex(hex);
        const gapSeconds = Number(dataset.gapThresholdFloor) || 300;

        datasets.push({
          label: dataset.label || meta.label || dataset.key,
          key: dataset.key,
          units: dataset.units || meta.units || '',
          data: dataset.points.map((point) => ({
            x: (point.t || 0) * 1000,
            y: point.v,
          })),
          borderColor: color.border,
          backgroundColor: (context) => this._resolveFillColor(context, color, fillUnderLine, chartType),
          borderWidth: isLine ? 2 : 1,
          pointRadius: 0,
          pointHoverRadius: 4,
          pointHoverBorderWidth: 2,
          pointBackgroundColor: color.border,
          pointBorderColor: color.border,
          tension: isLine ? 0.3 : 0,
          cubicInterpolationMode: isLine ? 'monotone' : 'default',
          spanGaps: gapSeconds * 1000,
          fill: chartType === 'bar' ? false : (fillUnderLine ? 'origin' : false),
          stack: stacked && chartType !== 'line' ? 'stack0' : undefined,
          segment: isLine ? {
            borderColor: color.border,
          } : undefined,
        });
      }

      return datasets;
    }

    _resolveFillColor(context, color, fillUnderLine, chartType) {
      if (chartType === 'bar') {
        return color.fillSolid;
      }

      if (!fillUnderLine) {
        return 'transparent';
      }

      const chart = context.chart;
      const { ctx, chartArea } = chart;

      if (!chartArea) {
        return color.fill;
      }

      const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
      gradient.addColorStop(0, color.fillTop);
      gradient.addColorStop(0.55, color.fill);
      gradient.addColorStop(1, color.fillBottom);

      return gradient;
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
        grid: styles?.getPropertyValue('--charts-grid')?.trim() || 'rgba(118, 141, 153, 0.2)',
        border: styles?.getPropertyValue('--charts-border')?.trim() || '#dfe4e7',
        crosshair: styles?.getPropertyValue('--charts-crosshair')?.trim() || 'rgba(118, 141, 153, 0.45)',
        tooltipBg: styles?.getPropertyValue('--charts-tooltip-bg')?.trim() || 'rgba(31, 44, 51, 0.92)',
        tooltipText: styles?.getPropertyValue('--charts-tooltip-text')?.trim() || '#ffffff',
      };
    }

    _colorFromHex(hex) {
      const normalized = String(hex || '').replace('#', '').trim();

      if (!/^[0-9A-Fa-f]{6}$/.test(normalized)) {
        return {
          border: 'rgb(69, 138, 220)',
          fill: 'rgba(69, 138, 220, 0.2)',
          fillTop: 'rgba(69, 138, 220, 0.38)',
          fillBottom: 'rgba(69, 138, 220, 0.02)',
          fillSolid: 'rgba(69, 138, 220, 0.75)',
        };
      }

      const r = parseInt(normalized.slice(0, 2), 16);
      const g = parseInt(normalized.slice(2, 4), 16);
      const b = parseInt(normalized.slice(4, 6), 16);

      return {
        border: `rgb(${r}, ${g}, ${b})`,
        fill: `rgba(${r}, ${g}, ${b}, 0.22)`,
        fillTop: `rgba(${r}, ${g}, ${b}, 0.4)`,
        fillBottom: `rgba(${r}, ${g}, ${b}, 0.03)`,
        fillSolid: `rgba(${r}, ${g}, ${b}, 0.82)`,
      };
    }

    _formatValue(value) {
      const numeric = Number(value);

      if (!Number.isFinite(numeric)) {
        return '—';
      }

      const abs = Math.abs(numeric);

      if (abs >= 1_000_000) {
        return `${(numeric / 1_000_000).toFixed(1)}M`;
      }

      if (abs >= 10_000) {
        return `${(numeric / 1_000).toFixed(1)}k`;
      }

      if (abs >= 100) {
        return `${Math.round(numeric)}`;
      }

      if (abs >= 10) {
        return numeric.toFixed(1);
      }

      return numeric.toFixed(2);
    }

    _formatAxisValue(value) {
      return this._formatValue(value);
    }

    _formatTooltipTitle(rawX) {
      if (rawX == null || rawX === '') {
        return '';
      }

      const date = rawX instanceof Date ? rawX : new Date(rawX);

      if (Number.isNaN(date.getTime())) {
        return String(rawX);
      }

      try {
        return new Intl.DateTimeFormat(undefined, {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
        }).format(date);
      }
      catch (_) {
        return date.toLocaleString();
      }
    }

    _showError(message) {
      const plot = this._body?.querySelector('.a-charts-plot');

      if (!plot) {
        return;
      }

      let banner = plot.querySelector('.a-charts-error');

      if (!banner) {
        banner = document.createElement('div');
        banner.className = 'a-charts-error';
        plot.appendChild(banner);
      }

      banner.textContent = message;
    }

    _clearError() {
      this._body?.querySelector('.a-charts-error')?.remove();
    }

  _destroyChart() {
    if (this._chart) {
      this._chart.destroy();
      this._chart = null;
    }

    this._body?.querySelector('.a-charts-error')?.remove();
  }
}
