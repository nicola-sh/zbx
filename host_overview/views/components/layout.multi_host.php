<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\HostOverview\Includes;

use CDiv;
use CSpan;
use CTag;

/**
 * @param array<string, mixed> $data
 */
function render_multi_host_root(array $data): CDiv
{
    $hosts = $data['hosts'] ?? [];
    $root = (new CDiv())
        ->addClass('host-overview-multi-root')
        ->setAttribute('data-host-overview-multi', '1');

    $list_view = (new CDiv())
        ->addClass('host-overview-multi-list-view')
        ->setAttribute('data-multi-view', 'list');

    $list = (new CDiv())->addClass('host-overview-multi-list');

    $detail_view = (new CDiv())
        ->addClass('host-overview-multi-detail-view')
        ->setAttribute('data-multi-view', 'detail')
        ->setAttribute('hidden', 'hidden');

    $back = (new CTag('button', true))
        ->addClass('host-overview-multi-back')
        ->setAttribute('type', 'button')
        ->setAttribute('data-host-overview-back', '1')
        ->addItem(_('Back to list'));

    $detail_inner = (new CDiv())->addClass('host-overview-multi-detail-panels');

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
            ->addClass('host-overview-multi-summary')
            ->setAttribute('data-host-overview-nav', $hostid)
            ->setAttribute('role', 'button')
            ->setAttribute('tabindex', '0')
            ->setAttribute('aria-label', _s('Open details for %1$s', $label));

        $left = (new CDiv())->addClass('host-overview-multi-summary-main');

        $left->addItem(
            (new CSpan())
                ->addClass('host-overview-multi-name')
                ->addItem($label)
        );

        $summary_toolbar = render_toolbar($summary_badges);

        if ($summary_toolbar !== null) {
            $summary_toolbar->addClass('host-overview-multi-summary-toolbar');
            $left->addItem($summary_toolbar);
        }

        $summary->addItem($left);

        $summary->addItem(
            (new CSpan())
                ->addClass('host-overview-light')
                ->addClass('host-overview-light--' . $light)
                ->setAttribute('title', $light)
                ->setAttribute('aria-label', $light)
        );

        $list->addItem($summary);

        $rows = is_array($host['rows'] ?? null) ? $host['rows'] : [];
        $config = is_array($host['config'] ?? null) ? $host['config'] : [];

        $panel = (new CDiv())
            ->addClass('host-overview-detail-panel')
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
