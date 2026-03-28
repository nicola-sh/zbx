<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

require_once __DIR__ . '/../includes/container.func.php';

use function Modules\HostOverview\Includes\render_overview_container;
use function Modules\HostOverview\Includes\render_toolbar;
use function Modules\HostOverview\Includes\render_metric_row;
use function Modules\HostOverview\Includes\render_sparkline_overlay;
use function Modules\HostOverview\Includes\build_all_patch_values;
use const Modules\HostOverview\Includes\SIDE_LEFT;
use const Modules\HostOverview\Includes\SIDE_RIGHT;

$view = new CWidgetView($data);

$config = $data['config'] ?? [];
$badges = $data['badges'] ?? [];
$rows = $data['rows'] ?? [];

// Render main container with styles and icon template.
$container = render_overview_container($config);

// Render toolbar from badges.
$left = array_values(array_filter($badges, fn($b) => ($b['side'] ?? SIDE_LEFT) === SIDE_LEFT));
$right = array_values(array_filter($badges, fn($b) => ($b['side'] ?? SIDE_LEFT) === SIDE_RIGHT));
$toolbar = render_toolbar($left, $right);

if ($toolbar !== null) {
    $container->addItem($toolbar);
}

// Render metric rows.
foreach ($rows as $row) {
    $container->addItem(render_metric_row($row));
}

// Output.
$view
    ->addItem(render_sparkline_overlay())
    ->addItem($container)
    ->setVar('config', $config)
    ->setVar('values', build_all_patch_values($badges, $rows))
    ->setVar('layout_signature', $data['layout_signature'] ?? '')
    ->show();
