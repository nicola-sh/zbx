<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\ACharts\Includes;

use API;

/**
 * Resolves series hostid against widget-selected hosts (shared by WidgetView and ChartHistory).
 */
final class SeriesHostResolver
{
    /**
     * @param list<string> $hostids
     * @return array<string, array{hostid: string, name: string, host: string}>
     */
    public static function fetchHostContext(array $hostids): array
    {
        $hostids = self::normalizeHostIds($hostids);

        if ($hostids === []) {
            return [];
        }

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
     * @param array{hostid?: string, host?: string, host_name?: string} $entry
     * @param array<string, array{hostid: string, name: string, host: string}> $host_context
     */
    public static function resolveSeriesHostId(array $entry, array $host_context): ?string
    {
        if ($host_context === []) {
            return null;
        }

        $requested_hostid = trim((string) ($entry['hostid'] ?? ''));

        if ($requested_hostid !== '') {
            return array_key_exists($requested_hostid, $host_context) ? $requested_hostid : null;
        }

        $requested_host = trim((string) ($entry['host'] ?? $entry['host_name'] ?? ''));

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

    /**
     * @return list<string>
     */
    public static function normalizeHostIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $hostids = [];

        foreach ($value as $entry) {
            if (is_array($entry)) {
                $hostid = trim((string) ($entry['hostid'] ?? ''));

                if ($hostid !== '') {
                    $hostids[] = $hostid;
                }

                continue;
            }

            $hostid = trim((string) $entry);

            if ($hostid !== '') {
                $hostids[] = $hostid;
            }
        }

        return array_values(array_unique($hostids));
    }
}
