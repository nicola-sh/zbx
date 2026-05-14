<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

declare(strict_types=1);

if (!function_exists('_m')) {
    /**
     * Translate a string from the main_overview gettext domain.
     */
    function _m(string $message): string
    {
        return \Modules\MainOverview\Includes\I18n::gettext($message);
    }
}

if (!function_exists('_ms')) {
    /**
     * Translate a format string, then sprintf with placeholders (%1$s, %s, …).
     */
    function _ms(string $format, mixed ...$args): string
    {
        return sprintf(\Modules\MainOverview\Includes\I18n::gettext($format), ...$args);
    }
}
