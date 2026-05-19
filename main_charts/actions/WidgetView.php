<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainCharts\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;
use Modules\MainCharts\Includes\ChartSeriesHelper;
use Modules\MainCharts\Includes\MetricMatcher;
use Modules\MainCharts\Includes\WidgetForm;

class WidgetView extends CControllerDashboardWidgetView
{
    protected function doAction(): void
    {
        $hostid = $this->resolveHostId();

        if ($hostid === null) {
            $this->setResponse(new CControllerResponseData([
                'name' => $this->getInput('name', $this->widget->getName()),
                'empty' => true,
                'message' => _c('Select a host to display charts.'),
                'config' => $this->fields_values,
                'layout_signature' => 'empty',
            ]));

            return;
        }

        $series_config = ChartSeriesHelper::parse($this->fields_values['chart_series'] ?? '');
        $resolved_series = $this->resolveSeries($hostid, $series_config);

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'empty' => false,
            'hostid' => $hostid,
            'host_name' => $this->fetchHostName($hostid),
            'config' => $this->buildClientConfig($resolved_series),
            'series' => $resolved_series,
            'layout_signature' => $this->buildLayoutSignature($resolved_series),
        ]));
    }

    private function resolveHostId(): ?string
    {
        $override = $this->normalizeHostIds($this->fields_values['override_hostid'] ?? []);

        if ($override !== []) {
            return $override[0];
        }

        $hostids = $this->normalizeHostIds($this->fields_values['hostid'] ?? []);

        return $hostids[0] ?? null;
    }

    /**
     * @param list<array{key: string, label: string, item_name: string, color: string}> $series_config
     * @return list<array<string, mixed>>
     */
    private function resolveSeries(string $hostid, array $series_config): array
    {
        $matcher = new MetricMatcher();
        $item_names = array_map(static fn(array $entry): string => $entry['item_name'], $series_config);
        $collection = $matcher->collect([$hostid], $item_names);
        $resolved = [];

        foreach ($series_config as $entry) {
            $metric = $matcher->resolve($collection['metrics'], $entry['item_name']);

            $resolved[] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'color' => $entry['color'],
                'item_name' => $entry['item_name'],
                'status' => $metric !== null ? 'ok' : 'missing',
                'item' => $metric !== null ? [
                    'itemid' => (string) $metric['itemid'],
                    'name' => (string) ($metric['name'] ?? ''),
                    'value_type' => (int) ($metric['value_type'] ?? 0),
                ] : null,
            ];
        }

        return $resolved;
    }

    /**
     * @param list<array<string, mixed>> $series
     * @return array<string, mixed>
     */
    private function buildClientConfig(array $series): array
    {
        return [
            'hostid' => $this->resolveHostId(),
            'period' => (string) ($this->fields_values['chart_period'] ?? WidgetForm::DEFAULT_PERIOD),
            'chart_type' => (int) ($this->fields_values['chart_type'] ?? WidgetForm::CHART_TYPE_LINE),
            'legend_position' => (int) ($this->fields_values['legend_position'] ?? WidgetForm::LEGEND_TOP),
            'chart_stacked' => (int) ($this->fields_values['chart_stacked'] ?? 0),
            'chart_fill' => (int) ($this->fields_values['chart_fill'] ?? 1),
            'show_grid' => (int) ($this->fields_values['show_grid'] ?? 1),
            'series' => array_map(static function(array $entry): array {
                return [
                    'key' => $entry['key'],
                    'label' => $entry['label'],
                    'color' => $entry['color'],
                    'item_name' => $entry['item_name'],
                    'itemid' => $entry['item']['itemid'] ?? null,
                    'value_type' => $entry['item']['value_type'] ?? null,
                ];
            }, array_values(array_filter(
                $series,
                static fn(array $entry): bool => ($entry['status'] ?? '') === 'ok' && is_array($entry['item'] ?? null)
            ))),
        ];
    }

    /**
     * @param list<array<string, mixed>> $series
     */
    private function buildLayoutSignature(array $series): string
    {
        $parts = [];

        foreach ($series as $entry) {
            $parts[] = implode(':', [
                $entry['key'] ?? '',
                $entry['status'] ?? '',
                $entry['item']['itemid'] ?? '',
            ]);
        }

        $parts[] = (string) ($this->fields_values['chart_period'] ?? '');
        $parts[] = (string) ($this->fields_values['chart_type'] ?? '');
        $parts[] = (string) ($this->fields_values['legend_position'] ?? '');
        $parts[] = (string) ($this->fields_values['chart_stacked'] ?? '');
        $parts[] = (string) ($this->fields_values['chart_fill'] ?? '');

        return implode('|', $parts);
    }

    private function fetchHostName(string $hostid): string
    {
        $hosts = API::Host()->get([
            'output' => ['name'],
            'hostids' => [$hostid],
            'limit' => 1,
        ]);

        return trim((string) ($hosts[0]['name'] ?? ''));
    }

    /**
     * @return list<string>
     */
    private function normalizeHostIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $hostids = [];

        foreach ($value as $hostid) {
            if (!is_scalar($hostid)) {
                continue;
            }

            $hostid = trim((string) $hostid);

            if ($hostid !== '') {
                $hostids[] = $hostid;
            }
        }

        return array_values(array_unique($hostids));
    }
}
