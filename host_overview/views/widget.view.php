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
$label_length = (int) ($data['config']['label_length'] ?? WidgetForm::LABELS_FULL);
$short = $label_length === WidgetForm::LABELS_SHORT;
$label_width = $short ? 50 : 90;

$container->setAttribute('style',
    '--bar-height: ' . $bar_height . 'px; '
    . '--label-width: ' . $label_width . 'px;'
);

$link_target = ((int) ($data['config']['open_links_same_window'] ?? 0) === 1) ? '_self' : '_blank';
$applyLinkTargetAttributes = static function ($link) use ($link_target): void {
    $link->setAttribute('target', $link_target);

    if ($link_target === '_blank') {
        $link->setAttribute('rel', 'noopener');
    }
};
$makeLinkAttributes = static function (string $href) use ($link_target): array {
    $attributes = [
        'href' => $href,
        'target' => $link_target,
    ];

    if ($link_target === '_blank') {
        $attributes['rel'] = 'noopener';
    }

    return $attributes;
};

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

$makeMetricCell = static function (
    string $item_key,
    string $item_label,
    ?string $metric_key = null,
    ?array $item_ref = null,
    array $options = []
) use ($makeLatestDataUrl, $applyLinkTargetAttributes): CDiv {
    $text_tag = $options['text_tag'] ?? 'span';
    $prefill_text = array_key_exists('prefill_text', $options) ? $options['prefill_text'] : null;
    $text = (new CTag($text_tag, true))->addClass('metric-text');

    if ($text_tag === 'a') {
        $text
            ->addClass('metric-link')
            ->addClass('metric-value-link')
            ->addClass('js-metric-link');
        $applyLinkTargetAttributes($text);

        if ($metric_key !== null) {
            $text->setAttribute('data-metric-key', $metric_key);
        }

        if (($latest_data_url = $makeLatestDataUrl($item_ref)) !== null) {
            $text->setAttribute('href', $latest_data_url);
        }
    }

    if ($prefill_text !== null && $prefill_text !== '') {
        $text->addItem($prefill_text);
    }

    $cell = (new CDiv())
        ->addClass('metric-cell')
        ->setAttribute('data-key', $item_key)
        ->setAttribute('data-label', $item_label)
        ->addItem(
            (new CDiv())
                ->addClass('metric-bar')
                ->addItem((new CDiv())->addClass('metric-fill'))
        )
        ->addItem($text);

    if ($metric_key !== null) {
        $cell->setAttribute('data-metric-key', $metric_key);
    }

    return $cell;
};

// --- Helper: create a single-metric row ---
$makeBarRow = static function (
    string $label,
    string $metric_key,
    string $metric_label,
    ?array $item_ref = null
) use ($makeLatestDataUrl, $makeMetricCell, $applyLinkTargetAttributes): CDiv {
    $label_link = (new CTag('a', true))
        ->addClass('metric-link')
        ->addClass('metric-label-link')
        ->addClass('js-metric-link')
        ->setAttribute('data-metric-key', $metric_key)
        ->addItem($label);
    $applyLinkTargetAttributes($label_link);

    if (($latest_data_url = $makeLatestDataUrl($item_ref)) !== null) {
        $label_link->setAttribute('href', $latest_data_url);
    }

    return (new CDiv())
        ->addClass('metric-row')
        ->addItem([
        (new CTag('aside', true))->addClass('metric-label')->addItem($label_link),
        (new CDiv())
            ->addClass('metric-list')
            ->addItem($makeMetricCell($metric_key, $metric_label, $metric_key, $item_ref)),
    ]);
};

