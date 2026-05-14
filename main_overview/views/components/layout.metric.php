<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainOverview\Includes;

use CDiv;
use CLinkAction;
use CMenuPopupHelper;
use CTag;

// =============================================================================
// Metric cell rendering functions
// =============================================================================

function render_metric_row(array $row): CDiv
{
    $is_multi = ($row['kind'] ?? 'single') !== 'single';
    $metric_row = (new CDiv())
        ->addClass('metric-row')
        ->addClass($is_multi ? 'metric-row-multi' : 'metric-row-single');
    $list = (new CDiv())->addClass('metric-list');

    $metric_row->addItem(
        (new CTag('div', true))
            ->addClass('metric-label')
            ->addItem(_render_row_label($row))
    );

    $cells = $row['cells'] ?? [];

    if ($cells === []) {
        $list->addItem(
            (new CTag('span', true))
                ->addClass('empty')
                ->addItem(_('No data'))
        );
    } else {
        foreach ($cells as $cell) {
            $list->addItem(render_metric_cell($cell, $is_multi));
        }
    }

    return $metric_row->addItem($list);
}

function render_metric_cell(array $cell, bool $is_multi = false): CDiv
{
    $state = (string) ($cell['state'] ?? 'ok');
    $metric_cell = (new CDiv())
        ->addClass('metric-cell')
        ->setAttribute('data-cell-id', (string) ($cell['cell_id'] ?? ''))
        ->setAttribute('data-cell-label', (string) ($cell['cell_label'] ?? ''));

    $sparkline = $cell['sparkline'] ?? null;
    $sparkline_spec = _encode_sparkline_spec($sparkline['spec'] ?? null);

    if (($sparkline['enabled'] ?? false) && $sparkline_spec !== null) {
        $metric_cell->setAttribute('data-sparkline-spec', $sparkline_spec);
    }

    if ($state === 'empty') {
        $metric_cell->addClass('is-empty');
    }

    return $metric_cell
        ->addItem(_render_metric_bar($cell))
        ->addItem(_render_metric_text($cell, $is_multi));
}

// =============================================================================
// Internal helpers (prefixed with _)
// =============================================================================

function _render_row_label(array $row): CTag
{
    $label = (string) ($row['label'] ?? '');

    return (new CTag('span', true))->addItem($label);
}

function _render_metric_text(array $cell, bool $is_multi = false): CTag
{
    $latest_data_link = $cell['links']['latest_data'] ?? null;
    $prefix = trim((string) ($cell['display']['prefix'] ?? ''));
    $value_text = (string) ($cell['display']['value_text'] ?? '');
    $content = (new CTag('span', true))
        ->addClass('metric-value-content');

    if (! $is_multi) {
        $content->addClass('metric-value-single');
    }

    if ($prefix !== '') {
        $content->addItem(
            (new CTag('span', true))
                ->addClass('metric-value-prefix')
                ->addItem($prefix)
        );
    }

    $text = _render_metric_action($cell, $latest_data_link, $value_text !== ''
        ? $value_text
        : (string) ($cell['display']['text'] ?? ''));

    if (($cell['state'] ?? 'ok') === 'empty') {
        $text->addClass('empty');
    }

    $content->addItem($text);

    return $content;
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
}

function _render_metric_action(array $cell, ?array $latest_data_link, string $text): CTag|CLinkAction
{
    $itemid = trim((string) (($cell['item_ref']['itemid'] ?? '')));
    $backurl = is_array($latest_data_link) ? (string) ($latest_data_link['href'] ?? '') : '';

    if ($itemid !== '' && $backurl !== '') {
        return (new CLinkAction(
            (new CTag('span', true))
                ->addClass('metric-value-label')
                ->addItem($text)
        ))
            ->addClass('metric-value-link')
            ->setAttribute('title', _('Open item menu'))
            ->setMenuPopup(CMenuPopupHelper::getItem([
                'itemid' => $itemid,
                'context' => 'host',
                'backurl' => $backurl,
            ]));
    }

    $tag = _has_link_href($latest_data_link) ? 'a' : 'span';
    $element = (new CTag($tag, true))
        ->addClass('metric-value-link')
        ->addItem(
            (new CTag('span', true))
                ->addClass('metric-value-label')
                ->addItem($text)
        );

    if ($tag === 'a') {
        _apply_link_attrs($element, $latest_data_link, _('Open latest data'));
    }

    return $element;
}

function _has_link_href(?array $link): bool
{
    return $link !== null && ($link['href'] ?? '') !== '';
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
