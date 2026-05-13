<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

require_once __DIR__ . '/components/layout.php';

use function Modules\HostOverview\Includes\render_multi_host_root;
use function Modules\HostOverview\Includes\render_overview_container;
use function Modules\HostOverview\Includes\render_toolbar;
use function Modules\HostOverview\Includes\render_metric_row;
use function Modules\HostOverview\Includes\render_sparkline_overlay;

$view = new CWidgetView($data);

$config = $data['config'] ?? [];
$multi_host = !empty($data['multi_host']);

if ($multi_host) {
    $view
        ->addItem(render_sparkline_overlay())
        ->addItem(render_multi_host_root($data))
        ->setVar('config', $config)
        ->setVar('values', $data['values'] ?? [])
        ->setVar('layout_signature', $data['layout_signature'] ?? '')
        ->setVar('multi_host', true)
        ->show();
}
else {
    $badges = $data['badges'] ?? [];
    $rows = $data['rows'] ?? [];

    $container = render_overview_container($config);
    $toolbar = render_toolbar($badges);

    if ($toolbar !== null) {
        $container->addItem($toolbar);
    }

    foreach ($rows as $row) {
        $container->addItem(render_metric_row($row));
    }

    $view
        ->addItem(render_sparkline_overlay())
        ->addItem($container)
        ->setVar('config', $config)
        ->setVar('values', $data['values'] ?? [])
        ->setVar('layout_signature', $data['layout_signature'] ?? '')
        ->setVar('multi_host', false)
        ->show();
}
