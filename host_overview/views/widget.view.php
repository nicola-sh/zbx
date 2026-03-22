<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

use Modules\HostOverview\Includes\CWidgetFieldBadgesList;
use Modules\HostOverview\Includes\WidgetForm;

$view = new CWidgetView($data);

// Container
$container = (new CDiv())->setId('container');
$enabled = $data['config']['metrics_show'] ?? [];
$bar_height = (int) ($data['config']['bar_height'] ?? WidgetForm::DEFAULT_BAR_HEIGHT);
$corners = (int) ($data['config']['corners'] ?? WidgetForm::CORNERS_ROUNDED);
$problems_pulse = (int) ($data['config']['problems_pulse'] ?? 0);
$label_length = (int) ($data['config']['label_length'] ?? WidgetForm::LABELS_FULL);
$short = $label_length === WidgetForm::LABELS_SHORT;
$label_width = $short ? 50 : 90;
$badge_size = (int) ($data['config']['badge_size'] ?? WidgetForm::BADGES_REGULAR);

// Badge size presets: [padding_y, padding_x, font_size, icon_size, icon_margin]
$badge_presets = [
    WidgetForm::BADGES_TINY    => [4, 8, 9, 9, 4],
    WidgetForm::BADGES_SMALL   => [4, 10, 11, 11, 6],
    WidgetForm::BADGES_REGULAR => [6, 11, 12, 14, 8],
];
[$badge_padding_y, $badge_padding_x, $badge_font_size, $badge_icon_size, $badge_icon_margin]
    = $badge_presets[$badge_size] ?? $badge_presets[WidgetForm::BADGES_REGULAR];

$container->setAttribute('style',
    '--bar-height: ' . $bar_height . 'px; '
    . '--label-width: ' . $label_width . 'px; '
    . '--badge-padding-y: ' . $badge_padding_y . 'px; '
    . '--badge-padding-x: ' . $badge_padding_x . 'px; '
    . '--badge-font-size: ' . $badge_font_size . 'px; '
    . '--badge-icon-size: ' . $badge_icon_size . 'px; '
    . '--badge-icon-margin: ' . $badge_icon_margin . 'px;'
);

if ($corners === WidgetForm::CORNERS_SQUARE) {
    $container->addClass('square-corners');
}

if ($problems_pulse === 1) {
    $container->addClass('problems-pulse-enabled');
}

// --- Helper: create a single-metric bar row ---
$makeBarRow = static function (string $label, string $fillClass, string $textClass): CDiv {
    return (new CDiv())->addClass('row')->addItem([
        (new CTag('aside', true))->addClass('label')->addItem($label),
        (new CDiv())->addClass('data')->addItem([
            (new CDiv())->addClass('bar')->addItem(
                (new CDiv())->addClass('fill ' . $fillClass)
            ),
            (new CTag('span'))->addClass('text ' . $textClass),
        ]),
    ]);
};

// --- Helper: create a multi-row section (disks, partitions, interfaces) ---
$makeMultiRow = static function (string $label, string $dataClass, array $items, bool $useKeys = false): CDiv {
    $row       = (new CDiv())->addClass('row');
    $labelEl   = (new CTag('aside', true))->addClass('label')->addItem($label);
    $data_cell = (new CDiv())->addClass('data ' . $dataClass . ' multi');

    foreach ($items as $key => $item) {
        $name = $useKeys ? $key : ($item['name'] ?? $key);
        $textEl = (new CTag('span'))->addClass('text');

        // For interfaces, pre-fill text with name placeholder
        if ($useKeys) {
            $textEl->addItem($name . ' -');
        }

        $data_cell->addItem(
            (new CDiv())
                ->addClass('cell')
                ->setAttribute('data-key', $name)
                ->addItem((new CDiv())->addClass('bar')->addItem((new CDiv())->addClass('fill')))
                ->addItem($textEl)
        );
    }

    $row->addItem($labelEl)->addItem($data_cell);
    return $row;
};

// --- Helper: create an inline SVG icon ---
$makeSvg = static function (array $paths, string $class = '', array $circles = []): CTag {
    $svg = (new CTag('svg', true))
        ->setAttribute('xmlns', 'http://www.w3.org/2000/svg')
        ->setAttribute('width', '24')
        ->setAttribute('height', '24')
        ->setAttribute('viewBox', '0 0 24 24')
        ->setAttribute('fill', 'none')
        ->setAttribute('stroke', 'currentColor')
        ->setAttribute('stroke-width', '2')
        ->setAttribute('stroke-linecap', 'round')
        ->setAttribute('stroke-linejoin', 'round');

    if ($class !== '') {
        $svg->addClass($class);
    }

    foreach ($paths as $d) {
        $svg->addItem((new CTag('path', true))->setAttribute('d', $d));
    }

    foreach ($circles as [$cx, $cy, $r]) {
        $svg->addItem((new CTag('circle', true))
            ->setAttribute('cx', $cx)
            ->setAttribute('cy', $cy)
            ->setAttribute('r', $r));
    }

    return $svg;
};