// --- Helper: create a multi-row section (disks, partitions, interfaces) ---
$makeMultiRow = static function (
    string $label,
    string $dataClass,
    array $items,
    string $metric_prefix = '',
    bool $useKeys = false
) use ($makeMetricCell): CDiv {
    $row       = (new CDiv())->addClass('metric-row');
    $labelEl   = (new CTag('aside', true))->addClass('metric-label')->addItem($label);
    $data_cell = (new CDiv())->addClass('metric-list metric-list-multi ' . $dataClass);

    foreach ($items as $key => $item) {
        $item_key = $item['key'] ?? ($useKeys ? $key : ($item['name'] ?? $key));
        $item_label = $item['label'] ?? ($item['name'] ?? $item_key);
        $metric_key = $metric_prefix !== '' ? $metric_prefix . ':' . $item_key : null;
        $data_cell->addItem($makeMetricCell(
            $item_key,
            $item_label,
            $metric_key,
            $item['item_ref'] ?? null,
            [
                'text_tag' => 'a',
                'prefill_text' => $useKeys ? $item_label . ' -' : null,
            ]
        ));
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

$link_icon = fn() => $makeSvg([
    'M13 5H19V11',
    'M19 5L5 19',
], 'badge-icon');
$tags_icon = fn() => $makeSvg([
    'M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z',
    'M7 7h.01'
], 'badge-icon');
$uptime_icon = fn() => $makeSvg([
    'M12 6v6l1.56.78',
    'M13.227 21.925a10 10 0 1 1 8.767-9.588',
    'm14 18 4-4 4 4',
    'M18 22v-8',
], 'badge-icon');
$freshness_icon = fn() => $makeSvg([
    'M16.247 7.761a6 6 0 0 1 0 8.478',
    'M19.075 4.933a10 10 0 0 1 0 14.134',
    'M4.925 19.067a10 10 0 0 1 0-14.134',
    'M7.753 16.239a6 6 0 0 1 0-8.478',
], 'badge-icon', [['12', '12', '2']]);
$back_icon = fn() => $makeSvg([
    'M15 18l-6-6 6-6',
    'M9 12h10',
], 'badge-icon');

$maintenance_icon = fn() => $makeSvg([
    'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z'
], 'badge-icon');
$toolbar_more_icon = fn() => $makeSvg([], 'badge-icon', [
    ['12', '12', '1'],
    ['19', '12', '1'],
    ['5', '12', '1'],
]);

$makeBadgeText = static function (string $text = '', array $classes = []): CTag {
    $badge_text = (new CTag('span', true))->addClass('badge-text');

    foreach ($classes as $class_name) {
        $badge_text->addClass($class_name);
    }

    if ($text !== '') {
        $badge_text->addItem($text);
    }

    return $badge_text;
};

$makeBadgeElement = static function (
    string $tag_name,
    array $class_names,
    array $items = [],
    array $attributes = []
): CTag {
    $element = new CTag($tag_name, true);

    foreach ($class_names as $class_name) {
        $element->addClass($class_name);
    }

    foreach ($attributes as $name => $value) {
        $element->setAttribute($name, $value);
    }

    if ($items !== []) {
        $element->addItem($items);
    }

    return $element;
};

$makeToolbarLink = static function (
    array $items = [],
    array $class_names = [],
    array $attributes = []
): CLinkAction {
    $link = new CLinkAction($items);

    foreach ($class_names as $class_name) {
        $link->addClass($class_name);
    }

    foreach ($attributes as $name => $value) {
        $link->setAttribute($name, $value);
    }

    return $link;
};

$finalizeBadge = static function ($badge, int $index, bool $is_interactive = false) {
    if ($is_interactive) {
        $badge->addClass('link');
    }

    $badge->setAttribute('data-badge-index', $index);

    return $badge;
};

// Host info badges — driven by badges config
$badges_raw = $data['config']['badges'] ?? '[]';
$badges = is_string($badges_raw) ? (json_decode($badges_raw, true) ?: []) : [];
$hostid = $data['config']['hostid'][0] ?? null;

if (!empty($badges)) {
    $toolbar = (new CDiv())->addClass('toolbar');
    $left_group = (new CDiv())->addClass('left');
    $right_group = (new CDiv())->addClass('right');
    $left_count = 0;
    $right_count = 0;

    foreach ($badges as $index => $badge) {
        $type = (int) ($badge['type'] ?? CWidgetFieldBadgesList::BADGE_HOSTNAME);
        $badge_payload = $data['badge_data'][$index] ?? [];
        $is_interactive = false;

        switch ($type) {
            case CWidgetFieldBadgesList::BADGE_HOSTNAME:
                if ($hostid !== null) {
                    $el = $makeToolbarLink(
                        [
                            $makeBadgeText(),
                            $toolbar_more_icon(),
                        ],
                        ['badge', 'host-badge']
                    )
                        ->setMenuPopup(CMenuPopupHelper::getHost($hostid));
                    $is_interactive = true;
                }
                else {
                    $el = $makeBadgeElement('span', ['badge', 'host-badge'], [
                        $makeBadgeText(),
                    ]);
                }
                break;

            case CWidgetFieldBadgesList::BADGE_UPTIME:
                $el = $makeBadgeElement('span', ['badge', 'uptime-badge'], [
                    $uptime_icon(),
                    $makeBadgeText(),
                ]);
                break;

            case CWidgetFieldBadgesList::BADGE_LIVELINESS:
                $el = $makeBadgeElement('span', ['badge', 'freshness-badge'], [
                    $freshness_icon(),
                    $makeBadgeText(),
                ]);
                break;

            case CWidgetFieldBadgesList::BADGE_MAINTENANCE:
                $status = (int) ($badge_payload['status'] ?? 0);

                $el = $makeBadgeElement('span', ['badge', 'maintenance-badge'], [
                    $maintenance_icon(),
                    $makeBadgeText($status === 1 ? _('Maintenance') : ''),
                ]);

                if ($status === 1) {
                    $el->addClass('maintenance');
                } else {
                    $el->setAttribute('hidden', 'hidden');
                }
                break;

            case CWidgetFieldBadgesList::BADGE_TAGS:
                $tags = $badge_payload['tags'] ?? [];
                $tag_parts = [];

                foreach ($tags as $tag) {
                    $tag_str = $tag['tag'];
                    if ($tag['value'] !== '') {
                        $tag_str .= ': ' . $tag['value'];
                    }

                    $tag_parts[] = $tag_str;
                }

                $el = $makeBadgeElement('span', ['badge', 'tags-badge'], [
                    $tags_icon(),
                    $makeBadgeText(implode(' • ', $tag_parts)),
                ]);
                break;

            case CWidgetFieldBadgesList::BADGE_PROBLEMS:
                if ($hostid !== null) {
                    $el = $makeBadgeElement('a', ['badge', 'problems-badge'], [
                        $makeBadgeText(),
                        $link_icon(),
                    ], $makeLinkAttributes(
                        'zabbix.php?action=problem.view&hostids%5B%5D=' . urlencode($hostid)
                    ));
                    $is_interactive = true;
                } else {
                    $el = $makeBadgeElement('span', ['badge', 'problems-badge'], [
                        $makeBadgeText(),
                    ]);
                }
                break;

            case CWidgetFieldBadgesList::BADGE_TEXT:
                $el = $makeBadgeElement('span', ['badge', 'text-badge'], [
                    $makeBadgeText($badge['text'] ?? ''),
                ]);
                break;

            case CWidgetFieldBadgesList::BADGE_LINK:
                $url = CWidgetFieldBadgesList::sanitizeLinkUrl($badge['url'] ?? null);
                if ($url !== null) {
                    $el = $makeBadgeElement('a', ['badge', 'link-badge'], [
                        $makeBadgeText($badge['text'] ?? $url),
                        $link_icon(),
                    ], $makeLinkAttributes($url));
                    $is_interactive = true;
                }
                else {
                    $el = $makeBadgeElement('span', ['badge', 'link-badge'], [
                        $makeBadgeText($badge['text'] ?? ''),
                    ]);
                }
                break;

            default:
                continue 2;
        }

        $el = $finalizeBadge($el, $index, $is_interactive);
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
        $toolbar->addItem($left_group);
    }

    if ($right_count > 0) {
        $toolbar->addItem($right_group);
    }

    $container->addItem($toolbar);
}

// Single-metric rows: [metric_id, metric_key, full_label, short_label]
$single_metrics = [
    [WidgetForm::METRIC_CPU,  'cpu',  'Processor', 'CPU'],
    [WidgetForm::METRIC_RAM,  'ram',  'Memory',    'RAM'],
    [WidgetForm::METRIC_LOAD, 'load', 'Load',      'Load'],
    [WidgetForm::METRIC_SWAP, 'swap', 'Swap',      'Swap'],
];

foreach ($single_metrics as [$metric_id, $metric_key, $full_label, $short_label]) {
    if (in_array($metric_id, $enabled)) {
        $container->addItem($makeBarRow(
            $short ? $short_label : $full_label,
            $metric_key,
            $full_label,
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
$sparkline_overlay = (new CDiv())
    ->addClass('sparkline-overlay')
    ->setAttribute('aria-hidden', 'true')
    ->setAttribute('aria-label', _('Sparkline viewer'))
    ->setAttribute('role', 'dialog');

$sparkline_toolbar = (new CDiv())->addClass('toolbar');
$sparkline_left = (new CDiv())->addClass('left');
$sparkline_left->addItem(
    $makeToolbarLink(
        [
            $back_icon(),
            (new CTag('span', true))->addItem(_('Back')),
        ],
        ['badge', 'link', 'js-sparkline-close'],
        ['aria-label' => _('Back to overview')]
    )
);
$sparkline_right = (new CDiv())->addClass('right sparkline-periods');
foreach (['1h', '3h', '12h', '1d', '3d', '1w', '30d'] as $p) {
    $btn = $makeToolbarLink(
        [$p],
        ['badge', 'link'],
        ['data-period' => $p]
    );

    if ($p === '1h') {
        $btn->addClass('active');
        $btn->setAttribute('aria-current', 'true');
    }

    $sparkline_right->addItem($btn);
}
$sparkline_toolbar->addItem($sparkline_left);
$sparkline_toolbar->addItem($sparkline_right);
$sparkline_stage = (new CDiv())->addClass('sparkline-stage');
$sparkline_stage->addItem(
    (new CTag('canvas', true))->addClass('sparkline-canvas')
);
$sparkline_labels = (new CDiv())->addClass('sparkline-y-labels');
$sparkline_labels->addItem((new CTag('span', true))->addItem('100%'));
$sparkline_labels->addItem((new CTag('span', true))->addItem('50%'));
$sparkline_labels->addItem((new CTag('span', true))->addItem('0%'));
$sparkline_stage->addItem($sparkline_labels);

$sparkline_overlay->addItem($sparkline_toolbar);
$sparkline_overlay->addItem($sparkline_stage);

$view
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
