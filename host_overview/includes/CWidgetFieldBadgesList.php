<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\HostOverview\Includes;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldBadgesList extends CWidgetField {

    public const BADGE_HOSTNAME    = 0;
    public const BADGE_UPTIME      = 1;
    public const BADGE_LIVELINESS  = 2;
    public const BADGE_PROBLEMS    = 3;
    public const BADGE_TEXT        = 4;
    public const BADGE_LINK        = 5;
    public const BADGE_MAINTENANCE = 6;
    public const BADGE_TAGS        = 7;

    public const BADGE_TYPE_LABELS = [
        self::BADGE_HOSTNAME    => 'Hostname',
        self::BADGE_UPTIME      => 'Uptime',
        self::BADGE_LIVELINESS  => 'Liveliness',
        self::BADGE_PROBLEMS    => 'Problems',
        self::BADGE_TEXT        => 'Text',
        self::BADGE_LINK        => 'Link',
        self::BADGE_MAINTENANCE => 'Maintenance',
        self::BADGE_TAGS        => 'Tags',
    ];

    public const SIDE_LEFT  = 'left';
    public const SIDE_RIGHT = 'right';

    public const DEFAULT_ITEM_UPTIME = 'System uptime';
    public const DEFAULT_ITEM_LIVELINESS = 'Zabbix agent ping';

    private const LINK_BADGE_ALLOWED_SCHEMES = ['http', 'https'];

    private const DEFAULT_BADGES = [
        ['type' => self::BADGE_HOSTNAME,    'text' => '', 'url' => '', 'side' => self::SIDE_LEFT],
        ['type' => self::BADGE_UPTIME,      'text' => '', 'url' => '', 'side' => self::SIDE_LEFT],
        ['type' => self::BADGE_LIVELINESS,  'text' => '', 'url' => '', 'side' => self::SIDE_LEFT],
        ['type' => self::BADGE_PROBLEMS,    'text' => '', 'url' => '', 'side' => self::SIDE_RIGHT],
        ['type' => self::BADGE_MAINTENANCE, 'text' => '', 'url' => '', 'side' => self::SIDE_RIGHT],
    ];

    public function __construct(string $name, ?string $label = null) {
        parent::__construct($name, $label);

        $this->setDefault(json_encode(self::DEFAULT_BADGES));
        $this->setValidationRules(['type' => API_STRING_UTF8]);
    }

    public function validate(bool $strict = false): array {
        $errors = parent::validate($strict);

        $badges = $this->getBadges();
        $single_badge_counts = [];

        foreach ($badges as $index => $badge) {
            $type = (int) ($badge['type'] ?? self::BADGE_HOSTNAME);
            $pos = $index + 1;

            if (!self::badgeTypeExists($type)) {
                $errors[] = _s('Badge %1$s: Unsupported badge type.', $pos);
                continue;
            }

            if (!self::badgeTypeAllowsMultiple($type)) {
                $single_badge_counts[$type] = ($single_badge_counts[$type] ?? 0) + 1;
            }

            if (self::badgeTypeUsesTextField($type) && trim($badge['text'] ?? '') === '') {
                $errors[] = _s(
                    'Badge %1$s: Display text cannot be empty for a %2$s badge.',
                    $pos,
                    _(self::BADGE_TYPE_LABELS[$type])
                );
            }

            if (self::badgeTypeUsesUrlField($type)) {
                $safe_url = self::sanitizeLinkUrl($badge['url'] ?? null);

                if (trim($badge['url'] ?? '') === '') {
                    $errors[] = _s('Badge %1$s: URL cannot be empty for a Link badge.', $pos);
                }
                elseif ($safe_url === null) {
                    $errors[] = _s(
                        'Badge %1$s: URL must use http://, https://, or a relative path such as zabbix.php?action=...',
                        $pos
                    );
                }
            }
        }

        foreach ($single_badge_counts as $type => $count) {
            if ($count > 1) {
                $label = self::BADGE_TYPE_LABELS[$type] ?? _('This');
                $errors[] = _s('%1$s badge can only be added once.', _($label));
            }
        }

        return $errors;
    }

    public static function badgeTypeAllowsMultiple(int $type): bool {
        return in_array($type, [self::BADGE_TEXT, self::BADGE_LINK], true);
    }

    public static function badgeTypeExists(int $type): bool {
        return array_key_exists($type, self::BADGE_TYPE_LABELS);
    }

    public static function badgeTypeUsesTextField(int $type): bool {
        return in_array($type, [self::BADGE_TEXT, self::BADGE_LINK], true);
    }

    public static function badgeTypeUsesUrlField(int $type): bool {
        return $type === self::BADGE_LINK;
    }

    public static function getBadgeTypeOptions(): array {
        $options = [];

        foreach (self::BADGE_TYPE_LABELS as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => _($label),
            ];
        }

        return $options;
    }

    public static function getMultipleBadgeTypes(): array {
        return array_values(array_map(
            'strval',
            array_keys(array_filter(
                self::BADGE_TYPE_LABELS,
                static fn(string $_label, int $type): bool => self::badgeTypeAllowsMultiple($type),
                ARRAY_FILTER_USE_BOTH
            ))
        ));
    }

    public static function getTextFieldBadgeTypes(): array {
        return array_values(array_map(
            'strval',
            array_keys(array_filter(
                self::BADGE_TYPE_LABELS,
                static fn(string $_label, int $type): bool => self::badgeTypeUsesTextField($type),
                ARRAY_FILTER_USE_BOTH
            ))
        ));
    }

    public static function getUrlFieldBadgeTypes(): array {
        return array_values(array_map(
            'strval',
            array_keys(array_filter(
                self::BADGE_TYPE_LABELS,
                static fn(string $_label, int $type): bool => self::badgeTypeUsesUrlField($type),
                ARRAY_FILTER_USE_BOTH
            ))
        ));
    }

    public static function normalizeBadge(array $badge): array {
        $type = (int) ($badge['type'] ?? self::BADGE_HOSTNAME);
        $side = ($badge['side'] ?? self::SIDE_LEFT) === self::SIDE_RIGHT
            ? self::SIDE_RIGHT
            : self::SIDE_LEFT;

        return [
            'type' => $type,
            'text' => self::badgeTypeUsesTextField($type) ? (string) ($badge['text'] ?? '') : '',
            'url' => self::badgeTypeUsesUrlField($type) ? (string) ($badge['url'] ?? '') : '',
            'side' => $side,
        ];
    }

    public static function sanitizeLinkUrl(?string $url): ?string {
        if (!is_string($url)) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        if (preg_match('/^(?:\/\/|\\\\\\\\)/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        if (array_key_exists('scheme', $parts)) {
            $scheme = strtolower($parts['scheme']);

            return in_array($scheme, self::LINK_BADGE_ALLOWED_SCHEMES, true) ? $url : null;
        }

        if (array_intersect_key($parts, array_flip(['host', 'port', 'user', 'pass'])) !== []) {
            return null;
        }

        return $url;
    }

    /**
     * Decode the stored JSON string into a badges array.
     */
    public function getBadges(): array {
        $val = $this->getValue();

        if (is_string($val)) {
            $decoded = json_decode($val, true);

            if (is_array($decoded)) {
                return array_values(array_map(
                    static fn(array $badge): array => self::normalizeBadge($badge),
                    array_filter($decoded, 'is_array')
                ));
            }

            return self::DEFAULT_BADGES;
        }

        return self::DEFAULT_BADGES;
    }

    public function toApi(array &$widget_fields = []): void {
        $widget_fields[] = [
            'type'  => ZBX_WIDGET_FIELD_TYPE_STR,
            'name'  => $this->name,
            'value' => json_encode($this->getBadges()),
        ];
    }
}
