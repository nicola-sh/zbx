<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainOverview\Includes;

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
// Widget theme (colors from widget settings)
// =============================================================================

function normalize_widget_hex(mixed $value, string $default): string
{
    $hex = strtoupper(preg_replace('/^#/', '', trim((string) $value)));

    return preg_match('/^[0-9A-F]{6}$/', $hex) === 1 ? $hex : strtoupper($default);
}

function widget_hex_to_rgba(string $hex, float $alpha): string
{
    $hex = normalize_widget_hex($hex, '000000');
    $alpha = max(0.0, min(1.0, $alpha));

    return sprintf(
        'rgba(%d, %d, %d, %s)',
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
        rtrim(rtrim(sprintf('%.2f', $alpha), '0'), '.')
    );
}

/**
 * Inline CSS custom properties for metric bars, badges, sparkline, traffic lights.
 *
 * @param array<string, mixed> $config Widget field values (th_color_*, fill_color).
 */
function build_overview_theme_style(array $config): string
{
    $green = normalize_widget_hex(
        $config['th_color_3'] ?? null,
        WidgetForm::DEFAULT_COLOR_THRESHOLD_LOW
    );
    $yellow = normalize_widget_hex(
        $config['th_color_2'] ?? null,
        WidgetForm::DEFAULT_COLOR_THRESHOLD_MEDIUM
    );
    $red = normalize_widget_hex(
        $config['th_color_1'] ?? null,
        WidgetForm::DEFAULT_COLOR_THRESHOLD_HIGH
    );
    $solid = normalize_widget_hex(
        $config['fill_color'] ?? null,
        WidgetForm::DEFAULT_COLOR_FILL
    );

    $vars = [
        '--ho-color-green: #' . $green,
        '--ho-color-yellow: #' . $yellow,
        '--ho-color-red: #' . $red,
        '--ho-color-solid: #' . $solid,
        '--ho-bar-track: rgba(128, 128, 128, 0.16)',
        '--ho-freshness-warn-bg: ' . widget_hex_to_rgba($yellow, 0.38),
        '--ho-freshness-stale-bg: ' . widget_hex_to_rgba($red, 0.38),
        '--ho-maintenance-bg: ' . widget_hex_to_rgba($solid, 0.32),
        '--ho-problems-info-bg: ' . widget_hex_to_rgba($solid, 0.34),
        '--ho-problems-warning-bg: ' . widget_hex_to_rgba($yellow, 0.4),
        '--ho-problems-average-bg: ' . widget_hex_to_rgba($yellow, 0.48),
        '--ho-problems-high-bg: ' . widget_hex_to_rgba($red, 0.42),
        '--ho-problems-disaster-bg: ' . widget_hex_to_rgba($red, 0.52),
        '--ho-problems-info-pulse: ' . widget_hex_to_rgba($solid, 0.16),
        '--ho-problems-warning-pulse: ' . widget_hex_to_rgba($yellow, 0.18),
        '--ho-problems-average-pulse: ' . widget_hex_to_rgba($yellow, 0.2),
        '--ho-problems-high-pulse: ' . widget_hex_to_rgba($red, 0.2),
        '--ho-problems-disaster-pulse: ' . widget_hex_to_rgba($red, 0.24),
        '--sparkline-active-color: #' . $solid,
        '--badge-bg: rgba(0, 0, 0, 0.14)',
        '--badge-hover-bg: rgba(0, 0, 0, 0.22)',
    ];

    return implode('; ', $vars) . ';';
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
