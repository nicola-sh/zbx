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
use Modules\ACharts\Includes\RequestRateLimiter;
use Modules\ACharts\Includes\SeriesHostResolver;
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
            'hostids' => 'string',
            'period' => 'string',
            'time_from' => 'int',
            'time_till' => 'int',
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
        if (!RequestRateLimiter::check('history')) {
            $this->setErrorResponse(['Too many requests. Please wait and try again.']);

            return;
        }

        try {
            $this->setJsonResponse($this->build(
                SeriesHostResolver::normalizeHostIds($this->decodeHostIdsInput()),
                trim((string) $this->getInput('period', '3h')),
                (string) $this->getInput('series'),
                $this->getInput('time_from'),
                $this->getInput('time_till')
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

    /**
     * @param list<string> $allowed_hostids
     */
    private function build(
        array $allowed_hostids,
        string $period,
        string $series_json,
        mixed $time_from_input = null,
        mixed $time_till_input = null
    ): array {
        $requested = $this->decodeSeriesRequest($series_json);

        if ($requested === []) {
            throw new RuntimeException('No series requested');
        }

        $custom_range = $this->resolveCustomTimeRange($time_from_input, $time_till_input);

        if ($custom_range === null && !array_key_exists($period, HistoryLoader::PERIODS)) {
            throw new RuntimeException('Unsupported period');
        }

        $host_context = SeriesHostResolver::fetchHostContext($allowed_hostids);

        if ($allowed_hostids === [] || $host_context === []) {
            throw new RuntimeException('No hosts are available for this widget.');
        }

        $matcher = new MetricMatcher();
        $item_names_by_host = [];

        foreach ($requested as $entry) {
            $hostid = SeriesHostResolver::resolveSeriesHostId($entry, $host_context);

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
            $hostid = SeriesHostResolver::resolveSeriesHostId($entry, $host_context);

            if ($hostid === null) {
                $datasets[] = [
                    'key' => $key,
                    'label' => (string) ($entry['label'] ?? $key),
                    'missing' => true,
                    'missing_reason' => 'Series host is not in the widget host selection.',
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

            $loaded = $custom_range !== null
                ? $loader->loadSeriesRange(
                    (string) $item['itemid'],
                    (int) $item['value_type'],
                    $custom_range['from'],
                    $custom_range['till']
                )
                : $loader->loadSeries(
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

        $fallback_period = array_key_exists($period, HistoryLoader::PERIODS) ? $period : '3h';

        return [
            'period' => $period,
            'timeFrom' => $time_from ?? (time() - HistoryLoader::PERIODS[$fallback_period]),
            'timeTill' => $time_till ?? time(),
            'datasets' => $datasets,
        ];
    }

    /**
     * @return array{from: int, till: int}|null
     */
    private function resolveCustomTimeRange(mixed $time_from_input, mixed $time_till_input): ?array
    {
        if (!is_numeric($time_from_input) || !is_numeric($time_till_input)) {
            return null;
        }

        $from = (int) $time_from_input;
        $till = (int) $time_till_input;

        if ($from <= 0 || $till <= 0 || $till <= $from) {
            return null;
        }

        return ['from' => $from, 'till' => $till];
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

    /**
     * @return list<string>
     */
    private function decodeHostIdsInput(): array
    {
        $raw = $this->getInput('hostids', []);

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $hostids = SeriesHostResolver::normalizeHostIds($decoded);

                if ($hostids !== []) {
                    return $hostids;
                }
            }
        }

        $hostids = SeriesHostResolver::normalizeHostIds($raw);

        if ($hostids !== []) {
            return $hostids;
        }

        $single = trim((string) $this->getInput('hostid', ''));

        return $single !== '' ? [$single] : [];
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
