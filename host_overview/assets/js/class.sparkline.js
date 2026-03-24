/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

class HostOverviewSparkline {

  static PERIODS = {
    '1h': 3600,
    '3h': 10800,
    '12h': 43200,
    '1d': 86400,
    '3d': 259200,
    '1w': 604800,
    '30d': 2592000,
  };

  constructor(options = {}) {
    this.options = options;
    this.state = {
      open: false,
      metricKey: null,
      itemRef: null,
      fallbackTitle: '',
      period: '1h',
      data: null,
      message: '',
    };
    this._drawState = null;
    this._snapshot = null;
    this._updatesPaused = false;
    this._abortController = null;
    this._keydownHandler = null;
    this._redrawRaf = null;
    this._requestId = 0;
    this._resizeObserver = null;
    this._resizeObserverTarget = null;
    this._attachedBody = null;
    this._attachedCanvas = null;
    this._handleBodyClick = (e) => this._onBodyClick(e);
    this._handleMouseMove = (e) => this._onMouseMove(e);
    this._handleMouseLeave = () => this._onMouseLeave();
  }

  attach() {
    const body = this._getBody();
    if (!body) {
      return;
    }

    if (this._attachedBody !== body) {
      if (this._attachedBody) {
        this._attachedBody.removeEventListener('click', this._handleBodyClick);
      }

      body.addEventListener('click', this._handleBodyClick);
      this._attachedBody = body;
    }

    const canvas = body.querySelector('.sparkline-canvas');
    if (this._attachedCanvas !== canvas) {
      if (this._attachedCanvas) {
        this._attachedCanvas.removeEventListener('mousemove', this._handleMouseMove);
        this._attachedCanvas.removeEventListener('mouseleave', this._handleMouseLeave);
      }

      if (canvas) {
        canvas.addEventListener('mousemove', this._handleMouseMove);
        canvas.addEventListener('mouseleave', this._handleMouseLeave);
      }

      this._attachedCanvas = canvas;
    }

    this._setupResizeObserver();
  }

  toggle(metricKey, fallbackTitle) {
    if (this.state.open && this.state.metricKey === metricKey) {
      this.close();
      return;
    }

    const itemRef = this._normalizeRef(this._getItemRef(metricKey));
    if (!itemRef || (!itemRef.itemid && !itemRef.name)) {
      return;
    }

    this.state.open = true;
    this.state.metricKey = metricKey;
    this.state.itemRef = itemRef;
    this.state.fallbackTitle = fallbackTitle;
    this.state.data = null;
    this.state.message = 'Loading...';
    this._pauseUpdating();
    this._updateChrome();
    this._primeYLabels();

    const root = this._getWidgetRoot();
    if (root) {
      root.classList.add('sparkline-open');
    }

    const body = this._getBody();
    const overlay = body?.querySelector('.sparkline-overlay');
    if (overlay) {
      overlay.style.backgroundColor = this._getSurfaceColor();
      overlay.setAttribute('aria-hidden', 'false');
      overlay.classList.add('visible');
    }

    const container = body?.querySelector('#container');
    if (container) {
      container.classList.add('sparkline-active');
    }

    this._bindKeydown();
    this.scheduleRedraw();
    this.fetchSparkline(itemRef, this.state.period);
  }

  close() {
    if (!this.state.open && !this._updatesPaused) {
      this._removeKeydownHandler();
      return;
    }

    this._cancelRequest();
    this._cancelScheduledRedraw();

    this.state.open = false;
    this.state.metricKey = null;
    this.state.itemRef = null;
    this.state.fallbackTitle = '';
    this.state.data = null;
    this.state.message = '';
    this._clearDrawState();

    const body = this._getBody();
    const overlay = body?.querySelector('.sparkline-overlay');
    if (overlay) {
      overlay.setAttribute('aria-hidden', 'true');
      overlay.classList.remove('visible');
    }

    const root = this._getWidgetRoot();
    if (root) {
      root.classList.remove('sparkline-open');
    }

    const container = body?.querySelector('#container');
    if (container) {
      container.classList.remove('sparkline-active');
    }

    this._removeKeydownHandler();
    this._resumeUpdating();
  }

