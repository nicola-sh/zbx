<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\ZbxCommon\Includes;

/**
 * Per-session rate limit for widget JSON actions (shared by AOverview and ACharts).
 */
final class RequestRateLimiter
{
    private const WINDOW_SECONDS = 60;

    private const MAX_REQUESTS = 120;

    public static function check(string $action_key): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }

        $now = time();
        $bucket_key = 'zbx_rl_' . preg_replace('/[^a-z0-9_]/', '', strtolower($action_key));

        if (!isset($_SESSION[$bucket_key]) || !is_array($_SESSION[$bucket_key])) {
            $_SESSION[$bucket_key] = ['start' => $now, 'count' => 0];
        }

        $bucket = &$_SESSION[$bucket_key];

        if (($now - (int) ($bucket['start'] ?? 0)) >= self::WINDOW_SECONDS) {
            $bucket = ['start' => $now, 'count' => 0];
        }

        $bucket['count'] = (int) ($bucket['count'] ?? 0) + 1;

        return $bucket['count'] <= self::MAX_REQUESTS;
    }
}
