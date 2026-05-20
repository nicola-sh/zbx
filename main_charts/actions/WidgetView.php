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
        $hostids = $this->resolveHostIds();

        if ($hostids === []) {
            $this->setResponse(new CControllerResponseData([
                'name' => $this->getInput('name', $this->widget->getName()),
                'empty' => true,
                'message' => 'Select one or more hosts to display charts.',
                'config' => $this->fields_values,
                'layout_signature' => 'empty',
            ]));

            return;
        }

        $host_context = $this->fetchHostContext($hostids);

        if ($host_context === []) {
            $this->setResponse(new CControllerResponseData([
                'name' => $this->getInput('name', $this->widget->getName()),
                'empty' => true,
                'message' => 'None of the selected hosts are available.',
                'config' => $this->fields_values,
                'layout_signature' => 'empty',
            ]));

            return;
        }

        $series_config = ChartSeriesHelper::parse($this->fields_values['chart_series'] ?? '');
        $resolved_series = $this->resolveSeries($series_config, $host_context);
        $primary_hostid = array_key_first($host_context);
        $host_title = count($host_context) === 1
            ? (string) ($host_context[$primary_hostid]['name'] ?? '')
            : sprintf('%d hosts', count($host_context));

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
            return $override;
        }

        return $this->normalizeHostIds($this->fields_values['hostid'] ?? []);
    }

    /**
     * @param list<string> $hostids
     * @return array<string, array{hostid: string, name: string, host: string}>
     */
    private function fetchHostContext(array $hostids): array
    {
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name', 'host'],
            'hostids' => $hostids,
        ]);

        $indexed = [];

        foreach ($hosts as $host) {
            $hostid = trim((string) ($host['hostid'] ?? ''));

            if ($hostid === '') {
                continue;
            }

            $indexed[$hostid] = [
                'hostid' => $hostid,
                'name' => trim((string) ($host['name'] ?? $hostid)),
                'host' => trim((string) ($host['host'] ?? '')),
            ];
        }

        $ordered = [];

        foreach ($hostids as $hostid) {
            if (isset($indexed[$hostid])) {
                $ordered[$hostid] = $indexed[$hostid];
            }
        }

        return $ordered;
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
            $resolved_hostid = $this->resolveSeriesHostId($entry, $host_context);

            if ($resolved_hostid === null) {
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

        $multi_host = count($host_context) > 1;
        $resolved = [];

        foreach ($series_config as $entry) {
            $hostid = $this->resolveSeriesHostId($entry, $host_context);
            $host_name = $hostid !== null ? (string) ($host_context[$hostid]['name'] ?? $hostid) : null;
            $metrics = $hostid !== null
                ? (array) (($collection_by_host[$hostid]['metrics'] ?? []))
                : [];
            $metric = $hostid !== null
                ? $matcher->resolve($metrics, (string) ($entry['item_name'] ?? ''))
                : null;
            $legend_label = $this->buildLegendLabel((string) $entry['label'], $host_name, $multi_host);

            $resolved[] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'legend_label' => $legend_label,
                'color' => $entry['color'],
                'item_name' => $entry['item_name'],
                'hostid' => $hostid,
                'host_name' => $host_name,
                'host' => $entry['host'] ?? '',
                'status' => $metric !== null ? 'ok' : 'missing',
                'missing_reason' => $this->resolveMissingReason($entry, $hostid, $metric, $host_context),
                'item' => $metric !== null ? [
                    'itemid' => (string) $metric['itemid'],
                    'name' => (string) ($metric['name'] ?? ''),
                    'value_type' => (int) ($metric['value_type'] ?? 0),
                    'hostid' => $hostid,
                ] : null,
            ];
        }

        return $resolved;
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
            'hosts' => array_values($host_context),
            'period' => (string) ($this->fields_values['chart_period'] ?? WidgetForm::DEFAULT_PERIOD),
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

        return implode('|', $parts);
    }

    /**
     * @param array{hostid?: string, host?: string} $entry
     * @param array<string, array{hostid: string, name: string, host: string}> $host_context
     */
    private function resolveSeriesHostId(array $entry, array $host_context): ?string
    {
        if ($host_context === []) {
            return null;
        }

        $requested_hostid = trim((string) ($entry['hostid'] ?? ''));

        if ($requested_hostid !== '') {
            return array_key_exists($requested_hostid, $host_context) ? $requested_hostid : null;
        }

        $requested_host = trim((string) ($entry['host'] ?? ''));

        if ($requested_host !== '') {
            $needle = strtolower($requested_host);
            $matched = null;

            foreach ($host_context as $hostid => $host) {
                $candidates = [
                    strtolower((string) ($host['name'] ?? '')),
                    strtolower((string) ($host['host'] ?? '')),
                ];

                if (in_array($needle, $candidates, true)) {
                    if ($matched !== null && $matched !== $hostid) {
                        return null;
                    }

                    $matched = $hostid;
                }
            }

            return $matched;
        }

        if (count($host_context) === 1) {
            return array_key_first($host_context);
        }

        return null;
    }

    private function buildLegendLabel(string $label, ?string $host_name, bool $multi_host): string
    {
        if (!$multi_host || $host_name === null || $host_name === '') {
            return $label;
        }

        return sprintf('%s / %s', $host_name, $label);
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
            if (count($host_context) > 1
                    && trim((string) ($entry['hostid'] ?? '')) === ''
                    && trim((string) ($entry['host'] ?? '')) === '') {
                return 'Set "hostid" or "host" for this series when multiple hosts are selected.';
            }

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
