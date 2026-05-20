/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

(function () {
  'use strict';

  const MAX_SERIES = 8;
  const DEFAULT_COLORS = ['458ADC', '4C9F38', 'FF851B', 'FF4136', '9665FF', '00C0D7', 'B8860B', '7F8C8D'];

  const UI = {
    series_heading: 'Chart series',
    add_series: 'Add series',
    col_label: 'Label',
    col_host: 'Host',
    col_item: 'Data item',
    col_color: 'Color',
    col_actions: '',
    find_item: 'Find item',
    remove_series: 'Remove',
    pick_host: 'Select at least one host above.',
    enter_item: 'Enter an item name to search.',
    checking: 'Checking…',
    exact_fmt: 'Exact match: %s',
    unique_partial_fmt: 'Single match: %s',
    ambiguous_fmt: 'Several matches (%s). Pick one:',
    none_no_items: 'No items found on this host.',
    lookup_failed: 'Could not check items.',
    advanced_json: 'Advanced: edit JSON',
    host_auto: '(default host)',
    max_series: 'Maximum %s series.',
  };

  class MainChartsSeriesEditor {
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

    normalizeColor(value) {
      const color = String(value ?? '').replace('#', '').trim();
      return /^[0-9A-Fa-f]{6}$/.test(color) ? color.toUpperCase() : DEFAULT_COLORS[0];
    }

    writeSeries(series) {
      this.textarea.value = JSON.stringify(series, null, 2);
      this.textarea.dispatchEvent(new Event('change', { bubbles: true }));
      this.render();
    }

    syncFromDom() {
      const rows = this.mount.querySelectorAll('.charts-series-row');
      const series = [];

      rows.forEach((row, index) => {
        const label = row.querySelector('.js-series-label')?.value?.trim() ?? '';
        const hostid = row.querySelector('.js-series-host')?.value?.trim() ?? '';
        const item_name = row.querySelector('.js-series-item-name')?.value?.trim() ?? '';
        const itemid = row.querySelector('.js-series-itemid')?.value?.trim() ?? '';
        const color = this.normalizeColor(row.querySelector('.js-series-color')?.value ?? '');

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
          host: '',
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
      const multiHost = hosts.length > 1;

      this.mount.replaceChildren();
      this.mount.classList.toggle('is-multi-host', multiHost);

      const header = document.createElement('div');
      header.className = 'charts-series-header';
      header.innerHTML = `
        <span class="charts-series-col-label">${UI.col_label}</span>
        ${multiHost ? `<span class="charts-series-col-host">${UI.col_host}</span>` : ''}
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
          body.appendChild(this.createSeriesRow(entry, index, hosts, multiHost));
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
      hint.textContent = 'Add a series and pick a data item for each line on the chart.';
      return hint;
    }

    createSeriesRow(entry, index, hosts, multiHost) {
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

      if (multiHost) {
        const hostSelect = document.createElement('select');
        hostSelect.className = 'text-box-default js-series-host';

        const auto = document.createElement('option');
        auto.value = '';
        auto.textContent = UI.host_auto;
        hostSelect.appendChild(auto);

        for (const host of hosts) {
          const opt = document.createElement('option');
          opt.value = host.hostid;
          opt.textContent = host.label;
          if (entry.hostid === host.hostid) {
            opt.selected = true;
          }
          hostSelect.appendChild(opt);
        }

        hostSelect.addEventListener('change', () => this.syncFromDom());

        const hostCol = document.createElement('div');
        hostCol.className = 'charts-series-col-host';
        hostCol.appendChild(hostSelect);
        row.appendChild(hostCol);
      }

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

      const findBtn = document.createElement('button');
      findBtn.type = 'button';
      findBtn.className = 'btn-alt js-series-find-item';
      findBtn.textContent = UI.find_item;
      findBtn.addEventListener('click', () => this.lookupItem(row));

      const preview = document.createElement('div');
      preview.className = 'charts-series-item-preview';
      preview.hidden = true;

      const itemCol = document.createElement('div');
      itemCol.className = 'charts-series-col-item';
      itemCol.append(itemInput, itemidInput, findBtn, preview);
      row.appendChild(itemCol);

      const colorInput = document.createElement('input');
      colorInput.type = 'text';
      colorInput.className = 'text-box-default js-series-color';
      colorInput.value = entry.color || DEFAULT_COLORS[index % DEFAULT_COLORS.length];
      colorInput.maxLength = 6;
      colorInput.addEventListener('input', () => this.syncFromDom());

      const colorCol = document.createElement('div');
      colorCol.className = 'charts-series-col-color';
      colorCol.appendChild(colorInput);
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

      const series = this.readSeries();
      this.writeSeries(series);
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
        hostid: hosts.length === 1 ? (hosts[0]?.hostid ?? '') : '',
        host: '',
      });

      this.writeSeries(series);
    }

    resolveRowHostId(row) {
      const hosts = this.getSelectedHosts();
      const select = row.querySelector('.js-series-host');

      if (select) {
        const selected = String(select.value ?? '').trim();
        if (selected !== '') {
          return selected;
        }
      }

      if (hosts.length === 1) {
        return hosts[0].hostid;
      }

      return '';
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
    if (window.__mainChartsSeriesEditor) {
      return;
    }

    const mount = document.querySelector('.js-charts-series-editor');
    if (!mount) {
      return;
    }

    let defaultSeries = [];
    try {
      defaultSeries = JSON.parse(mount.getAttribute('data-default-series') || '[]');
    }
    catch (_e) {
      defaultSeries = [];
    }

    window.__mainChartsSeriesEditor = new MainChartsSeriesEditor({
      lookup_action: mount.getAttribute('data-lookup-action') || '',
      max_series: Number(mount.getAttribute('data-max-series') || MAX_SERIES),
      default_series: defaultSeries,
    });
    window.__mainChartsSeriesEditor.init();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  }
  else {
    boot();
  }

  try {
    const overlay = overlays_stack.getById('widget_properties');
    if (overlay?.$dialogue?.[0]) {
      overlay.$dialogue[0].addEventListener('overlay.reload', boot);
    }
  }
  catch (_e) {
    // overlays_stack may be unavailable outside dashboard overlay
  }
})();
