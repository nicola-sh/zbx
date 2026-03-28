<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

use CDiv;
use CLinkAction;
use CTag;

const SIDE_LEFT = 'L';
const SIDE_RIGHT = 'R';

// =============================================================================
// Toolbar rendering
// =============================================================================

/**
 * Render a toolbar from left and right badge arrays.
 * Each badge entry should have: ['element' => CTag, 'id' => string, 'hidden' => bool]
 */
function render_toolbar(array $left_badges, array $right_badges): ?CDiv
{
    if ($left_badges === [] && $right_badges === []) {
        return null;
    }

    $toolbar = (new CDiv())->addClass('toolbar');

    if ($left_badges !== []) {
        $left_div = (new CDiv())->addClass('left');
        foreach ($left_badges as $badge) {
            $left_div->addItem(_prepare_badge_element($badge));
        }
        $toolbar->addItem($left_div);
    }

    if ($right_badges !== []) {
        $right_div = (new CDiv())->addClass('right');
        foreach ($right_badges as $badge) {
            $right_div->addItem(_prepare_badge_element($badge));
        }
        $toolbar->addItem($right_div);
    }

    return $toolbar;
}

// =============================================================================
// Patch value builders
// =============================================================================

/**
 * Build patch values for badge dynamic updates.
 * Each badge entry should have: ['id' => string, 'text' => string, 'hidden' => bool, 'state_classes' => array]
 */
function build_badge_patch_values(array $badges): array
{
    $values = [];

    foreach ($badges as $badge) {
        $id = (string) ($badge['id'] ?? '');

        if ($id === '') {
            continue;
        }

        $values[$id] = [
            'text' => (string) ($badge['text'] ?? ''),
            'hidden' => (bool) ($badge['hidden'] ?? false),
            'state_classes' => array_values(array_filter((array) ($badge['state_classes'] ?? []), 'is_string')),
        ];
    }

    return $values;
}

// =============================================================================
// Internal helper
// =============================================================================

function _prepare_badge_element(array $badge): CTag|CLinkAction
{
    /** @var CTag|CLinkAction $element */
    $element = $badge['element'];
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
