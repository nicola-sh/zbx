<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\AOverview\Includes;

use CDiv;
use CSpan;
use CTag;

/**
 * @param array<string, mixed> $data
 */
function render_multi_host_root(array $data): CDiv
{
    $hosts = $data['hosts'] ?? [];
    $config = is_array($data['config'] ?? null) ? $data['config'] : [];
    $root = (new CDiv())
        ->addClass('a-overview-multi-root')
        ->setAttribute('data-a-overview-multi', '1');

    $theme_style = build_overview_theme_style($config);

    if ($theme_style !== '') {
        $root->setAttribute('style', $theme_style);
    }

    $list_view = (new CDiv())
        ->addClass('a-overview-multi-list-view')
        ->setAttribute('data-multi-view', 'list');

    $search = (new CTag('input', false))
        ->addClass('text-box-default a-overview-multi-search')
        ->setAttribute('type', 'search')
        ->setAttribute('placeholder', _('Filter hosts…'))
        ->setAttribute('aria-label', _('Filter hosts'));

    $list = (new CDiv())->addClass('a-overview-multi-list');

    $detail_view = (new CDiv())
        ->addClass('a-overview-multi-detail-view')
        ->setAttribute('data-multi-view', 'detail')
        ->setAttribute('hidden', 'hidden');

    $back = (new CTag('button', true))
        ->addClass('a-overview-multi-back')
        ->setAttribute('type', 'button')
        ->setAttribute('data-a-overview-back', '1')
        ->addItem('Back to list');

    $detail_inner = (new CDiv())->addClass('a-overview-multi-detail-panels');

    foreach ($hosts as $host) {
        if (!is_array($host)) {
            continue;
        }

        $hostid = trim((string) ($host['hostid'] ?? ''));

        if ($hostid === '') {
            continue;
        }

        $label = (string) ($host['display_label'] ?? $host['name'] ?? $hostid);
        $light = normalize_traffic_light_color($host['light'] ?? 'green');
        $summary_badges = is_array($host['summary_badges'] ?? null) ? $host['summary_badges'] : [];
        $detail_badges = is_array($host['detail_badges'] ?? null) ? $host['detail_badges'] : [];

        $summary = (new CDiv())
            ->addClass('a-overview-multi-summary')
            ->setAttribute('data-a-overview-nav', $hostid)
            ->setAttribute('data-a-overview-search-label', mb_strtolower($label))
            ->setAttribute('role', 'button')
            ->setAttribute('tabindex', '0')
            ->setAttribute('aria-label', sprintf(_('Open details: %1$s'), $label));

        $left = (new CDiv())->addClass('a-overview-multi-summary-main');

        $left->addItem(
            (new CSpan())
                ->addClass('a-overview-multi-name')
                ->addItem($label)
        );

        $summary_toolbar = render_toolbar($summary_badges);

        if ($summary_toolbar !== null) {
            $summary_toolbar->addClass('a-overview-multi-summary-toolbar');
            $left->addItem($summary_toolbar);
        }

        $summary->addItem($left);

        $light_labels = [
            'red' => _('Critical'),
            'yellow' => _('Warning'),
            'green' => _('OK'),
        ];
        $light_label = $light_labels[$light] ?? $light;

        $summary->addItem(
            (new CSpan())
                ->addClass('a-overview-light')
                ->addClass('a-overview-light--' . $light)
                ->setAttribute('title', $light_label)
                ->setAttribute('aria-label', $light_label)
        );

        $list->addItem($summary);

        $rows = is_array($host['rows'] ?? null) ? $host['rows'] : [];
        $config = is_array($host['config'] ?? null) ? $host['config'] : [];

        $panel = (new CDiv())
            ->addClass('a-overview-detail-panel')
            ->setAttribute('data-host-detail-panel', $hostid)
            ->setAttribute('hidden', 'hidden');

        $container = render_overview_container($config);
        $container->setAttribute('data-overview-hostid', $hostid);

        $detail_toolbar = render_toolbar($detail_badges);

        if ($detail_toolbar !== null) {
            $container->addItem($detail_toolbar);
        }

        foreach ($rows as $row) {
            if (is_array($row)) {
                $container->addItem(render_metric_row($row));
            }
        }

        $panel->addItem($container);
        $detail_inner->addItem($panel);
    }

    $detail_view->addItem($back);
    $detail_view->addItem($detail_inner);

    $list_view->addItem($search);
    $list_view->addItem($list);

    return $root
        ->addItem($list_view)
        ->addItem($detail_view);
}

function normalize_traffic_light_color(mixed $light): string
{
    $light = is_string($light) ? strtolower(trim($light)) : 'green';

    return in_array($light, ['green', 'yellow', 'red'], true) ? $light : 'green';
}
