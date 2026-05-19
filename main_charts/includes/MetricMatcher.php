<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainCharts\Includes;

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
            'output' => ['itemid', 'name', 'lastvalue', 'lastclock', 'value_type'],
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
}
