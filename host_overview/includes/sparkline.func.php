<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

require_once __DIR__ . '/icons.func.php';

use CDiv;
use CLinkAction;
use CTag;

// =============================================================================
// Sparkline overlay rendering
// =============================================================================

function render_sparkline_overlay(): CDiv
{
    $overlay = (new CDiv())
        ->addClass('sparkline-overlay')
        ->setAttribute('data-host-overview-role', 'sparkline')
        ->setAttribute('aria-hidden', 'true')
        ->setAttribute('aria-label', _('Sparkline viewer'))
        ->setAttribute('role', 'dialog');

    $toolbar = (new CDiv())->addClass('toolbar');
    $left = (new CDiv())->addClass('left');
    $right = (new CDiv())->addClass('right sparkline-periods');

    $left->addItem(
        _sparkline_link(
            [render_icon('back', ['badge-icon']), (new CTag('span', true))->addItem(_('Back'))],
            ['badge', 'link', 'js-sparkline-close'],
            ['aria-label' => _('Back to overview')]
        )
    );

    foreach (['1h', '3h', '12h', '1d', '3d', '1w', '30d'] as $period) {
        $button = _sparkline_link([$period], ['badge', 'link'], ['data-period' => $period]);

        if ($period === '1h') {
            $button->addClass('active');
            $button->setAttribute('aria-current', 'true');
        }

        $right->addItem($button);
    }

    $toolbar->addItem($left);
    $toolbar->addItem($right);

    $stage = (new CDiv())->addClass('sparkline-stage');
    $stage->addItem((new CTag('canvas', true))->addClass('sparkline-canvas'));

    $labels = (new CDiv())->addClass('sparkline-y-labels');
    $labels->addItem((new CTag('span', true))->addItem('100%'));
    $labels->addItem((new CTag('span', true))->addItem('50%'));
    $labels->addItem((new CTag('span', true))->addItem('0%'));

    $stage->addItem($labels);
    $overlay->addItem($toolbar);
    $overlay->addItem($stage);

    return $overlay;
}

// =============================================================================
// Internal helper
// =============================================================================

function _sparkline_link(array $items, array $classes, array $attrs = []): CLinkAction
{
    $link = new CLinkAction($items);

    foreach ($classes as $class) {
        $link->addClass($class);
    }

    foreach ($attrs as $name => $value) {
        $link->setAttribute($name, $value);
    }

    return $link;
}
