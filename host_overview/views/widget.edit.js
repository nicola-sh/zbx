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
    const resolveMenuSurface = (element) => {
      let current = element;

      while (current) {
        const styles = getComputedStyle(current);
        const backgroundColor = styles.backgroundColor;
        const color = parseColor(backgroundColor);

        if (
          backgroundColor
          && backgroundColor !== 'rgba(0, 0, 0, 0)'
          && backgroundColor !== 'transparent'
          && color
          && color.alpha >= 1
        ) {
          return {
            backgroundColor,
            color: styles.color || getComputedStyle(document.body).color,
          };
        }

        current = current.parentElement;
      }

      const bodyStyles = getComputedStyle(document.body);
      return {
        backgroundColor: bodyStyles.backgroundColor || 'Canvas',
        color: bodyStyles.color || 'CanvasText',
      };
    };
    const applyMenuTheme = (menu, anchor) => {
      const surface = resolveMenuSurface(anchor);

      menu.style.setProperty('--badge-add-menu-bg', surface.backgroundColor);
      menu.style.setProperty('--badge-add-menu-fg', surface.color);
      menu.style.setProperty('--badge-add-menu-border', withAlpha(surface.color, 0.22, 'rgba(127, 127, 127, 0.35)'));
      menu.style.setProperty('--badge-add-menu-hover', withAlpha(surface.color, 0.1, 'rgba(127, 127, 127, 0.12)'));
      menu.style.setProperty('--badge-add-menu-shadow', 'rgba(0, 0, 0, 0.18)');
    };
    const renderAddMenuOptions = (menu) => {
      const options = getMenuOptions();

      menu.replaceChildren();

      if (options.length === 0) {
        const empty = document.createElement('span');

        empty.className = 'badge-add-empty';
        empty.textContent = 'No badges available';
        menu.appendChild(empty);
        return;
      }

      options.forEach(({value, label}) => {
        const option = document.createElement('button');

        option.type = 'button';
        option.className = 'js-badge-add-option';
        option.dataset.type = String(value);
        option.textContent = label;
        menu.appendChild(option);
      });
    };
    const createAddMenu = () => {
      const menu = document.createElement('div');

      menu.className = 'badge-add-menu js-badge-add-menu';
      menu.hidden = true;
      renderAddMenuOptions(menu);

      return menu;
    };

    addButtons.forEach((addButton) => {
      const wrap = addButton.closest('.badge-add-wrap');

      if (wrap && !wrap.querySelector('.js-badge-add-menu')) {
        wrap.appendChild(createAddMenu());
      }
    });

    const addMenus = [...container.querySelectorAll('.js-badge-add-menu')];
    const closeAddMenus = () => {
      addMenus.forEach((menu) => {
        menu.hidden = true;
      });
      addButtons.forEach((button) => {
        button.setAttribute('aria-expanded', 'false');
      });
    };
    const refreshAddButtons = () => {
      const hasOptions = getMenuOptions().length > 0;

      addButtons.forEach((button) => {
        button.disabled = !hasOptions;
      });
    };
    const toggleAddMenu = (button) => {
      const wrap = button.closest('.badge-add-wrap');
      const menu = wrap ? wrap.querySelector('.js-badge-add-menu') : null;

      if (!menu) {
        return;
      }

      const shouldOpen = menu.hidden;

      closeAddMenus();

      if (shouldOpen) {
        renderAddMenuOptions(menu);
        applyMenuTheme(menu, button);
        menu.hidden = false;
        button.setAttribute('aria-expanded', 'true');
      }
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
      addMenus.forEach((menu) => {
        if (!menu.hidden) {
          renderAddMenuOptions(menu);
        }
      });
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
        toggleAddMenu(addButton);
        return;
      }

      const addOption = e.target.closest('.js-badge-add-option');
      if (addOption) {
        e.preventDefault();
        const wrap = addOption.closest('.badge-add-wrap');
        const side = wrap ? wrap.querySelector('.js-badge-add')?.dataset.side ?? 'left' : 'left';
        const targetLane = side === 'right' ? rightLaneRows : leftLaneRows;
        const row = createBadgeRow(addOption.dataset.type ?? defaultType);

        if (!row) {
          return;
        }

        targetLane.appendChild(row);
        closeAddMenus();
        syncJson();
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

      closeAddMenus();
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

    if (this._badgeMenuDocumentClickHandler) {
      document.removeEventListener('click', this._badgeMenuDocumentClickHandler);
    }

    this._badgeMenuDocumentClickHandler = (e) => {
      if (!container.contains(e.target)) {
        closeAddMenus();
      }
    };

    document.addEventListener('click', this._badgeMenuDocumentClickHandler);
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
