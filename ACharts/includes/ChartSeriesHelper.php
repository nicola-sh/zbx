<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\ACharts\Includes;

use JsonException;

final class ChartSeriesHelper
{
    public const MAX_SERIES = 8;

    /**
     * @return array
     */
    public static function defaults(): array
    {
        return [
            [
                'key' => 'cpu',
                'label' => 'CPU',
                'item_name' => 'CPU utilization',
                'itemid' => '',
                'color' => '458ADC',
                'hostid' => '',
                'host' => '',
            ],
            [
                'key' => 'ram',
                'label' => 'Memory',
                'item_name' => 'Memory utilization',
                'itemid' => '',
                'color' => '4C9F38',
                'hostid' => '',
                'host' => '',
            ],
        ];
    }

    public static function encode(array $series): string
    {
        return json_encode(array_values($series), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array
     */
    public static function parse($raw): array
    {
        if (is_array($raw)) {
            return self::normalizeList($raw);
        }

        $json = trim((string) $raw);

        if ($json === '') {
            return self::defaults();
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }
        catch (JsonException) {
            return self::defaults();
        }

        if (!is_array($decoded)) {
            return self::defaults();
        }

        return self::normalizeList($decoded);
    }

    /**
     * @param array $entries
     * @return array
     */
    private static function normalizeList(array $entries): array
    {
        $normalized = [];
        $index = 0;

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $item_name = trim((string) ($entry['item_name'] ?? ''));
            $itemid = self::normalizeOptionalString($entry['itemid'] ?? null);

            if ($item_name === '' && $itemid === null) {
                continue;
            }

            $label = trim((string) ($entry['label'] ?? ''));

            if ($label === '') {
                $label = $item_name !== '' ? $item_name : ('Item ' . ($index + 1));
            }

            $key = trim((string) ($entry['key'] ?? ''));

            if ($key === '') {
                $key = 'series_' . ($index + 1);
            }

            $color = self::normalizeColor((string) ($entry['color'] ?? ''));
            $hostid = self::normalizeOptionalString($entry['hostid'] ?? null);
            $host = self::normalizeOptionalString($entry['host'] ?? null);

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'item_name' => $item_name,
                'itemid' => $itemid ?? '',
                'color' => $color,
                'hostid' => $hostid ?? '',
                'host' => $host ?? '',
            ];

            $index++;

            if (count($normalized) >= self::MAX_SERIES) {
                break;
            }
        }

        return $normalized !== [] ? $normalized : self::defaults();
    }

    private static function normalizeColor(string $color): string
    {
        $color = ltrim(trim($color), '#');

        if (preg_match('/^[0-9A-Fa-f]{6}$/', $color) === 1) {
            return strtoupper($color);
        }

        return '458ADC';
    }

    private static function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
