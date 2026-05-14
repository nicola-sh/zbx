<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\HostOverview\Includes;

class WildcardMetricResolver
{
    public const STATUS_READY = 'ready';
    public const STATUS_EMPTY = 'empty';
    public const STATUS_INVALID_PATTERN = 'invalid_pattern';
    public const STATUS_TOO_BROAD = 'too_broad';
    public const STATUS_NONE = 'none';
    public const STATUS_MATCHES = 'matches';

    public function extractSearchTerms(string $pattern): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn(string $part): string => trim($part), explode('*', trim($pattern))),
            static fn(string $part): bool => $part !== ''
        )));
    }

    public function inspectSingleWildcardPattern(string $pattern): array
    {
        return $this->inspectWildcardPattern($pattern, 1);
    }

    public function inspectInterfacePattern(string $pattern): array
    {
        return $this->inspectWildcardPattern($pattern, 2);
    }

    public function buildInterfaceRows(
        array $metrics,
        string $pattern,
        string $exclude = '',
        int $capacity = 0
    ): array {
        return $this->buildInterfaceRowsResult($metrics, $pattern, $exclude, $capacity)['rows'];
    }

    public function previewInterfaceRows(
        array $metrics,
        string $pattern,
        string $exclude = '',
        int $capacity = 0,
        int $row_limit = 6
    ): array {
        $inspection = $this->inspectInterfacePattern($pattern);

        if ($inspection['status'] !== self::STATUS_READY) {
            return [
                'status' => $inspection['status'],
                'matched_item_count' => 0,
                'excluded_item_count' => 0,
                'excluded_row_count' => 0,
                'interface_count' => 0,
                'row_count' => 0,
                'rows' => [],
                'excluded_rows' => [],
                'has_more_rows' => false,
                'has_more_excluded_rows' => false,
            ];
        }

        $result = $this->buildInterfaceRowsResult($metrics, $inspection['pattern'], $exclude, $capacity);
        $rows = $result['rows'];
        $excluded_rows = $result['excluded_rows'];
        $row_limit = max(1, $row_limit);
        $limited_rows = array_slice($rows, 0, $row_limit);
        $limited_excluded_rows = array_slice($excluded_rows, 0, $row_limit);

        return [
            'status' => $rows === [] ? self::STATUS_NONE : self::STATUS_MATCHES,
            'matched_item_count' => $result['matched_item_count'],
            'excluded_item_count' => $result['excluded_item_count'],
            'excluded_row_count' => count($excluded_rows),
            'interface_count' => $result['interface_count'],
            'row_count' => count($rows),
            'rows' => array_map([$this, 'toPreviewInterfaceRow'], $limited_rows),
            'excluded_rows' => array_map([$this, 'toPreviewInterfaceRow'], $limited_excluded_rows),
            'has_more_rows' => count($rows) > count($limited_rows),
            'has_more_excluded_rows' => count($excluded_rows) > count($limited_excluded_rows),
        ];
    }

    private function inspectWildcardPattern(string $pattern, int $required_wildcards): array
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return [
                'status' => self::STATUS_EMPTY,
                'pattern' => $pattern,
                'search_terms' => [],
            ];
        }

        if (substr_count($pattern, '*') < $required_wildcards) {
            return [
                'status' => self::STATUS_INVALID_PATTERN,
                'pattern' => $pattern,
                'search_terms' => [],
            ];
        }

        $search_terms = $this->extractSearchTerms($pattern);

        if ($search_terms === []) {
            return [
                'status' => self::STATUS_TOO_BROAD,
                'pattern' => $pattern,
                'search_terms' => [],
            ];
        }

        return [
            'status' => self::STATUS_READY,
            'pattern' => $pattern,
            'search_terms' => $search_terms,
        ];
    }

    public function buildSingleWildcardRows(array $metrics, string $pattern, string $exclude = ''): array
    {
        return $this->buildSingleWildcardRowsResult($metrics, $pattern, $exclude)['rows'];
    }

    public function previewSingleWildcardRows(
        array $metrics,
        string $pattern,
        string $exclude = '',
        int $row_limit = 6
    ): array {
        $inspection = $this->inspectSingleWildcardPattern($pattern);

        if ($inspection['status'] !== self::STATUS_READY) {
            return [
                'status' => $inspection['status'],
                'matched_item_count' => 0,
                'excluded_item_count' => 0,
                'excluded_row_count' => 0,
                'row_count' => 0,
                'rows' => [],
                'excluded_rows' => [],
                'has_more_rows' => false,
                'has_more_excluded_rows' => false,
            ];
        }

        $result = $this->buildSingleWildcardRowsResult($metrics, $inspection['pattern'], $exclude);
        $rows = $result['rows'];
        $excluded_rows = $result['excluded_rows'];
        $row_limit = max(1, $row_limit);
        $limited_rows = array_slice($rows, 0, $row_limit);
        $limited_excluded_rows = array_slice($excluded_rows, 0, $row_limit);

        return [
            'status' => $rows === [] ? self::STATUS_NONE : self::STATUS_MATCHES,
            'matched_item_count' => $result['matched_item_count'],
            'excluded_item_count' => $result['excluded_item_count'],
            'excluded_row_count' => count($excluded_rows),
            'row_count' => count($rows),
            'rows' => array_map([$this, 'toPreviewRow'], $limited_rows),
            'excluded_rows' => array_map([$this, 'toPreviewRow'], $limited_excluded_rows),
            'has_more_rows' => count($rows) > count($limited_rows),
            'has_more_excluded_rows' => count($excluded_rows) > count($limited_excluded_rows),
        ];
    }

    private function buildInterfaceRowsResult(array $metrics, string $pattern, string $exclude, int $capacity): array
    {
        $interface_regex = $this->buildInterfaceRegex($pattern);

        if ($interface_regex === null) {
            return [
                'matched_item_count' => 0,
                'excluded_item_count' => 0,
                'interface_count' => 0,
                'rows' => [],
                'excluded_rows' => [],
            ];
        }

        $interface_rows = [];
        $excluded_rows = [];
        $excluded_index = [];
        $matched_item_count = 0;
        $excluded_item_count = 0;

        foreach ($metrics as $metric_name => $details) {
            $parsed_metric = $this->parseInterfaceMetric($metric_name, $details, $interface_regex, $exclude, $capacity);

            if ($parsed_metric === null) {
                continue;
            }

            if ($parsed_metric['excluded']) {
                $excluded_item_count++;

                if (!array_key_exists($parsed_metric['row']['key'], $excluded_index)) {
                    $excluded_index[$parsed_metric['row']['key']] = count($excluded_rows);
                    $excluded_rows[] = $parsed_metric['row'];
                }

                continue;
            }

            $matched_item_count++;
            $interface_rows[$parsed_metric['name']][$parsed_metric['direction']] = $parsed_metric['row'];
        }

        if ($interface_rows === []) {
            return [
                'matched_item_count' => $matched_item_count,
                'excluded_item_count' => $excluded_item_count,
                'interface_count' => 0,
                'rows' => [],
                'excluded_rows' => $this->sortInterfacePreviewRows($excluded_rows),
            ];
        }

        $ordered_names = $this->sortNames(array_keys($interface_rows));
        $interface_aliases = $this->generateInterfaceAliases($ordered_names);

        return [
            'matched_item_count' => $matched_item_count,
            'excluded_item_count' => $excluded_item_count,
            'interface_count' => count($ordered_names),
            'rows' => $this->buildInterfaceOutputRows($interface_rows, $ordered_names, $interface_aliases),
            'excluded_rows' => $this->sortInterfacePreviewRows($excluded_rows),
        ];
    }

    private function buildSingleWildcardRowsResult(array $metrics, string $pattern, string $exclude): array
    {
        $regex = $this->buildSingleWildcardRegex($pattern);

        if ($regex === null) {
            return [
                'matched_item_count' => 0,
                'excluded_item_count' => 0,
                'rows' => [],
                'excluded_rows' => [],
            ];
        }

        $rows = [];
        $index = [];
        $excluded_rows = [];
        $excluded_index = [];
        $matched_item_count = 0;
        $excluded_item_count = 0;

        foreach ($metrics as $metric_name => $details) {
            if (!preg_match($regex, $metric_name, $match)) {
                continue;
            }

            $row_name = trim($match[1]);

            if ($row_name === '') {
                $row_name = '?';
            }

            if ($this->matchesExcludePattern($row_name, $exclude)) {
                $excluded_item_count++;

                if (!array_key_exists($row_name, $excluded_index)) {
                    $excluded_index[$row_name] = count($excluded_rows);
                    $excluded_rows[] = [
                        'name' => $row_name,
                        'item_name' => $metric_name,
                        'excluded' => true,
                    ];
                }

                continue;
            }

            $matched_item_count++;

            $percent = $details['value'] === null
                ? null
                : $this->clampPercent($details['value']);

            if (array_key_exists($row_name, $index)) {
                $row_index = $index[$row_name];

                if ($percent !== null || $rows[$row_index]['percent'] === null) {
                    $rows[$row_index]['percent'] = $percent;
                    $rows[$row_index]['item_name'] = $metric_name;
                    $rows[$row_index]['item_ref'] = $this->toItemRef($details);
                }
            }
            else {
                $index[$row_name] = count($rows);
                $rows[] = [
                    'name' => $row_name,
                    'percent' => $percent,
                    'item_name' => $metric_name,
                    'item_ref' => $this->toItemRef($details),
                ];
            }
        }

        usort($rows, static fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));
        usort($excluded_rows, static fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

        return [
            'matched_item_count' => $matched_item_count,
            'excluded_item_count' => $excluded_item_count,
            'rows' => $rows,
            'excluded_rows' => $excluded_rows,
        ];
    }

    private function buildInterfaceRegex(string $pattern): ?string
    {
        $parts = explode('*', trim($pattern), 3);

        if (count($parts) < 3) {
            return null;
        }

        return '/^' . preg_quote($parts[0], '/') . '(.+?)'
            . preg_quote($parts[1], '/') . '(\S+)'
            . preg_quote($parts[2], '/') . '$/';
    }

    private function buildSingleWildcardRegex(string $pattern): ?string
    {
        $parts = explode('*', trim($pattern), 2);

        if (count($parts) < 2) {
            return null;
        }

        return '/^' . preg_quote($parts[0], '/') . '(.+?)' . preg_quote($parts[1], '/') . '$/';
    }

    private function parseInterfaceMetric(
        string $metric_name,
        array $details,
        string $interface_regex,
        string $exclude,
        int $capacity
    ): ?array {
        if (!preg_match($interface_regex, $metric_name, $match)) {
            return null;
        }

        $interface_name = trim($match[1]);
        $direction = $this->parseInterfaceDirection($match[2]);

        if ($direction === null) {
            return null;
        }

        if ($interface_name === '') {
            $interface_name = '?';
        }

        if ($this->matchesExcludePattern($interface_name, $exclude)) {
            return [
                'excluded' => true,
                'name' => $interface_name,
                'direction' => $direction,
                'row' => [
                    'key' => $interface_name . '|' . $direction,
                    'label' => $interface_name . ' ' . ($direction === 'received' ? 'RX' : 'TX'),
                    'interface_name' => $interface_name,
                    'item_name' => $metric_name,
                    'excluded' => true,
                ],
            ];
        }

        return [
            'excluded' => false,
            'name' => $interface_name,
            'direction' => $direction,
            'row' => [
                'key' => $interface_name . '|' . $direction,
                'bps' => $details['value'],
                'percent' => $this->calculateInterfacePercent($details['value'], $capacity),
                'item_name' => $metric_name,
                'item_ref' => $this->toItemRef($details),
                'excluded' => false,
            ],
        ];
    }

    private function parseInterfaceDirection(string $direction_raw): ?string
    {
        if (str_contains($direction_raw, 'received') || str_contains($direction_raw, 'in')) {
            return 'received';
        }

        if (str_contains($direction_raw, 'sent') || str_contains($direction_raw, 'out')) {
            return 'sent';
        }

        return null;
    }

    private function calculateInterfacePercent(mixed $bps, int $capacity): ?int
    {
        if ($bps === null) {
            return null;
        }

        if ($capacity > 0 && is_numeric($bps)) {
            return $this->clampPercent(($bps / $capacity) * 100);
        }

        return 0;
    }

    private function sortNames(array $names): array
    {
        natcasesort($names);

        return array_values($names);
    }

    private function generateInterfaceAliases(array $ordered_names): array
    {
        $alias_counter = 1;
        $interface_aliases = [];
        $used_labels = [];

        foreach ($ordered_names as $interface_name) {
            if (strlen($interface_name) <= 4) {
                $interface_aliases[$interface_name] = $interface_name;
                $used_labels[strtoupper($interface_name)] = true;
            }
        }

        foreach ($ordered_names as $interface_name) {
            if (isset($interface_aliases[$interface_name])) {
                continue;
            }

            do {
                $alias = 'IF' . $alias_counter++;
            } while (isset($used_labels[$alias]));

            $interface_aliases[$interface_name] = $alias;
            $used_labels[$alias] = true;
        }

        return $interface_aliases;
    }

    private function buildInterfaceOutputRows(array $interface_rows, array $ordered_names, array $interface_aliases): array
    {
        $rows = [];

        foreach ($ordered_names as $interface_name) {
            $label = $interface_aliases[$interface_name];

            foreach (['received' => 'RX', 'sent' => 'TX'] as $direction => $suffix) {
                if (!isset($interface_rows[$interface_name][$direction])) {
                    continue;
                }

                $row = $interface_rows[$interface_name][$direction];
                $row['label'] = strtoupper($label . ' ' . $suffix);
                $row['interface_name'] = $interface_name;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function sortInterfacePreviewRows(array $rows): array
    {
        usort($rows, static function(array $left, array $right): int {
            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $rows;
    }

    private function matchesExcludePattern(string $name, string $patterns): bool
    {
        if ($patterns === '') {
            return false;
        }

        foreach (explode(',', $patterns) as $pattern) {
            $pattern = trim($pattern);

            if ($pattern !== '' && fnmatch($pattern, $name, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }

    private function clampPercent(float | int $value): int
    {
        return max(0, min(100, (int) round($value)));
    }

    private function toItemRef(?array $metric): ?array
    {
        if ($metric === null || !array_key_exists('itemid', $metric) || !array_key_exists('value_type', $metric)) {
            return null;
        }

        return [
            'itemid' => (string) $metric['itemid'],
            'name' => $metric['name'] ?? null,
            'value_type' => (int) $metric['value_type'],
        ];
    }

    private function toPreviewRow(array $row): array
    {
        return [
            'name' => (string) ($row['name'] ?? ''),
            'match_name' => (string) ($row['item_name'] ?? ''),
            'item_name' => (string) ($row['item_name'] ?? ''),
            'excluded' => (bool) ($row['excluded'] ?? false),
        ];
    }

    private function toPreviewInterfaceRow(array $row): array
    {
        return [
            'name' => (string) ($row['label'] ?? ''),
            'match_name' => (string) ($row['interface_name'] ?? ''),
            'item_name' => (string) ($row['item_name'] ?? ''),
            'excluded' => (bool) ($row['excluded'] ?? false),
        ];
    }
}
