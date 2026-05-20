/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

(function () {
  'use strict';

  const MAX_SERIES = 8;
  const DEFAULT_COLORS = ['458ADC', '4C9F38', 'FF851B', 'FF4136', '9665FF', '00C0D7', 'B8860B', '7F8C8D'];

  const QUICK_ITEMS = [
    { label: 'CPU', item_name: 'CPU utilization' },
    { label: 'Memory', item_name: 'Memory utilization' },
    { label: 'Load', item_name: 'Load average (5m avg)' },
    { label: 'Disk', item_name: 'Disk utilization' },
  ];

  const UI = {
    series_heading: 'Chart series',
    add_series: 'Add series',
    add_from_template: 'Quick add',
    col_label: 'Label',
    col_item: 'Data item',
    col_color: 'Color',
    col_actions: '',
    find_item: 'Find',
    pick_item: 'Browse items',
    pick_item_filter: 'Filter items…',
    pick_item_empty: 'No numeric items on this host.',
    pick_item_loading: 'Loading items…',
    remove_series: 'Remove',
    pick_host: 'Select one host above — all metrics must belong to that host.',
    enter_item: 'Enter an item name or use Browse items.',
    checking: 'Checking…',
    exact_fmt: 'Exact match: %s',
    unique_partial_fmt: 'Single match: %s',
    ambiguous_fmt: 'Several matches (%s). Pick one:',
    browse_fmt: 'Items on host (%s). Click to use:',
    none_no_items: 'No items found on this host.',
    lookup_failed: 'Could not check items.',
    advanced_json: 'Advanced: edit JSON',
    max_series: 'Maximum %s series.',
  };

  class AChartsSeriesEditor {
    constructor(options = {}) {
      this.lookupAction = options.lookup_action || '';
      this.maxSeries = Number(options.max_series) || MAX_SERIES;
      this.defaultSeries = Array.isArray(options.default_series) ? options.default_series : [];
      this.mount = null;
      this.textarea = null;
      this.jsonWrap = null;
      this.hostObserver = null;
      this._hostRefreshTimer = null;
    }

    init() {
      this.mount = document.querySelector('.js-charts-series-editor');
      this.textarea = document.querySelector('textarea[name="chart_series"]');
      this.jsonWrap = document.querySelector('.js-charts-series-json-wrap');

      if (!this.mount || !this.textarea) {
        return;
      }

      this.bindHostObserver();
      this.render();
      this.textarea.addEventListener('change', () => {
        if (!this.mount.dataset.syncing) {
          this.render();
        }
      });

      const resetBtn = document.querySelector('.js-charts-reset-series');
      if (resetBtn && !resetBtn.dataset.chartsEditorBound) {
        resetBtn.dataset.chartsEditorBound = '1';
        resetBtn.addEventListener('click', () => {
          try {
            const defaults = JSON.parse(resetBtn.getAttribute('data-default-series') || '[]');
            this.writeSeries(defaults);
          }
          catch (_e) {
            this.writeSeries(this.defaultSeries);
          }
        });
      }
    }

    bindHostObserver() {
      const hostRoot = this.resolveHostMultiselectRoot();
      if (!hostRoot) {
        return;
      }

      const schedule = () => {
        if (this._hostRefreshTimer) {
          clearTimeout(this._hostRefreshTimer);
        }
        this._hostRefreshTimer = setTimeout(() => this.refreshHostSelects(), 120);
      };

      hostRoot.addEventListener('change', schedule);

      try {
        this.hostObserver = new MutationObserver(schedule);
        this.hostObserver.observe(hostRoot, { childList: true, subtree: true });
      }
      catch (_e) {
        // ignore
      }
    }

    resolveHostMultiselectRoot() {
      return document.getElementById('hostid__')
        || document.getElementById('hostid')
        || document.querySelector('div.multiselect[id^="hostid"]');
    }

    getSelectedHosts() {
      const hosts = [];
      const seen = new Set();
      const root = this.resolveHostMultiselectRoot();

      if (!root) {
        return hosts;
      }

      for (const input of root.querySelectorAll(
        'input[name="hostid[]"], input[name="hostid"], input[type="hidden"][name^="hostid"]'
      )) {
        const hostid = String(input.value ?? '').trim();
        if (hostid === '' || seen.has(hostid)) {
          continue;
        }

        seen.add(hostid);
        const label = this.resolveHostLabel(hostid, input);
        hosts.push({ hostid, label: label || hostid });
      }

      return hosts;
    }

    resolveHostLabel(hostid, input = null) {
      const root = this.resolveHostMultiselectRoot();
      if (!root) {
        return hostid;
      }

      if (input) {
        const dataName = input.getAttribute('data-name');
        if (dataName) {
          const prefix = input.getAttribute('data-prefix') || '';
          return `${prefix}${dataName}`.trim();
        }
      }

      for (const li of root.querySelectorAll('li[data-id]')) {
        if (String(li.dataset.id).trim() === hostid) {
          const fromData = String(li.dataset.label ?? '').trim();
          if (fromData !== '') {
            return fromData;
          }

          const span = li.querySelector('.subfilter-enabled span[title]');
          if (span?.title) {
            return String(span.title).trim();
          }
        }
      }

      return hostid;
    }

    readSeries() {
      const raw = this.textarea.value.trim();
      if (raw === '') {
        return [];
      }

      try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? this.normalizeSeries(parsed) : [];
      }
      catch (_e) {
        return [];
      }
    }

    normalizeSeries(entries) {
      const normalized = [];

      for (let index = 0; index < entries.length; index++) {
        const entry = entries[index];
        if (!entry || typeof entry !== 'object') {
          continue;
        }

        const item_name = String(entry.item_name ?? '').trim();
        const itemid = String(entry.itemid ?? '').trim();
        if (item_name === '' && itemid === '') {
          continue;
        }

        const label = String(entry.label ?? '').trim()
          || item_name
          || `Series ${index + 1}`;

        normalized.push({
          key: String(entry.key ?? '').trim() || `series_${index + 1}`,
          label,
          item_name,
          itemid,
          color: this.normalizeColor(entry.color),
          hostid: String(entry.hostid ?? '').trim(),
          host: String(entry.host ?? '').trim(),
        });

        if (normalized.length >= this.maxSeries) {
          break;
        }
      }

      return normalized;
    }

    normalizeColor(value, fallback) {
      const color = String(value ?? '').replace('#', '').trim();

      if (/^[0-9A-Fa-f]{6}$/.test(color)) {
        return color.toUpperCase();
      }

      const fb = String(fallback ?? '').replace('#', '').trim();

      return /^[0-9A-Fa-f]{6}$/.test(fb) ? fb.toUpperCase() : DEFAULT_COLORS[0];
    }

    writeSeries(series) {
      this.textarea.value = JSON.stringify(series, null, 2);
      this.textarea.dispatchEvent(new Event('change', { bubbles: true }));
      this.render();
    }

    syncFromDom() {
      const rows = this.mount.querySelectorAll('.charts-series-row');
      const series = [];
      const hostid = this.getSelectedHostId();

      rows.forEach((row, index) => {
        const existing = this.readSeries()[index] || {};
        const label = row.querySelector('.js-series-label')?.value?.trim() ?? '';
        const item_name = row.querySelector('.js-series-item-name')?.value?.trim() ?? '';
        const itemid = row.querySelector('.js-series-itemid')?.value?.trim() ?? '';
        const colorRaw = row.querySelector('.js-series-color')?.value ?? '';
        const color = this.normalizeColor(colorRaw, existing.color);

        if (item_name === '' && itemid === '') {
          return;
        }

        series.push({
          key: row.dataset.seriesKey || `series_${index + 1}`,
          label: label || item_name || `Series ${index + 1}`,
          item_name,
          itemid,
          color,
          hostid,
          host: String(existing.host || '').trim(),
        });
      });

      this.mount.dataset.syncing = '1';
      this.textarea.value = JSON.stringify(series, null, 2);
      this.textarea.dispatchEvent(new Event('change', { bubbles: true }));
      delete this.mount.dataset.syncing;
    }

    render() {
      const series = this.readSeries();
      const hosts = this.getSelectedHosts();

      this.mount.replaceChildren();

      const header = document.createElement('div');
      header.className = 'charts-series-header';
      header.innerHTML = `
        <span class="charts-series-col-label">${UI.col_label}</span>
        <span class="charts-series-col-item">${UI.col_item}</span>
        <span class="charts-series-col-color">${UI.col_color}</span>
        <span class="charts-series-col-actions">${UI.col_actions}</span>
      `;
      this.mount.appendChild(header);

      const body = document.createElement('div');
      body.className = 'charts-series-body';

      if (series.length === 0) {
        body.appendChild(this.createEmptyHint());
      }
      else {
        series.forEach((entry, index) => {
          body.appendChild(this.createSeriesRow(entry, index));
        });
      }

      this.mount.appendChild(body);

      const toolbar = document.createElement('div');
      toolbar.className = 'charts-series-toolbar';

      const addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.className = 'btn-alt js-charts-add-series';
      addBtn.textContent = UI.add_series;
      addBtn.disabled = series.length >= this.maxSeries;
      addBtn.addEventListener('click', () => this.addSeries(hosts));
      toolbar.appendChild(addBtn);

      const quickWrap = document.createElement('div');
      quickWrap.className = 'charts-series-quick-add';
      for (const preset of QUICK_ITEMS) {
        const qBtn = document.createElement('button');
        qBtn.type = 'button';
        qBtn.className = 'btn-alt js-charts-quick-item';
        qBtn.textContent = preset.label;
        qBtn.disabled = series.length >= this.maxSeries;
        qBtn.addEventListener('click', () => this.addSeriesFromPreset(hosts, preset));
        quickWrap.appendChild(qBtn);
      }
      toolbar.appendChild(quickWrap);

      if (series.length >= this.maxSeries) {
        const hint = document.createElement('span');
        hint.className = 'charts-series-limit-hint';
        hint.textContent = UI.max_series.replace('%s', String(this.maxSeries));
        toolbar.appendChild(hint);
      }

      this.mount.appendChild(toolbar);
    }

    createEmptyHint() {
      const hint = document.createElement('div');
      hint.className = 'charts-series-empty';
      hint.textContent = 'Add metrics (items) from the selected host — one series per line.';
      return hint;
    }

    createSeriesRow(entry, index) {
      const row = document.createElement('div');
      row.className = 'charts-series-row';
      row.dataset.seriesKey = entry.key || `series_${index + 1}`;

      const labelInput = document.createElement('input');
      labelInput.type = 'text';
      labelInput.className = 'text-box-default js-series-label';
      labelInput.value = entry.label || '';
      labelInput.placeholder = 'CPU';
      labelInput.addEventListener('input', () => this.syncFromDom());

      const labelCol = document.createElement('div');
      labelCol.className = 'charts-series-col-label';
      labelCol.appendChild(labelInput);
      row.appendChild(labelCol);

      const itemidInput = document.createElement('input');
      itemidInput.type = 'hidden';
      itemidInput.className = 'js-series-itemid';
      itemidInput.value = entry.itemid || '';

      const itemInput = document.createElement('input');
      itemInput.type = 'text';
      itemInput.className = 'text-box-default js-series-item-name';
      itemInput.value = entry.item_name || '';
      itemInput.placeholder = 'CPU utilization';
      itemInput.addEventListener('input', () => {
        itemidInput.value = '';
        this.syncFromDom();
      });

      const itemActions = document.createElement('div');
      itemActions.className = 'charts-series-item-actions';

      const findBtn = document.createElement('button');
      findBtn.type = 'button';
      findBtn.className = 'btn-alt js-series-find-item';
      findBtn.textContent = UI.find_item;
      findBtn.addEventListener('click', () => this.lookupItem(row));

      const browseBtn = document.createElement('button');
      browseBtn.type = 'button';
      browseBtn.className = 'btn-alt js-series-browse-items';
      browseBtn.textContent = UI.pick_item;
      browseBtn.addEventListener('click', () => this.toggleItemPicker(row));

      itemActions.append(findBtn, browseBtn);

      const preview = document.createElement('div');
      preview.className = 'charts-series-item-preview';
      preview.hidden = true;

      const picker = document.createElement('div');
      picker.className = 'charts-series-item-picker';
      picker.hidden = true;

      const pickerFilter = document.createElement('input');
      pickerFilter.type = 'search';
      pickerFilter.className = 'text-box-default js-series-picker-filter';
      pickerFilter.placeholder = UI.pick_item_filter;
      pickerFilter.addEventListener('input', () => {
        this.scheduleBrowseItems(row, pickerFilter.value);
      });

      const pickerList = document.createElement('ul');
      pickerList.className = 'charts-series-picker-list js-series-picker-list';

      picker.append(pickerFilter, pickerList);

      const itemCol = document.createElement('div');
      itemCol.className = 'charts-series-col-item';
      itemCol.append(itemInput, itemidInput, itemActions, preview, picker);
      row.appendChild(itemCol);

      const colorHex = entry.color || DEFAULT_COLORS[index % DEFAULT_COLORS.length];
      const colorInput = document.createElement('input');
      colorInput.type = 'text';
      colorInput.className = 'text-box-default js-series-color';
      colorInput.value = colorHex;
      colorInput.maxLength = 6;
      colorInput.addEventListener('change', () => this.syncFromDom());

      const colorPicker = document.createElement('input');
      colorPicker.type = 'color';
      colorPicker.className = 'charts-series-color-picker js-series-color-picker';
      colorPicker.value = `#${colorHex}`;
      colorPicker.title = 'Pick series color';
      colorPicker.addEventListener('input', () => {
        colorInput.value = colorPicker.value.replace('#', '').toUpperCase();
        this.syncFromDom();
      });
      colorInput.addEventListener('input', () => {
        const normalized = this.normalizeColor(colorInput.value, colorHex);

        if (/^[0-9A-Fa-f]{6}$/.test(normalized)) {
          colorPicker.value = `#${normalized}`;
        }
      });

      const colorCol = document.createElement('div');
      colorCol.className = 'charts-series-col-color';
      colorCol.append(colorPicker, colorInput);
      row.appendChild(colorCol);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn-link-style js-series-remove';
      removeBtn.textContent = UI.remove_series;
      removeBtn.addEventListener('click', () => {
        row.remove();
        this.syncFromDom();
        this.render();
      });

      const actionsCol = document.createElement('div');
      actionsCol.className = 'charts-series-col-actions';
      actionsCol.appendChild(removeBtn);
      row.appendChild(actionsCol);

      return row;
    }

    refreshHostSelects() {
      if (!this.mount) {
        return;
      }

      const hostid = this.getSelectedHostId();
      if (hostid === '') {
        this.render();
        return;
      }

      const series = this.readSeries().map((entry) => ({
        ...entry,
        hostid,
        host: '',
      }));
      this.writeSeries(series);
    }

    getSelectedHostId() {
      const hosts = this.getSelectedHosts();
      return hosts.length === 1 ? hosts[0].hostid : '';
    }

    addSeries(hosts) {
      const series = this.readSeries();
      if (series.length >= this.maxSeries) {
        return;
      }

      const index = series.length;
      series.push({
        key: `series_${index + 1}`,
        label: `Series ${index + 1}`,
        item_name: '',
        itemid: '',
        color: DEFAULT_COLORS[index % DEFAULT_COLORS.length],
        hostid: this.getSelectedHostId(),
        host: '',
      });

      this.writeSeries(series);
    }

    addSeriesFromPreset(hosts, preset) {
      if (!preset?.item_name) {
        return;
      }

      const series = this.readSeries();
      if (series.length >= this.maxSeries) {
        return;
      }

      const index = series.length;
      series.push({
        key: `series_${index + 1}`,
        label: preset.label || `Series ${index + 1}`,
        item_name: preset.item_name,
        itemid: '',
        color: DEFAULT_COLORS[index % DEFAULT_COLORS.length],
        hostid: this.getSelectedHostId(),
        host: '',
      });

      this.writeSeries(series);
    }

    toggleItemPicker(row) {
      const picker = row.querySelector('.charts-series-item-picker');
      if (!picker) {
        return;
      }

      const willOpen = picker.hidden;
      picker.hidden = !willOpen;

      if (willOpen) {
        const filter = row.querySelector('.js-series-picker-filter');
        if (filter) {
          filter.value = row.querySelector('.js-series-item-name')?.value?.trim() ?? '';
        }
        this.browseItems(row, filter?.value?.trim() ?? '');
      }
    }

    scheduleBrowseItems(row, search) {
      if (row._browseTimer) {
        clearTimeout(row._browseTimer);
      }

      row._browseTimer = setTimeout(() => {
        row._browseTimer = null;
        this.browseItems(row, search);
      }, 220);
    }

    async browseItems(row, search = '') {
      if (!this.lookupAction) {
        return;
      }

      const hostid = this.resolveRowHostId(row);
      const preview = row.querySelector('.charts-series-item-preview');
      const pickerList = row.querySelector('.js-series-picker-list');

      if (!hostid) {
        this.showPreview(preview, 'warning', UI.pick_host);
        return;
      }

      if (pickerList) {
        pickerList.replaceChildren();
        const li = document.createElement('li');
        li.className = 'charts-series-picker-muted';
        li.textContent = UI.pick_item_loading;
        pickerList.appendChild(li);
      }

      const curl = new Curl('zabbix.php');
      curl.setArgument('action', this.lookupAction);

      try {
        const response = await fetch(curl.getUrl(), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          credentials: 'same-origin',
          body: JSON.stringify({ hostid, search, mode: 'browse' }),
        });

        const result = await this.parseLookupResponse(response);
        if ('error' in result) {
          const messages = Array.isArray(result.error?.messages)
            ? result.error.messages.filter(Boolean)
            : [];
          throw new Error(messages[0] ?? UI.lookup_failed);
        }

        this.renderBrowseResult(row, result);
      }
      catch (error) {
        this.showPreview(
          preview,
          'error',
          error instanceof Error ? error.message : UI.lookup_failed
        );

        if (pickerList) {
          pickerList.replaceChildren();
        }
      }
    }

    renderBrowseResult(row, result) {
      const itemInput = row.querySelector('.js-series-item-name');
      const itemidInput = row.querySelector('.js-series-itemid');
      const preview = row.querySelector('.charts-series-item-preview');
      const pickerList = row.querySelector('.js-series-picker-list');
      const candidates = Array.isArray(result?.candidates) ? result.candidates : [];
      const count = Number.parseInt(result?.candidate_count ?? 0, 10) || candidates.length;

      if (!pickerList) {
        return;
      }

      pickerList.replaceChildren();

      if (candidates.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'charts-series-picker-muted';
        empty.textContent = UI.pick_item_empty;
        pickerList.appendChild(empty);
        this.showPreview(preview, 'warning', UI.pick_item_empty);
        return;
      }

      for (const candidate of candidates) {
        if (!candidate?.name) {
          continue;
        }

        const li = document.createElement('li');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-link-style';
        const units = String(candidate.units ?? '').trim();
        btn.textContent = units !== '' ? `${candidate.name} (${units})` : candidate.name;
        btn.addEventListener('click', () => {
          itemInput.value = candidate.name;
          itemidInput.value = candidate.itemid ?? '';
          row.querySelector('.charts-series-item-picker')?.setAttribute('hidden', 'hidden');
          this.showPreview(preview, 'success', UI.exact_fmt.replace('%s', candidate.name));
          this.syncFromDom();
        });
        li.appendChild(btn);
        pickerList.appendChild(li);
      }

      this.showPreview(
        preview,
        'success',
        UI.browse_fmt.replace('%s', String(count))
      );
    }

    resolveRowHostId(_row) {
      return this.getSelectedHostId();
    }

    async lookupItem(row) {
      if (!this.lookupAction) {
        return;
      }

      const hostid = this.resolveRowHostId(row);
      const itemInput = row.querySelector('.js-series-item-name');
      const itemidInput = row.querySelector('.js-series-itemid');
      const preview = row.querySelector('.charts-series-item-preview');
      const search = itemInput?.value?.trim() ?? '';

      if (!hostid) {
        this.showPreview(preview, 'warning', UI.pick_host);
        return;
      }

      if (search === '') {
        this.showPreview(preview, 'warning', UI.enter_item);
        return;
      }

      this.showPreview(preview, 'muted', UI.checking);

      const curl = new Curl('zabbix.php');
      curl.setArgument('action', this.lookupAction);

      try {
        const response = await fetch(curl.getUrl(), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          credentials: 'same-origin',
          body: JSON.stringify({ hostid, search }),
        });

        const result = await this.parseLookupResponse(response);
        if ('error' in result) {
          const messages = Array.isArray(result.error?.messages)
            ? result.error.messages.filter(Boolean)
            : [];
          throw new Error(messages[0] ?? UI.lookup_failed);
        }

        this.renderLookupResult(preview, itemInput, itemidInput, result);
        this.syncFromDom();
      }
      catch (error) {
        this.showPreview(
          preview,
          'error',
          error instanceof Error ? error.message : UI.lookup_failed
        );
      }
    }

    async parseLookupResponse(response) {
      const raw = await response.text();
      if (raw === '') {
        throw new Error(UI.lookup_failed);
      }

      try {
        return JSON.parse(raw);
      }
      catch (_e) {
        throw new Error(UI.lookup_failed);
      }
    }

    renderLookupResult(preview, itemInput, itemidInput, result) {
      const status = String(result?.status ?? 'none');
      const match = result?.match ?? null;
      const candidates = Array.isArray(result?.candidates) ? result.candidates : [];
      const candidateCount = Number.parseInt(result?.candidate_count ?? 0, 10) || 0;

      if (status === 'exact' && match?.name) {
        itemInput.value = match.name;
        itemidInput.value = match.itemid ?? '';
        this.showPreview(preview, 'success', UI.exact_fmt.replace('%s', match.name));
        return;
      }

      if (status === 'unique_partial' && match?.name) {
        itemInput.value = match.name;
        itemidInput.value = match.itemid ?? '';
        const fragment = document.createDocumentFragment();
        const summary = document.createElement('div');
        summary.className = 'charts-series-preview-text';
        summary.textContent = UI.unique_partial_fmt.replace('%s', match.name);
        fragment.append(summary);
        fragment.append(this.createCandidateList(candidates, itemInput, itemidInput, preview));
        this.showPreview(preview, 'success', fragment);
        return;
      }

      if (status === 'ambiguous') {
        const fragment = document.createDocumentFragment();
        const summary = document.createElement('div');
        summary.className = 'charts-series-preview-text';
        summary.textContent = UI.ambiguous_fmt.replace('%s', String(candidateCount));
        fragment.append(summary);
        fragment.append(this.createCandidateList(candidates, itemInput, itemidInput, preview));
        this.showPreview(preview, 'warning', fragment);
        return;
      }

      this.showPreview(preview, 'error', UI.none_no_items);
    }

    createCandidateList(candidates, itemInput, itemidInput, preview) {
      const list = document.createElement('ul');
      list.className = 'charts-series-candidates';

      for (const candidate of candidates) {
        if (!candidate?.name) {
          continue;
        }

        const li = document.createElement('li');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-link-style';
        btn.textContent = candidate.name;
        btn.addEventListener('click', () => {
          itemInput.value = candidate.name;
          itemidInput.value = candidate.itemid ?? '';
          this.showPreview(preview, 'success', UI.exact_fmt.replace('%s', candidate.name));
          this.syncFromDom();
        });
        li.appendChild(btn);
        list.appendChild(li);
      }

      return list;
    }

    showPreview(preview, state, content) {
      preview.hidden = false;
      preview.dataset.state = state;
      preview.replaceChildren();

      if (typeof content === 'string') {
        const text = document.createElement('div');
        text.className = 'charts-series-preview-text';
        text.textContent = content;
        preview.appendChild(text);
        return;
      }

      preview.appendChild(content);
    }
  }

  function boot() {
    const mount = document.querySelector('.js-charts-series-editor');
    const textarea = document.querySelector('textarea[name="chart_series"]');

    if (!mount || !textarea) {
      return;
    }

    let defaultSeries = [];

    try {
      defaultSeries = JSON.parse(mount.getAttribute('data-default-series') || '[]');
    }
    catch (_e) {
      defaultSeries = [];
    }

    if (!window.__aChartsSeriesEditor) {
      window.__aChartsSeriesEditor = new AChartsSeriesEditor({
        lookup_action: mount.getAttribute('data-lookup-action') || '',
        max_series: Number(mount.getAttribute('data-max-series') || MAX_SERIES),
        default_series: defaultSeries,
      });
    }

    window.__aChartsSeriesEditor.mount = mount;
    window.__aChartsSeriesEditor.textarea = textarea;
    window.__aChartsSeriesEditor.init();
  }

  function scheduleBoot() {
    window.requestAnimationFrame(() => {
      boot();
    });
  }

  window.scheduleAChartsSeriesEditorBoot = scheduleBoot;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleBoot);
  }
  else {
    scheduleBoot();
  }

  try {
    const overlay = overlays_stack.getById('widget_properties');

    if (overlay?.$dialogue?.[0]) {
      overlay.$dialogue[0].addEventListener('overlay.reload', scheduleBoot);
    }
  }
  catch (_e) {
    // overlays_stack may be unavailable outside dashboard overlay
  }
})();
