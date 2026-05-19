<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainCharts\Actions;

use API;
use CController;
use CControllerResponseData;
use Modules\MainCharts\Includes\HistoryLoader;
use Modules\MainCharts\Includes\MetricMatcher;
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
            'hostid' => 'required|db hosts.hostid',
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
                trim((string) $this->getInput('hostid')),
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

    private function build(string $hostid, string $period, string $series_json): array
    {
        if ($hostid === '') {
            throw new RuntimeException('No host');
        }

        $requested = $this->decodeSeriesRequest($series_json);

        if ($requested === []) {
            throw new RuntimeException('No series requested');
        }

        $matcher = new MetricMatcher();
        $item_names = array_values(array_unique(array_filter(array_map(
            static fn(array $entry): string => trim((string) ($entry['item_name'] ?? '')),
            $requested
        ))));
        $collection = $matcher->collect([$hostid], $item_names);
        $loader = new HistoryLoader();
        $datasets = [];
        $time_from = null;
        $time_till = null;

        foreach ($requested as $entry) {
            $key = (string) ($entry['key'] ?? '');
            $item = $this->resolveSeriesItem($entry, $collection['metrics'], $matcher, $hostid);

            if ($item === null) {
                $datasets[] = [
                    'key' => $key,
                    'label' => (string) ($entry['label'] ?? $key),
                    'missing' => true,
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
                'item_ref' => [
                    'itemid' => (string) $item['itemid'],
                    'name' => (string) ($item['name'] ?? ''),
                    'value_type' => (int) ($item['value_type'] ?? 0),
                ],
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
                'output' => ['itemid', 'name', 'value_type'],
                'hostids' => [$hostid],
                'itemids' => [$itemid],
                'limit' => 1,
            ]);

            if ($items !== []) {
                return [
                    'itemid' => (string) $items[0]['itemid'],
                    'name' => (string) ($items[0]['name'] ?? ''),
                    'value_type' => (int) ($items[0]['value_type'] ?? 0),
                ];
            }
        }

        $item_name = $entry['item_name'] ?? null;

        if ($item_name === null) {
            return null;
        }

        return $matcher->resolve($metrics, $item_name);
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
