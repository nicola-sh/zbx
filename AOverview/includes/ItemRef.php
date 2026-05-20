<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

declare(strict_types=1);

namespace Modules\AOverview\Includes;

final class ItemRef
{
    /**
     * @param array<string, mixed>|null $metric
     *
     * @return array{itemid: string, name: mixed, value_type: int}|null
     */
    public static function fromMetric(?array $metric): ?array
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
}
