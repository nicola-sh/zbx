/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

class HostOverviewSparkline {

  static ACTION = 'widget.host_overview.sparkline';

  constructor(options = {}) {
    this.options = options;
    this.state = {
      open: false,
      cellId: null,
      spec: null,
      color: '',
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
    this._attachedOverlay = null;
    this._attachedCanvas = null;
    this._handleOverlayClick = (event) => this._onOverlayClick(event);
    this._handleMouseMove = (event) => this._onMouseMove(event);
    this._handleMouseLeave = () => this._onMouseLeave();
  }

  attach() {
    const overlay = this._getOverlayRoot();
    if (this._attachedOverlay !== overlay) {
      if (this._attachedOverlay) {
        this._attachedOverlay.removeEventListener('click', this._handleOverlayClick);
      }

      if (overlay) {
        overlay.addEventListener('click', this._handleOverlayClick);
      }

      this._attachedOverlay = overlay;
    }

    const canvas = overlay?.querySelector('.sparkline-canvas') || null;
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

  open(metric = {}) {
    const cellId = metric?.cellId ?? null;
    const spec = this._normalizeSpec(metric?.spec);

    if (!cellId || !spec?.item_ref || (!spec.item_ref.itemid && !spec.item_ref.name)) {
      return;
    }

    if (this.state.open && this.state.cellId === cellId) {
      this.close();
      return;
    }

    this.attach();

    this.state.open = true;
    this.state.cellId = cellId;
    this.state.spec = spec;
    this.state.color = typeof metric?.color === 'string' ? metric.color : '';
    this.state.data = null;
    this.state.message = 'Loading...';
    this._pauseUpdating();
    this._updateChrome();
    this._primeYLabels();

    const root = this._getWidgetRoot();
    if (root) {
      root.classList.add('sparkline-open');
    }

    const overlay = this._getOverlayRoot();
    if (overlay) {
      overlay.style.backgroundColor = this._getSurfaceColor();
      overlay.setAttribute('aria-hidden', 'false');
      overlay.classList.add('visible');
    }

    const overviewRoot = this._getOverviewRoot();
    if (overviewRoot) {
      overviewRoot.classList.add('sparkline-active');
    }

    this._bindKeydown();
    this.scheduleRedraw();
    this.fetchSparkline(spec, this.state.period);
  }

  close() {
    if (!this.state.open && !this._updatesPaused) {
      this._removeKeydownHandler();
      return;
    }

    this._cancelRequest();
    this._cancelScheduledRedraw();

    this.state.open = false;
    this.state.cellId = null;
    this.state.spec = null;
    this.state.color = '';
    this.state.data = null;
    this.state.message = '';
    this._clearDrawState();

    const overlay = this._getOverlayRoot();
    if (overlay) {
      overlay.setAttribute('aria-hidden', 'true');
      overlay.classList.remove('visible');
    }

    const root = this._getWidgetRoot();
    if (root) {
      root.classList.remove('sparkline-open');
    }

    const overviewRoot = this._getOverviewRoot();
    if (overviewRoot) {
      overviewRoot.classList.remove('sparkline-active');
    }

    this._removeKeydownHandler();
    this._resumeUpdating();

    if (typeof this.options.onClose === 'function') {
      this.options.onClose();
    }
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

    if (this._attachedOverlay) {
      this._attachedOverlay.removeEventListener('click', this._handleOverlayClick);
      this._attachedOverlay = null;
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

  fetchSparkline(spec, period) {
    const normalizedSpec = this._normalizeSpec(spec);

    if (!normalizedSpec?.item_ref || (!normalizedSpec.item_ref.itemid && !normalizedSpec.item_ref.name)) {
      this.state.data = null;
      this.state.message = 'Item not found';
      this.scheduleRedraw();
      return;
    }

    this.state.spec = normalizedSpec;
    this.state.period = period;
    this.state.data = null;
    this.state.message = 'Loading...';
    this._updateChrome();
    this.scheduleRedraw();

    this._cancelRequest();
    const requestId = ++this._requestId;
    const controller = new AbortController();
    this._abortController = controller;
    this._fetchSparklineAsync(normalizedSpec, period, requestId, controller.signal);
  }

  async _fetchSparklineAsync(spec, period, requestId, signal) {
    try {
      const result = await this._fetchSparklineData(spec, period, signal);
      const data = this._normalizeSparklineResult(result);

      if (requestId === this._requestId && this.state.open && this.state.period === period) {
        if (result?.item_ref) {
          this.state.spec = {
            ...this.state.spec,
            item_ref: this._normalizeRef(result.item_ref),
          };
        }

        this.state.data = data;
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
        this.state.message = error instanceof Error && error.message
          ? error.message
          : 'Error loading data';
        this.scheduleRedraw();
      }
    } finally {
      if (this._abortController?.signal === signal) {
        this._abortController = null;
      }
    }
  }

  async _fetchSparklineData(spec, period, signal) {
    const curl = new Curl('zabbix.php');
    curl.setArgument('action', HostOverviewSparkline.ACTION);

    const response = await fetch(curl.getUrl(), {
      method: 'POST',
      signal,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify(this._buildSparklineRequest(spec, period)),
    });

    return this._parseSparklineResponse(response);
  }

  _buildSparklineRequest(spec, period) {
    const fields = this._getFields();
    const fromSpec = spec?.hostid != null && String(spec.hostid).trim() !== ''
      ? String(spec.hostid).trim()
      : '';
    const hostid = fromSpec !== ''
      ? fromSpec
      : (Array.isArray(fields.hostid)
        ? ((fields.hostid[0] || '').toString())
        : ((fields.hostid || '').toString()));

    return {
      hostid,
      period,
      itemid: spec.item_ref?.itemid ?? '',
      item_name: spec.item_ref?.name ?? '',
      display_kind: spec.display_kind ?? 'percent',
      axis_min: spec.axis?.min ?? '',
      axis_max: spec.axis?.max ?? '',
      invert_percent: spec.transform?.invert_percent ? '1' : '0',
    };
  }

  async _parseSparklineResponse(response) {
    const raw = await response.text();

    if (raw === '') {
      throw new Error('The sparkline endpoint returned an empty response.');
    }

    let parsed;
    try {
      parsed = JSON.parse(raw);
    } catch (_error) {
      const contentType = response.headers.get('content-type') ?? '';

      if (contentType.includes('text/html') || this._looksLikeHtmlDocument(raw)) {
        throw new Error('The sparkline endpoint returned an HTML page instead of JSON.');
      }

      throw new Error('Could not read the sparkline response.');
    }

    if ('error' in parsed) {
      const messages = Array.isArray(parsed.error?.messages)
        ? parsed.error.messages.filter(Boolean)
        : [];

      throw new Error(messages[0] ?? 'Could not load sparkline data.');
    }

    return parsed;
  }

  _normalizeSparklineResult(result) {
    const points = Array.isArray(result?.points)
      ? result.points
        .map((point) => ({
          t: parseInt(point?.t, 10),
          v: Number(point?.v),
        }))
        .filter((point) => Number.isFinite(point.t) && Number.isFinite(point.v))
      : [];

    const min = Number(result?.min);
    const max = Number(result?.max);
    const timeFrom = Number(result?.timeFrom);
    const timeTill = Number(result?.timeTill);
    const gapThresholdFloor = Number(result?.gapThresholdFloor);

    return {
      points,
      min: Number.isFinite(min) ? min : 0,
      max: Number.isFinite(max) ? max : 0,
      timeFrom: Number.isFinite(timeFrom) ? timeFrom : 0,
      timeTill: Number.isFinite(timeTill) ? timeTill : 0,
      gapThresholdFloor: Number.isFinite(gapThresholdFloor) ? gapThresholdFloor : 300,
    };
  }

  _looksLikeHtmlDocument(text) {
    return /^\s*<!DOCTYPE html/i.test(text) || /^\s*<html[\s>]/i.test(text);
  }

  _onOverlayClick(event) {
    const overlay = this._getOverlayRoot();
    if (!overlay) {
      return;
    }

    const closeBtn = event.target.closest('.js-sparkline-close');
    if (closeBtn) {
      event.preventDefault();
      this.close();
      return;
    }

    const periodBtn = event.target.closest('.sparkline-periods [data-period]');
    if (periodBtn && overlay.contains(periodBtn)) {
      event.preventDefault();
      const period = periodBtn.getAttribute('data-period');
      if (!period || this.state.spec === null) {
        return;
      }

      this.state.period = period;
      this._updateChrome();
      this.fetchSparkline(this.state.spec, period);
    }
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

    const overlay = this._getOverlayRoot();
    if (!overlay || !overlay.classList.contains('visible')) {
      return;
    }

    if (this.state.data) {
      this._drawSparkline(
        this.state.data.points,
        this.state.data.min,
        this.state.data.max,
        this.state.data.timeFrom,
        this.state.data.timeTill,
        this.state.data.gapThresholdFloor
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
    const displayKind = this._getDisplayKind();

    if (!points || points.length === 0) {
      this._drawMessage('No data');
      return;
    }

    min = Number.isFinite(min) ? min : 0;
    max = Number.isFinite(max) ? max : 0;
    max = Math.max(max, min + 1);
    this._updateYLabels(max, displayKind);

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

    const fillColor = this._getSparklineColor();

    const intervals = [];
    for (let i = 1; i < points.length; i++) {
      intervals.push(points[i].t - points[i - 1].t);
    }
    intervals.sort((left, right) => left - right);
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
      ctx.save();
      ctx.globalAlpha = 0.15;
      ctx.fillStyle = fillColor;
      ctx.fill(path);
      ctx.restore();
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
      displayKind,
    };
    this._snapshot = ctx.getImageData(0, 0, canvas.width, canvas.height);
    this._setYLabelsVisible(true);
  }

  _onMouseMove(event) {
    const state = this._drawState;
    if (!state) {
      return;
    }

    const canvas = this._getOverlayRoot()?.querySelector('.sparkline-canvas');
    if (!canvas) {
      return;
    }

    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    const mouseX = (event.clientX - rect.left) * dpr;

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
    ctx.globalAlpha = 0.4;
    ctx.strokeStyle = fillColor;
    ctx.lineWidth = Math.round(dpr);
    ctx.moveTo(sx + 0.5, 0);
    ctx.lineTo(sx + 0.5, drawH);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.globalAlpha = 1;

    ctx.beginPath();
    ctx.arc(sx, sy, dotR, 0, Math.PI * 2);
    ctx.fillStyle = fillColor;
    ctx.fill();

    const valueText = this._formatSeekerValue(state.points[nearest].v, state.displayKind);
    const timeText = this._formatSeekerTime(state.points[nearest].t, state.tRange);

    const textColor = 'rgba(255,255,255,0.95)';
    const boxBg = 'rgba(0,0,0,0.8)';
    const pad = Math.round(5 * dpr);
    const boxH = Math.round(18 * dpr);
    const gap = Math.round(10 * dpr);
    const cornerR = this._hasSquareCorners() ? 0 : Math.round(3 * dpr);
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
    const canvas = this._getOverlayRoot()?.querySelector('.sparkline-canvas');
    if (canvas && this._snapshot) {
      canvas.getContext('2d')?.putImageData(this._snapshot, 0, 0);
    }
  }

  _formatSeekerValue(value, displayKind) {
    switch (displayKind) {
      case 'interface':
        return this._formatBps(value);

      case 'load':
        return value % 1 === 0 ? value.toFixed(0) : value.toFixed(2);

      case 'percent':
      default:
        return value.toFixed(1) + '%';
    }
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

  _updateYLabels(max, displayKind) {
    const labels = this._getOverlayRoot()?.querySelector('.sparkline-y-labels');
    if (!labels) {
      return;
    }

    const spans = labels.querySelectorAll('span');
    if (spans.length < 3) {
      return;
    }

    if (displayKind === 'interface') {
      spans[0].textContent = this._formatBps(max);
      spans[1].textContent = this._formatBps(max * 0.5);
      spans[2].textContent = '0';
      return;
    }

    if (displayKind === 'load') {
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
    const displayKind = this._getDisplayKind();
    const axisMax = Number(this.state.spec?.axis?.max);

    if (displayKind === 'percent') {
      this._updateYLabels(Number.isFinite(axisMax) && axisMax > 0 ? axisMax : 100, displayKind);
      this._setYLabelsVisible(true);
      return;
    }

    if (Number.isFinite(axisMax) && axisMax > 0) {
      this._updateYLabels(axisMax, displayKind);
      this._setYLabelsVisible(true);
      return;
    }

    this._setYLabelsVisible(false);
  }

  _setYLabelsVisible(visible) {
    const labels = this._getOverlayRoot()?.querySelector('.sparkline-y-labels');
    if (labels) {
      labels.style.visibility = visible ? '' : 'hidden';
    }
  }

  _prepareCanvas() {
    const canvas = this._getOverlayRoot()?.querySelector('.sparkline-canvas');
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
    const fillColor = this._getSparklineColor();

    this._getOverlayRoot()?.querySelectorAll('.sparkline-periods [data-period]').forEach((btn) => {
      btn.style.setProperty('--sparkline-active-color', fillColor);
      const isActive = btn.getAttribute('data-period') === this.state.period;
      btn.classList.toggle('active', isActive);
      if (isActive) {
        btn.setAttribute('aria-current', 'true');
      } else {
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

    this._keydownHandler = (event) => {
      if (event.key === 'Escape' && this.state.open) {
        event.preventDefault();
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

  _normalizeSpec(spec) {
    if (!spec || typeof spec !== 'object') {
      return null;
    }

    return {
      item_ref: this._normalizeRef(spec.item_ref),
      display_kind: spec.display_kind || 'percent',
      axis: {
        min: spec.axis?.min ?? 0,
        max: spec.axis?.max ?? null,
      },
      transform: {
        invert_percent: Boolean(spec.transform?.invert_percent),
      },
    };
  }

  _normalizeRef(itemRef) {
    if (!itemRef || typeof itemRef !== 'object') {
      return null;
    }

    return {
      itemid: itemRef.itemid ?? null,
      name: itemRef.name ?? null,
    };
  }

  _getDisplayKind() {
    return this.state.spec?.display_kind || 'percent';
  }

  _getSparklineColor() {
    if (this.state.color) {
      return this.state.color;
    }

    const fields = this._getFields();
    return fields.fill_color ? `#${fields.fill_color}` : '#458ADC';
  }

  _hasSquareCorners() {
    if (typeof this.options.hasSquareCorners === 'function') {
      return this.options.hasSquareCorners() === true;
    }

    return this._getWidgetRoot()?.classList.contains('square-corners') ?? false;
  }

  _getBody() {
    return typeof this.options.getBody === 'function'
      ? this.options.getBody()
      : null;
  }

  _getOverlayRoot() {
    return (typeof this.options.getOverlayRoot === 'function'
      ? this.options.getOverlayRoot()
      : null) || this._getBody()?.querySelector('[data-host-overview-role="sparkline"]');
  }

  _getOverviewRoot() {
    return (typeof this.options.getOverviewRoot === 'function'
      ? this.options.getOverviewRoot()
      : null) || this._getBody()?.querySelector('[data-host-overview-role="overview"]');
  }

  _getFields() {
    return typeof this.options.getFields === 'function'
      ? this.options.getFields() || {}
      : {};
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
