<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

$view = new CWidgetView($data);

if (!empty($data['empty'])) {
    $view
        ->addItem(
            (new CDiv())
                ->addClass('main-charts-empty')
                ->addItem(new CSpan($data['message'] ?? 'No data'))
        )
        ->show();

    return;
}

$config = $data['config'] ?? [];
$series = $data['series'] ?? [];
$legend_position = (int) ($config['legend_position'] ?? 0);

$root = (new CDiv())
    ->addClass('main-charts-root')
    ->setAttribute('data-main-charts', '1')
    ->setAttribute('data-layout-signature', (string) ($data['layout_signature'] ?? ''));

$header = (new CDiv())->addClass('main-charts-header');

if (!empty($data['host_name'])) {
    $header->addItem(
        (new CSpan())
            ->addClass('main-charts-host')
            ->addItem($data['host_name'])
    );
}

$period_labels = [
    '1h' => '1h',
    '3h' => '3h',
    '12h' => '12h',
    '1d' => '1d',
    '3d' => '3d',
    '1w' => '1w',
    '30d' => '30d',
];
$period = (string) ($config['period'] ?? '3h');

$header->addItem(
    (new CSpan())
        ->addClass('main-charts-period')
        ->addItem($period_labels[$period] ?? $period)
);

$root->addItem($header);

$missing = array_values(array_filter(
    $series,
    static function (array $entry): bool {
        return ($entry['status'] ?? '') === 'missing';
    }
));

if ($missing !== []) {
    $warnings = (new CDiv())->addClass('main-charts-warnings');

    foreach ($missing as $entry) {
        $warnings->addItem(
            (new CDiv())
                ->addClass('main-charts-warning')
                ->addItem(
                    'Item not found: '.($entry['item_name'] ?? $entry['label'] ?? '')
                )
        );
    }

    $root->addItem($warnings);
}

$legend_class = 'top';

if ($legend_position === 1) {
    $legend_class = 'bottom';
}
elseif ($legend_position === 2) {
    $legend_class = 'hidden';
}

$chart_wrap = (new CDiv())
    ->addClass('main-charts-stage')
    ->addClass('legend-' . $legend_class);

$chart_wrap->addItem(
    (new CDiv())
        ->addClass('main-charts-canvas-wrap')
        ->addItem(
            (new CTag('canvas', true))
                ->addClass('main-charts-canvas')
                ->setAttribute('role', 'img')
                ->setAttribute('aria-label', 'Host metrics chart')
        )
);

$root->addItem($chart_wrap);

$view
    ->addItem($root)
    ->setVar('config_json', json_encode($config, JSON_THROW_ON_ERROR))
    ->show();
