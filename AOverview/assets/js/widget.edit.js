/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

window.form = new (class {
  init(options) {
    this.badgeTypeOptions = Array.isArray(options?.badge_type_options)
      ? options.badge_type_options
      : [];
    this.badgeMultipleTypes = Array.isArray(options?.badge_multiple_types)
      ? options.badge_multiple_types.map(String)
      : [];
    this.badgeTypesWithText = Array.isArray(options?.badge_types_with_text)
      ? options.badge_types_with_text.map(String)
      : [];
    this.badgeTypesWithUrl = Array.isArray(options?.badge_types_with_url)
      ? options.badge_types_with_url.map(String)
      : [];
    this.perHostLabels = options?.per_host_labels && typeof options.per_host_labels === "object"
      ? options.per_host_labels
      : {};
    this.thresholdUi = options?.threshold_ui && typeof options.threshold_ui === "object"
      ? options.threshold_ui
      : {};
    this.lookupUi = options?.lookup_ui && typeof options.lookup_ui === "object"
      ? options.lookup_ui
      : {};
    this.helpUi = options?.help_ui && typeof options.help_ui === "object"
      ? options.help_ui
      : {};
    this.metricCheckboxRows = Array.isArray(options?.metric_checkbox_rows)
      ? options.metric_checkbox_rows
      : [];
    this.metricLookupAction = typeof options?.item_lookup_action === "string"
      ? options.item_lookup_action
      : "";
    this._hostAccordionRefreshTimer = null;

    // Color pickers
    if (
      options &&
      options.color_picker_class &&
      typeof jQuery !== "undefined" &&
      jQuery.fn &&
      typeof jQuery.fn.colorpicker === "function"
    ) {
      this.initColorPickers(options.color_picker_class);
    }
    // Field toggles
    this.initColorSchemeToggle();
    this.initBadgesTable();
    this.initPerHostAccordionEditor();
    this.initMetricLookupAssistants();
    this.initGlobalThresholdScales();
    window.requestAnimationFrame(() => {
      this.initMetricLookupAssistants();
      this.bindEditorHelpIcons();
    });

  }

  bindEditorHelpIcons() {
    if (typeof jQuery === "undefined" || typeof jQuery.fn.hintBox !== "function") {
      return;
    }

    for (const btn of document.querySelectorAll(".a-overview-field-help:not([data-hintbox-bound])")) {
      btn.dataset.hintboxBound = "1";
      jQuery(btn).hintBox();
    }
  }

  th(key, fallback = "") {
    const v = this.thresholdUi?.[key];

    return typeof v === "string" && v !== "" ? v : fallback;
  }

  lu(key, fallback = "") {
    const v = this.lookupUi?.[key];

    return typeof v === "string" && v !== "" ? v : fallback;
  }

  luFmt(key, ...parts) {
    let s = this.lu(key, "");

    for (const p of parts) {
      s = s.replace("%s", String(p));
    }

    return s;
  }

  hu(key, fallback = "") {
    const v = this.helpUi?.[key];

    return typeof v === "string" && v !== "" ? v : fallback;
  }

  makeFieldHelp(hint) {
    if (!hint) {
      return null;
    }

    const help = document.createElement("button");

    help.type = "button";
    help.className = "a-overview-field-help";
    help.setAttribute("aria-label", "Подсказка");
    help.setAttribute("title", hint);
    help.textContent = "?";

    return help;
  }

  initColorPickers(colorPickerClass) {
    const selector = `.${colorPickerClass} input`;

    // Initialize color picker on all matching inputs
    for (const colorpicker of jQuery(selector)) {
      jQuery(colorpicker).colorpicker();
    }

    const overlay = overlays_stack.getById("widget_properties");
    if (!overlay || !overlay.$dialogue || !overlay.$dialogue[0]) {
      return;
    }

    // Hide colorpickers when the overlay reloads or closes
    for (const event of ["overlay.reload", "overlay.close"]) {
      overlay.$dialogue[0].addEventListener(event, () => {
        jQuery.colorpicker("hide");
      });
    }
  }

  // Init checkbox / radio dependency groups
  initColorSchemeToggle() {
    const container = document.getElementById("color_scheme");
    if (!container) return;

    const thresholdRows = [...document.querySelectorAll(".js-threshold-color-row")];
    const solidRows = [...document.querySelectorAll(".js-solid-color-row")];
    const thresholdBlocks = [...document.querySelectorAll(".js-threshold-group, .js-threshold-block")];
    const thresholdTables = [...document.querySelectorAll(".js-threshold-table")];
    const colorsGrid = document.querySelector(".js-threshold-colors-grid");
    const radios = container.querySelectorAll('input[type="radio"]');

    const toggleRows = (rows, visible) => {
      rows.forEach((row) => {
        row.style.display = visible ? "" : "none";
      });
    };

    const update = () => {
      const selected = container.querySelector('input[type="radio"]:checked')?.value ?? "0";
      const showSolid = selected === "1";

      toggleRows(solidRows, showSolid);
      toggleRows(thresholdRows, !showSolid);
      toggleRows(thresholdBlocks, !showSolid);
      toggleRows(thresholdTables, !showSolid);

      if (colorsGrid) {
        colorsGrid.classList.toggle("is-solid-mode", showSolid);
      }

      this.refreshAllThresholdScales();
    };

    radios.forEach((radio) => {
      radio.addEventListener("change", update);
    });

    for (const input of document.querySelectorAll(
      'input[name="th_color_1"], input[name="th_color_2"], input[name="th_color_3"]'
    )) {
      input.addEventListener("input", () => this.refreshAllThresholdScales());
      input.addEventListener("change", () => this.refreshAllThresholdScales());
    }

    update();
  }

  initGlobalThresholdScales() {
    for (const group of document.querySelectorAll(".js-threshold-group")) {
      this.mountThresholdScale(group);
    }
  }

  refreshAllThresholdScales() {
    for (const block of document.querySelectorAll(".js-threshold-block, .js-threshold-group")) {
      this.updateThresholdScale(block);
    }
  }

  readFormNumberByName(name, root = document) {
    const input = root.querySelector(`input[name="${name}"]`);

    if (!input) {
      return null;
    }

    const n = Number.parseInt(String(input.value ?? "").trim(), 10);

    return Number.isFinite(n) ? n : null;
  }

  readGlobalThresholdPair(highKey, mediumKey) {
    let medium = this.readFormNumberByName(mediumKey);
    let high = this.readFormNumberByName(highKey);

    if (medium === null) {
      medium = this.readFormNumberByName("th_num_2");
    }

    if (high === null) {
      high = this.readFormNumberByName("th_num_1");
    }

    if (medium === null) {
      medium = 70;
    }

    if (high === null) {
      high = 85;
    }

    return {medium, high};
  }

  readThresholdColors() {
    const pick = (name, fallback) => {
      const input = document.querySelector(`input[name="${name}"]`);
      let raw = input?.value ?? fallback;

      if (typeof raw === "string" && raw.startsWith("#")) {
        raw = raw.slice(1);
      }

      raw = String(raw).trim().replace(/^#/, "");

      return /^[0-9a-fA-F]{6}$/.test(raw) ? `#${raw}` : fallback;
    };

    return {
      green: pick("th_color_3", "#4C9F38"),
      yellow: pick("th_color_2", "#FF851B"),
      red: pick("th_color_1", "#FF4136"),
    };
  }

  mountThresholdScale(container) {
    const mount = container.querySelector(".js-threshold-scale-mount");

    if (!mount || mount.dataset.mounted === "1") {
      return mount?.querySelector(".js-threshold-block") ?? container;
    }

    mount.dataset.mounted = "1";
    const block = this.createThresholdScaleElement(container);

    mount.appendChild(block);

    return block;
  }

  createThresholdScaleElement(container) {
    const highKey = container.dataset.thresholdHigh ?? "";
    const mediumKey = container.dataset.thresholdMedium ?? "";
    const metric = container.dataset.thresholdMetric ?? "";

    const block = document.createElement("div");

    block.className = "ho-threshold-block js-threshold-block";
    block.dataset.thresholdHigh = highKey;
    block.dataset.thresholdMedium = mediumKey;

    if (metric !== "") {
      block.dataset.thresholdMetric = metric;
    }

    if (container.classList.contains("js-threshold-group")) {
      block.dataset.thresholdScope = "global";
    }
    else {
      block.dataset.thresholdScope = "host";
    }

    block.classList.add("ho-threshold-block--compact");

    const row = document.createElement("div");

    row.className = "ho-threshold-compact-row";

    const inputs = document.createElement("div");

    inputs.className = "ho-threshold-scale-inputs ho-threshold-scale-inputs--inline";

    const mkField = (labelText, key, placeholder) => {
      const wrap = document.createElement("label");

      wrap.className = "ho-threshold-scale-field";

      const lab = document.createElement("span");

      lab.className = "ho-threshold-scale-field-label";
      lab.textContent = labelText;

      const input = document.createElement("input");

      input.type = "number";
      input.min = "0";
      input.max = "100";
      input.className = "ho-threshold-scale-input";
      input.placeholder = placeholder;
      input.dataset.thresholdRole = key === highKey ? "high" : "medium";

      if (!container.classList.contains("js-threshold-group")) {
        input.dataset.overrideKey = key;
        input.classList.add("a-overview-phost-input");
      }

      wrap.append(lab, input);

      return {wrap, input};
    };

    const mediumField = mkField(
      this.th("medium_label", "Yellow from (%)"),
      mediumKey,
      this.th("inherit_hint", "Global")
    );
    const highField = mkField(
      this.th("high_label", "Red from (%)"),
      highKey,
      this.th("inherit_hint", "Global")
    );

    inputs.append(mediumField.wrap, highField.wrap);

    const bar = document.createElement("div");

    bar.className = "ho-threshold-scale-bar";
    bar.setAttribute("role", "img");
    bar.setAttribute("aria-hidden", "true");

    const segGreen = document.createElement("span");
    const segYellow = document.createElement("span");
    const segRed = document.createElement("span");

    segGreen.className = "ho-threshold-scale-seg ho-threshold-scale-seg--green";
    segYellow.className = "ho-threshold-scale-seg ho-threshold-scale-seg--yellow";
    segRed.className = "ho-threshold-scale-seg ho-threshold-scale-seg--red";
    bar.append(segGreen, segYellow, segRed);

    const legend = document.createElement("div");

    legend.className = "ho-threshold-scale-legend";

    const legGreen = document.createElement("span");
    const legYellow = document.createElement("span");
    const legRed = document.createElement("span");

    legGreen.className = "ho-threshold-scale-legend-item ho-threshold-scale-legend-item--green";
    legYellow.className = "ho-threshold-scale-legend-item ho-threshold-scale-legend-item--yellow";
    legRed.className = "ho-threshold-scale-legend-item ho-threshold-scale-legend-item--red";

    legend.append(legGreen, legYellow, legRed);

    const note = document.createElement("div");

    note.className = "ho-threshold-scale-note";
    note.hidden = true;

    row.append(inputs, bar);
    block.append(row, legend, note);
    block._scaleRefs = {
      mediumInput: mediumField.input,
      highInput: highField.input,
      segGreen,
      segYellow,
      segRed,
      legGreen,
      legYellow,
      legRed,
      note,
    };

    const schedule = () => this.updateThresholdScale(block);

    for (const input of [mediumField.input, highField.input]) {
      input.addEventListener("input", schedule);
      input.addEventListener("change", schedule);
    }

    if (container.classList.contains("js-threshold-group")) {
      const existingMedium = container.querySelector(`input[name="${mediumKey}"]`);
      const existingHigh = container.querySelector(`input[name="${highKey}"]`);

      if (existingMedium) {
        existingMedium.closest("li, .form-field, label, .horlist-item, tr, .ho-threshold-group-fields > *")
          ?.classList?.add("ho-threshold-native-input-hidden");
        existingMedium.classList.add("ho-threshold-native-input-hidden");
        existingMedium.tabIndex = -1;
        existingMedium.setAttribute("aria-hidden", "true");

        const syncFromNative = () => {
          mediumField.input.value = existingMedium.value;
          this.updateThresholdScale(block);
        };

        existingMedium.addEventListener("input", syncFromNative);
        existingMedium.addEventListener("change", syncFromNative);
        mediumField.input.addEventListener("input", () => {
          existingMedium.value = mediumField.input.value;
          existingMedium.dispatchEvent(new Event("input", {bubbles: true}));
        });
        syncFromNative();
      }

      if (existingHigh) {
        existingHigh.classList.add("ho-threshold-native-input-hidden");
        existingHigh.tabIndex = -1;
        existingHigh.setAttribute("aria-hidden", "true");

        const syncFromNative = () => {
          highField.input.value = existingHigh.value;
          this.updateThresholdScale(block);
        };

        existingHigh.addEventListener("input", syncFromNative);
        existingHigh.addEventListener("change", syncFromNative);
        highField.input.addEventListener("input", () => {
          existingHigh.value = highField.input.value;
          existingHigh.dispatchEvent(new Event("input", {bubbles: true}));
        });
        syncFromNative();
      }
    }

    this.updateThresholdScale(block);

    return block;
  }

  updateThresholdScale(block) {
    const refs = block._scaleRefs;

    if (!refs) {
      return;
    }

    const highKey = block.dataset.thresholdHigh ?? "";
    const mediumKey = block.dataset.thresholdMedium ?? "";
    const scope = block.dataset.thresholdScope ?? "host";
    let medium = Number.parseInt(String(refs.mediumInput.value ?? "").trim(), 10);
    let high = Number.parseInt(String(refs.highInput.value ?? "").trim(), 10);
    const colors = this.readThresholdColors();

    if (!Number.isFinite(medium) || refs.mediumInput.value === "") {
      const global = this.readGlobalThresholdPair(highKey, mediumKey);

      medium = global.medium;
      refs.mediumInput.placeholder = String(global.medium);
    }

    if (!Number.isFinite(high) || refs.highInput.value === "") {
      const global = this.readGlobalThresholdPair(highKey, mediumKey);

      high = global.high;
      refs.highInput.placeholder = String(global.high);
    }

    medium = Math.max(0, Math.min(100, medium));
    high = Math.max(0, Math.min(100, high));

    const invalid = high <= medium;

    if (invalid && scope === "host") {
      const global = this.readGlobalThresholdPair(highKey, mediumKey);

      medium = global.medium;
      high = global.high;
    }

    refs.note.hidden = !invalid || scope === "global";
    refs.note.textContent = this.th("invalid_order", "Red threshold must be greater than yellow.");
    block.classList.toggle("is-invalid", invalid && scope === "global");

    refs.segGreen.style.width = `${medium}%`;
    refs.segYellow.style.width = `${Math.max(0, high - medium)}%`;
    refs.segRed.style.width = `${Math.max(0, 100 - high)}%`;
    refs.segGreen.style.backgroundColor = colors.green;
    refs.segYellow.style.backgroundColor = colors.yellow;
    refs.segRed.style.backgroundColor = colors.red;
    refs.legGreen.style.setProperty("--ho-zone-color", colors.green);
    refs.legYellow.style.setProperty("--ho-zone-color", colors.yellow);
    refs.legRed.style.setProperty("--ho-zone-color", colors.red);
    refs.legGreen.textContent = this.thFmt("legend_green", medium);
    refs.legYellow.textContent = this.thFmt("legend_yellow", medium, high);
    refs.legRed.textContent = this.thFmt("legend_red", high);
  }

  thFmt(key, ...parts) {
    let s = this.th(key, "");

    for (const p of parts) {
      s = s.replace("%s", String(p));
    }

    return s;
  }

  initMetricLookupAssistants() {
    if (!this.metricLookupAction) {
      return;
    }

    document.querySelectorAll(".js-item-match-assistant:not([data-ho-assistant-bound])").forEach((assistant) => {
      assistant.setAttribute("data-ho-assistant-bound", "1");
      this.bindMetricAssistant(assistant);
    });
  }

  bindMetricAssistant(assistant) {
    const contextRoot = assistant.closest(".js-per-host-block");
    const metricList = contextRoot?.querySelector(".js-hp-metrics-show")
      || document.getElementById("metrics_show");
    const fieldName = assistant.dataset.fieldName ?? "";
    const metricValue = assistant.dataset.metricValue ?? "";
    const mode = assistant.dataset.lookupMode ?? "single";
    const metricType = assistant.dataset.metricType ?? "";
    const excludeFieldName = assistant.dataset.excludeFieldName ?? "";
    const input = this.resolveAssistantInput(assistant, fieldName);
    let excludeInput = null;

    if (excludeFieldName !== "") {
      const ctx = assistant.closest(".js-per-host-block");

      excludeInput = ctx?.querySelector(`[data-override-key="${excludeFieldName}"]`)
        || document.querySelector(`input[name="${excludeFieldName}"]`);
    }
    const relatedInputs = this.getMetricLookupRelatedInputs(metricType, contextRoot);
    const button = assistant.querySelector(".js-item-match-test");
    const preview = assistant.querySelector(".js-item-match-preview");
    const metricToggle = metricList?.querySelector(
      `input[type="checkbox"][value="${metricValue}"]`
    ) ?? null;
    const state = {abortController: null, updateEnabled: null};

    if (!fieldName || !input || !button || !preview) {
      return;
    }

    const updateEnabled = () => {
      const enabled = !input.disabled && (metricToggle ? metricToggle.checked : true);

      button.disabled = !enabled;

      if (!enabled) {
        this.abortMetricLookupRequest(state);
        this.hideMetricLookupPreview(preview);
      }
    };
    state.updateEnabled = updateEnabled;

    button.addEventListener("click", () => {
      this.lookupMetricMatch({input, excludeInput, button, preview, state, mode, metricType});
    });

    const markPreviewStale = () => {
      this.abortMetricLookupRequest(state);

      if (!preview.hidden) {
        this.renderMetricLookupNotice(
          preview,
          "muted",
          this.getMetricLookupStaleText(mode)
        );
      }
    };

    input.addEventListener("input", markPreviewStale);
    excludeInput?.addEventListener("input", markPreviewStale);
    relatedInputs.forEach(({element, eventName}) => {
      element.addEventListener(eventName, markPreviewStale);
    });

    input.addEventListener("keydown", (event) => {
      if (event.key === "Enter" && !button.disabled) {
        event.preventDefault();
        button.click();
      }
    });

    if (metricToggle) {
      metricToggle.addEventListener("change", updateEnabled);
    }

    updateEnabled();
  }

  resolveAssistantInput(assistant, fieldName) {
    const scoped = assistant.querySelector(".item-match-controls input[type=\"text\"]")
      || assistant.querySelector(".item-match-controls textarea")
      || assistant.querySelector(".item-match-controls input[name]");

    if (scoped) {
      return scoped;
    }

    return document.querySelector(`input[name="${fieldName}"]`)
      || document.querySelector(`textarea[name="${fieldName}"]`);
  }

  getMetricLookupRelatedInputs(metricType, contextRoot = null) {
    if (metricType !== "interface") {
      return [];
    }

    const root = contextRoot || document;
    const related = [];
    const interfaceHigh = root.querySelector('[data-override-key="interfaces_high"]')
      || document.querySelector('input[name="interfaces_high"]');

    if (interfaceHigh) {
      related.push({element: interfaceHigh, eventName: "input"});
    }

    const unitRadios = root.querySelectorAll('input[type="radio"][name^="hp_iu_"]');

    if (unitRadios.length > 0) {
      unitRadios.forEach((radio) => {
        related.push({element: radio, eventName: "change"});
      });
    }
    else {
      document.querySelectorAll('input[name="interfaces_unit"]').forEach((radio) => {
        related.push({element: radio, eventName: "change"});
      });
    }

    return related;
  }

  getMetricLookupStaleText(mode) {
    return mode === "wildcard"
      ? this.lu("stale_wildcard", "")
      : this.lu("stale_single", "");
  }

  getMetricLookupEmptyText(mode, metricType) {
    if (mode !== "wildcard") {
      return this.lu("wildcard_empty_single", "");
    }

    switch (metricType) {
      case "disk":
        return this.lu("wildcard_empty_disk", "");
      case "partition":
        return this.lu("wildcard_empty_partition", "");
      case "interface":
        return this.lu("wildcard_empty_interface", "");
      default:
        return this.lu("wildcard_empty_default", "");
    }
  }

  async lookupMetricMatch({input, excludeInput, button, preview, state, mode = "single", metricType = ""}) {
    const hostid = this.getMetricLookupHostId(input);
    const search = input.value.trim();
    const exclude = excludeInput?.value.trim() ?? "";

    if (!hostid) {
      this.renderMetricLookupNotice(preview, "warning", this.lu("pick_host", ""));
      return;
    }

    if (search === "") {
      this.renderMetricLookupNotice(preview, "warning", this.getMetricLookupEmptyText(mode, metricType));
      return;
    }

    this.abortMetricLookupRequest(state);
    this.renderMetricLookupNotice(preview, "muted", this.lu("checking", ""));

    const curl = new Curl("zabbix.php");
    curl.setArgument("action", this.metricLookupAction);

    const abortController = new AbortController();
    state.abortController = abortController;
    button.disabled = true;

    try {
      const requestBody = {hostid, search, mode};

      if (mode === "wildcard") {
        requestBody.metric_type = metricType;
        requestBody.exclude = exclude;

        if (metricType === "interface") {
          const ctx = input?.closest(".js-per-host-block");
          const hi = ctx?.querySelector('[data-override-key="interfaces_high"]')?.value?.trim();

          requestBody.interfaces_high = hi !== undefined && hi !== ""
            ? hi
            : this.getNamedInputValue("interfaces_high");
          const unit = ctx?.querySelector('input[type="radio"][name^="hp_iu_"]:checked')?.value;

          requestBody.interfaces_unit = unit !== undefined && unit !== ""
            ? unit
            : this.getCheckedRadioValue("interfaces_unit", ctx ?? undefined);
        }
      }

      const response = await fetch(curl.getUrl(), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify(requestBody),
        signal: abortController.signal,
      });
      const result = await this.parseMetricLookupResponse(response);

      if ("error" in result) {
        const messages = Array.isArray(result.error?.messages)
          ? result.error.messages.filter(Boolean)
          : [];

        throw new Error(messages[0] ?? this.lu("lookup_failed", ""));
      }

      this.renderMetricLookupResult(preview, input, result, {mode, metricType});
    }
    catch (error) {
      if (error?.name === "AbortError") {
        return;
      }

      // Lookup failed; preview already shows a user-visible message.
      this.renderMetricLookupNotice(
        preview,
        "error",
        error instanceof Error && error.message
          ? error.message
          : this.lu("lookup_failed", "")
      );
    }
    finally {
      if (state.abortController === abortController) {
        state.abortController = null;
        if (typeof state.updateEnabled === "function") {
          state.updateEnabled();
        }
        else {
          button.disabled = input.disabled;
        }
      }
    }
  }

  async parseMetricLookupResponse(response) {
    const raw = await response.text();

    if (raw === "") {
      throw new Error(this.lu("lookup_empty_response", ""));
    }

    try {
      return JSON.parse(raw);
    }
    catch (error) {
      const contentType = response.headers.get("content-type") ?? "";

      if (typeof window !== 'undefined' && window.ZBX_DEBUG_WIDGETS) {
        console.warn('Unexpected metric lookup response', {
          status: response.status,
          contentType,
          body: raw.slice(0, 200),
        });
      }

      if (contentType.includes("text/html") || this.looksLikeHtmlDocument(raw)) {
        throw new Error(this.lu("lookup_html_error", ""));
      }

      throw new Error(this.lu("read_response_error", ""));
    }
  }

  looksLikeHtmlDocument(text) {
    return /^\s*<!DOCTYPE html/i.test(text) || /^\s*<html[\s>]/i.test(text);
  }

  renderMetricLookupResult(preview, input, result, options = {}) {
    const mode = typeof result?.mode === "string" ? result.mode : (options.mode ?? "single");

    if (mode === "wildcard") {
      this.renderWildcardMetricLookupResult(preview, result, options.metricType ?? "");
      return;
    }

    const status = typeof result?.status === "string" ? result.status : "none";
    const matchName = typeof result?.match?.name === "string" ? result.match.name : "";
    const candidateCount = Number.parseInt(result?.candidate_count ?? 0, 10) || 0;
    const candidates = Array.isArray(result?.candidates)
      ? result.candidates.filter((candidate) => typeof candidate?.name === "string" && candidate.name !== "")
      : [];
    const hasMoreCandidates = Boolean(result?.has_more_candidates);
    const fragment = document.createDocumentFragment();
    const summary = document.createElement("div");

    summary.className = "item-match-preview-text";

    switch (status) {
      case "exact":
        summary.textContent = this.luFmt("exact_fmt", matchName);
        fragment.append(summary);
        this.showMetricLookupPreview(preview, "success", fragment);
        return;

      case "unique_partial":
        summary.textContent = this.luFmt("unique_partial_fmt", matchName);
        fragment.append(summary);
        fragment.append(this.createMetricCandidateList([{name: matchName}], input, preview));
        this.showMetricLookupPreview(preview, "success", fragment);
        return;

      case "ambiguous":
        summary.textContent = this.luFmt("ambiguous_fmt", String(candidateCount));
        fragment.append(summary);
        fragment.append(this.createMetricCandidateList(candidates, input, preview, hasMoreCandidates));
        this.showMetricLookupPreview(preview, "warning", fragment);
        return;

      case "none":
        if (candidateCount > 0) {
          summary.textContent = this.lu("none_partial", "");
          fragment.append(summary);
          fragment.append(this.createMetricCandidateList(candidates, input, preview, hasMoreCandidates));
          this.showMetricLookupPreview(preview, "warning", fragment);
          return;
        }

        summary.textContent = this.lu("none_no_items", "");
        fragment.append(summary);
        this.showMetricLookupPreview(preview, "error", fragment);
        return;

      default:
        summary.textContent = this.lu("enter_name", "");
        fragment.append(summary);
        this.showMetricLookupPreview(preview, "muted", fragment);
    }
  }

  wildcardNoMatchMessage(metricType) {
    switch (metricType) {
      case "disk":
        return this.lu("wildcard_no_disk", "");
      case "partition":
        return this.lu("wildcard_no_partition", "");
      case "interface":
        return this.lu("wildcard_no_interface", "");
      default:
        return this.lu("wildcard_no_default", "");
    }
  }

  renderWildcardMetricLookupResult(preview, result, fallbackMetricType = "") {
    const metricType = typeof result?.metric_type === "string" && result.metric_type !== ""
      ? result.metric_type
      : fallbackMetricType;
    const status = typeof result?.status === "string" ? result.status : "none";
    const rowCount = Number.parseInt(result?.row_count ?? 0, 10) || 0;
    const rows = Array.isArray(result?.rows)
      ? result.rows.filter((row) => typeof row?.name === "string" && row.name !== "")
      : [];
    const excludedRows = Array.isArray(result?.excluded_rows)
      ? result.excluded_rows.filter((row) => typeof row?.name === "string" && row.name !== "")
      : [];
    const hasMoreRows = Boolean(result?.has_more_rows);
    const hasMoreExcludedRows = Boolean(result?.has_more_excluded_rows);
    const fragment = document.createDocumentFragment();
    const matchesTitle = this.luFmt("matches_heading_fmt", String(rowCount));
    const filteredTitle = this.lu("filtered_heading", "");

    switch (status) {
      case "matches":
        fragment.append(this.createMetricPreviewSection(matchesTitle, rows, {
          hasMoreRows,
        }));

        if (excludedRows.length > 0) {
          fragment.append(this.createMetricPreviewSection(filteredTitle, excludedRows, {
            hasMoreRows: hasMoreExcludedRows,
            filtered: true,
          }));
        }

        this.showMetricLookupPreview(preview, "success", fragment);
        return;

      case "none":
        if (excludedRows.length > 0) {
          fragment.append(this.createMetricPreviewSection(filteredTitle, excludedRows, {
            hasMoreRows: hasMoreExcludedRows,
            filtered: true,
          }));
          this.showMetricLookupPreview(preview, "warning", fragment);
          return;
        }

        this.renderMetricLookupNotice(preview, "error", this.wildcardNoMatchMessage(metricType));
        return;

      case "invalid_pattern":
        this.renderMetricLookupNotice(
          preview,
          "warning",
          metricType === "interface"
            ? this.lu("wildcard_invalid_iface", "")
            : this.lu("wildcard_invalid_other", "")
        );
        return;

      case "too_broad":
        this.renderMetricLookupNotice(
          preview,
          "warning",
          this.lu("wildcard_too_broad", "")
        );
        return;

      case "empty":
        this.renderMetricLookupNotice(
          preview,
          "muted",
          this.getMetricLookupEmptyText("wildcard", metricType)
        );
        return;

      default:
        this.renderMetricLookupNotice(preview, "error", this.wildcardNoMatchMessage(metricType));
    }
  }

  createMetricPreviewSection(title, rows, options = {}) {
    const section = document.createElement("div");
    const heading = document.createElement("div");

    section.className = "item-match-preview-section";
    heading.className = "item-match-preview-heading";
    heading.textContent = title;
    section.append(heading);
    section.append(this.createMetricPreviewRowList(rows, Boolean(options.hasMoreRows), Boolean(options.filtered)));

    return section;
  }

  createMetricCandidateList(candidates, input, preview, hasMoreCandidates = false) {
    const container = document.createElement("div");

    container.className = "item-match-preview-actions";

    candidates.forEach((candidate) => {
      container.append(this.createMetricApplyButton(candidate.name, input, preview));
    });

    if (hasMoreCandidates) {
      const note = document.createElement("div");

      note.className = "item-match-preview-note";
      note.textContent = this.lu("refine_candidates", "");
      container.append(note);
    }

    return container;
  }

  createMetricPreviewRowList(rows, hasMoreRows = false, filtered = false) {
    const container = document.createElement("div");

    container.className = "item-match-preview-list";

    rows.forEach((row) => {
      container.append(this.createMetricPreviewRow(row, filtered));
    });

    if (hasMoreRows) {
      container.append(this.createMetricPreviewNote(this.lu("refine_rows", "")));
    }

    return container;
  }

  createMetricPreviewRow(row, filtered = false) {
    const container = document.createElement("div");
    const main = document.createElement("div");
    const matchName = typeof row?.match_name === "string" ? row.match_name : "";
    const itemName = typeof row?.item_name === "string" ? row.item_name : "";
    const isExcluded = filtered || Boolean(row?.excluded);
    const primaryText = itemName !== ""
      ? itemName
      : (matchName !== "" ? matchName : row.name);

    container.className = "item-match-preview-row";
    if (isExcluded) {
      container.dataset.filtered = "true";
    }

    main.className = "item-match-preview-main";
    main.append(this.createMetricPreviewTextNode(primaryText, isExcluded));

    if (!isExcluded && row.name !== "" && primaryText !== row.name) {
      main.append(this.createMetricPreviewArrowIcon());
      main.append(this.createMetricPreviewTextNode(row.name, isExcluded));
    }

    container.append(main);

    return container;
  }

  createMetricPreviewArrowIcon() {
    const ns = "http://www.w3.org/2000/svg";
    const svg = document.createElementNS(ns, "svg");
    const pathHead = document.createElementNS(ns, "path");
    const pathLine = document.createElementNS(ns, "path");

    svg.classList.add("item-match-preview-arrow");
    svg.setAttribute("xmlns", ns);
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("fill", "none");
    svg.setAttribute("stroke", "currentColor");
    svg.setAttribute("stroke-width", "2");
    svg.setAttribute("stroke-linecap", "round");
    svg.setAttribute("stroke-linejoin", "round");
    svg.setAttribute("aria-hidden", "true");
    svg.setAttribute("focusable", "false");

    pathHead.setAttribute("d", "M18 8L22 12L18 16");
    pathLine.setAttribute("d", "M2 12H22");
    svg.append(pathHead, pathLine);

    return svg;
  }

  createMetricPreviewTextNode(text, strike = false) {
    if (!strike) {
      return document.createTextNode(text);
    }

    const element = document.createElement("s");

    element.textContent = text;

    return element;
  }

  createMetricPreviewNote(text) {
    const note = document.createElement("div");

    note.className = "item-match-preview-note";
    note.textContent = text;

    return note;
  }

  createMetricApplyButton(name, input, preview) {
    const button = document.createElement("button");

    button.type = "button";
    button.className = "btn-link item-match-apply";
    button.textContent = name;
    button.addEventListener("click", () => {
      input.value = name;
      input.dispatchEvent(new Event("input", {bubbles: true}));
      this.renderMetricLookupNotice(preview, "success", this.luFmt("apply_fmt", name));
      input.focus();
    });

    return button;
  }

  getNamedInputValue(name, contextRoot = null) {
    const root = contextRoot || document;
    const input = root.querySelector(`input[name="${name}"]`);

    return input?.value ?? "";
  }

  getCheckedRadioValue(name, contextRoot = null) {
    const root = contextRoot || document;
    const input = root.querySelector(`input[name="${name}"]:checked`);

    return input?.value ?? "";
  }

  /**
   * Zabbix formats multiselect DOM id with zbx_formatDomId(): "hostid[]" -> "hostid__".
   */
  resolveHostMultiselectRoot() {
    return document.getElementById("hostid__")
      || document.getElementById("hostid")
      || document.querySelector("div.multiselect[id^=\"hostid\"]");
  }

  getSelectedHostId() {
    const hostField = this.resolveHostMultiselectRoot();

    if (!hostField) {
      return "";
    }

    for (const selector of [
      'input[name="hostid"]',
      'input[name="hostid[]"]',
      'input[type="hidden"][name^="hostid"]',
    ]) {
      const input = hostField.querySelector(selector);

      if (input?.value) {
        return input.value;
      }
    }

    return "";
  }

  abortMetricLookupRequest(state) {
    if (state.abortController !== null) {
      state.abortController.abort();
      state.abortController = null;
    }
  }

  hideMetricLookupPreview(preview) {
    preview.hidden = true;
    delete preview.dataset.state;
    preview.replaceChildren();
  }

  renderMetricLookupNotice(preview, state, text) {
    const fragment = document.createDocumentFragment();
    const message = document.createElement("div");

    message.className = "item-match-preview-text";
    message.textContent = text;
    fragment.append(message);

    this.showMetricLookupPreview(preview, state, fragment);
  }

  showMetricLookupPreview(preview, state, content) {
    preview.hidden = false;
    preview.dataset.state = state;
    preview.replaceChildren(content);
  }

  getMetricLookupHostId(input) {
    const block = input?.closest(".js-per-host-block");

    if (block?.dataset?.hostid) {
      return String(block.dataset.hostid).trim();
    }

    return this.getSelectedHostId();
  }

  initPerHostAccordionEditor() {
    this._captureProfilesFromHidden();

    const mount = document.getElementById("js-host-accordion-mount");
    const profilesInput = document.querySelector('[name="host_profiles"]');
    const hostRoot = this.resolveHostMultiselectRoot();

    if (!mount || !profilesInput) {
      return;
    }

    this._perHostMount = mount;
    this._perHostProfilesInput = profilesInput;
    this._perHostHostRoot = hostRoot;
    this._hostLabelCache = new Map();

    const scheduleProfilesSync = () => {
      if (this._hostProfilesSyncTimer) {
        clearTimeout(this._hostProfilesSyncTimer);
      }

      this._hostProfilesSyncTimer = setTimeout(() => this.writePerHostProfilesToHidden(), 200);
    };

    const scheduleHostListRebuild = () => {
      if (this._hostProfilesSyncTimer) {
        clearTimeout(this._hostProfilesSyncTimer);
        this._hostProfilesSyncTimer = null;
      }

      if (this._hostAccordionRefreshTimer) {
        clearTimeout(this._hostAccordionRefreshTimer);
      }

      this._hostLabelCache.clear();
      this._hostAccordionRefreshTimer = setTimeout(() => this.refreshHostAccordion(), 80);
    };

    mount.addEventListener("input", scheduleProfilesSync);
    mount.addEventListener("change", scheduleProfilesSync);

    if (hostRoot) {
      hostRoot.addEventListener("change", scheduleHostListRebuild);

      try {
        const mo = new MutationObserver(scheduleHostListRebuild);

        mo.observe(hostRoot, {childList: true, subtree: true});
        this._perHostMutationObserver = mo;
      }
      catch (_e) {
        // ignore
      }
    }

    const form = document.querySelector("#widget-dialogue-form") || document.querySelector("form");

    if (form && !form.dataset.hoPerhostSync) {
      form.dataset.hoPerhostSync = "1";
      form.addEventListener("submit", () => this.writePerHostProfilesToHidden(), true);
    }

    try {
      const overlay = overlays_stack.getById("widget_properties");

      if (overlay?.$dialogue?.[0]) {
        const releasePerHostResources = () => {
          if (this._hostAccordionRefreshTimer) {
            clearTimeout(this._hostAccordionRefreshTimer);
            this._hostAccordionRefreshTimer = null;
          }

          if (this._hostProfilesSyncTimer) {
            clearTimeout(this._hostProfilesSyncTimer);
            this._hostProfilesSyncTimer = null;
          }

          if (this._perHostMutationObserver) {
            this._perHostMutationObserver.disconnect();
            this._perHostMutationObserver = null;
          }
        };

        overlay.$dialogue[0].addEventListener("overlay.reload", () => {
          releasePerHostResources();
          this._captureProfilesFromHidden();
          this._hostLabelCache?.clear();
          this._perHostHostRoot = this.resolveHostMultiselectRoot();

          if (this._perHostHostRoot) {
            this._perHostHostRoot.addEventListener("change", scheduleHostListRebuild);

            try {
              const mo = new MutationObserver(scheduleHostListRebuild);

              mo.observe(this._perHostHostRoot, {childList: true, subtree: true});
              this._perHostMutationObserver = mo;
            }
            catch (_e) {
              // ignore
            }
          }

          scheduleHostListRebuild();
          this.initGlobalThresholdScales();
        });
        overlay.$dialogue[0].addEventListener("overlay.close", releasePerHostResources);
      }
    }
    catch (_err) {
      // overlays_stack may be unavailable outside the dashboard overlay.
    }

    const refreshBtn = document.querySelector(".js-ho-refresh-host-panels");

    if (refreshBtn && !refreshBtn.dataset.hoBound) {
      refreshBtn.dataset.hoBound = "1";
      refreshBtn.addEventListener("click", () => {
        this.refreshHostAccordion();
      });
    }

    this.refreshHostAccordion();
  }

  _captureProfilesFromHidden() {
    this._profilesByHostId = new Map();
    const raw = document.querySelector('[name="host_profiles"]')?.value;
    let profiles = [];

    try {
      profiles = JSON.parse(raw || "[]");
    }
    catch (_e) {
      profiles = [];
    }

    if (!Array.isArray(profiles)) {
      profiles = [];
    }

    for (const p of profiles) {
      if (p && p.hostid) {
        this._profilesByHostId.set(String(p.hostid), p);
      }
    }
  }

  refreshHostAccordion() {
    const mount = this._perHostMount;
    const hostRoot = this._perHostHostRoot || this.resolveHostMultiselectRoot();
    const profilesInput = this._perHostProfilesInput;

    if (!mount || !profilesInput) {
      return;
    }

    this.writePerHostProfilesToHidden();
    this._captureProfilesFromHidden();

    const ordered = hostRoot ? this.collectOrderedHostIds(hostRoot) : [];
    const L = this.perHostLabels;

    mount.replaceChildren();

    if (ordered.length === 0) {
      const empty = document.createElement("div");

      empty.className = "a-overview-per-host-empty";
      empty.textContent = L.empty ?? "";
      mount.appendChild(empty);
      profilesInput.value = "[]";

      return;
    }

    for (const hostid of ordered) {
      const existing = this._profilesByHostId.get(String(hostid));
      const profile = existing && typeof existing === "object"
        ? existing
        : {hostid: String(hostid), alias: "", badges_placement: 0, overrides: {}};

      mount.appendChild(this.createPerHostAccordion(String(hostid), profile, L));
    }

    this.initMetricLookupAssistants();
    this.initScopedPerHostDependencies();
    this.syncGlobalHiddenMetricsFromFirstHost();
    this.writePerHostProfilesToHidden();
    this.refreshAllThresholdScales();
  }

  defaultMetricSelection() {
    return ["0", "1", "2", "3", "4", "5", "6"];
  }

  readHiddenGlobalMetricsSelection() {
    const hidden = document.querySelector(".a-overview-hidden-metrics");

    if (!hidden) {
      return null;
    }

    const boxes = hidden.querySelectorAll('input[type="checkbox"][name="metrics_show[]"]');

    if (!boxes.length) {
      return null;
    }

    const vals = [...hidden.querySelectorAll('input[type="checkbox"][name="metrics_show[]"]:checked')]
      .map((c) => c.value);

    return vals.length ? vals : null;
  }

  buildMetricsShowSection(hostid, profile) {
    const ov = profile.overrides && typeof profile.overrides === "object" ? profile.overrides : {};
    let selected = Array.isArray(ov.metrics_show) ? ov.metrics_show.map(String) : null;

    if (!selected || selected.length === 0) {
      selected = this.readHiddenGlobalMetricsSelection() ?? this.defaultMetricSelection();
    }

    const wrap = document.createElement("div");

    wrap.className = "js-hp-metrics-show";

    const rows = this.metricCheckboxRows.length > 0
      ? this.metricCheckboxRows.map((r) => [String(r.value ?? ""), String(r.label ?? "")])
      : [
        ["0", "Processor"],
        ["1", "Memory"],
        ["2", "Load"],
        ["3", "Swap"],
        ["4", "Interfaces"],
        ["5", "Disk util."],
        ["6", "Partitions"],
      ];

    for (const [val, lab] of rows) {
      if (val === "") {
        continue;
      }

      const labEl = document.createElement("label");
      const cb = document.createElement("input");

      cb.type = "checkbox";
      cb.value = val;
      cb.checked = selected.includes(val);
      labEl.append(cb, document.createTextNode(` ${lab}`));
      wrap.appendChild(labEl);
    }

    return wrap;
  }

  syncGlobalHiddenMetricsFromFirstHost() {
    const hidden = document.querySelector(".a-overview-hidden-metrics");
    const first = document.querySelector(".js-per-host-block .js-hp-metrics-show");

    if (!hidden || !first) {
      return;
    }

    const targetBoxes = hidden.querySelectorAll('input[type="checkbox"][name="metrics_show[]"]');
    const src = [...first.querySelectorAll('input[type="checkbox"]')];

    if (!targetBoxes.length || !src.length) {
      return;
    }

    for (const tb of targetBoxes) {
      const match = src.find((s) => s.value === tb.value);

      if (match) {
        tb.checked = !!match.checked;
      }
    }
  }

  initScopedPerHostDependencies() {
    for (const block of document.querySelectorAll(".js-per-host-block")) {
      this.initScopedPerHostDependenciesForBlock(block);
    }
  }

  initScopedPerHostDependenciesForBlock(block) {
    const mlist = block.querySelector(".js-hp-metrics-show");
    const hostid = block.dataset?.hostid ?? "";

    if (!mlist || !hostid) {
      return;
    }

    const setDisabled = (selector, enabled) => {
      const el = block.querySelector(selector);

      if (el) {
        el.disabled = !enabled;
      }
    };

    const wireMetric = (opt, fn) => {
      const cb = mlist.querySelector(`input[type="checkbox"][value="${opt}"]`);

      if (!cb) {
        return;
      }

      const run = () => fn(cb.checked);

      cb.addEventListener("change", run);
      run();
    };

    wireMetric("4", (on) => {
      setDisabled('[data-override-key="interfaces_high"]', on);
      block.querySelectorAll(`input[name="hp_iu_${hostid}"]`).forEach((r) => {
        r.disabled = !on;
      });
    });

    wireMetric("2", (on) => setDisabled('[data-override-key="load_high"]', on));

    const wireText = (opt, key) => {
      wireMetric(opt, (on) => setDisabled(`[data-override-key="${key}"]`, on));
    };

    wireText("0", "item_name_cpu");
    wireText("0", "th_cpu_1");
    wireText("0", "th_cpu_2");
    wireText("1", "item_name_ram");
    wireText("1", "th_ram_1");
    wireText("1", "th_ram_2");
    wireText("2", "item_name_load");
    wireText("2", "th_load_1");
    wireText("2", "th_load_2");
    wireText("3", "item_name_swap");
    wireText("3", "th_swap_1");
    wireText("3", "th_swap_2");
    wireMetric("3", (on) => {
      const inv = block.querySelector('[data-override-key="item_swap_invert"]');

      if (inv) {
        inv.disabled = !on;
      }
    });
    wireText("4", "th_iface_1");
    wireText("4", "th_iface_2");
    wireText("4", "item_name_interface");
    wireText("4", "interfaces_exclude");
    wireText("5", "item_name_disk");
    wireText("5", "th_disk_1");
    wireText("5", "th_disk_2");
    wireText("5", "disks_exclude");
    wireText("6", "item_name_partition");
    wireText("6", "th_partition_1");
    wireText("6", "th_partition_2");
    wireText("6", "partitions_exclude");
  }

  createPerHostAccordion(hostid, profile, L) {
    const root = document.createElement("div");

    root.className = "a-overview-phost js-per-host-block";
    root.dataset.hostid = String(hostid);

    const head = document.createElement("button");

    head.type = "button";
    head.className = "a-overview-phost-head";
    head.setAttribute("aria-expanded", "false");

    const arrow = document.createElement("span");

    arrow.className = "a-overview-phost-arrow";
    arrow.textContent = "\u25B6";

    const title = document.createElement("span");

    title.className = "a-overview-phost-title";
    title.textContent = this.getHostAccordionTitle(hostid, profile);

    head.append(arrow, title);

    const body = document.createElement("div");

    body.className = "a-overview-phost-body";

    body.setAttribute("hidden", "hidden");
    head.addEventListener("click", () => {
      const hidden = body.hasAttribute("hidden");

      if (hidden) {
        body.removeAttribute("hidden");
        head.setAttribute("aria-expanded", "true");
        arrow.textContent = "\u25BC";
      }
      else {
        body.setAttribute("hidden", "hidden");
        head.setAttribute("aria-expanded", "false");
        arrow.textContent = "\u25B6";
      }
    });

    body.appendChild(this.buildPerHostSection(
      L.section_metrics ?? "Metrics",
      this.buildMetricsShowSection(hostid, profile),
      false,
      "section_metrics"
    ));
    body.appendChild(this.buildPerHostSection(
      L.section_display ?? "Display",
      this.buildDisplayBadges(hostid, profile),
      true,
      "section_display"
    ));
    body.appendChild(this.buildPerHostSection(
      L.section_proc ?? "CPU, memory, load",
      this.buildProcessorMemoryLoad(hostid, profile),
      false,
      "section_proc"
    ));
    body.appendChild(this.buildPerHostSection(
      L.section_swap ?? "Swap",
      this.buildSwapSection(hostid, profile),
      false,
      "section_swap"
    ));
    body.appendChild(this.buildPerHostSection(
      L.section_if ?? "Interfaces",
      this.buildInterfacesSection(hostid, profile),
      false,
      "section_if"
    ));
    body.appendChild(this.buildPerHostSection(
      L.section_disk ?? "Disks",
      this.buildDiskSection(hostid, profile),
      false,
      "section_disk"
    ));
    body.appendChild(this.buildPerHostSection(
      L.section_part ?? "Partitions",
      this.buildPartitionsSection(hostid, profile),
      false,
      "section_part"
    ));

    root.append(head, body);

    return root;
  }

  getHostMultiselectJQuery() {
    const wrapper = this.resolveHostMultiselectRoot();

    if (!wrapper || typeof jQuery === "undefined") {
      return null;
    }

    const inner = wrapper.querySelector('[data-field-type="multiselect"]');
    const $el = inner ? jQuery(inner) : jQuery(wrapper);

    return $el.data("multiSelect") ? $el : null;
  }

  resolveHostLabel(hostid) {
    const id = String(hostid ?? "").trim();

    if (id === "") {
      return "";
    }

    if (this._hostLabelCache?.has(id)) {
      return this._hostLabelCache.get(id);
    }

    const $ms = this.getHostMultiselectJQuery();

    if ($ms && typeof $ms.multiSelect === "function") {
      try {
        const data = $ms.multiSelect("getData") || [];
        const found = data.find((item) => String(item.id) === id);

        if (found?.name) {
          const label = `${found.prefix ?? ""}${found.name}`.trim();

          this._hostLabelCache.set(id, label);

          return label;
        }
      }
      catch (_e) {
        // ignore
      }
    }

    const hostRoot = this.resolveHostMultiselectRoot();

    if (hostRoot) {
      for (const li of hostRoot.querySelectorAll("li[data-id]")) {
        if (String(li.dataset.id).trim() === id) {
          const fromData = String(li.dataset.label ?? "").trim();

          if (fromData !== "") {
            this._hostLabelCache.set(id, fromData);

            return fromData;
          }

          const span = li.querySelector(".subfilter-enabled span[title]");

          if (span?.title) {
            const label = String(span.title).trim();

            this._hostLabelCache.set(id, label);

            return label;
          }

          break;
        }
      }

      for (const inp of hostRoot.querySelectorAll('input[type="hidden"][name="hostid[]"]')) {
        if (String(inp.value).trim() === id) {
          const name = inp.getAttribute("data-name");

          if (name) {
            const prefix = inp.getAttribute("data-prefix") || "";
            const label = `${prefix}${name}`.trim();

            this._hostLabelCache.set(id, label);

            return label;
          }

          break;
        }
      }
    }

    return "";
  }

  getHostAccordionTitle(hostid, profile) {
    const alias = typeof profile.alias === "string" ? profile.alias.trim() : "";
    const zbx_name = this.resolveHostLabel(hostid);

    if (alias !== "") {
      return zbx_name !== "" ? `${alias} (${zbx_name})` : alias;
    }

    return zbx_name !== "" ? zbx_name : String(hostid);
  }

  updatePerHostAccordionTitle(block, hostid) {
    const title = block?.querySelector(".a-overview-phost-title");
    const aliasInput = block?.querySelector('[data-host-meta="alias"]');

    if (!title || !aliasInput) {
      return;
    }

    title.textContent = this.getHostAccordionTitle(hostid, {alias: aliasInput.value});
  }

  buildPerHostSection(titleText, inner, expanded = false, hintKey = "") {
    const wrap = document.createElement("div");

    wrap.className = "a-overview-phost-section";

    const h = document.createElement("button");

    h.type = "button";
    h.className = "a-overview-phost-section-head";

    const ar = document.createElement("span");

    ar.className = "a-overview-phost-arrow";
    ar.textContent = expanded ? "\u25BC" : "\u25B6";

    const t = document.createElement("span");

    t.className = "a-overview-phost-section-title";
    t.textContent = titleText;

    const hint = this.hu(hintKey);

    if (hint) {
      h.appendChild(this.makeFieldHelp(hint));
    }

    const b = document.createElement("div");

    b.className = "a-overview-phost-section-body";

    if (!expanded) {
      b.setAttribute("hidden", "hidden");
    }

    h.append(ar, t);

    h.addEventListener("click", () => {
      const hidden = b.hasAttribute("hidden");

      if (hidden) {
        b.removeAttribute("hidden");
        ar.textContent = "\u25BC";
      }
      else {
        b.setAttribute("hidden", "hidden");
        ar.textContent = "\u25B6";
      }
    });

    b.appendChild(inner);
    wrap.append(h, b);

    return wrap;
  }

  buildDisplayBadges(hostid, profile) {
    const frag = document.createDocumentFragment();
    const aliasInput = this.makeTextInput({value: profile.alias ?? "", dataHostMeta: "alias"});

    aliasInput.addEventListener("input", () => {
      const block = aliasInput.closest(".js-per-host-block");

      if (block) {
        this.updatePerHostAccordionTitle(block, hostid);
      }
    });

    frag.appendChild(this.makeLabeledRow(
      this.perHostLabels.label_alias ?? "",
      aliasInput,
      "label_alias"
    ));

    const bpWrap = document.createElement("div");

    bpWrap.className = "a-overview-phost-row";
    bpWrap.appendChild(this.makeInlineLabel(this.perHostLabels.label_badges ?? "", "label_badges"));

    const bp0 = document.createElement("label");
    const bp1 = document.createElement("label");
    const r0 = document.createElement("input");
    const r1 = document.createElement("input");

    r0.type = "radio";
    r1.type = "radio";
    r0.name = `hp_bp_${hostid}`;
    r1.name = `hp_bp_${hostid}`;
    r0.value = "0";
    r1.value = "1";
    r0.checked = Number(profile.badges_placement ?? 0) !== 1;
    r1.checked = Number(profile.badges_placement ?? 0) === 1;
    bp0.append(r0, document.createTextNode(` ${this.perHostLabels.bp_summary ?? ""}`));
    bp1.append(r1, document.createTextNode(` ${this.perHostLabels.bp_detail ?? ""}`));
    bpWrap.append(bp0, bp1);
    frag.appendChild(bpWrap);

    return frag;
  }

  buildProcessorMemoryLoad(hostid, profile) {
    const frag = document.createDocumentFragment();
    const ov = profile.overrides && typeof profile.overrides === "object" ? profile.overrides : {};

    frag.appendChild(this.makeItemAssistantRow(
      this.perHostLabels.label_cpu ?? "",
      "item_name_cpu",
      "0",
      ov.item_name_cpu ?? "",
      "label_cpu"
    ));
    frag.appendChild(this.makeThresholdScaleBlock(ov, "th_cpu_1", "th_cpu_2", "cpu"));

    frag.appendChild(this.makeItemAssistantRow(
      this.perHostLabels.label_ram ?? "",
      "item_name_ram",
      "1",
      ov.item_name_ram ?? "",
      "label_ram"
    ));
    frag.appendChild(this.makeThresholdScaleBlock(ov, "th_ram_1", "th_ram_2", "ram"));

    frag.appendChild(this.makeItemAssistantRow(
      this.perHostLabels.label_load ?? "",
      "item_name_load",
      "2",
      ov.item_name_load ?? "",
      "label_load"
    ));
    frag.appendChild(this.makeNumberRow(
      this.perHostLabels.label_load_high ?? "",
      "load_high",
      ov.load_high ?? "",
      "label_load_high"
    ));
    frag.appendChild(this.makeThresholdScaleBlock(ov, "th_load_1", "th_load_2", "load"));

    return frag;
  }

  buildSwapSection(hostid, profile) {
    const frag = document.createDocumentFragment();
    const ov = profile.overrides && typeof profile.overrides === "object" ? profile.overrides : {};

    frag.appendChild(this.makeItemAssistantRow(
      this.perHostLabels.label_swap ?? "",
      "item_name_swap",
      "3",
      ov.item_name_swap ?? "",
      "label_swap"
    ));

    const inv = document.createElement("div");

    inv.className = "a-overview-phost-row";
    const cb = document.createElement("input");

    cb.type = "checkbox";
    cb.dataset.overrideKey = "item_swap_invert";
    cb.checked = String(ov.item_swap_invert ?? "1") === "1" || ov.item_swap_invert === 1 || ov.item_swap_invert === true;
    inv.appendChild(this.makeInlineLabel(this.perHostLabels.label_swap_inv ?? "", "label_swap_inv"));
    inv.appendChild(cb);
    frag.appendChild(inv);

    frag.appendChild(this.makeThresholdScaleBlock(ov, "th_swap_1", "th_swap_2", "swap"));

    return frag;
  }

  buildInterfacesSection(hostid, profile) {
    const frag = document.createDocumentFragment();
    const ov = profile.overrides && typeof profile.overrides === "object" ? profile.overrides : {};

    frag.appendChild(this.makePatternAssistantRow(
      this.perHostLabels.label_iface ?? "",
      "item_name_interface",
      "interface",
      "4",
      ov.item_name_interface ?? "",
      "label_iface"
    ));

    frag.appendChild(this.makeTextRow(
      this.perHostLabels.label_iface_ex ?? "",
      "interfaces_exclude",
      ov.interfaces_exclude ?? "",
      "label_iface_ex"
    ));

    frag.appendChild(this.makeNumberRow(
      this.perHostLabels.label_iface_high ?? "",
      "interfaces_high",
      ov.interfaces_high ?? "",
      "label_iface_high"
    ));

    const unit = String(ov.interfaces_unit ?? "");
    const wrap = document.createElement("div");

    wrap.className = "a-overview-phost-row";
    wrap.appendChild(this.makeInlineLabel(this.perHostLabels.label_iface_unit ?? "", "label_iface_unit"));

    for (const [val, lab] of [["0", "Kbps"], ["1", "Mbps"], ["2", "Gbps"]]) {
      const labEl = document.createElement("label");
      const r = document.createElement("input");

      r.type = "radio";
      r.name = `hp_iu_${hostid}`;
      r.value = val;
      r.checked = (unit === "" && val === "2") || unit === val;
      labEl.append(r, document.createTextNode(` ${lab}`));
      wrap.appendChild(labEl);
    }

    frag.appendChild(wrap);
    frag.appendChild(this.makeThresholdScaleBlock(ov, "th_iface_1", "th_iface_2", "iface"));

    return frag;
  }

  buildDiskSection(hostid, profile) {
    const frag = document.createDocumentFragment();
    const ov = profile.overrides && typeof profile.overrides === "object" ? profile.overrides : {};

    frag.appendChild(this.makePatternAssistantRow(
      this.perHostLabels.label_disk ?? "",
      "item_name_disk",
      "disk",
      "5",
      ov.item_name_disk ?? "",
      "label_disk"
    ));
    frag.appendChild(this.makeTextRow(
      this.perHostLabels.label_disk_ex ?? "",
      "disks_exclude",
      ov.disks_exclude ?? "",
      "label_disk_ex"
    ));
    frag.appendChild(this.makeThresholdScaleBlock(ov, "th_disk_1", "th_disk_2", "disk"));

    return frag;
  }

  buildPartitionsSection(hostid, profile) {
    const frag = document.createDocumentFragment();
    const ov = profile.overrides && typeof profile.overrides === "object" ? profile.overrides : {};

    frag.appendChild(this.makePatternAssistantRow(
      this.perHostLabels.label_part ?? "",
      "item_name_partition",
      "partition",
      "6",
      ov.item_name_partition ?? "",
      "label_part"
    ));
    frag.appendChild(this.makeTextRow(
      this.perHostLabels.label_part_ex ?? "",
      "partitions_exclude",
      ov.partitions_exclude ?? "",
      "label_part_ex"
    ));
    frag.appendChild(this.makeThresholdScaleBlock(ov, "th_partition_1", "th_partition_2", "partition"));

    return frag;
  }

  makeLabeledRow(labelText, control, hintKey = "") {
    const row = document.createElement("div");

    row.className = "a-overview-phost-row";
    row.appendChild(this.makeInlineLabel(labelText, hintKey));
    row.appendChild(control);

    return row;
  }

  makeInlineLabel(text, hintKey = "") {
    const wrap = document.createElement("span");

    wrap.className = "a-overview-phost-label-wrap";

    const span = document.createElement("span");

    span.className = "a-overview-phost-label";
    span.textContent = text;
    wrap.appendChild(span);

    const hint = this.hu(hintKey);

    if (hint) {
      wrap.appendChild(this.makeFieldHelp(hint));
    }

    return wrap;
  }

  makeTextInput({value, dataHostMeta}) {
    const input = document.createElement("input");

    input.type = "text";
    input.className = "a-overview-phost-input";
    input.value = value ?? "";

    if (dataHostMeta) {
      input.dataset.hostMeta = dataHostMeta;
    }

    return input;
  }

  makeTextRow(label, key, value, hintKey = "") {
    const input = document.createElement("input");

    input.type = "text";
    input.className = "a-overview-phost-input";
    input.dataset.overrideKey = key;
    input.value = value ?? "";

    return this.makeLabeledRow(label, input, hintKey);
  }

  makeNumberRow(label, key, value, hintKey = "") {
    const input = document.createElement("input");

    input.type = "number";
    input.className = "a-overview-phost-input";
    input.dataset.overrideKey = key;
    input.value = value === undefined || value === null ? "" : String(value);

    return this.makeLabeledRow(label, input, hintKey);
  }

  makeThresholdScaleBlock(ov, highKey, mediumKey, metricKey = "") {
    const wrap = document.createElement("div");

    wrap.className = "ho-threshold-block-wrap js-threshold-block-wrap";
    wrap.dataset.thresholdHigh = highKey;
    wrap.dataset.thresholdMedium = mediumKey;

    if (metricKey !== "") {
      wrap.dataset.thresholdMetric = metricKey;
    }

    const mount = document.createElement("div");

    mount.className = "ho-threshold-scale-mount js-threshold-scale-mount";

    wrap.appendChild(mount);

    const block = this.createThresholdScaleElement(wrap);

    const refs = block._scaleRefs;

    if (refs) {
      if (ov[mediumKey] !== undefined && ov[mediumKey] !== null && String(ov[mediumKey]) !== "") {
        refs.mediumInput.value = String(ov[mediumKey]);
      }

      if (ov[highKey] !== undefined && ov[highKey] !== null && String(ov[highKey]) !== "") {
        refs.highInput.value = String(ov[highKey]);
      }

      this.updateThresholdScale(block);
    }

    return wrap;
  }

  makeItemAssistantRow(label, fieldName, metricValue, value, hintKey = "") {
    return this.makeMetricAssistantRow({
      label,
      fieldName,
      metricValue,
      value,
      mode: "single",
      hintKey,
    });
  }

  makePatternAssistantRow(label, fieldName, metricType, metricToggleValue, value, hintKey = "") {
    const excludeByType = {
      partition: "partitions_exclude",
      disk: "disks_exclude",
      interface: "interfaces_exclude",
    };

    return this.makeMetricAssistantRow({
      label,
      fieldName,
      metricValue: String(metricToggleValue ?? ""),
      value,
      mode: "wildcard",
      metricType,
      excludeFieldName: excludeByType[metricType] ?? "",
      hintKey,
    });
  }

  makeMetricAssistantRow({
    label,
    fieldName,
    metricValue,
    value,
    mode = "single",
    metricType = "",
    excludeFieldName = "",
    hintKey = "",
  }) {
    const row = document.createElement("div");

    row.className = "a-overview-phost-row a-overview-phost-row--item";
    row.appendChild(this.makeInlineLabel(label, hintKey));

    const assistant = document.createElement("div");

    assistant.className = "item-match-assistant item-match-assistant--inline js-item-match-assistant";
    assistant.dataset.fieldName = fieldName;
    assistant.dataset.metricValue = String(metricValue ?? "");
    assistant.dataset.lookupMode = mode;

    if (metricType !== "") {
      assistant.dataset.metricType = metricType;
    }

    if (excludeFieldName !== "") {
      assistant.dataset.excludeFieldName = excludeFieldName;
    }

    const controls = document.createElement("div");

    controls.className = "item-match-controls";

    const input = document.createElement("input");

    input.type = "text";
    input.className = "a-overview-phost-input";
    input.dataset.overrideKey = fieldName;
    input.value = value ?? "";
    input.placeholder = mode === "wildcard" ? "Шаблон с *" : "Имя элемента";

    const btn = document.createElement("button");

    btn.type = "button";
    btn.className = "btn-alt js-item-match-test";
    btn.textContent = this.lu("test", "Тест");

    const preview = document.createElement("div");

    preview.className = "item-match-preview js-item-match-preview";
    preview.hidden = true;

    controls.append(input, btn);
    assistant.append(controls, preview);
    row.appendChild(assistant);

    return row;
  }

  writePerHostProfilesToHidden() {
    const profilesInput = this._perHostProfilesInput || document.querySelector('[name="host_profiles"]');
    const hostRoot = this._perHostHostRoot || this.resolveHostMultiselectRoot();

    if (!profilesInput || !hostRoot) {
      return;
    }

    const ordered = this.collectOrderedHostIds(hostRoot);
    const blocks = [...document.querySelectorAll(".js-per-host-block")];
    const profiles = ordered.map((hid) => {
      const block = blocks.find((n) => n.dataset.hostid === String(hid));

      if (block) {
        return this.serializePerHostBlock(block, hid);
      }

      const fallback = this._profilesByHostId?.get(String(hid));

      if (fallback && typeof fallback === "object") {
        return fallback;
      }

      return {hostid: String(hid), alias: "", badges_placement: 0, overrides: {}};
    });

    profilesInput.value = JSON.stringify(profiles);
  }

  serializePerHostBlock(block, hostid) {
    const aliasInput = block.querySelector('[data-host-meta="alias"]');
    const alias = aliasInput?.value?.trim() ?? "";
    const bp = block.querySelector(`input[name="hp_bp_${hostid}"]:checked`)?.value ?? "0";
    const overrides = {};
    const mlist = block.querySelector(".js-hp-metrics-show");

    if (mlist) {
      const arr = [...mlist.querySelectorAll('input[type="checkbox"]:checked')].map((c) => c.value);

      if (arr.length) {
        overrides.metrics_show = arr;
      }
    }

    for (const el of block.querySelectorAll("[data-override-key]")) {
      const k = el.getAttribute("data-override-key");

      if (!k) {
        continue;
      }

      if (el.type === "checkbox") {
        overrides[k] = el.checked ? "1" : "0";
      }
      else {
        const v = String(el.value ?? "").trim();

        if (v !== "") {
          overrides[k] = v;
        }
      }
    }

    const iu = block.querySelector(`input[name="hp_iu_${hostid}"]:checked`);

    if (iu) {
      overrides.interfaces_unit = iu.value;
    }

    return {
      hostid: String(hostid),
      alias,
      badges_placement: bp === "1" ? 1 : 0,
      overrides,
    };
  }

  collectOrderedHostIds(hostRoot) {
    if (!hostRoot) {
      return [];
    }

    const collected = [];

    for (const input of hostRoot.querySelectorAll('input[name="hostid[]"]')) {
      if (input.value) {
        collected.push(String(input.value).trim());
      }
    }

    if (collected.length > 0) {
      return [...new Set(collected)];
    }

    for (const selector of ['input[name="hostid"]', 'input[type="hidden"][name^="hostid"]']) {
      const input = hostRoot.querySelector(selector);

      if (input?.value) {
        collected.push(String(input.value).trim());
      }
    }

    return [...new Set(collected)];
  }

  // Badge editor: add, remove, reorder, and keep the hidden JSON in sync.
  initBadgesTable() {
    const jsonInput = document.getElementById('badges-json');
    const container = jsonInput ? jsonInput.closest('fieldset') : null;
    const addButtons = container ? [...container.querySelectorAll('.js-badge-add')] : [];

    if (!container || addButtons.length === 0 || !jsonInput) {
      return;
    }

    const leftLaneRows = container.querySelector('.js-badge-lane-rows[data-side="left"]');
    const rightLaneRows = container.querySelector('.js-badge-lane-rows[data-side="right"]');
    const badgeRowTemplate = container.querySelector('#badge-row-template');

    if (!leftLaneRows || !rightLaneRows) {
      return;
    }

    let draggingRow = null;
    const badgeTypeOptions = [...this.badgeTypeOptions];
    const badgeTypeLabels = new Map(
      badgeTypeOptions.map(({value, label}) => [String(value), label])
    );
    const multipleBadgeTypes = new Set(this.badgeMultipleTypes);
    const badgeTypesWithText = new Set(this.badgeTypesWithText);
    const badgeTypesWithUrl = new Set(this.badgeTypesWithUrl);
    const defaultType = badgeTypeOptions.find(({value}) => String(value) === '4')?.value
      ?? badgeTypeOptions[0]?.value
      ?? '0';
    const defaultLabel = badgeTypeLabels.get(String(defaultType)) ?? badgeTypeLabels.get("0") ?? "";
    const allowsMultiple = (type) => multipleBadgeTypes.has(String(type));
    const showsTextInput = (type) => badgeTypesWithText.has(String(type));
    const showsUrlInput = (type) => badgeTypesWithUrl.has(String(type));
    const parseColor = (value) => {
      const match = String(value).match(/^rgba?\(([^)]+)\)$/i);

      if (!match) {
        return null;
      }

      const parts = match[1].split(',').map((part) => part.trim());
      const [r = 0, g = 0, b = 0] = parts
        .slice(0, 3)
        .map((part) => Math.max(0, Math.min(255, parseInt(part, 10) || 0)));
      const alpha = parts[3] !== undefined ? Math.max(0, Math.min(1, parseFloat(parts[3]) || 0)) : 1;

      return {r, g, b, alpha};
    };
    const withAlpha = (value, alpha, fallback) => {
      const rgb = parseColor(value);

      if (!rgb) {
        return fallback;
      }

      return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
    };
    const getBadgeTypeLabel = (type) => (
      badgeTypeLabels.get(String(type)) ?? defaultLabel
    );
    const getUsedSingleTypes = () => {
      const usedSingleTypes = new Map();

      container.querySelectorAll('.badge-row').forEach((row) => {
        const type = row.dataset.type ?? defaultType;

        if (!allowsMultiple(type)) {
          usedSingleTypes.set(type, (usedSingleTypes.get(type) ?? 0) + 1);
        }
      });

      return usedSingleTypes;
    };
    const getMenuOptions = () => {
      const usedSingleTypes = getUsedSingleTypes();

      return badgeTypeOptions.filter(({value}) => allowsMultiple(value) || !usedSingleTypes.has(String(value)));
    };

    const refreshAddButtons = () => {
      const hasOptions = getMenuOptions().length > 0;

      addButtons.forEach((button) => {
        button.disabled = !hasOptions;
      });
    };

    const applyBadgeRowType = (row, type) => {
      if (!row) return;

      row.dataset.type = String(type);

      const typeBadge = row.querySelector('.badge-row-type');
      const textInput = row.querySelector('.js-badge-text');
      const urlInput = row.querySelector('.js-badge-url');

      if (typeBadge) typeBadge.textContent = getBadgeTypeLabel(type);
      if (textInput) textInput.style.display = showsTextInput(type) ? '' : 'none';
      if (urlInput) urlInput.style.display = showsUrlInput(type) ? '' : 'none';
    };

    const hydrateBadgeRow = (row, badge = {}) => {
      if (!row) {
        return null;
      }

      const textInput = row.querySelector('.js-badge-text');
      const urlInput = row.querySelector('.js-badge-url');

      if (textInput) {
        textInput.value = badge.text ?? '';
      }
      if (urlInput) {
        urlInput.value = badge.url ?? '';
      }

      applyBadgeRowType(row, badge.type ?? defaultType);

      return row;
    };

    const refreshBadgeTypeMenu = () => {
      refreshAddButtons();
    };
    const serializeBadgeRow = (row, side) => {
      const type = row.dataset.type ?? defaultType;
      const parsedType = Number.parseInt(type, 10);
      const badge = {
        type: Number.isNaN(parsedType) ? Number.parseInt(defaultType, 10) : parsedType,
        text: '',
        url: '',
        side,
      };

      if (showsTextInput(type)) {
        badge.text = row.querySelector('.js-badge-text')?.value ?? '';
      }

      if (showsUrlInput(type)) {
        badge.url = row.querySelector('.js-badge-url')?.value ?? '';
      }

      return badge;
    };

    const syncJson = () => {
      const badges = [];
      [leftLaneRows, rightLaneRows].forEach((lane) => {
        const side = lane.dataset.side || 'left';
        lane.querySelectorAll('.badge-row').forEach((row) => {
          badges.push(serializeBadgeRow(row, side));
        });
      });
      refreshBadgeTypeMenu();
      jsonInput.value = JSON.stringify(badges);
    };

    const getDragAfterRow = (lane, clientY) => {
      const rows = [...lane.querySelectorAll('.badge-row:not(.is-dragging)')];

      return rows.reduce((closest, row) => {
        const rect = row.getBoundingClientRect();
        const offset = clientY - rect.top - rect.height / 2;

        if (offset < 0 && offset > closest.offset) {
          return {offset, element: row};
        }

        return closest;
      }, {offset: Number.NEGATIVE_INFINITY, element: null}).element;
    };

    const createBadgeRow = (initialType = defaultType) => {
      const templateRow = badgeRowTemplate?.content?.firstElementChild;

      if (!templateRow) {
        return null;
      }

      return hydrateBadgeRow(templateRow.cloneNode(true), {
        type: initialType,
        text: '',
        url: '',
      });
    };

    container.addEventListener('dragstart', (e) => {
      const handle = e.target.closest('.js-badge-drag');
      if (!handle) return;

      draggingRow = handle.closest('.badge-row');
      if (!draggingRow) return;

      draggingRow.classList.add('is-dragging');
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', 'badge-row');
      }
    });

    container.addEventListener('dragover', (e) => {
      if (!draggingRow) return;

      const lane = e.target.closest('.js-badge-lane-rows');
      if (!lane) return;

      e.preventDefault();
      const afterRow = getDragAfterRow(lane, e.clientY);
      if (afterRow) {
        lane.insertBefore(draggingRow, afterRow);
      }
      else {
        lane.appendChild(draggingRow);
      }
    });

    container.addEventListener('drop', (e) => {
      if (!draggingRow) return;
      e.preventDefault();
      syncJson();
    });

    container.addEventListener('dragend', () => {
      if (!draggingRow) return;

      draggingRow.classList.remove('is-dragging');
      draggingRow = null;
      syncJson();
    });

    container.addEventListener('click', (e) => {
      const addButton = e.target.closest('.js-badge-add');
      if (addButton) {
        e.preventDefault();
        
        const options = getMenuOptions();
        if (options.length === 0) return;

        const menu_data = [{
          items: options.map(opt => ({
            label: opt.label,
            clickCallback: () => {
              const side = addButton.dataset.side ?? 'left';
              const targetLane = side === 'right' ? rightLaneRows : leftLaneRows;
              const row = createBadgeRow(opt.value ?? defaultType);

              if (row) {
                targetLane.appendChild(row);
                syncJson();
              }
            }
          }))
        }];

        jQuery(addButton).menuPopup(menu_data, new jQuery.Event(e), {
          position: {
            of: addButton,
            my: 'left top',
            at: 'left bottom'
          }
        });
        return;
      }

      const removeButton = e.target.closest('.js-badge-remove');
      if (removeButton) {
        const row = removeButton.closest('.badge-row');
        if (row) {
          row.remove();
          syncJson();
        }
        return;
      }
    });

    // Sync on text and URL changes.
    container.addEventListener('input', (e) => {
      if (e.target.classList.contains('js-badge-text') || e.target.classList.contains('js-badge-url')) {
        syncJson();
      }
    });

    container.querySelectorAll('.badge-row').forEach((row) => {
      hydrateBadgeRow(row, {
        type: row.dataset.type ?? defaultType,
        text: row.querySelector('.js-badge-text')?.value ?? '',
        url: row.querySelector('.js-badge-url')?.value ?? '',
      });
    });
    syncJson();
  }
})();
