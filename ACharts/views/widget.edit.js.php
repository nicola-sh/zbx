<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 *
 * Zabbix loads this via CWidgetFormView::includeJsFile() from the module views
 * directory. The script body lives in assets/js per module file structure docs.
 */

declare(strict_types=1);

$script = __DIR__ . '/../assets/js/widget.edit.js';

if (!is_readable($script)) {
    throw new RuntimeException('ACharts: cannot read widget.edit.js');
}

readfile($script);
