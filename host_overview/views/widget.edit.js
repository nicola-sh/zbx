/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
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
    this.metricLookupAction = typeof options?.item_lookup_action === "string"
      ? options.item_lookup_action
      : "";

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
    this.initFieldDependencies();
    this.initMetricLookupAssistants();
    this.initHostProfilesHostSync();

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
    };

    radios.forEach((radio) => {
      radio.addEventListener("change", update);
    });

    update();
  }

  initFieldDependencies() {
    this.initCheckBoxListToggle({
      listId: "metrics_show",
      optionValue: "4",
      checkId: "interfaces_high",
      radiosContainerId: "interfaces_unit",
    });

    this.initCheckBoxListToggle({
      listId: "metrics_show",
      optionValue: "2",
      checkId: "load_high",
    });

    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "0",
      textBoxName: "item_name_cpu",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "0",
      textBoxName: "th_cpu_1",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "0",
      textBoxName: "th_cpu_2",
    });

    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "1",
      textBoxName: "item_name_ram",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "1",
      textBoxName: "th_ram_1",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "1",
      textBoxName: "th_ram_2",
    });

    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "2",
      textBoxName: "item_name_load",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "2",
      textBoxName: "th_load_1",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "2",
      textBoxName: "th_load_2",
    });

    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "3",
      textBoxName: "item_name_swap",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "3",
      textBoxName: "th_swap_1",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "3",
      textBoxName: "th_swap_2",
    });

    this.initCheckBoxListToggle({
      listId: "metrics_show",
      optionValue: "3",
      checkId: "item_swap_invert",
    });

    this.initBadgesTable();

    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "4",
      textBoxName: "th_iface_1",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "4",
      textBoxName: "th_iface_2",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "5",
      textBoxName: "th_disk_1",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "5",
      textBoxName: "th_disk_2",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "6",
      textBoxName: "th_partition_1",
    });
    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "6",
      textBoxName: "th_partition_2",
    });
  }

  initMetricLookupAssistants() {
    if (!this.metricLookupAction) {
      return;
    }

    const metricList = document.getElementById("metrics_show");

    document.querySelectorAll(".js-item-match-assistant").forEach((assistant) => {
      const fieldName = assistant.dataset.fieldName ?? "";
      const metricValue = assistant.dataset.metricValue ?? "";
      const mode = assistant.dataset.lookupMode ?? "single";
      const metricType = assistant.dataset.metricType ?? "";
      const excludeFieldName = assistant.dataset.excludeFieldName ?? "";
      const input = document.querySelector(`input[name="${fieldName}"]`);
      const excludeInput = excludeFieldName !== ""
        ? document.querySelector(`input[name="${excludeFieldName}"]`)
        : null;
      const relatedInputs = this.getMetricLookupRelatedInputs(metricType);
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
    });
  }

  getMetricLookupRelatedInputs(metricType) {
    if (metricType !== "interface") {
      return [];
    }

    const related = [];
    const interfaceHigh = document.querySelector('input[name="interfaces_high"]');

    if (interfaceHigh) {
      related.push({element: interfaceHigh, eventName: "input"});
    }

    document.querySelectorAll('input[name="interfaces_unit"]').forEach((radio) => {
      related.push({element: radio, eventName: "change"});
    });

    return related;
  }

  getMetricLookupStaleText(mode) {
    return mode === "wildcard"
      ? "Pattern or excludes changed. Test again to refresh the preview."
      : "Input changed. Test again to refresh the preview.";
  }

  getMetricLookupEmptyText(mode, metricType) {
    if (mode !== "wildcard") {
      return "Enter an item name to preview.";
    }

    const [, plural] = this.getMetricTypeLabels(metricType);

    return `Enter a wildcard pattern to preview matching ${plural}.`;
  }

  async lookupMetricMatch({input, excludeInput, button, preview, state, mode = "single", metricType = ""}) {
    const hostid = this.getMetricLookupHostId(input);
    const search = input.value.trim();
    const exclude = excludeInput?.value.trim() ?? "";

    if (!hostid) {
      this.renderMetricLookupNotice(preview, "warning", "Pick a host first.");
      return;
    }

    if (search === "") {
      this.renderMetricLookupNotice(preview, "warning", this.getMetricLookupEmptyText(mode, metricType));
      return;
    }

    this.abortMetricLookupRequest(state);
    this.renderMetricLookupNotice(preview, "muted", "Checking for matches...");

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
          requestBody.interfaces_high = this.getNamedInputValue("interfaces_high");
          requestBody.interfaces_unit = this.getCheckedRadioValue("interfaces_unit");
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

        throw new Error(messages[0] ?? "Could not check item matches right now.");
      }

      this.renderMetricLookupResult(preview, input, result, {mode, metricType});
    }
    catch (error) {
      if (error?.name === "AbortError") {
        return;
      }

      console.log("Could not check item matches", error);
      this.renderMetricLookupNotice(
        preview,
        "error",
        error instanceof Error && error.message
          ? error.message
          : "Could not check item matches right now."
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
      throw new Error("The lookup endpoint returned an empty response.");
    }

    try {
      return JSON.parse(raw);
    }
    catch (error) {
      const contentType = response.headers.get("content-type") ?? "";

      console.log("Unexpected metric lookup response", {
        status: response.status,
        contentType,
        body: raw.slice(0, 200),
      });

      if (contentType.includes("text/html") || this.looksLikeHtmlDocument(raw)) {
        throw new Error("The lookup endpoint returned an HTML page instead of JSON.");
      }

      throw new Error("Could not read the lookup response.");
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
        summary.textContent = `Exact match: ${matchName}.`;
        fragment.append(summary);
        this.showMetricLookupPreview(preview, "success", fragment);
        return;

      case "unique_partial":
        summary.textContent = `Unique partial match: ${matchName}.`;
        fragment.append(summary);
        fragment.append(this.createMetricCandidateList([{name: matchName}], input, preview));
        this.showMetricLookupPreview(preview, "success", fragment);
        return;

      case "ambiguous":
        summary.textContent = `${candidateCount} matching item names found. Choose one exact name:`;
        fragment.append(summary);
        fragment.append(this.createMetricCandidateList(candidates, input, preview, hasMoreCandidates));
        this.showMetricLookupPreview(preview, "warning", fragment);
        return;

      case "none":
        if (candidateCount > 0) {
          summary.textContent = "No exact or unique partial match yet. Choose an exact item name:";
          fragment.append(summary);
          fragment.append(this.createMetricCandidateList(candidates, input, preview, hasMoreCandidates));
          this.showMetricLookupPreview(preview, "warning", fragment);
          return;
        }

        summary.textContent = "No matching item names found.";
        fragment.append(summary);
        this.showMetricLookupPreview(preview, "error", fragment);
        return;

      default:
        summary.textContent = "Enter an item name to preview.";
        fragment.append(summary);
        this.showMetricLookupPreview(preview, "muted", fragment);
    }
  }

  renderWildcardMetricLookupResult(preview, result, fallbackMetricType = "") {
    const metricType = typeof result?.metric_type === "string" && result.metric_type !== ""
      ? result.metric_type
      : fallbackMetricType;
    const [singular, plural] = this.getMetricTypeLabels(metricType);
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

    switch (status) {
      case "matches":
        fragment.append(this.createMetricPreviewSection(`MATCHES (${rowCount})`, rows, {
          hasMoreRows,
        }));

        if (excludedRows.length > 0) {
          fragment.append(this.createMetricPreviewSection("FILTERED OUT", excludedRows, {
            hasMoreRows: hasMoreExcludedRows,
            filtered: true,
          }));
        }

        this.showMetricLookupPreview(preview, "success", fragment);
        return;

      case "none":
        if (excludedRows.length > 0) {
          fragment.append(this.createMetricPreviewSection("FILTERED OUT", excludedRows, {
            hasMoreRows: hasMoreExcludedRows,
            filtered: true,
          }));
          this.showMetricLookupPreview(preview, "warning", fragment);
          return;
        }

        this.renderMetricLookupNotice(preview, "error", `No matching ${plural} found.`);
        return;

      case "invalid_pattern":
        this.renderMetricLookupNotice(preview, "warning", metricType === "interface"
          ? "Use at least two * wildcards to preview matching interfaces."
          : `Use at least one * wildcard to preview matching ${plural}.`);
        return;

      case "too_broad":
        this.renderMetricLookupNotice(
          preview,
          "warning",
          `Include some fixed text around * so the preview can narrow matching ${plural}.`
        );
        return;

      case "empty":
        this.renderMetricLookupNotice(preview, "muted", `Enter a wildcard pattern to preview matching ${plural}.`);
        return;

      default:
        this.renderMetricLookupNotice(preview, "error", `No matching ${plural} found.`);
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
      note.textContent = "Refine the search to narrow the list.";
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
      container.append(this.createMetricPreviewNote(
        "Only the first few rows are shown. Refine the pattern to narrow the list."
      ));
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
      this.renderMetricLookupNotice(preview, "success", `Exact item name applied: ${name}.`);
      input.focus();
    });

    return button;
  }

  getMetricTypeLabels(metricType) {
    switch (metricType) {
      case "disk":
        return ["disk", "disks"];

      case "partition":
        return ["partition", "partitions"];

      case "interface":
        return ["interface", "interfaces"];

      default:
        return ["row", "rows"];
    }
  }

  getNamedInputValue(name) {
    const input = document.querySelector(`input[name="${name}"]`);

    return input?.value ?? "";
  }

  getCheckedRadioValue(name) {
    const input = document.querySelector(`input[name="${name}"]:checked`);

    return input?.value ?? "";
  }

  getSelectedHostId() {
    const hostField = document.getElementById("hostid");

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
    const slot = input?.closest(".js-host-slot");

    if (slot) {
      const hostRoot = slot.querySelector('[id$="_hostid"]');
      const hid = this.readHostIdFromMultiselectContainer(hostRoot);

      if (hid) {
        return hid;
      }
    }

    return this.getSelectedHostId();
  }

  readHostIdFromMultiselectContainer(hostRoot) {
    if (!hostRoot) {
      return "";
    }

    for (const input of hostRoot.querySelectorAll('input[name="hostid[]"]')) {
      if (input.value) {
        return String(input.value).trim();
      }
    }

    for (const selector of ['input[name="hostid"]', 'input[type="hidden"][name^="hostid"]']) {
      const input = hostRoot.querySelector(selector);

      if (input?.value) {
        return String(input.value).trim();
      }
    }

    return "";
  }

  initHostProfilesHostSync() {
    const hostRoot = document.getElementById("hostid");
    const profilesInput = document.querySelector('[name="host_profiles"]');

    if (!profilesInput) {
      return;
    }

    const run = () => this.syncHostProfilesCombined(hostRoot, profilesInput);

    document.querySelectorAll(".js-host-slot").forEach((slot) => {
      slot.addEventListener("change", run);
      slot.addEventListener("input", run);
    });

    if (hostRoot) {
      hostRoot.addEventListener("change", run);
    }

    try {
      const overlay = overlays_stack.getById("widget_properties");

      if (overlay?.$dialogue?.[0]) {
        overlay.$dialogue[0].addEventListener("overlay.reload", () => {
          run();
          this.inflateMultiHostSlotsFromJson(profilesInput);
        });
      }
    }
    catch (_err) {
      // overlays_stack may be unavailable outside the dashboard overlay.
    }

    this.inflateMultiHostSlotsFromJson(profilesInput);
    run();
  }

  inflateMultiHostSlotsFromJson(profilesInput) {
    if (!profilesInput?.value) {
      return;
    }

    let profiles = [];

    try {
      profiles = JSON.parse(profilesInput.value || "[]");
    }
    catch (_err) {
      return;
    }

    if (!Array.isArray(profiles)) {
      return;
    }

    const rows = [...document.querySelectorAll(".js-host-slot")];

    for (let i = 0; i < rows.length; i++) {
      const slot = rows[i];
      const p = profiles[i];

      if (!p || !p.hostid) {
        continue;
      }

      const aliasInput = slot.querySelector('input[name*="display_alias"], textarea[name*="display_alias"]');

      if (aliasInput && typeof p.alias === "string") {
        aliasInput.value = p.alias;
      }

      const bp = Number.parseInt(p.badges_placement ?? 0, 10) || 0;

      slot.querySelectorAll('input[name*="badges_placement"]').forEach((radio) => {
        radio.checked = String(radio.value) === String(bp);
      });

      const ov = slot.querySelector('input[name*="overrides"], textarea[name*="overrides"]');

      if (ov) {
        ov.value = typeof p.overrides === "object" ? JSON.stringify(p.overrides ?? {}) : String(p.overrides ?? "{}");
      }
    }
  }

  syncHostProfilesCombined(hostRoot, profilesInput) {
    const slotProfiles = [];

    const rows = [...document.querySelectorAll(".js-host-slot")];

    for (const slot of rows) {
      const hostField = slot.querySelector('[id$="_hostid"]');
      const hid = this.readHostIdFromMultiselectContainer(hostField);

      if (!hid) {
        continue;
      }

      const aliasInput = slot.querySelector('input[name*="display_alias"], textarea[name*="display_alias"]');
      const alias = aliasInput?.value?.trim() ?? "";
      const bpRadio = slot.querySelector('input[name*="badges_placement"]:checked');
      const badges_placement = Number.parseInt(bpRadio?.value ?? "0", 10) || 0;
      const ovInput = slot.querySelector('input[name*="overrides"], textarea[name*="overrides"]');
      let overrides = {};

      try {
        overrides = JSON.parse(ovInput?.value || "{}");
      }
      catch (_err) {
        overrides = {};
      }

      if (!overrides || typeof overrides !== "object") {
        overrides = {};
      }

      slotProfiles.push({
        hostid: hid,
        alias,
        badges_placement: badges_placement === 1 ? 1 : 0,
        overrides,
      });
    }

    if (slotProfiles.length > 0) {
      profilesInput.value = JSON.stringify(slotProfiles);

      return;
    }

    if (hostRoot) {
      this.syncHostProfilesFieldValue(hostRoot, profilesInput);
    }
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

  syncHostProfilesFieldValue(hostRoot, profilesInput) {
    const ordered = this.collectOrderedHostIds(hostRoot);
    let profiles = [];

    try {
      profiles = JSON.parse(profilesInput.value || "[]");
    }
    catch (_err) {
      profiles = [];
    }

    if (!Array.isArray(profiles)) {
      profiles = [];
    }

    const byHost = {};
    const byMeta = {};

    for (const entry of profiles) {
      if (entry && entry.hostid) {
        const id = String(entry.hostid);

        byHost[id] =
          entry.overrides && typeof entry.overrides === "object" ? entry.overrides : {};
        byMeta[id] = {
          alias: typeof entry.alias === "string" ? entry.alias : "",
          badges_placement: Number.parseInt(entry.badges_placement ?? 0, 10) || 0,
        };
      }
    }

    const next = ordered.map((hostid) => ({
      hostid,
      alias: byMeta[hostid]?.alias ?? "",
      badges_placement: byMeta[hostid]?.badges_placement === 1 ? 1 : 0,
      overrides: byHost[hostid] || {},
    }));

    profilesInput.value = JSON.stringify(next);
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
    const defaultLabel = badgeTypeLabels.get(String(defaultType)) ?? 'Hostname';
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

  // Link a checkbox within a CheckBoxList to dependent fields
  initCheckBoxListToggle({ listId, optionValue, checkId, radiosContainerId }) {
    const container = document.getElementById(listId);
    if (!container) return;

    const show = container.querySelector(`input[type="checkbox"][value="${optionValue}"]`);
    if (!show) return;

    // Find the target element — try by ID first, then by name attribute
    let check = checkId ? document.getElementById(checkId) : null;
    if (!check && checkId) {
      check = document.querySelector(`input[name="${checkId}"]`);
    }

    const radiosContainer = radiosContainerId
      ? document.getElementById(radiosContainerId)
      : null;
    const radios = radiosContainer
      ? radiosContainer.querySelectorAll('input[type="radio"]')
      : null;

    const setRadiosEnabled = (enabled) => {
      if (!radios) return;
      radios.forEach((radio) => {
        radio.disabled = !enabled;
      });
    };

    const update = () => {
      const enabled = !!show.checked;

      if (check) {
        check.disabled = !enabled;
        if (!enabled) {
          if (check.type === 'checkbox') {
            check.checked = false;
          }
        }
      }

      setRadiosEnabled(enabled);
    };

    show.addEventListener("change", update);
    update();
  }

  // Link a checkbox within a CheckBoxList to a text input field
  initTextBoxToggle({ listId, optionValue, textBoxName }) {
    const container = document.getElementById(listId);
    if (!container) return;

    const show = container.querySelector(`input[type="checkbox"][value="${optionValue}"]`);
    if (!show) return;

    const textBox = document.querySelector(`input[name="${textBoxName}"]`);
    if (!textBox) return;

    const update = () => {
      textBox.disabled = !show.checked;
    };

    show.addEventListener("change", update);
    update();
  }
})();
