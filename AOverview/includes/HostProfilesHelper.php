<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

declare(strict_types=1);

namespace Modules\AOverview\Includes;

final class HostProfilesHelper
{
    /**
     * @return list<array{hostid: string, alias?: string, badges_placement?: int, overrides: array<string, mixed>}>
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
                'alias' => trim((string) ($entry['alias'] ?? '')),
                'badges_placement' => (int) ($entry['badges_placement'] ?? 0),
                'overrides' => $overrides,
            ];
        }

        return $out;
    }

    /**
     * @param list<array{hostid: string, alias?: string, badges_placement?: int, overrides: array<string, mixed>}> $profiles
     */
    public static function encode(array $profiles): string
    {
        return (string) json_encode($profiles, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array{hostid: string, alias?: string, badges_placement?: int, overrides: array<string, mixed>}> $profiles
     * @param list<string> $ordered_hostids
     * @return list<array{hostid: string, alias: string, badges_placement: int, overrides: array<string, mixed>}>
     */
    public static function syncWithHostOrder(array $profiles, array $ordered_hostids): array
    {
        $by_host = [];

        foreach ($profiles as $entry) {
            $id = $entry['hostid'] ?? '';

            if ($id === '') {
                continue;
            }

            $by_host[$id] = [
                'overrides' => $entry['overrides'] ?? [],
                'alias' => trim((string) ($entry['alias'] ?? '')),
                'badges_placement' => (int) ($entry['badges_placement'] ?? 0),
            ];
        }

        $result = [];

        foreach ($ordered_hostids as $hostid) {
            $hostid = trim((string) $hostid);

            if ($hostid === '') {
                continue;
            }

            $meta = $by_host[$hostid] ?? ['overrides' => [], 'alias' => '', 'badges_placement' => 0];

            $result[] = [
                'hostid' => $hostid,
                'alias' => $meta['alias'] ?? '',
                'badges_placement' => (int) ($meta['badges_placement'] ?? 0),
                'overrides' => is_array($meta['overrides'] ?? null) ? $meta['overrides'] : [],
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $base_fields
     * @param array{hostid: string, alias?: string, badges_placement?: int, overrides: array<string, mixed>} $profile
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

            if (in_array($key, ['hostid', 'override_hostid', 'host_profiles', 'alias', 'badges_placement'], true)) {
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }
}
