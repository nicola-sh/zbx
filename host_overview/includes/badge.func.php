<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

require_once __DIR__ . '/icons.func.php';

use CLinkAction;
use CMenuPopupHelper;
use CTag;

// =============================================================================
// Badge creators - each returns a ready-to-use element
// =============================================================================

function badge_hostname(string $text, ?string $hostid = null): CTag|CLinkAction
{
    if ($hostid !== null && $hostid !== '') {
        return (new CLinkAction([
            _badge_text_span($text),
            render_icon('more', ['badge-icon']),
        ]))
            ->addClass('badge')
            ->addClass('host-badge')
            ->addClass('link')
            ->setMenuPopup(CMenuPopupHelper::getHost($hostid));
    }

    return _badge_span(['badge', 'host-badge'], [_badge_text_span($text)]);
}

function badge_uptime(string $text, array $state_classes = []): CTag
{
    return _badge_with_icon('uptime', 'uptime-badge', $text, $state_classes);
}

function badge_freshness(string $text, array $state_classes = []): CTag
{
    return _badge_with_icon('freshness', 'freshness-badge', $text, $state_classes);
}

function badge_maintenance(string $text): CTag
{
    return _badge_with_icon('maintenance', 'maintenance-badge', $text);
}

function badge_tags(string $text): CTag
{
    return _badge_with_icon('tags', 'tags-badge', $text);
}

function badge_problems(string $text, ?array $link = null, array $state_classes = []): CTag
{
    return _badge_with_optional_link('problems-badge', $text, $link, $state_classes);
}

function badge_link(string $text, ?array $link = null): CTag
{
    return _badge_with_optional_link('link-badge', $text, $link);
}

function badge_text(string $text): CTag
{
    return _badge_span(['badge', 'text-badge'], [_badge_text_span($text)]);
}

// =============================================================================
// Internal helpers (prefixed with _)
// =============================================================================

function _badge_with_icon(string $icon_name, string $class_name, string $text, array $state_classes = []): CTag
{
    $el = _badge_span(['badge', $class_name], [
        render_icon($icon_name, ['badge-icon']),
        _badge_text_span($text),
    ]);

    foreach ($state_classes as $class) {
        $el->addClass($class);
    }

    return $el;
}

function _badge_with_optional_link(string $class_name, string $text, ?array $link = null, array $state_classes = []): CTag
{
    $items = [_badge_text_span($text)];

    if ($link !== null) {
        $items[] = render_icon('link', ['badge-icon']);
    }

    $tag = $link !== null ? 'a' : 'span';
    $el = _badge_span(['badge', $class_name], $items, $tag);

    if ($link !== null && ($link['href'] ?? '') !== '') {
        $el->setAttribute('href', $link['href']);
        $el->setAttribute('target', $link['target'] ?? '_blank');

        if (($link['rel'] ?? '') !== '') {
            $el->setAttribute('rel', $link['rel']);
        }
    }

    foreach ($state_classes as $class) {
        $el->addClass($class);
    }

    return $el;
}

function _badge_text_span(string $text): CTag
{
    return (new CTag('span', true))
        ->addClass('badge-text')
        ->addItem($text);
}

function _badge_span(array $class_names, array $items, string $tag = 'span'): CTag
{
    $el = new CTag($tag, true);

    foreach ($class_names as $class_name) {
        $el->addClass($class_name);
    }

    if ($items !== []) {
        $el->addItem($items);
    }

    return $el;
}
