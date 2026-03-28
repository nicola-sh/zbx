<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Includes;

use CDiv;
use CTag;

// =============================================================================
// Metric cell rendering functions
// =============================================================================

function render_metric_row(array $row): CDiv
{
    $metric_row = (new CDiv())->addClass('metric-row');
    $list = (new CDiv())->addClass('metric-list');

    if (($row['kind'] ?? 'single') !== 'single') {
        $list->addClass('metric-list-multi');
    }

    $metric_row->addItem(
        (new CTag('aside', true))
            ->addClass('metric-label')
            ->addItem(_render_row_label($row))
    );

    $cells = $row['cells'] ?? [];

    if ($cells === []) {
        $list->addItem(
            (new CTag('span', true))
                ->addClass('metric-empty')
                ->addItem(_('No data'))
        );
    } else {
        foreach ($cells as $cell) {
            $list->addItem(render_metric_cell($cell));
        }
    }

    return $metric_row->addItem($list);
}

function render_metric_cell(array $cell): CDiv
{
    $state = (string) ($cell['state'] ?? 'ok');
    $metric_cell = (new CDiv())
        ->addClass('metric-cell')
        ->setAttribute('data-cell-id', (string) ($cell['cell_id'] ?? ''))
        ->setAttribute('data-cell-label', (string) ($cell['cell_label'] ?? ''));

    $sparkline = $cell['sparkline'] ?? null;
    $sparkline_spec = _encode_sparkline_spec($sparkline['spec'] ?? null);

    if (($sparkline['enabled'] ?? false) && $sparkline_spec !== null) {
        $metric_cell->setAttribute('data-sparkline-title', (string) ($sparkline['title'] ?? ''));
        $metric_cell->setAttribute('data-sparkline-spec', $sparkline_spec);
    }

    if ($state === 'empty') {
        $metric_cell->addClass('is-empty');
    }

    return $metric_cell
        ->addItem(_render_metric_bar($cell))
        ->addItem(_render_metric_text($cell));
}

// =============================================================================
// Internal helpers (prefixed with _)
// =============================================================================

function _render_row_label(array $row): CTag
{
    $label = (string) ($row['label'] ?? '');
    $link = $row['label_link'] ?? null;

    if ($link !== null) {
        $label_link = (new CTag('a', true))
            ->addClass('metric-link')
            ->addClass('metric-label-link')
            ->addClass('js-metric-link')
            ->addItem($label);

        _apply_link_attrs($label_link, $link, _('Open latest data'));

        return $label_link;
    }

    return (new CTag('span', true))->addItem($label);
}

function _render_metric_text(array $cell): CTag
{
    $link = (new CTag('a', true))
        ->addClass('metric-link')
        ->addClass('metric-value-link')
        ->addClass('js-metric-link')
        ->addClass(($cell['state'] ?? 'ok') === 'empty' ? 'metric-empty' : 'metric-text')
        ->setAttribute('data-link-role', 'value')
        ->addItem((string) ($cell['display']['text'] ?? ''));

    _apply_link_attrs($link, $cell['links']['latest_data'] ?? null, _('Open latest data'));

    return $link;
}

function _render_metric_bar(array $cell): CDiv
{
    $state = (string) ($cell['state'] ?? 'ok');
    $bar = (new CDiv())->addClass('metric-bar');

    if ($state === 'empty') {
        $bar->setAttribute('hidden', 'hidden');
    }

    $fill = (new CDiv())->addClass('metric-fill');
    $bar_style = _build_bar_style($cell);

    if ($bar_style !== '') {
        $fill->setAttribute('style', $bar_style);
    }

    $bar->addItem($fill);

    return $bar;
}

function _build_bar_style(array $cell): string
{
    $style = [];
    $percent = $cell['bar']['percent'] ?? null;
    $color = $cell['bar']['color'] ?? null;

    if ($percent !== null && $percent !== '') {
        $style[] = 'width: ' . max(0, min(100, (int) round((float) $percent))) . '%';
    }

    if (is_string($color) && $color !== '') {
        $style[] = 'background-color: ' . $color;
    }

    return implode('; ', $style);
}

function _apply_link_attrs(CTag $element, ?array $link, ?string $title = null): void
{
    if ($link !== null && ($link['href'] ?? '') !== '') {
        $element->setAttribute('href', $link['href']);
        $element->setAttribute('target', $link['target'] ?? '_blank');

        if (($link['rel'] ?? '') !== '') {
            $element->setAttribute('rel', $link['rel']);
        }

        if ($title !== null && $title !== '') {
            $element->setAttribute('title', $title);
        }

        return;
    }

    $element->addClass('is-disabled');
}

function _encode_sparkline_spec($sparkline_spec): ?string
{
    if (!is_array($sparkline_spec)) {
        return null;
    }

    $json = json_encode(
        $sparkline_spec,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    return is_string($json) && $json !== '' ? $json : null;
}
