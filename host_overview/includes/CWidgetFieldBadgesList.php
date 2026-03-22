<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldBadgesList extends CWidgetField {

    public const BADGE_HOSTNAME   = 0;
    public const BADGE_UPTIME     = 1;
    public const BADGE_LIVELINESS = 2;
    public const BADGE_PROBLEMS   = 3;
    public const BADGE_TEXT       = 4;
    public const BADGE_LINK       = 5;

    public const BADGE_TYPE_LABELS = [
        self::BADGE_HOSTNAME   => 'Hostname',
        self::BADGE_UPTIME     => 'Uptime',
        self::BADGE_LIVELINESS => 'Liveliness',
        self::BADGE_PROBLEMS   => 'Problems',
        self::BADGE_TEXT       => 'Text',
        self::BADGE_LINK       => 'Link',
    ];

    public const SCOPE_ALL   = 0;
    public const SCOPE_UNACK = 1;

	public const SCOPE_LABELS = [
		self::SCOPE_UNACK => 'Unacknowledged',
		self::SCOPE_ALL   => 'Any',
	];

    public const HOSTNAME_LINK_DISABLED = 0;
    public const HOSTNAME_LINK_LATEST   = 1;
    public const HOSTNAME_LINK_PROBLEMS = 2;

    public const HOSTNAME_LINK_LABELS = [
        self::HOSTNAME_LINK_DISABLED => 'Nothing',
        self::HOSTNAME_LINK_LATEST   => 'Latest data',
        self::HOSTNAME_LINK_PROBLEMS => 'Problems',
    ];

    public const SIDE_LEFT  = 'left';
    public const SIDE_RIGHT = 'right';

    public const DEFAULT_ITEM_UPTIME = 'System uptime';

    private const LINK_BADGE_ALLOWED_SCHEMES = ['http', 'https'];

    private const DEFAULT_BADGES = [
        ['type' => self::BADGE_HOSTNAME,   'text' => '', 'url' => '', 'link' => self::HOSTNAME_LINK_LATEST, 'side' => self::SIDE_LEFT],
        ['type' => self::BADGE_UPTIME,     'text' => '', 'url' => '', 'item_name' => self::DEFAULT_ITEM_UPTIME, 'side' => self::SIDE_LEFT],
        ['type' => self::BADGE_LIVELINESS, 'text' => '', 'url' => '', 'side' => self::SIDE_LEFT],
        ['type' => self::BADGE_PROBLEMS,   'text' => '', 'url' => '', 'scope' => self::SCOPE_ALL, 'side' => self::SIDE_RIGHT],
    ];

    public function __construct(string $name, ?string $label = null) {
        parent::__construct($name, $label);

        $this->setDefault(json_encode(self::DEFAULT_BADGES));
        $this->setValidationRules(['type' => API_STRING_UTF8]);
    }

    public function validate(bool $strict = false): array {
        $errors = parent::validate($strict);

        $badges = $this->getBadges();

        foreach ($badges as $index => $badge) {
            $type = (int) ($badge['type'] ?? self::BADGE_HOSTNAME);
            $pos = $index + 1;

            if ($type === self::BADGE_TEXT) {
                if (trim($badge['text'] ?? '') === '') {
                    $errors[] = _s('Badge %1$s: Display text cannot be empty for a Text badge.', $pos);
                }
            }

            if ($type === self::BADGE_LINK) {
                if (trim($badge['text'] ?? '') === '') {
                    $errors[] = _s('Badge %1$s: Display text cannot be empty for a Link badge.', $pos);
                }
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

            if ($type === self::BADGE_UPTIME) {
                if (trim($badge['item_name'] ?? '') === '') {
                    $errors[] = _s('Badge %1$s: Item name cannot be empty for an Uptime badge.', $pos);
                }
            }
        }

        return $errors;
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
            return is_array($decoded) ? $decoded : self::DEFAULT_BADGES;
        }

        return self::DEFAULT_BADGES;
    }

    public function toApi(array &$widget_fields = []): void {
        $widget_fields[] = [
            'type'  => ZBX_WIDGET_FIELD_TYPE_STR,
            'name'  => $this->name,
            'value' => $this->getValue(),
        ];
    }
}