$link_icon = fn() => $makeSvg(['M13 5H19V11', 'M19 5L5 19']);
$uptime_icon = fn() => $makeSvg([
    'M12 6v6l1.56.78',
    'M13.227 21.925a10 10 0 1 1 8.767-9.588',
    'm14 18 4-4 4 4',
    'M18 22v-8',
], 'badge-leading-icon');
$freshness_icon = fn() => $makeSvg([
    'M16.247 7.761a6 6 0 0 1 0 8.478',
    'M19.075 4.933a10 10 0 0 1 0 14.134',
    'M4.925 19.067a10 10 0 0 1 0-14.134',
    'M7.753 16.239a6 6 0 0 1 0-8.478',
], 'badge-leading-icon', [['12', '12', '2']]);

// Host info badges — driven by badges config
$badges_raw = $data['config']['badges'] ?? '[]';
$badges = is_string($badges_raw) ? (json_decode($badges_raw, true) ?: []) : [];
$hostid = $data['config']['hostid'][0] ?? null;

if (!empty($badges)) {
    $info_bar = (new CDiv())->addClass('info-bar');
    $left_group = (new CDiv())->addClass('info-bar-group')->addClass('info-bar-group-left');
    $right_group = (new CDiv())->addClass('info-bar-group')->addClass('info-bar-group-right');
    $left_count = 0;
    $right_count = 0;

    foreach ($badges as $index => $badge) {
        $type = (int) ($badge['type'] ?? CWidgetFieldBadgesList::BADGE_HOSTNAME);

        switch ($type) {
            case CWidgetFieldBadgesList::BADGE_HOSTNAME:
                $hostname_link = (int) ($badge['link'] ?? CWidgetFieldBadgesList::HOSTNAME_LINK_LATEST);
                $hostname_href = null;

                if ($hostid !== null) {
                    if ($hostname_link === CWidgetFieldBadgesList::HOSTNAME_LINK_LATEST) {
                        $hostname_href = 'zabbix.php?action=latest.view&hostids%5B%5D=' . urlencode($hostid);
                    }
                    elseif ($hostname_link === CWidgetFieldBadgesList::HOSTNAME_LINK_PROBLEMS) {
                        $hostname_href = 'zabbix.php?action=problem.view&hostids%5B%5D=' . urlencode($hostid);
                    }
                }

                if ($hostname_href !== null) {
                    $el = (new CTag('a', true))
                        ->addClass('badge host-badge')
                        ->setAttribute('href', $hostname_href)
                        ->setAttribute('target', '_blank')
                        ->setAttribute('rel', 'noopener')
                        ->addItem((new CTag('span', true))->addClass('badge-text'))
                        ->addItem($link_icon());
                } else {
                    $el = (new CTag('span', true))
                        ->addClass('badge host-badge')
                        ->addItem((new CTag('span', true))->addClass('badge-text'));
                }
                break;

            case CWidgetFieldBadgesList::BADGE_UPTIME:
                $el = (new CTag('span', true))->addClass('badge uptime-badge')
                    ->addItem($uptime_icon())
                    ->addItem((new CTag('span', true))->addClass('badge-text'));
                break;

            case CWidgetFieldBadgesList::BADGE_LIVELINESS:
                $el = (new CTag('span', true))->addClass('badge freshness-badge')
                    ->addItem($freshness_icon())
                    ->addItem((new CTag('span', true))->addClass('badge-text'));
                break;

            case CWidgetFieldBadgesList::BADGE_PROBLEMS:
                if ($hostid !== null) {
                    $el = (new CTag('a', true))
                        ->addClass('badge problems-badge')
                        ->setAttribute('href', 'zabbix.php?action=problem.view&hostids%5B%5D=' . urlencode($hostid))
                        ->setAttribute('target', '_blank')
                        ->setAttribute('rel', 'noopener')
                        ->addItem((new CTag('span', true))->addClass('badge-text'))
                        ->addItem($link_icon());
                } else {
                    $el = (new CTag('span', true))->addClass('badge problems-badge')
                        ->addItem((new CTag('span', true))->addClass('badge-text'));
                }
                break;

            case CWidgetFieldBadgesList::BADGE_TEXT:
                $el = (new CTag('span', true))->addClass('badge text-badge')
                    ->addItem(
                        (new CTag('span', true))
                            ->addClass('badge-text')
                            ->addItem($badge['text'] ?? '')
                    );
                break;

            case CWidgetFieldBadgesList::BADGE_LINK:
                $url = $badge['url'] ?? '';
                if ($url !== '') {
                    $el = (new CTag('a', true))
                        ->addClass('badge link-badge')
                        ->setAttribute('href', $url)
                        ->setAttribute('target', '_blank')
                        ->setAttribute('rel', 'noopener')
                        ->addItem(
                            (new CTag('span', true))
                                ->addClass('badge-text')
                                ->addItem($badge['text'] ?? $url)
                        )
                        ->addItem($link_icon());
                } else {
                    $el = (new CTag('span', true))->addClass('badge link-badge')
                        ->addItem(
                            (new CTag('span', true))
                                ->addClass('badge-text')
                                ->addItem($badge['text'] ?? '')
                        );
                }
                break;

            default:
                continue 2;
        }

        $el->setAttribute('data-badge-index', $index);
        $side = $badge['side'] ?? CWidgetFieldBadgesList::SIDE_LEFT;

        if ($side === CWidgetFieldBadgesList::SIDE_RIGHT) {
            $right_group->addItem($el);
            $right_count++;
        }
        else {
            $left_group->addItem($el);
            $left_count++;
        }
    }

    if ($left_count > 0) {
        $info_bar->addItem($left_group);
    }

    if ($right_count > 0) {
        $info_bar->addItem($right_group);
    }

    $container->addItem(
        (new CDiv())->addClass('row')->addItem([
            $info_bar,
        ])
    );
}

