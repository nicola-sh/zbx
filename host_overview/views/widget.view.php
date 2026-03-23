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
$container = (new CDiv())->setId('container')->addClass('host-overview-container');
$enabled = $data['config']['metrics_show'] ?? [];
$bar_height = (int) ($data['config']['bar_height'] ?? WidgetForm::DEFAULT_BAR_HEIGHT);
$corners = (int) ($data['config']['corners'] ?? WidgetForm::CORNERS_ROUNDED);
$problems_pulse = (int) ($data['config']['problems_pulse'] ?? 0);
$label_length = (int) ($data['config']['label_length'] ?? WidgetForm::LABELS_FULL);
$short = $label_length === WidgetForm::LABELS_SHORT;
$label_width = $short ? 50 : 90;

$container->setAttribute('style',
    '--bar-height: ' . $bar_height . 'px; '
    . '--label-width: ' . $label_width . 'px;'
);

if ($corners === WidgetForm::CORNERS_SQUARE) {
    $container->addClass('square-corners');
}

if ($problems_pulse === 1) {
    $container->addClass('problems-pulse-enabled');
}

$metric_hostid = isset($data['config']['hostid'][0]) ? (string) $data['config']['hostid'][0] : null;

$makeLatestDataUrl = static function (?array $item_ref) use ($metric_hostid): ?string {
    if ($metric_hostid === null || $metric_hostid === '') {
        return null;
    }

    $item_name = is_array($item_ref) && array_key_exists('name', $item_ref)
        ? trim((string) $item_ref['name'])
        : '';

    if ($item_name === '') {
        return null;
    }

    return 'zabbix.php?action=latest.view&hostids%5B%5D=' . urlencode($metric_hostid)
        . '&name=' . urlencode($item_name)
        . '&filter_set=1';
};

// --- Helper: create a single-metric bar row ---
$makeBarRow = static function (
    string $label,
    string $metric_key,
    string $fillClass,
    string $textClass,
    ?array $item_ref = null
) use ($makeLatestDataUrl): CDiv {
    $label_link = (new CTag('a', true))
        ->addClass('metric-link')
        ->addClass('metric-label-link')
        ->addClass('js-metric-link')
        ->setAttribute('data-metric-key', $metric_key)
        ->addItem($label);

    if (($latest_data_url = $makeLatestDataUrl($item_ref)) !== null) {
        $label_link->setAttribute('href', $latest_data_url);
    }

    return (new CDiv())
        ->addClass('row')
        ->setAttribute('data-metric-key', $metric_key)
        ->addItem([
        (new CTag('aside', true))->addClass('label')->addItem($label_link),
        (new CDiv())->addClass('data')->addItem([
            (new CDiv())->addClass('bar')->addItem(
                (new CDiv())->addClass('fill ' . $fillClass)
            ),
            (new CTag('span'))->addClass('text ' . $textClass),
        ]),
    ]);
};

