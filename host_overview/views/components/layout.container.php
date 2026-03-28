<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

require_once __DIR__ . '/layout.icons.php';
require_once __DIR__ . '/layout.toolbar.php';
require_once __DIR__ . '/layout.metric.php';
require_once __DIR__ . '/layout.sparkline.php';

use CDiv;
use CTag;

// =============================================================================
// Container rendering
// =============================================================================

function render_overview_container(array $config): CDiv
{
    $label_length = (int) ($config['label_length'] ?? WidgetForm::LABELS_FULL);
    $label_width = $label_length === WidgetForm::LABELS_SHORT ? 50 : 90;

    $style = implode('; ', [
        '--bar-height: ' . (int) ($config['bar_height'] ?? WidgetForm::DEFAULT_BAR_HEIGHT) . 'px',
        '--label-width: ' . $label_width . 'px',
        '--sparkline-color: ' . htmlspecialchars(
            '#' . (string) ($config['fill_color'] ?? WidgetForm::DEFAULT_COLOR_FILL)
        ),
    ]) . ';';

    $container = (new CDiv())
        ->addClass('host-overview-container')
        ->setAttribute('data-host-overview-role', 'overview')
        ->setAttribute('style', $style);

    // Icon template for JS.
    $container->addItem(
        (new CTag('template', true))
            ->setAttribute('data-host-overview-icon-template', 'trend-arrow')
            ->addItem(render_icon('trend-arrow', ['dir-arrow']))
    );

    return $container;
}
