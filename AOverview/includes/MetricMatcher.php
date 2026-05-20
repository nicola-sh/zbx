<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\AOverview\Includes;

use API;

class MetricMatcher
{
    public const STATUS_EMPTY = 'empty';
    public const STATUS_EXACT = 'exact';
    public const STATUS_UNIQUE_PARTIAL = 'unique_partial';
    public const STATUS_AMBIGUOUS = 'ambiguous';
    public const STATUS_NONE = 'none';

    public function collect(array $hostids, array $name_filters): array
    {
        $name_filters = $this->normalizeNameFilters($name_filters);

        if ($hostids === [] || $name_filters === []) {
            return [
                'metrics' => [],
            ];
        }

        $items = API::Item()->get([
            'output' => ['itemid', 'name', 'lastvalue', 'lastclock', 'value_type', 'units'],
            'hostids' => $hostids,
            'search' => ['name' => $name_filters],
            'searchByAny' => true,
        ]);

        $metrics = [];

        foreach ($items as $item) {
            $name = $item['name'];
            $clock = (int) ($item['lastclock'] ?? 0);
            $value = $item['lastvalue'] ?? null;
            $numeric_value = ($clock > 0 && is_numeric($value)) ? (float) $value : null;

            $metrics[$name] = [
                'itemid' => $item['itemid'],
                'name' => $name,
                'lastclock' => $clock,
                'value_type' => (int) ($item['value_type'] ?? 0),
                'units' => trim((string) ($item['units'] ?? '')),
                'value' => $numeric_value,
                'raw' => $value,
            ];
        }

        return [
            'metrics' => $metrics,
        ];
    }

    public function resolve(array $metrics, string $search): ?array
    {
        return $this->match($metrics, trim($search))['resolved'];
    }

    /**
     * @return array{status: string, resolved: ?array, matches: list<array>}
     */
    public function matchMetrics(array $metrics, string $search): array
    {
        return $this->match($metrics, trim($search));
    }

    public function preview(array $metrics, string $search, int $candidate_limit = 5): array
    {
        $search = trim($search);

        if ($search === '') {
            return [
                'status' => self::STATUS_EMPTY,
                'match' => null,
                'candidate_count' => 0,
                'candidates' => [],
                'has_more_candidates' => false,
            ];
        }

        $match = $this->match($metrics, $search);
        $candidates = $this->sortMetricsByName($match['matches']);
        $candidate_limit = max(1, $candidate_limit);
        $limited_candidates = array_slice($candidates, 0, $candidate_limit);

        return [
            'status' => $match['status'],
            'match' => $this->toPreviewMetric($match['resolved']),
            'candidate_count' => count($candidates),
            'candidates' => array_map([$this, 'toPreviewMetric'], $limited_candidates),
            'has_more_candidates' => count($candidates) > count($limited_candidates),
        ];
    }

    private function match(array $metrics, string $search): array
    {
        if ($search === '') {
            return [
                'status' => self::STATUS_EMPTY,
                'resolved' => null,
                'matches' => [],
            ];
        }

        if (isset($metrics[$search])) {
            return [
                'status' => self::STATUS_EXACT,
                'resolved' => $metrics[$search],
                'matches' => [$metrics[$search]],
            ];
        }

        $matches = [];

        foreach ($metrics as $name => $details) {
            if (str_contains($name, $search)) {
                $matches[] = $details;
            }
        }

        if (count($matches) === 1) {
            return [
                'status' => self::STATUS_UNIQUE_PARTIAL,
                'resolved' => $matches[0],
                'matches' => $matches,
            ];
        }

        return [
            'status' => count($matches) > 1 ? self::STATUS_AMBIGUOUS : self::STATUS_NONE,
            'resolved' => null,
            'matches' => $matches,
        ];
    }

    /**
     * @param list<string> $name_filters
     * @return list<string>
     */
    private function normalizeNameFilters(array $name_filters): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn($value): string => trim((string) $value), $name_filters),
            static fn(string $value): bool => $value !== ''
        )));
    }

    /**
     * @param list<array> $metrics
     * @return list<array>
     */
    private function sortMetricsByName(array $metrics): array
    {
        usort($metrics, static function (array $left, array $right): int {
            return strnatcasecmp($left['name'] ?? '', $right['name'] ?? '');
        });

        return $metrics;
    }

    private function toPreviewMetric(?array $metric): ?array
    {
        if ($metric === null) {
            return null;
        }

        return [
            'itemid' => (string) ($metric['itemid'] ?? ''),
            'name' => (string) ($metric['name'] ?? ''),
            'units' => (string) ($metric['units'] ?? ''),
        ];
    }
}
