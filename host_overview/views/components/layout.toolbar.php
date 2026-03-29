<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

require_once __DIR__ . '/layout.badge.php';

use CDiv;
use CLinkAction;
use CTag;

const SIDE_LEFT = CWidgetFieldBadgesList::SIDE_LEFT;
const SIDE_RIGHT = CWidgetFieldBadgesList::SIDE_RIGHT;

// =============================================================================
// Toolbar rendering
// =============================================================================

/**
 * Render a toolbar from badge models.
 * Each badge model should have: type, text, id, hidden, side, and type-specific fields.
 */
function render_toolbar(array $badges): ?CDiv
{
    if ($badges === []) {
        return null;
    }

    $left_badges = _filter_badges_by_side($badges, SIDE_LEFT);
    $right_badges = _filter_badges_by_side($badges, SIDE_RIGHT);

    if ($left_badges === [] && $right_badges === []) {
        return null;
    }

    $toolbar = (new CDiv())->addClass('toolbar');

    if ($left_badges !== []) {
        $left_div = (new CDiv())->addClass('left');
        foreach ($left_badges as $badge) {
            $left_div->addItem(_render_badge_with_attrs($badge));
        }
        $toolbar->addItem($left_div);
    }

    if ($right_badges !== []) {
        $right_div = (new CDiv())->addClass('right');
        foreach ($right_badges as $badge) {
            $right_div->addItem(_render_badge_with_attrs($badge));
        }
        $toolbar->addItem($right_div);
    }

    return $toolbar;
}

// =============================================================================
// Internal helper
// =============================================================================

function _filter_badges_by_side(array $badges, string $side): array
{
    return array_values(array_filter(
        $badges,
        static fn(array $badge): bool => ($badge['side'] ?? SIDE_LEFT) === $side
    ));
}

function _render_badge_with_attrs(array $badge): CTag|CLinkAction
{
    $element = render_badge($badge);
    $id = (string) ($badge['id'] ?? '');
    $hidden = (bool) ($badge['hidden'] ?? false);

    if ($id !== '') {
        $element->setAttribute('data-badge-id', $id);
    }

    if ($hidden) {
        $element->setAttribute('hidden', 'hidden');
    }

    return $element;
}
