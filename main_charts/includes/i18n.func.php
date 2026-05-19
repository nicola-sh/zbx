<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

declare(strict_types=1);

if (!function_exists('_c')) {
    /**
     * Translate a string from the main_charts gettext domain.
     */
    function _c(string $message): string
    {
        return \Modules\MainCharts\Includes\I18n::gettext($message);
    }
}

if (!function_exists('_cs')) {
    /**
     * Translate a format string, then sprintf with placeholders.
     */
    function _cs(string $format, mixed ...$args): string
    {
        return sprintf(\Modules\MainCharts\Includes\I18n::gettext($format), ...$args);
    }
}
