/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

(function () {
  'use strict';

  if (window.__mainChartsEditBound) {
    return;
  }

  window.__mainChartsEditBound = true;

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.js-charts-reset-series');

    if (!button) {
      return;
    }

    const root = button.closest('#widget-dialogue-form, form') || document;
    const defaults = button.getAttribute('data-default-series') || '[]';
    const textarea = root.querySelector('textarea[name="chart_series"]')
      || document.querySelector('textarea[name="chart_series"]');

    if (textarea) {
      textarea.value = defaults;
      textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
})();
