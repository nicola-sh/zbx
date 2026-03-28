<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

require_once __DIR__ . '/icons.func.php';
require_once __DIR__ . '/toolbar.func.php';
require_once __DIR__ . '/metric.func.php';
require_once __DIR__ . '/sparkline.func.php';

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

// =============================================================================
// Patch values aggregation
// =============================================================================

function build_all_patch_values(array $badges, array $rows): array
{
    return [
        'badges' => build_badge_patch_values($badges),
        'cells' => build_cell_patch_values($rows),
    ];
}
