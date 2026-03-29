<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

// =============================================================================
// Time/Duration formatting
// =============================================================================

function format_uptime(?int $seconds): ?string
{
    if ($seconds === null || $seconds < 0) {
        return null;
    }

    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    if ($days > 0) {
        return $days . 'd ' . $hours . 'h';
    }

    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }

    return $minutes . 'm';
}

function format_freshness(?int $freshness): string
{
    if ($freshness === null) {
        return 'No ping';
    }

    return $freshness < 60
        ? $freshness . 's ago'
        : (int) floor($freshness / 60) . 'm ago';
}

// =============================================================================
// Value formatting
// =============================================================================

function format_load(float $value): string
{
    $rounded = round($value, 2);

    return floor($rounded) === $rounded
        ? number_format($rounded, 0, '.', '')
        : number_format($rounded, 2, '.', '');
}

function format_bps(float $bps): string
{
    if ($bps >= 1e9) {
        return number_format($bps / 1e9, fmod($bps, 1e9) === 0.0 ? 0 : 1, '.', '') . ' Gbps';
    }

    if ($bps >= 1e6) {
        return number_format($bps / 1e6, fmod($bps, 1e6) === 0.0 ? 0 : 1, '.', '') . ' Mbps';
    }

    if ($bps >= 1e3) {
        return number_format($bps / 1e3, fmod($bps, 1e3) === 0.0 ? 0 : 1, '.', '') . ' Kbps';
    }

    return (string) round($bps) . ' bps';
}

function format_percent(float|int $value): string
{
    return max(0, min(100, (int) round($value))) . '%';
}

function format_problems(int $total): string
{
    return $total > 0 ? $total . ' problems' : 'Problems —';
}

// =============================================================================
// Text formatting
// =============================================================================

function format_tags(array $tags): string
{
    $parts = [];

    foreach ($tags as $tag) {
        $tag_name = trim((string) ($tag['tag'] ?? ''));
        $tag_value = trim((string) ($tag['value'] ?? ''));

        if ($tag_name === '') {
            continue;
        }

        $parts[] = $tag_value === '' ? $tag_name : $tag_name . ': ' . $tag_value;
    }

    return $parts === [] ? 'No tags' : implode(' • ', $parts);
}

function format_display_text(string $display_kind, float $value, ?string $prefix = null): string
{
    $text = match ($display_kind) {
        'load' => format_load($value),
        'interface' => format_bps($value),
        default => format_percent($value),
    };

    return ($prefix !== null && $prefix !== '')
        ? $prefix . ' ' . $text
        : $text;
}

function format_display_value(string $display_kind, float $value): string
{
    return match ($display_kind) {
        'load' => format_load($value),
        'interface' => format_bps($value),
        default => format_percent($value),
    };
}

function format_empty_text(?string $prefix = null): string
{
    return ($prefix !== null && $prefix !== '')
        ? $prefix . ' ' . 'No data'
        : 'No data';
}

function format_empty_value(): string
{
    return 'No data';
}

// =============================================================================
// State class resolvers
// =============================================================================

function freshness_state_classes(?int $freshness, int $warn_threshold, int $stale_threshold): array
{
    if ($freshness === null) {
        return [];
    }

    if ($freshness >= $stale_threshold) {
        return ['freshness-stale'];
    }

    if ($freshness >= $warn_threshold) {
        return ['freshness-warn'];
    }

    return [];
}

function problems_state_classes(int $total, int $max_severity): array
{
    if ($total <= 0) {
        return [];
    }

    $severity_class = match ($max_severity) {
        2       => 'problems-warning',
        3       => 'problems-average',
        4       => 'problems-high',
        5       => 'problems-disaster',
        default => 'problems-info',
    };

    return ['problems-severity', $severity_class];
}
