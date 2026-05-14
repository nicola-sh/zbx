<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 *
 * GNU gettext domain for this widget (see locale/*/LC_MESSAGES/main_overview.{po,mo}).
 */

declare(strict_types=1);

namespace Modules\MainOverview\Includes;

final class I18n
{
    public const DOMAIN = 'main_overview';

    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        $helpers = __DIR__ . '/i18n.func.php';

        if (is_readable($helpers)) {
            require_once $helpers;
        }

        if (function_exists('bindtextdomain')) {
            $localeRoot = dirname(__DIR__) . '/locale';
            bindtextdomain(self::DOMAIN, $localeRoot);
            bind_textdomain_codeset(self::DOMAIN, 'UTF-8');
        }

        self::$booted = true;
    }

    public static function gettext(string $message): string
    {
        self::boot();

        if (!function_exists('dgettext')) {
            return $message;
        }

        return dgettext(self::DOMAIN, $message);
    }
}
