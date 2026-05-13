<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

declare(strict_types=1);

namespace Modules\HostOverview\Includes;

final class HostProfilesHelper
{
    /**
     * @return list<array{hostid: string, overrides: array<string, mixed>}>
     */
    public static function parse(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (!is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        $out = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $hostid = trim((string) ($entry['hostid'] ?? ''));

            if ($hostid === '') {
                continue;
            }

            $overrides = $entry['overrides'] ?? [];

            if (!is_array($overrides)) {
                $overrides = [];
            }

            $out[] = [
                'hostid' => $hostid,
                'overrides' => $overrides,
            ];
        }

        return $out;
    }

    /**
     * @param list<array{hostid: string, overrides: array<string, mixed>}> $profiles
     */
    public static function encode(array $profiles): string
    {
        return (string) json_encode($profiles, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array{hostid: string, overrides: array<string, mixed>}> $profiles
     * @param list<string> $ordered_hostids
     * @return list<array{hostid: string, overrides: array<string, mixed>}>
     */
    public static function syncWithHostOrder(array $profiles, array $ordered_hostids): array
    {
        $by_host = [];

        foreach ($profiles as $entry) {
            $id = $entry['hostid'] ?? '';

            if ($id === '') {
                continue;
            }

            $by_host[$id] = $entry['overrides'] ?? [];
        }

        $result = [];

        foreach ($ordered_hostids as $hostid) {
            $hostid = trim((string) $hostid);

            if ($hostid === '') {
                continue;
            }

            $result[] = [
                'hostid' => $hostid,
                'overrides' => $by_host[$hostid] ?? [],
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $base_fields
     * @param array{hostid: string, overrides: array<string, mixed>} $profile
     * @return array<string, mixed>
     */
    public static function mergeProfile(array $base_fields, array $profile): array
    {
        $merged = $base_fields;
        $merged['hostid'] = [$profile['hostid']];
        $merged['override_hostid'] = [];

        foreach ($profile['overrides'] as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (in_array($key, ['hostid', 'override_hostid', 'host_profiles'], true)) {
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }
}
