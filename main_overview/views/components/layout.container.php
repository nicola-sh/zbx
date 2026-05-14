<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainOverview\Includes;

use CDiv;

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
    ]) . ';';

    $container = (new CDiv())
        ->addClass('main-overview-container')
        ->setAttribute('data-main-overview-role', 'overview')
        ->setAttribute('data-main-overview-config', json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT))
        ->setAttribute('style', $style);

    return $container;
}