// Single-metric bars: [metric_id, full_label, short_label, fill_class, text_class]
$single_metrics = [
    [WidgetForm::METRIC_CPU,  'Processor', 'CPU',  'cpu',  'cpu-text'],
    [WidgetForm::METRIC_RAM,  'Memory',    'RAM',  'ram',  'ram-text'],
    [WidgetForm::METRIC_LOAD, 'Load',      'Load', 'load', 'load-text'],
    [WidgetForm::METRIC_SWAP, 'Swap',      'Swap', 'swap', 'swap-text'],
];

foreach ($single_metrics as [$metric_id, $full_label, $short_label, $fillClass, $textClass]) {
    if (in_array($metric_id, $enabled)) {
        $container->addItem($makeBarRow($short ? $short_label : $full_label, $fillClass, $textClass));
    }
}

// Interfaces
if (in_array(WidgetForm::METRIC_INTERFACES, $enabled)) {
    $container->addItem($makeMultiRow(
        $short ? 'NICs' : 'Interfaces',
        'interfaces-data',
        $data['interfaces'] ?? [],
        true
    ));
}

// Disks
if (in_array(WidgetForm::METRIC_DISKS, $enabled)) {
    $container->addItem($makeMultiRow(
        $short ? 'Disks' : 'Disk util.',
        'disks-data',
        $data['disks'] ?? []
    ));
}

// Partitions
if (in_array(WidgetForm::METRIC_PARTITIONS, $enabled)) {
    $container->addItem($makeMultiRow(
        $short ? 'Parts' : 'Partitions',
        'partitions-data',
        $data['partitions'] ?? []
    ));
}

// Sparkline overlay (hidden by default)
$sparkline_backdrop = (new CDiv())->addClass('sparkline-backdrop');

$sparkline_overlay = (new CDiv())->addClass('sparkline-overlay');

$sparkline_header = (new CDiv())->addClass('sparkline-header');
$sparkline_header->addItem(
    (new CTag('span', true))->addClass('sparkline-title')
);

$sparkline_periods = (new CDiv())->addClass('sparkline-periods');
foreach (['1h', '3h', '6h', '12h', '1d', '3d', '1w', '2w'] as $p) {
    $btn = (new CTag('button', true))
        ->setAttribute('data-period', $p)
        ->addItem($p);
    if ($p === '1h') {
        $btn->addClass('active');
    }
    $sparkline_periods->addItem($btn);
}
$sparkline_header->addItem($sparkline_periods);

$sparkline_overlay->addItem($sparkline_header);
$sparkline_overlay->addItem(
    (new CTag('canvas', true))->addClass('sparkline-canvas')
);
$sparkline_labels = (new CDiv())->addClass('sparkline-y-labels');
$sparkline_labels->addItem((new CTag('span', true))->addItem('100%'));
$sparkline_labels->addItem((new CTag('span', true))->addItem('50%'));
$sparkline_labels->addItem((new CTag('span', true))->addItem('0%'));
$sparkline_overlay->addItem($sparkline_labels);

$container->addItem($sparkline_overlay);

$view
    ->addItem($sparkline_backdrop)
    ->addItem($container)
    ->setVar('cpu', $data['cpu'] ?? null)
    ->setVar('ram', $data['ram'] ?? null)
    ->setVar('load_percent', $data['load_percent'] ?? null)
    ->setVar('swap', $data['swap'] ?? null)
    ->setVar('badge_data', $data['badge_data'] ?? [])
    ->setVar('interfaces', $data['interfaces'] ?? [])
    ->setVar('disks', $data['disks'] ?? [])
    ->setVar('partitions', $data['partitions'] ?? [])
    ->setVar('item_map', $data['item_map'] ?? [])
    ->setVar('config', $data['config'])
    ->show();