// --- Helper: create a multi-row section (disks, partitions, interfaces) ---
$makeMultiRow = static function (
    string $label,
    string $dataClass,
    array $items,
    string $metric_prefix = '',
    bool $useKeys = false
) use ($makeLatestDataUrl): CDiv {
    $row       = (new CDiv())->addClass('row');
    $labelEl   = (new CTag('aside', true))->addClass('label')->addItem($label);
    $data_cell = (new CDiv())->addClass('data ' . $dataClass . ' multi');

    foreach ($items as $key => $item) {
        $item_key = $item['key'] ?? ($useKeys ? $key : ($item['name'] ?? $key));
        $item_label = $item['label'] ?? ($item['name'] ?? $item_key);
        $metric_key = $metric_prefix !== '' ? $metric_prefix . ':' . $item_key : null;
        $textEl = (new CTag('a', true))
            ->addClass('text')
            ->addClass('metric-link')
            ->addClass('metric-value-link')
            ->addClass('js-metric-link');

        if ($metric_key !== null) {
            $textEl->setAttribute('data-metric-key', $metric_key);
        }
        if (($latest_data_url = $makeLatestDataUrl($item['item_ref'] ?? null)) !== null) {
            $textEl->setAttribute('href', $latest_data_url);
        }

        // For interfaces, pre-fill text with name placeholder
        if ($useKeys) {
            $textEl->addItem($item_label . ' -');
        }

        $cell = (new CDiv())
            ->addClass('cell')
            ->setAttribute('data-key', $item_key)
            ->setAttribute('data-label', $item_label)
            ->addItem((new CDiv())->addClass('bar')->addItem((new CDiv())->addClass('fill')))
            ->addItem($textEl);

        if ($metric_key !== null) {
            $cell->setAttribute('data-metric-key', $metric_key);
        }

        $data_cell->addItem($cell);
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

$link_icon = fn() => $makeSvg(['M13 5H19V11', 'M19 5L5 19'], 'badge-trailing-icon');
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

$maintenance_icon = fn() => $makeSvg([
    'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z'
], 'badge-leading-icon');
$menu_icon = fn() => (new CSpan())
    ->addClass('badge-trailing-icon')
    ->addClass('badge-menu-icon')
    ->addClass(defined('ZBX_ICON_MORE') ? ZBX_ICON_MORE : 'zi-more');

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
        $badge_payload = $data['badge_data'][$index] ?? [];

        switch ($type) {
            case CWidgetFieldBadgesList::BADGE_HOSTNAME:
                if ($hostid !== null) {
                    $el = (new CLinkAction([
                        (new CTag('span', true))->addClass('badge-text'),
                        $menu_icon(),
                    ]))
                        ->addClass('badge host-badge')
                        ->setMenuPopup(CMenuPopupHelper::getHost($hostid));
                }
                else {
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

            case CWidgetFieldBadgesList::BADGE_MAINTENANCE:
                $status = (int) ($badge_payload['status'] ?? 0);

                $el = (new CTag('span', true))->addClass('badge maintenance-badge')
                    ->addItem($maintenance_icon())
                    ->addItem(
                        (new CTag('span', true))
                            ->addClass('badge-text')
                            ->addItem($status === 1 ? _('Maintenance') : '')
                    );

                if ($status === 1) {
                    $el->addClass('maintenance-active');
                } else {
                    $el->addClass('is-hidden');
                }
                break;

            case CWidgetFieldBadgesList::BADGE_TAGS:
                $tags = $badge_payload['tags'] ?? [];
                $tag_text = (new CTag('span', true))->addClass('badge-text')->addClass('badge-parts');

                foreach ($tags as $tag_index => $tag) {
                    $tag_str = $tag['tag'];
                    if ($tag['value'] !== '') {
                        $tag_str .= ': ' . $tag['value'];
                    }

                    if ($tag_index > 0) {
                        $tag_text->addItem((new CTag('span', true))->addClass('badge-dot-separator'));
                    }

                    $tag_text->addItem((new CTag('span', true))->addItem($tag_str));
                }

                $el = (new CTag('span', true))->addClass('badge tags-badge')
                    ->addItem($makeSvg([
                        'M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z',
                        'M7 7h.01'
                    ], 'badge-leading-icon'))
                    ->addItem($tag_text);
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
                $url = CWidgetFieldBadgesList::sanitizeLinkUrl($badge['url'] ?? null);
                if ($url !== null) {
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
                }
                else {
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

// Single-metric bars: [metric_id, metric_key, full_label, short_label, fill_class, text_class]
$single_metrics = [
    [WidgetForm::METRIC_CPU,  'cpu',  'Processor', 'CPU',  'cpu',  'cpu-text'],
    [WidgetForm::METRIC_RAM,  'ram',  'Memory',    'RAM',  'ram',  'ram-text'],
    [WidgetForm::METRIC_LOAD, 'load', 'Load',      'Load', 'load', 'load-text'],
    [WidgetForm::METRIC_SWAP, 'swap', 'Swap',      'Swap', 'swap', 'swap-text'],
];

foreach ($single_metrics as [$metric_id, $metric_key, $full_label, $short_label, $fillClass, $textClass]) {
    if (in_array($metric_id, $enabled)) {
        $container->addItem($makeBarRow(
            $short ? $short_label : $full_label,
            $metric_key,
            $fillClass,
            $textClass,
            $data['item_map'][$metric_key] ?? null
        ));
    }
}

// Interfaces
if (in_array(WidgetForm::METRIC_INTERFACES, $enabled)) {
    $container->addItem($makeMultiRow(
        $short ? 'NICs' : 'Interfaces',
        'interfaces-data',
        $data['interfaces'] ?? [],
        'iface',
        true
    ));
}

// Disks
if (in_array(WidgetForm::METRIC_DISKS, $enabled)) {
    $container->addItem($makeMultiRow(
        $short ? 'Disks' : 'Disk util.',
        'disks-data',
        $data['disks'] ?? [],
        'disk'
    ));
}

// Partitions
if (in_array(WidgetForm::METRIC_PARTITIONS, $enabled)) {
    $container->addItem($makeMultiRow(
        $short ? 'Parts' : 'Partitions',
        'partitions-data',
        $data['partitions'] ?? [],
        'partition'
    ));
}

// Sparkline overlay (hidden by default)
$sparkline_backdrop = (new CDiv())
    ->addClass('host-overview-backdrop')
    ->addClass('sparkline-backdrop');

$sparkline_overlay = (new CDiv())
    ->addClass('host-overview-overlay')
    ->addClass('sparkline-overlay');
if ($corners === WidgetForm::CORNERS_SQUARE) {
    $sparkline_overlay->addClass('square-corners');
}

$sparkline_header = (new CDiv())->addClass('sparkline-header');
$sparkline_header->addItem(
    (new CTag('span', true))->addClass('sparkline-title')
);

$sparkline_actions = (new CDiv())->addClass('sparkline-header-actions');
$sparkline_periods = (new CDiv())->addClass('sparkline-periods');
foreach (['1h', '3h', '6h', '12h', '1d', '3d', '1w', '2w'] as $p) {
    $btn = (new CTag('button', true))
        ->setAttribute('type', 'button')
        ->setAttribute('data-period', $p)
        ->addClass('sparkline-control')
        ->addItem($p);
    if ($p === '1h') {
        $btn->addClass('active');
    }
    $sparkline_periods->addItem($btn);
}
$sparkline_actions->addItem($sparkline_periods);
$sparkline_actions->addItem(
    (new CTag('button', true))
        ->setAttribute('type', 'button')
        ->setAttribute('aria-label', _('Close sparkline dialog'))
        ->addClass('sparkline-control')
        ->addClass('sparkline-close')
        ->addClass('js-sparkline-close')
        ->addItem(_('Close'))
);
$sparkline_header->addItem($sparkline_actions);

$sparkline_overlay->addItem($sparkline_header);
$sparkline_overlay->addItem(
    (new CTag('canvas', true))->addClass('sparkline-canvas')
);
$sparkline_labels = (new CDiv())->addClass('sparkline-y-labels');
$sparkline_labels->addItem((new CTag('span', true))->addItem('100%'));
$sparkline_labels->addItem((new CTag('span', true))->addItem('50%'));
$sparkline_labels->addItem((new CTag('span', true))->addItem('0%'));
$sparkline_overlay->addItem($sparkline_labels);

$view
    ->addItem($sparkline_backdrop)
    ->addItem($sparkline_overlay)
    ->addItem($container)
    ->setVar('cpu', $data['cpu'] ?? null)
    ->setVar('ram', $data['ram'] ?? null)
    ->setVar('load', $data['load'] ?? null)
    ->setVar('swap', $data['swap'] ?? null)
    ->setVar('badge_data', $data['badge_data'] ?? [])
    ->setVar('interfaces', $data['interfaces'] ?? [])
    ->setVar('disks', $data['disks'] ?? [])
    ->setVar('partitions', $data['partitions'] ?? [])
    ->setVar('item_map', $data['item_map'] ?? [])
    ->setVar('config', $data['config'])
    ->show();
