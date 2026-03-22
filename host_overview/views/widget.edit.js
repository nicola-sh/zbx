/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

window.form = new (class {
  init(options) {
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
    this.initFieldDependencies();
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
      optionValue: "1",
      textBoxName: "item_name_ram",
    });

    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "2",
      textBoxName: "item_name_load",
    });

    this.initTextBoxToggle({
      listId: "metrics_show",
      optionValue: "3",
      textBoxName: "item_name_swap",
    });

    this.initCheckBoxListToggle({
      listId: "metrics_show",
      optionValue: "3",
      checkId: "item_swap_invert",
    });

    this.initBadgesTable();
  }

  // Badges table: add / remove / type-change
  initBadgesTable() {
    const container = document.getElementById('badges-list');
    const addButtons = container ? [...container.querySelectorAll('.js-badge-add')] : [];
    const jsonInput = document.getElementById('badges-json');
    if (!container || addButtons.length === 0 || !jsonInput) return;
    const leftLaneRows = container.querySelector('.js-badge-lane-rows[data-side="left"]');
    const rightLaneRows = container.querySelector('.js-badge-lane-rows[data-side="right"]');
    if (!leftLaneRows || !rightLaneRows) return;
    let draggingRow = null;

    const syncJson = () => {
      const badges = [];
      [leftLaneRows, rightLaneRows].forEach((lane) => {
        const side = lane.dataset.side || 'left';
        lane.querySelectorAll('.badge-row').forEach(row => {
          const type = row.querySelector('.js-badge-type')?.value ?? '0';
          const text = row.querySelector('.js-badge-text')?.value ?? '';
          const url = row.querySelector('.js-badge-url')?.value ?? '';
          const badge = {type: parseInt(type), text, url, side};
          if (badge.type === 0) {
            badge.link = parseInt(row.querySelector('.js-badge-hostname-link')?.value ?? '1');
          }
          if (badge.type === 1) {
            badge.item_name = row.querySelector('.js-badge-item-name')?.value ?? 'System uptime';
          }
          if (badge.type === 3) {
            badge.scope = parseInt(row.querySelector('.js-badge-scope')?.value ?? '0');
          }
          badges.push(badge);
        });
      });
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

    const createBadgeRow = () => {
      const row = document.createElement('div');
      row.className = 'badge-row';
      row.innerHTML = `
        <span class="js-badge-drag" draggable="true" title="Drag to reorder">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-grip-vertical-icon lucide-grip-vertical"><circle cx="9" cy="12" r="1"></circle><circle cx="9" cy="5" r="1"></circle><circle cx="9" cy="19" r="1"></circle><circle cx="15" cy="12" r="1"></circle><circle cx="15" cy="5" r="1"></circle><circle cx="15" cy="19" r="1"></circle></svg>
        </span>
        <select class="js-badge-type">
          <option value="0">Hostname</option>
          <option value="1">Uptime</option>
          <option value="2">Liveliness</option>
          <option value="3">Problems</option>
          <option value="4">Text</option>
          <option value="5">Link</option>
        </select>
        <span class="js-badge-hostname-link-label">...links to..</span>
        <select class="js-badge-hostname-link">
          <option value="0">Nothing</option>
          <option value="1" selected>Latest data</option>
          <option value="2">Problems</option>
        </select>
        <span class="js-badge-scope-label" style="display:none">...with a status of...</span>
        <select class="js-badge-scope" style="display:none">
          <option value="1">Unacknowledged</option>
          <option value="0" selected>Any</option>
        </select>
        <span class="js-badge-item-name-label" style="display:none">...taken from item...</span>
        <input type="text" class="js-badge-item-name" placeholder="Uptime item name" value="System uptime" style="display:none">
        <span class="js-badge-text-label" style="display:none">...with value...</span>
        <input type="text" class="js-badge-text" placeholder="Display text" style="display:none">
        <input type="text" class="js-badge-url" placeholder="URL" style="display:none">
        <button type="button" class="btn-link js-badge-remove">Remove</button>
      `;
      return row;
    };

    addButtons.forEach((addButton) => {
      addButton.addEventListener('click', () => {
        const side = addButton.dataset.side || 'left';
        const targetLane = side === 'right' ? rightLaneRows : leftLaneRows;
        targetLane.appendChild(createBadgeRow());
        syncJson();
      });
    });

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

    // Remove badge row (delegated)
    container.addEventListener('click', (e) => {
      if (e.target.classList.contains('js-badge-remove')) {
        const row = e.target.closest('.badge-row');
        if (row) {
          row.remove();
          syncJson();
        }
      }
    });

    // Type change: show/hide text & url inputs (delegated)
    container.addEventListener('change', (e) => {
      if (e.target.classList.contains('js-badge-type')) {
        const row = e.target.closest('.badge-row');
        if (!row) return;
        const type = parseInt(e.target.value);
        const textInput = row.querySelector('.js-badge-text');
        const urlInput = row.querySelector('.js-badge-url');
        const scopeLabel = row.querySelector('.js-badge-scope-label');
        const scopeSelect = row.querySelector('.js-badge-scope');
        const itemNameLabel = row.querySelector('.js-badge-item-name-label');
        const itemNameInput = row.querySelector('.js-badge-item-name');
        const textLabel = row.querySelector('.js-badge-text-label');
        const hostnameLinkLabel = row.querySelector('.js-badge-hostname-link-label');
        const hostnameLinkSelect = row.querySelector('.js-badge-hostname-link');
        if (hostnameLinkLabel) hostnameLinkLabel.style.display = (type === 0) ? '' : 'none';
        if (hostnameLinkSelect) hostnameLinkSelect.style.display = (type === 0) ? '' : 'none';
        if (scopeLabel) scopeLabel.style.display = (type === 3) ? '' : 'none';
        if (scopeSelect) scopeSelect.style.display = (type === 3) ? '' : 'none';
        if (itemNameLabel) itemNameLabel.style.display = (type === 1) ? '' : 'none';
        if (itemNameInput) itemNameInput.style.display = (type === 1) ? '' : 'none';
        if (textLabel) textLabel.style.display = (type === 4) ? '' : 'none';
        if (textInput) textInput.style.display = (type === 4 || type === 5) ? '' : 'none';
        if (urlInput) urlInput.style.display = (type === 5) ? '' : 'none';
      }
      syncJson();
    });

    // Sync on text/url/scope input changes
    container.addEventListener('input', (e) => {
      if (e.target.classList.contains('js-badge-text') || e.target.classList.contains('js-badge-url') || e.target.classList.contains('js-badge-item-name')) {
        syncJson();
      }
    });
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
