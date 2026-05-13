<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

use CDiv;
use CSpan;

/**
 * @param array<string, mixed> $data
 */
function render_multi_host_root(array $data): CDiv
{
    $hosts = $data['hosts'] ?? [];
    $root = (new CDiv())
        ->addClass('host-overview-multi-root')
        ->setAttribute('data-host-overview-multi', '1');

    $list = (new CDiv())->addClass('host-overview-multi-list');

    foreach ($hosts as $host) {
        if (!is_array($host)) {
            continue;
        }

        $hostid = trim((string) ($host['hostid'] ?? ''));

        if ($hostid === '') {
            continue;
        }

        $name = (string) ($host['name'] ?? $hostid);
        $light = normalize_traffic_light_color($host['light'] ?? 'green');

        $summary = (new CDiv())
            ->addClass('host-overview-multi-summary')
            ->setAttribute('data-host-overview-expand', $hostid)
            ->setAttribute('role', 'button')
            ->setAttribute('tabindex', '0')
            ->setAttribute('aria-expanded', 'false');

        $summary->addItem(
            (new CSpan())
                ->addClass('host-overview-multi-name')
                ->addItem($name)
        );

        $summary->addItem(
            (new CSpan())
                ->addClass('host-overview-light')
                ->addClass('host-overview-light--' . $light)
                ->setAttribute('title', $light)
                ->setAttribute('aria-label', $light)
        );

        $list->addItem($summary);

        $badges = is_array($host['badges'] ?? null) ? $host['badges'] : [];
        $rows = is_array($host['rows'] ?? null) ? $host['rows'] : [];
        $config = is_array($host['config'] ?? null) ? $host['config'] : [];

        $panel = (new CDiv())
            ->addClass('host-overview-detail')
            ->setAttribute('data-host-detail', $hostid)
            ->setAttribute('hidden', 'hidden');

        $container = render_overview_container($config);
        $container->setAttribute('data-overview-hostid', $hostid);

        $toolbar = render_toolbar($badges);

        if ($toolbar !== null) {
            $container->addItem($toolbar);
        }

        foreach ($rows as $row) {
            if (is_array($row)) {
                $container->addItem(render_metric_row($row));
            }
        }

        $panel->addItem($container);
        $list->addItem($panel);
    }

    return $root->addItem($list);
}

function normalize_traffic_light_color(mixed $light): string
{
    $light = is_string($light) ? strtolower(trim($light)) : 'green';

    return in_array($light, ['green', 'yellow', 'red'], true) ? $light : 'green';
}