  destroy() {
    this._cancelRequest();
    this._cancelScheduledRedraw();
    this.close();
    this._clearDrawState();

    if (this._resizeObserver) {
      if (this._resizeObserverTarget) {
        this._resizeObserver.unobserve(this._resizeObserverTarget);
        this._resizeObserverTarget = null;
      }

      this._resizeObserver.disconnect();
      this._resizeObserver = null;
    }

    if (this._attachedBody) {
      this._attachedBody.removeEventListener('click', this._handleBodyClick);
      this._attachedBody = null;
    }

    if (this._attachedCanvas) {
      this._attachedCanvas.removeEventListener('mousemove', this._handleMouseMove);
      this._attachedCanvas.removeEventListener('mouseleave', this._handleMouseLeave);
      this._attachedCanvas = null;
    }
  }

  scheduleRedraw() {
    if (!this.state.open) {
      return;
    }

    this._cancelScheduledRedraw();
    this._redrawRaf = requestAnimationFrame(() => {
      this._redrawRaf = null;
      this._redraw();
    });
  }

  fetchSparkline(itemRef, period) {
    const normalizedRef = this._normalizeRef(itemRef);

    if (!normalizedRef || (!normalizedRef.itemid && !normalizedRef.name)) {
      this.state.data = null;
      this.state.message = 'Item not found';
      this.scheduleRedraw();
      return;
    }

    this.state.itemRef = normalizedRef;
    this.state.period = period;
    this.state.data = null;
    this.state.message = 'Loading...';
    this._updateChrome();
    this.scheduleRedraw();

    this._cancelRequest();
    const requestId = ++this._requestId;
    const controller = new AbortController();
    this._abortController = controller;
    this._fetchSparklineAsync(
      normalizedRef,
      period,
      this.state.metricKey,
      requestId,
      controller.signal
    );
  }

  async _fetchSparklineAsync(itemRef, period, metricKey, requestId, signal) {
    try {
      let itemid = itemRef.itemid || null;
      let valueType = itemRef.value_type != null ? parseInt(itemRef.value_type, 10) : NaN;
      let resolvedRef = { ...itemRef };

      if (!itemid || Number.isNaN(valueType)) {
        const hostid = (this._getFields().hostid || [])[0];
        if (!hostid) {
          if (requestId === this._requestId && this.state.open) {
            this.state.data = null;
            this.state.message = 'No host';
            this.scheduleRedraw();
          }
          return;
        }

        const items = await this._apiCall('item.get', {
          output: ['itemid', 'name', 'value_type'],
          hostids: [hostid],
          search: { name: itemRef.name },
          limit: 1,
        }, signal);

        if (!items || items.length === 0) {
          if (requestId === this._requestId && this.state.open) {
            this.state.data = null;
            this.state.message = 'Item not found';
            this.scheduleRedraw();
          }
          return;
        }

        itemid = items[0].itemid;
        valueType = parseInt(items[0].value_type, 10);
        resolvedRef = {
          itemid,
          name: items[0].name ?? itemRef.name,
          value_type: valueType,
        };
      }

      const seconds = HostOverviewSparkline.PERIODS[period] || 43200;
      const timeTill = Math.floor(Date.now() / 1000);
      const timeFrom = timeTill - seconds;

      const { points: loadedPoints, gapThresholdFloor } =
        await this._loadPoints(itemid, valueType, timeFrom, timeTill, seconds, signal);
      let points = loadedPoints;

      if (metricKey === 'swap' && this._getFields().item_swap_invert == 1) {
        points = points.map((point) => ({ t: point.t, v: 100 - point.v }));
      }

      if (points.length > 200) {
        const stride = points.length / 200;
        const downsampled = [];

        for (let i = 0; i < 200; i++) {
          downsampled.push(points[Math.floor(i * stride)]);
        }

        downsampled.push(points[points.length - 1]);
        points = downsampled;
      }

      let min = Infinity;
      let max = -Infinity;
      for (const point of points) {
        if (point.v < min) {
          min = point.v;
        }
        if (point.v > max) {
          max = point.v;
        }
      }

      if (points.length === 0) {
        min = 0;
        max = 0;
      }

      if (metricKey?.startsWith('iface:')) {
        const fields = this._getFields();
        const high = parseInt(fields.interfaces_high, 10) || 0;
        const unit = parseInt(fields.interfaces_unit, 10) || 0;
        if (high > 0) {
          const factors = { 2: 1e9, 1: 1e6, 0: 1e3 };
          max = high * (factors[unit] || 1e3);
        }
      }

      if (metricKey === 'load') {
        const loadHigh = parseFloat(this._getFields().load_high) || 0;
        if (loadHigh > 0) {
          max = loadHigh;
        }
      }

      const result = { points, min, max, timeFrom, timeTill, gapThresholdFloor };
      if (requestId === this._requestId
          && this.state.open
          && this.state.period === period) {
        this.state.itemRef = resolvedRef;
        this.state.data = result;
        this.state.message = '';
        this._updateChrome();
        this.scheduleRedraw();
      }
    } catch (error) {
      if (error?.name === 'AbortError') {
        return;
      }

      if (requestId === this._requestId && this.state.open) {
        this.state.data = null;
        this.state.message = 'Error loading data';
        this.scheduleRedraw();
      }
    } finally {
      if (this._abortController?.signal === signal) {
        this._abortController = null;
      }
    }
  }

