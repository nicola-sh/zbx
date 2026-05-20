<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\AOverview\Includes;

use CTag;

// =============================================================================
// Icon definitions
// =============================================================================

const ICON_SVG_ATTRS = [
    'xmlns' => 'http://www.w3.org/2000/svg',
    'width' => '24',
    'height' => '24',
    'viewBox' => '0 0 24 24',
    'fill' => 'none',
    'stroke' => 'currentColor',
    'stroke-width' => '2',
    'stroke-linecap' => 'round',
    'stroke-linejoin' => 'round',
];

const ICON_PATHS = [
    'link' => [
        ['tag' => 'path', 'attrs' => ['d' => 'M13 5H19V11']],
        ['tag' => 'path', 'attrs' => ['d' => 'M19 5L5 19']],
    ],
    'tags' => [
        ['tag' => 'path', 'attrs' => ['d' => 'M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z']],
        ['tag' => 'path', 'attrs' => ['d' => 'M7 7h.01']],
    ],
    'uptime' => [
        ['tag' => 'path', 'attrs' => ['d' => 'M12 6v6l1.56.78']],
        ['tag' => 'path', 'attrs' => ['d' => 'M13.227 21.925a10 10 0 1 1 8.767-9.588']],
        ['tag' => 'path', 'attrs' => ['d' => 'm14 18 4-4 4 4']],
        ['tag' => 'path', 'attrs' => ['d' => 'M18 22v-8']],
    ],
    'freshness' => [
        ['tag' => 'path', 'attrs' => ['d' => 'M16.247 7.761a6 6 0 0 1 0 8.478']],
        ['tag' => 'path', 'attrs' => ['d' => 'M19.075 4.933a10 10 0 0 1 0 14.134']],
        ['tag' => 'path', 'attrs' => ['d' => 'M4.925 19.067a10 10 0 0 1 0-14.134']],
        ['tag' => 'path', 'attrs' => ['d' => 'M7.753 16.239a6 6 0 0 1 0-8.478']],
        ['tag' => 'circle', 'attrs' => ['cx' => '12', 'cy' => '12', 'r' => '2']],
    ],
    'back' => [
        ['tag' => 'path', 'attrs' => ['d' => 'M15 18l-6-6 6-6']],
        ['tag' => 'path', 'attrs' => ['d' => 'M9 12h10']],
    ],
    'maintenance' => [
        ['tag' => 'path', 'attrs' => ['d' => 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z']],
    ],
    'more' => [
        ['tag' => 'circle', 'attrs' => ['cx' => '12', 'cy' => '12', 'r' => '1']],
        ['tag' => 'circle', 'attrs' => ['cx' => '19', 'cy' => '12', 'r' => '1']],
        ['tag' => 'circle', 'attrs' => ['cx' => '5', 'cy' => '12', 'r' => '1']],
    ],
    'grip-vertical' => [
        ['tag' => 'circle', 'attrs' => ['cx' => '9', 'cy' => '12', 'r' => '1']],
        ['tag' => 'circle', 'attrs' => ['cx' => '9', 'cy' => '5', 'r' => '1']],
        ['tag' => 'circle', 'attrs' => ['cx' => '9', 'cy' => '19', 'r' => '1']],
        ['tag' => 'circle', 'attrs' => ['cx' => '15', 'cy' => '12', 'r' => '1']],
        ['tag' => 'circle', 'attrs' => ['cx' => '15', 'cy' => '5', 'r' => '1']],
        ['tag' => 'circle', 'attrs' => ['cx' => '15', 'cy' => '19', 'r' => '1']],
    ],
];

// =============================================================================
// Icon rendering function
// =============================================================================

function render_icon(string $name, array $classes = []): CTag
{
    $svg = new CTag('svg', true);

    foreach (ICON_SVG_ATTRS as $attr => $value) {
        $svg->setAttribute($attr, $value);
    }

    foreach ($classes as $class_name) {
        if (is_string($class_name) && $class_name !== '') {
            $svg->addClass($class_name);
        }
    }

    foreach (ICON_PATHS[$name] ?? [] as $node) {
        $element = new CTag($node['tag'], true);

        foreach ($node['attrs'] as $attr => $value) {
            $element->setAttribute($attr, $value);
        }

        $svg->addItem($element);
    }

    return $svg;
}
