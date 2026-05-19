<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

header('Content-Type: application/javascript; charset=UTF-8');
?>
(function () {
  'use strict';

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.js-charts-reset-series');

    if (!button) {
      return;
    }

    const defaults = button.getAttribute('data-default-series') || '[]';
    const textarea = document.querySelector('textarea[name="chart_series"]');

    if (textarea) {
      textarea.value = defaults;
      textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
})();