  async _apiCall(method, params, signal = undefined) {
    const response = await fetch('api_jsonrpc.php', {
      method: 'POST',
      signal,
      headers: { 'Content-Type': 'application/json-rpc' },
      body: JSON.stringify({
        jsonrpc: '2.0',
        method,
        params,
        id: 1,
      }),
    });
    const data = await response.json();
    if (data.error) {
      throw new Error(data.error.data || data.error.message || 'API error');
    }
    return data.result;
  }

  async _loadPoints(itemid, valueType, timeFrom, timeTill, seconds, signal) {
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
        points: trendPoints.filter((point) => point.t < historyStart).concat(historyTail),
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

    const points = records.map((record) => ({
      t: parseInt(record.clock, 10),
      v: parseFloat(record.value),
    }));

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

    return records
      .map((record) => ({
        t: parseInt(record.clock, 10),
        v: parseFloat(record.value_avg),
      }))
      .sort((a, b) => a.t - b.t);
  }

  _onBodyClick(e) {
    const body = this._getBody();
    if (!body) {
      return;
    }

    const closeBtn = e.target.closest('.js-sparkline-close');
    if (closeBtn) {
      e.preventDefault();
      this.close();
      return;
    }

    const periodBtn = e.target.closest('.sparkline-periods .toolbar-control[data-period]');
    if (periodBtn) {
      e.preventDefault();
      const period = periodBtn.getAttribute('data-period');
      if (!period || this.state.itemRef === null) {
        return;
      }

      this.state.period = period;
      this._updateChrome();
      this.fetchSparkline(this.state.itemRef, period);
      return;
    }

    const bar = e.target.closest('.metric-bar');
    if (!bar) {
      return;
    }

    const overlay = body.querySelector('.sparkline-overlay');
    if (overlay && overlay.contains(bar)) {
      return;
    }

    const metric = this._getMetricFromBar(bar);
    if (!metric) {
      return;
    }

    e.preventDefault();
    this.toggle(metric.key, metric.title);
  }

  _getMetricFromBar(bar) {
    const cell = bar.closest('.metric-cell[data-metric-key]');
    if (!cell) {
      return null;
    }

    const key = cell.getAttribute('data-metric-key');
    if (!key) {
      return null;
    }

    return {
      key,
      title: cell.getAttribute('data-label') || key,
    };
  }

  _setupResizeObserver() {
    if (typeof ResizeObserver === 'undefined') {
      return;
    }

    const target = this._getWidgetRoot();
    if (!target) {
      return;
    }

    if (!this._resizeObserver) {
      this._resizeObserver = new ResizeObserver(() => {
        this.scheduleRedraw();
      });
    }

    if (this._resizeObserverTarget === target) {
      return;
    }

    if (this._resizeObserverTarget) {
      this._resizeObserver.unobserve(this._resizeObserverTarget);
    }

    this._resizeObserver.observe(target);
    this._resizeObserverTarget = target;
  }

