<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainCharts\Includes;

use API;

final class HistoryLoader
{
    public const PERIODS = [
        '1h' => 3600,
        '3h' => 10800,
        '12h' => 43200,
        '1d' => 86400,
        '3d' => 259200,
        '1w' => 604800,
        '30d' => 2592000,
    ];

    private const MAX_POINTS = 240;
    private const MAX_HISTORY_FETCH = self::MAX_POINTS * 20;
    private const MAX_TREND_FETCH = 2048;
    private const TREND_BLEND_SECONDS = 43200;
    private const RECENT_HISTORY_SECONDS = 7200;
    private const HISTORY_GAP_FLOOR = 300;
    private const TREND_GAP_FLOOR = 3600;
    private const VALUE_TYPE_FLOAT = 0;
    private const VALUE_TYPE_UINT64 = 3;

    /**
     * @return array{points: list<array{t: int, v: float}>, gapThresholdFloor: int}
     */
    public function loadSeries(string $itemid, int $value_type, string $period): array
    {
        if (!array_key_exists($period, self::PERIODS)) {
            throw new \InvalidArgumentException('Unsupported period');
        }

        $seconds = self::PERIODS[$period];
        $time_till = time();
        $time_from = $time_till - $seconds;

        $series = $this->fetchRawSeries($itemid, $value_type, $time_from, $time_till, $seconds);

        return [
            'points' => $this->downsamplePoints($series['points']),
            'gapThresholdFloor' => $series['gapThresholdFloor'],
            'timeFrom' => $time_from,
            'timeTill' => $time_till,
        ];
    }

    /**
     * @return array{points: list<array{t: int, v: float}>, gapThresholdFloor: int}
     */
    private function fetchRawSeries(
        string $itemid,
        int $value_type,
        int $time_from,
        int $time_till,
        int $seconds
    ): array {
        $supports_trends = in_array($value_type, [self::VALUE_TYPE_FLOAT, self::VALUE_TYPE_UINT64], true);

        if (!$supports_trends) {
            return [
                'points' => $this->fetchHistory($itemid, $value_type, $time_from, $time_till),
                'gapThresholdFloor' => self::HISTORY_GAP_FLOOR,
            ];
        }

        if ($seconds <= self::TREND_BLEND_SECONDS) {
            $points = $this->fetchHistory($itemid, $value_type, $time_from, $time_till);
            $used_trends = false;

            if ($points === []) {
                $points = $this->fetchTrends($itemid, $time_from, $time_till);
                $used_trends = true;
            }

            return [
                'points' => $points,
                'gapThresholdFloor' => $used_trends ? self::TREND_GAP_FLOOR : self::HISTORY_GAP_FLOOR,
            ];
        }

        $history_from = max($time_from, $time_till - self::RECENT_HISTORY_SECONDS);
        $trend_till = $history_from - 1;
        $history_points = $this->fetchHistory($itemid, $value_type, $history_from, $time_till);
        $trend_points = $trend_till >= $time_from
            ? $this->fetchTrends($itemid, $time_from, $trend_till)
            : [];

        if ($trend_points !== []) {
            return [
                'points' => array_values(array_merge($trend_points, $history_points)),
                'gapThresholdFloor' => self::TREND_GAP_FLOOR,
            ];
        }

        return [
            'points' => $this->fetchHistory($itemid, $value_type, $time_from, $time_till),
            'gapThresholdFloor' => self::HISTORY_GAP_FLOOR,
        ];
    }

    /**
     * @return list<array{t: int, v: float}>
     */
    private function fetchHistory(
        string $itemid,
        int $value_type,
        int $time_from,
        int $time_till,
        string $sortorder = 'ASC'
    ): array {
        $fetch_sortorder = $sortorder === 'ASC' ? 'DESC' : $sortorder;
        $records = API::History()->get([
            'output' => ['value', 'clock'],
            'history' => $value_type,
            'itemids' => [$itemid],
            'time_from' => $time_from,
            'time_till' => $time_till,
            'sortfield' => 'clock',
            'sortorder' => $fetch_sortorder,
            'limit' => self::MAX_HISTORY_FETCH,
        ]);

        $points = array_map(static function(array $record): array {
            return [
                't' => (int) ($record['clock'] ?? 0),
                'v' => (float) ($record['value'] ?? 0),
            ];
        }, $records);

        if ($fetch_sortorder === 'DESC') {
            usort($points, static fn(array $left, array $right): int => $left['t'] <=> $right['t']);
        }

        return $points;
    }

    /**
     * @return list<array{t: int, v: float}>
     */
    private function fetchTrends(string $itemid, int $time_from, int $time_till): array
    {
        $records = API::Trend()->get([
            'output' => ['value_avg', 'clock'],
            'itemids' => [$itemid],
            'time_from' => $time_from,
            'time_till' => $time_till,
            'sortfield' => 'clock',
            'sortorder' => 'DESC',
            'limit' => self::MAX_TREND_FETCH,
        ]);

        $points = array_map(static function(array $record): array {
            return [
                't' => (int) ($record['clock'] ?? 0),
                'v' => (float) ($record['value_avg'] ?? 0),
            ];
        }, $records);

        usort($points, static fn(array $left, array $right): int => $left['t'] <=> $right['t']);

        return $points;
    }

    /**
     * @param list<array{t: int, v: float}> $points
     * @return list<array{t: int, v: float}>
     */
    private function downsamplePoints(array $points): array
    {
        if (count($points) <= self::MAX_POINTS) {
            return $points;
        }

        $stride = count($points) / self::MAX_POINTS;
        $downsampled = [];

        for ($i = 0; $i < self::MAX_POINTS; $i++) {
            $downsampled[] = $points[(int) floor($i * $stride)];
        }
        $downsampled[self::MAX_POINTS - 1] = $points[count($points) - 1];

        return $downsampled;
    }
}
