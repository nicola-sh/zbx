<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\ACharts\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;
use Modules\ACharts\Includes\ChartSeriesHelper;
use Modules\ACharts\Includes\MetricMatcher;
use Modules\ACharts\Includes\SeriesHostResolver;
use Modules\ACharts\Includes\WidgetForm;

class WidgetView extends CControllerDashboardWidgetView
{
    protected function doAction(): void
    {
        $hostids = $this->resolveHostIds();

        if ($hostids === []) {
            $this->setResponse(new CControllerResponseData([
                'name' => $this->getInput('name', $this->widget->getName()),
                'empty' => true,
                'message' => _('Select a host, then add metrics for that host.'),
                'config' => $this->fields_values,
                'layout_signature' => 'empty',
            ]));

            return;
        }

        $host_context = SeriesHostResolver::fetchHostContext($hostids);

        if ($host_context === []) {
            $this->setResponse(new CControllerResponseData([
                'name' => $this->getInput('name', $this->widget->getName()),
                'empty' => true,
                'message' => _('The selected host is not available.'),
                'config' => $this->fields_values,
                'layout_signature' => 'empty',
            ]));

            return;
        }

        $series_config = ChartSeriesHelper::parse($this->fields_values['chart_series'] ?? '');

        if (!ChartSeriesHelper::hasConfiguredSeries($this->fields_values['chart_series'] ?? '')) {
            $this->setResponse(new CControllerResponseData([
                'name' => $this->getInput('name', $this->widget->getName()),
                'empty' => true,
                'message' => _('Configure at least one chart series in the widget settings.'),
                'config' => $this->fields_values,
                'layout_signature' => 'empty-series',
            ]));

            return;
        }

        $resolved_series = $this->resolveSeries($series_config, $host_context);
        $primary_hostid = array_key_first($host_context);
        $host_title = (string) ($host_context[$primary_hostid]['name'] ?? $primary_hostid);

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'empty' => false,
            'hostid' => $primary_hostid,
            'host_name' => $host_title,
            'config' => $this->buildClientConfig($resolved_series, $host_context, $primary_hostid),
            'series' => $resolved_series,
            'layout_signature' => $this->buildLayoutSignature($resolved_series),
        ]));
    }

    /**
     * @return list<string>
     */
    private function resolveHostIds(): array
    {
        $override = $this->normalizeHostIds($this->fields_values['override_hostid'] ?? []);

        if ($override !== []) {
            return $this->takeSingleHostId($override);
        }

        return $this->takeSingleHostId($this->normalizeHostIds($this->fields_values['hostid'] ?? []));
    }

    /**
     * @param list<string> $hostids
     * @return list<string>
     */
    private function takeSingleHostId(array $hostids): array
    {
        if ($hostids === []) {
            return [];
        }

        return [$hostids[0]];
    }

    /**
     * @param list<array{key: string, label: string, item_name: string, color: string, hostid: string, host: string}> $series_config
     * @param array<string, array{hostid: string, name: string, host: string}> $host_context
     * @return list<array<string, mixed>>
     */
    private function resolveSeries(array $series_config, array $host_context): array
    {
        $matcher = new MetricMatcher();
        $series_by_host = [];

        foreach ($series_config as $entry) {
            $resolved_hostid = SeriesHostResolver::resolveSeriesHostId($entry, $host_context);

            if ($resolved_hostid === null) {
                continue;
            }

            if (trim((string) ($entry['itemid'] ?? '')) !== '') {
                continue;
            }

            $item_name = trim((string) ($entry['item_name'] ?? ''));

            if ($item_name === '') {
                continue;
            }

            $series_by_host[$resolved_hostid][] = $item_name;
        }

        $collection_by_host = [];

        foreach ($series_by_host as $hostid => $item_names) {
            $collection_by_host[$hostid] = $matcher->collect([$hostid], array_values(array_unique($item_names)));
        }

        $resolved = [];

        foreach ($series_config as $entry) {
            $hostid = SeriesHostResolver::resolveSeriesHostId($entry, $host_context);
            $series_host_name = $hostid !== null ? (string) ($host_context[$hostid]['name'] ?? $hostid) : null;
            $metrics = $hostid !== null
                ? (array) (($collection_by_host[$hostid]['metrics'] ?? []))
                : [];
            $metric = $hostid !== null
                ? $this->resolveSeriesMetric($entry, $metrics, $matcher, $hostid)
                : null;
            $legend_label = (string) $entry['label'];
            $item_name = trim((string) ($entry['item_name'] ?? ''));

            if ($metric !== null && $item_name === '') {
                $item_name = (string) ($metric['name'] ?? '');
            }

            $resolved[] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'legend_label' => $legend_label,
                'color' => $entry['color'],
                'item_name' => $item_name,
                'itemid' => trim((string) ($entry['itemid'] ?? '')),
                'hostid' => $hostid,
                'host_name' => $series_host_name,
                'host' => $entry['host'] ?? '',
                'status' => $metric !== null ? 'ok' : 'missing',
                'missing_reason' => $this->resolveMissingReason($entry, $hostid, $metric, $host_context),
                'item' => $metric !== null ? [
                    'itemid' => (string) $metric['itemid'],
                    'name' => (string) ($metric['name'] ?? ''),
                    'value_type' => (int) ($metric['value_type'] ?? 0),
                    'units' => (string) ($metric['units'] ?? ''),
                    'hostid' => $hostid,
                ] : null,
            ];
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, array<string, mixed>> $metrics
     */
    private function resolveSeriesMetric(
        array $entry,
        array $metrics,
        MetricMatcher $matcher,
        string $hostid
    ): ?array {
        $itemid = trim((string) ($entry['itemid'] ?? ''));

        if ($itemid !== '') {
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'value_type', 'units'],
                'hostids' => [$hostid],
                'itemids' => [$itemid],
                'limit' => 1,
            ]);

            if ($items === []) {
                return null;
            }

            $item = $items[0];

            return [
                'itemid' => (string) ($item['itemid'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'value_type' => (int) ($item['value_type'] ?? 0),
                'units' => trim((string) ($item['units'] ?? '')),
            ];
        }

        $item_name = trim((string) ($entry['item_name'] ?? ''));

        if ($item_name === '') {
            return null;
        }

        return $matcher->resolve($metrics, $item_name);
    }

    /**
     * @param list<array<string, mixed>> $series
     * @param array<string, array{hostid: string, name: string, host: string}> $host_context
     * @return array<string, mixed>
     */
    private function buildClientConfig(array $series, array $host_context, ?string $primary_hostid): array
    {
        return [
            'hostid' => $primary_hostid,
            'hostids' => array_keys($host_context),
            'hosts' => array_values($host_context),
            'use_dashboard_time' => (int) ($this->fields_values['chart_use_dashboard_time'] ?? 0),
            'period' => WidgetForm::normalizePeriodForStorage(
                $this->fields_values['chart_period'] ?? WidgetForm::DEFAULT_PERIOD
            ),
            'chart_type' => (int) ($this->fields_values['chart_type'] ?? WidgetForm::CHART_TYPE_LINE),
            'legend_position' => (int) ($this->fields_values['legend_position'] ?? WidgetForm::LEGEND_TOP),
            'chart_stacked' => (int) ($this->fields_values['chart_stacked'] ?? 0),
            'chart_fill' => (int) ($this->fields_values['chart_fill'] ?? 1),
            'show_grid' => (int) ($this->fields_values['show_grid'] ?? 1),
            'series' => array_map(static function(array $entry): array {
                return [
                    'key' => $entry['key'],
                    'label' => $entry['legend_label'] ?? $entry['label'],
                    'raw_label' => $entry['label'],
                    'color' => $entry['color'],
                    'item_name' => $entry['item_name'],
                    'hostid' => $entry['hostid'] ?? null,
                    'host_name' => $entry['host_name'] ?? null,
                    'itemid' => $entry['item']['itemid'] ?? null,
                    'value_type' => $entry['item']['value_type'] ?? null,
                    'units' => $entry['item']['units'] ?? '',
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
                $entry['hostid'] ?? '',
                $entry['item']['itemid'] ?? '',
            ]);
        }

        $parts[] = (string) ($this->fields_values['chart_period'] ?? '');
        $parts[] = (string) ($this->fields_values['chart_type'] ?? '');
        $parts[] = (string) ($this->fields_values['legend_position'] ?? '');
        $parts[] = (string) ($this->fields_values['chart_stacked'] ?? '');
        $parts[] = (string) ($this->fields_values['chart_fill'] ?? '');
        $parts[] = (string) ($this->fields_values['show_grid'] ?? '');
        $parts[] = (string) ($this->fields_values['chart_use_dashboard_time'] ?? '');

        return implode('|', $parts);
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, array{hostid: string, name: string, host: string}> $host_context
     */
    private function resolveMissingReason(array $entry, ?string $hostid, ?array $metric, array $host_context): ?string
    {
        if ($metric !== null) {
            return null;
        }

        if ($hostid === null) {
            if (trim((string) ($entry['hostid'] ?? '')) !== '' || trim((string) ($entry['host'] ?? '')) !== '') {
                return 'Series host is not present in selected hosts.';
            }

            return 'Could not resolve target host for this series.';
        }

        return 'Item was not found on the selected host.';
    }

    /**
     * @return list<string>
     */
    private function normalizeHostIds(mixed $value): array
    {
        if (!is_array($value)) {
            $hostid = trim((string) $value);

            return $hostid !== '' ? [$hostid] : [];
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
