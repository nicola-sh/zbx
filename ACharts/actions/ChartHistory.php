<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\ACharts\Actions;

use API;
use CController;
use CControllerResponseData;
use Modules\ACharts\Includes\HistoryLoader;
use Modules\ACharts\Includes\MetricMatcher;
use RuntimeException;
use Throwable;

class ChartHistory extends CController
{
    protected function init(): void
    {
        $this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
        $this->disableCsrfValidation();
    }

    protected function checkPermissions(): bool
    {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function checkInput(): bool
    {
        $fields = [
            'hostid' => 'db hosts.hostid',
            'period' => 'required|in ' . implode(',', array_keys(HistoryLoader::PERIODS)),
            'series' => 'required|string',
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setErrorResponse(array_column(get_and_clear_messages(), 'message'));
        }

        return $ret;
    }

    protected function doAction(): void
    {
        try {
            $this->setJsonResponse($this->build(
                trim((string) $this->getInput('hostid', '')),
                trim((string) $this->getInput('period')),
                (string) $this->getInput('series')
            ));
        }
        catch (Throwable $exception) {
            $message = trim($exception->getMessage()) !== ''
                ? $exception->getMessage()
                : 'Could not load chart data.';

            $this->setErrorResponse([$message]);
        }
    }

    private function setJsonResponse(array $payload): void
    {
        $this->setResponse(
            (new CControllerResponseData([
                'main_block' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]))->disableView()
        );
    }

    private function setErrorResponse(array $messages): void
    {
        $this->setJsonResponse([
            'error' => [
                'messages' => array_values(array_filter($messages, static fn($message): bool => $message !== '')),
            ],
        ]);
    }

    private function build(string $default_hostid, string $period, string $series_json): array
    {
        $requested = $this->decodeSeriesRequest($series_json);

        if ($requested === []) {
            throw new RuntimeException('No series requested');
        }

        $matcher = new MetricMatcher();
        $item_names_by_host = [];

        foreach ($requested as $entry) {
            $hostid = $this->resolveSeriesHostId($entry, $default_hostid);

            if ($hostid === null) {
                continue;
            }

            if (($entry['itemid'] ?? null) !== null) {
                continue;
            }

            $item_name = trim((string) ($entry['item_name'] ?? ''));

            if ($item_name === '') {
                continue;
            }

            $item_names_by_host[$hostid][] = $item_name;
        }

        $collection_by_host = [];

        foreach ($item_names_by_host as $hostid => $item_names) {
            $collection_by_host[$hostid] = $matcher->collect([$hostid], array_values(array_unique($item_names)));
        }

        $loader = new HistoryLoader();
        $datasets = [];
        $time_from = null;
        $time_till = null;

        foreach ($requested as $entry) {
            $key = (string) ($entry['key'] ?? '');
            $hostid = $this->resolveSeriesHostId($entry, $default_hostid);

            if ($hostid === null) {
                $datasets[] = [
                    'key' => $key,
                    'label' => (string) ($entry['label'] ?? $key),
                    'missing' => true,
                    'missing_reason' => 'No host is defined for this series.',
                    'points' => [],
                ];

                continue;
            }

            $metrics = (array) (($collection_by_host[$hostid]['metrics'] ?? []));
            $item = $this->resolveSeriesItem($entry, $metrics, $matcher, $hostid);

            if ($item === null) {
                $datasets[] = [
                    'key' => $key,
                    'label' => (string) ($entry['label'] ?? $key),
                    'missing' => true,
                    'missing_reason' => 'Item was not found on the selected host.',
                    'hostid' => $hostid,
                    'points' => [],
                ];

                continue;
            }

            $loaded = $loader->loadSeries(
                (string) $item['itemid'],
                (int) $item['value_type'],
                $period
            );

            $time_from = $time_from === null
                ? $loaded['timeFrom']
                : min($time_from, $loaded['timeFrom']);
            $time_till = $time_till === null
                ? $loaded['timeTill']
                : max($time_till, $loaded['timeTill']);

            $datasets[] = [
                'key' => $key,
                'label' => (string) ($entry['label'] ?? $item['name'] ?? $key),
                'missing' => false,
                'hostid' => $hostid,
                'item_ref' => [
                    'itemid' => (string) $item['itemid'],
                    'name' => (string) ($item['name'] ?? ''),
                    'value_type' => (int) ($item['value_type'] ?? 0),
                    'units' => (string) ($item['units'] ?? ''),
                ],
                'units' => (string) ($item['units'] ?? ''),
                'points' => $loaded['points'],
                'gapThresholdFloor' => $loaded['gapThresholdFloor'],
            ];
        }

        return [
            'period' => $period,
            'timeFrom' => $time_from ?? (time() - HistoryLoader::PERIODS[$period]),
            'timeTill' => $time_till ?? time(),
            'datasets' => $datasets,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeSeriesRequest(string $series_json): array
    {
        $decoded = json_decode($series_json, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid series payload');
        }

        $normalized = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $key = trim((string) ($entry['key'] ?? ''));

            if ($key === '') {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'label' => trim((string) ($entry['label'] ?? $key)),
                'itemid' => $this->normalizeOptionalString($entry['itemid'] ?? null),
                'item_name' => $this->normalizeOptionalString($entry['item_name'] ?? null),
                'hostid' => $this->normalizeOptionalString($entry['hostid'] ?? null),
                'host_name' => $this->normalizeOptionalString($entry['host_name'] ?? null),
                'value_type' => $this->normalizeOptionalInt($entry['value_type'] ?? null),
            ];
        }

        return $normalized;
    }

    private function resolveSeriesItem(array $entry, array $metrics, MetricMatcher $matcher, string $hostid): ?array
    {
        $itemid = $entry['itemid'] ?? null;

        if ($itemid !== null) {
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'value_type', 'units'],
                'hostids' => [$hostid],
                'itemids' => [$itemid],
                'limit' => 1,
            ]);

            if ($items !== []) {
                return [
                    'itemid' => (string) $items[0]['itemid'],
                    'name' => (string) ($items[0]['name'] ?? ''),
                    'value_type' => (int) ($items[0]['value_type'] ?? 0),
                    'units' => trim((string) ($items[0]['units'] ?? '')),
                ];
            }
        }

        $item_name = $entry['item_name'] ?? null;

        if ($item_name === null) {
            return null;
        }

        return $matcher->resolve($metrics, $item_name);
    }

    private function resolveSeriesHostId(array $entry, string $default_hostid): ?string
    {
        $hostid = $entry['hostid'] ?? null;

        if ($hostid !== null) {
            return $hostid;
        }

        $default_hostid = trim($default_hostid);

        return $default_hostid !== '' ? $default_hostid : null;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