  _redraw() {
    if (!this.state.open) {
      return;
    }

    const overlay = this._getBody()?.querySelector('.sparkline-overlay');
    if (!overlay || !overlay.classList.contains('visible')) {
      return;
    }

    const data = this.state.data;
    if (data) {
      this._drawSparkline(
        data.points,
        data.min,
        data.max,
        data.timeFrom,
        data.timeTill,
        data.gapThresholdFloor
      );
      return;
    }

    this._drawMessage(this.state.message || 'Loading...');
  }

  _drawMessage(message) {
    const canvasState = this._prepareCanvas();
    if (!canvasState) {
      return;
    }

    const { ctx, dpr, w, h } = canvasState;
    ctx.scale(dpr, dpr);
    const cssW = Math.round(w / dpr);
    const cssH = Math.round(h / dpr);
    ctx.fillStyle = 'rgba(128,128,128,0.5)';
    ctx.font = '12px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(message, cssW / 2, cssH / 2);
    this._drawState = null;
    this._snapshot = null;
  }

  _drawSparkline(points, min, max, timeFrom, timeTill, gapThresholdFloor = 300) {
    const metricKey = this.state.metricKey || '';
    const isInterface = metricKey.startsWith('iface:');
    const isLoad = metricKey === 'load';

    if (!points || points.length === 0) {
      this._drawMessage('No data');
      return;
    }

    min = 0;
    if (!isInterface && !isLoad) {
      max = Math.max(100, max);
    }
    max = Math.max(max, 1);
    this._updateYLabels(max, isInterface, isLoad);

    const canvasState = this._prepareCanvas();
    if (!canvasState) {
      return;
    }

    const { canvas, ctx, dpr, w: drawW, h: drawH } = canvasState;
    const tMin = timeFrom || points[0].t;
    const tMax = timeTill || points[points.length - 1].t;
    const tRange = tMax - tMin || 1;

    const coords = points.map((point) => ({
      x: Math.round(((point.t - tMin) / tRange) * drawW),
      y: Math.round((1 - (point.v - min) / (max - min)) * drawH),
    }));

    ctx.beginPath();
    ctx.strokeStyle = 'rgba(128, 128, 128, 0.2)';
    const gridLineWidth = Math.round(dpr);
    ctx.lineWidth = gridLineWidth;
    const gridOffset = gridLineWidth % 2 === 1 ? 0.5 : 0;
    for (const frac of [0.25, 0.5, 0.75]) {
      const y = Math.round((1 - frac) * drawH) + gridOffset;
      ctx.moveTo(0, y);
      ctx.lineTo(drawW, y);
    }
    ctx.stroke();

    const fields = this._getFields();
    const fillColor = fields.fill_color ? `#${fields.fill_color}` : '#458ADC';

    const intervals = [];
    for (let i = 1; i < points.length; i++) {
      intervals.push(points[i].t - points[i - 1].t);
    }
    intervals.sort((a, b) => a - b);
    const medianInterval = intervals.length > 0
      ? intervals[Math.floor(intervals.length / 2)]
      : 0;
    const gapThreshold = Math.max(medianInterval * 3, gapThresholdFloor);

    const segments = [];
    let segment = [0];
    for (let i = 1; i < coords.length; i++) {
      if (points[i].t - points[i - 1].t > gapThreshold) {
        segments.push(segment);
        segment = [i];
      } else {
        segment.push(i);
      }
    }
    segments.push(segment);

    ctx.strokeStyle = fillColor;
    ctx.lineWidth = Math.round(1.5 * dpr);
    for (const currentSegment of segments) {
      if (currentSegment.length === 0) {
        continue;
      }

      const path = new Path2D();
      path.moveTo(coords[currentSegment[0]].x, coords[currentSegment[0]].y);
      for (let i = 1; i < currentSegment.length; i++) {
        path.lineTo(coords[currentSegment[i]].x, coords[currentSegment[i]].y);
      }
      ctx.stroke(path);
      path.lineTo(coords[currentSegment[currentSegment.length - 1]].x, drawH);
      path.lineTo(coords[currentSegment[0]].x, drawH);
      path.closePath();
      ctx.fillStyle = fillColor + '26';
      ctx.fill(path);
    }

    this._drawState = {
      points,
      coords,
      min,
      max,
      tMin,
      tMax,
      tRange,
      drawW,
      drawH,
      fillColor,
      isInterface,
      isLoad,
    };
    this._snapshot = ctx.getImageData(0, 0, canvas.width, canvas.height);
    this._setYLabelsVisible(true);
  }

  _onMouseMove(e) {
    const state = this._drawState;
    if (!state) {
      return;
    }

    const canvas = this._getBody()?.querySelector('.sparkline-canvas');
    if (!canvas) {
      return;
    }

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
      } else if (state.coords[i].x > mouseX) {
        break;
      }
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) {
      return;
    }

    if (this._snapshot) {
      ctx.putImageData(this._snapshot, 0, 0);
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
    ctx.lineWidth = Math.round(dpr);
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
    const cornerR = this._getFields().corners == 1 ? 0 : Math.round(3 * dpr);
    const inset = Math.round(3 * dpr);

    ctx.font = `${fontSize}px sans-serif`;
    const valMetrics = ctx.measureText(valueText);
    const valW = Math.round(valMetrics.width + pad * 2);
    const flipRight = sx + gap + valW > drawW - inset;
    const valX = Math.round(Math.max(
      inset,
      Math.min(flipRight ? sx - gap - valW : sx + gap, drawW - valW - inset)
    ));
    const valY = Math.round(Math.max(
      inset,
      Math.min(sy - boxH / 2, drawH - boxH - inset)
    ));

    ctx.fillStyle = boxBg;
    this._roundRect(ctx, valX, valY, valW, boxH, cornerR);
    ctx.fill();

    ctx.fillStyle = textColor;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';
    ctx.fillText(
      valueText,
      valX + pad,
      valY + Math.round((boxH - fontSize) / 2) + Math.round(dpr)
    );

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
    ctx.fillText(
      timeText,
      timeX + pad,
      timeY + Math.round((boxH - fontSize) / 2) + Math.round(dpr)
    );

    ctx.restore();
  }

  _onMouseLeave() {
    const canvas = this._getBody()?.querySelector('.sparkline-canvas');
    if (canvas && this._snapshot) {
      canvas.getContext('2d')?.putImageData(this._snapshot, 0, 0);
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
    const labels = this._getBody()?.querySelector('.sparkline-y-labels');
    if (!labels) {
      return;
    }

    const spans = labels.querySelectorAll('span');
    if (spans.length < 3) {
      return;
    }

    if (isInterface) {
      spans[0].textContent = this._formatBps(max);
      spans[1].textContent = this._formatBps(max * 0.5);
      spans[2].textContent = '0';
      return;
    }

    if (isLoad) {
      spans[0].textContent = max % 1 === 0 ? max.toFixed(0) : max.toFixed(1);
      spans[1].textContent = (max * 0.5) % 1 === 0
        ? (max * 0.5).toFixed(0)
        : (max * 0.5).toFixed(1);
      spans[2].textContent = '0';
      return;
    }

    spans[0].textContent = '100%';
    spans[1].textContent = '50%';
    spans[2].textContent = '0%';
  }

  _primeYLabels() {
    const metricKey = this.state.metricKey || '';
    const fields = this._getFields();
    const isInterface = metricKey.startsWith('iface:');
    const isLoad = metricKey === 'load';

    if (isInterface) {
      const high = parseInt(fields.interfaces_high, 10);
      const unit = parseInt(fields.interfaces_unit, 10);
      const safeHigh = Number.isFinite(high) && high > 0 ? high : 1;
      const safeUnit = Number.isFinite(unit) ? unit : 2;
      const factors = { 2: 1e9, 1: 1e6, 0: 1e3 };
      this._updateYLabels(safeHigh * (factors[safeUnit] || 1e9), true, false);
      this._setYLabelsVisible(true);
      return;
    }

    if (isLoad) {
      const loadHigh = parseFloat(fields.load_high);
      this._updateYLabels(
        Number.isFinite(loadHigh) && loadHigh > 0 ? loadHigh : 2,
        false,
        true
      );
      this._setYLabelsVisible(true);
      return;
    }

    this._updateYLabels(100, false, false);
    this._setYLabelsVisible(true);
  }

  _setYLabelsVisible(visible) {
    const labels = this._getBody()?.querySelector('.sparkline-y-labels');
    if (labels) {
      labels.style.visibility = visible ? '' : 'hidden';
    }
  }

  _prepareCanvas() {
    const canvas = this._getBody()?.querySelector('.sparkline-canvas');
    if (!canvas) {
      return null;
    }

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
    if (!ctx) {
      return null;
    }

    ctx.clearRect(0, 0, w, h);
    return { canvas, ctx, dpr, w, h };
  }

  _updateChrome() {
    const body = this._getBody();
    const titleEl = body?.querySelector('.sparkline-title');
    if (titleEl) {
      titleEl.textContent = this._getTitle(this.state.itemRef, this.state.fallbackTitle);
    }

    const fields = this._getFields();
    const fillColor = fields.fill_color ? `#${fields.fill_color}` : '#458ADC';

    body?.querySelectorAll('.sparkline-periods .toolbar-control[data-period]').forEach((btn) => {
      btn.style.setProperty('--sparkline-active-color', fillColor);
      const isActive = btn.getAttribute('data-period') === this.state.period;
      btn.classList.toggle('active', isActive);
      if (isActive) {
        btn.setAttribute('aria-current', 'true');
      }
      else {
        btn.removeAttribute('aria-current');
      }
    });
  }

  _getSurfaceColor() {
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

  _pauseUpdating() {
    if (this._updatesPaused || typeof this.options.pauseUpdating !== 'function') {
      return;
    }

    this._updatesPaused = this.options.pauseUpdating() !== false;
  }

  _resumeUpdating() {
    if (!this._updatesPaused) {
      return;
    }

    if (typeof this.options.resumeUpdating === 'function') {
      this.options.resumeUpdating();
    }

    this._updatesPaused = false;
  }

  _bindKeydown() {
    if (this._keydownHandler) {
      return;
    }

    this._keydownHandler = (e) => {
      if (e.key === 'Escape' && this.state.open) {
        e.preventDefault();
        this.close();
      }
    };

    document.addEventListener('keydown', this._keydownHandler);
  }

  _removeKeydownHandler() {
    if (!this._keydownHandler) {
      return;
    }

    document.removeEventListener('keydown', this._keydownHandler);
    this._keydownHandler = null;
  }

  _cancelRequest() {
    if (this._abortController) {
      this._abortController.abort();
      this._abortController = null;
    }
  }

  _cancelScheduledRedraw() {
    if (this._redrawRaf) {
      cancelAnimationFrame(this._redrawRaf);
      this._redrawRaf = null;
    }
  }

  _clearDrawState() {
    this._drawState = null;
    this._snapshot = null;
    this._setYLabelsVisible(false);
  }

  _normalizeRef(itemRef) {
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

  _getTitle(ref, fallbackTitle = '') {
    return ref?.name ? ref.name : fallbackTitle;
  }

  _getBody() {
    return typeof this.options.getBody === 'function'
      ? this.options.getBody()
      : null;
  }

  _getFields() {
    return typeof this.options.getFields === 'function'
      ? this.options.getFields() || {}
      : {};
  }

  _getItemRef(metricKey) {
    return typeof this.options.getItemRef === 'function'
      ? this.options.getItemRef(metricKey)
      : null;
  }

  _getWidgetRoot() {
    return (typeof this.options.getWidgetRoot === 'function'
      ? this.options.getWidgetRoot()
      : null) || this._getBody();
  }

  _formatBps(bps) {
    if (typeof this.options.formatBps === 'function') {
      return this.options.formatBps(bps);
    }

    if (bps >= 1e9) return (bps / 1e9).toFixed(bps % 1e9 === 0 ? 0 : 1) + ' Gbps';
    if (bps >= 1e6) return (bps / 1e6).toFixed(bps % 1e6 === 0 ? 0 : 1) + ' Mbps';
    if (bps >= 1e3) return (bps / 1e3).toFixed(bps % 1e3 === 0 ? 0 : 1) + ' Kbps';
    return Math.round(bps) + ' bps';
  }
}
