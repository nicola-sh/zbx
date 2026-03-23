<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

use Modules\HostOverview\Includes\CWidgetFieldBadgesList;

// Backwards compatibility
// The ZBX_STYLE_COLOR_PICKER constant disappeared in Zabbix 7.4
$color_picker_class = defined('ZBX_STYLE_COLOR_PICKER') ? ZBX_STYLE_COLOR_PICKER : null;

$form = new CWidgetFormView($data);

$form
    ->addField(
        new CWidgetFieldMultiSelectHostView($data['fields']['hostid'])
    )
    ->addField(
        new CWidgetFieldCheckBoxListView($data['fields']['metrics_show'])
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Badges')))
            ->addItem(getBadgesListView($data['fields']['badges']))
            ->addItem(getBadgeHostnameLinkViews($form, $data['fields']['badge_hostname_link']))
            ->addItem(getBadgeUptimeItemViews($form, $data['fields']['badge_uptime_item_name']))
            ->addItem(getBadgeProblemsScopeViews($form, $data['fields']['badge_problems_scope']))
            ->addField(
                new CWidgetFieldRadioButtonListView($data['fields']['badge_size'])
            )
            ->addItem(getFreshnessThresholdViews($form, $data['fields']))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_show_zero'],
                'Keep the Problems badge visible even when the selected host has zero active problems.'
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_pulse'],
                'Animate the problems badge with a pulsing effect when there are active problems.'
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Processor, Memory and Load')))
            ->addItem(getItemNameView($form, $data['fields']['item_name_cpu'],
                'Enter the exact CPU item name, for example "CPU utilization". Partial names are only used when they match one item uniquely; otherwise the widget shows No data.'
            ))
            ->addItem(getItemNameView($form, $data['fields']['item_name_ram'],
                'Enter the exact memory item name, for example "Memory utilization". Partial names are only used when they match one item uniquely; otherwise the widget shows No data.'
            ))
            ->addItem(getItemNameView($form, $data['fields']['item_name_load'],
                'Enter the exact load item name, for example "Load average (5m avg)". The widget converts that value using the Load High setting below. Partial names are only used when they match one item uniquely; otherwise the widget shows No data.'
            ))
            ->addField(
                new CWidgetFieldIntegerBoxView($data['fields']['load_high'])
            )
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Swap')))
            ->addItem(getItemNameView($form, $data['fields']['item_name_swap'],
                'Enter the exact swap item name, for example "Free swap space in %". Partial names are only used when they match one item uniquely; otherwise the widget shows No data.'
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['item_swap_invert'],
                'Enable if the swap item reports free % instead of used %. The value will be inverted (100 − value).'
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Interfaces')))
            ->addItem(getPatternView($form, $data['fields']['item_name_interface'],
                'Use * as wildcard. First * = interface name, second * = direction. Direction is automatically labeled RX (received/in) or TX (sent/out).'
            ))
            ->addItem(getPatternView($form, $data['fields']['interfaces_exclude'],
                'Comma-separated list of interface names to hide. Wildcards * and ? are supported.'
            ))
            ->addItem(getInterfaceHighViews($form, $data['fields']))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Disk utilization')))
            ->addItem(getPatternView($form, $data['fields']['item_name_disk'],
                'Use * as wildcard for the disk name.'
            ))
            ->addItem(getPatternView($form, $data['fields']['disks_exclude'],
                'Comma-separated list of disk names to hide. Wildcards * and ? are supported.'
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Partitions')))
            ->addItem(getPatternView($form, $data['fields']['item_name_partition'],
                'Use * as wildcard for the partition/mount path.'
            ))
            ->addItem(getPatternView($form, $data['fields']['partitions_exclude'],
                'Comma-separated list of partition paths to hide. Wildcards * and ? are supported.'
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Style')))
            ->addField(
                new CWidgetFieldRadioButtonListView($data['fields']['color_scheme'])
            )
            ->addItem(getThresholdHighViews($form, $data['fields']))
            ->addItem(getThresholdMediumViews($form, $data['fields']))
            ->addField(
                new CWidgetFieldColorView($data['fields']['th_color_3'])
            )
            ->addField(
                new CWidgetFieldColorView($data['fields']['fill_color'])
            )
            ->addField(
                new CWidgetFieldRadioButtonListView($data['fields']['corners'])
            )
            ->addField(
                new CWidgetFieldRadioButtonListView($data['fields']['label_length'])
            )
            ->addField(
                new CWidgetFieldSelectView($data['fields']['bar_height'])
            )
    )
    ->includeJsFile('widget.edit.js')
    ->addJavaScript('form.init(' . json_encode([
        'color_picker_class' => $color_picker_class,
        'badge_type_options' => getBadgeTypeOptions(),
    ], JSON_THROW_ON_ERROR) . ');')
    ->addItem(new CTag('style', true,
        '.badge-lane { background: rgba(0, 0, 0, 0.15); border-radius: 4px; padding: 8px; min-height: 52px; }'
        . '.badge-lane-rows { min-height: 28px; }'
        . '.badge-add-wrap { position: relative; display: inline-block; margin-top: 4px; }'
        . '.badge-lane .js-badge-add { margin-top: 0; }'
        . '.badge-add-wrap .js-badge-add[aria-expanded="true"] { font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }'
        . '.badge-add-menu { position: absolute; left: 0; top: calc(100% + 4px); z-index: 10; display: flex; min-width: 160px; flex-direction: column; padding: 4px 0; color: var(--badge-add-menu-fg, CanvasText); background: var(--badge-add-menu-bg, Canvas); border: 1px solid var(--badge-add-menu-border, rgba(127, 127, 127, 0.35)); border-radius: 4px; box-shadow: 0 10px 24px var(--badge-add-menu-shadow, rgba(0, 0, 0, 0.18)); }'
        . '.badge-add-menu[hidden] { display: none; }'
        . '.badge-add-menu .js-badge-add-option { display: flex; align-items: center; box-sizing: border-box; width: 100%; min-height: 30px; padding: 6px 10px; color: inherit; font: inherit; line-height: 1.2; text-align: left; text-decoration: none; white-space: nowrap; background: transparent; border: 0; border-radius: 0; appearance: none; cursor: pointer; }'
        . '.badge-add-menu .js-badge-add-option:hover { background: var(--badge-add-menu-hover, rgba(127, 127, 127, 0.12)); }'
        . '.badge-add-menu .badge-add-empty { display: block; padding: 5px 10px; opacity: 0.7; white-space: nowrap; }'
        . '.badge-row { display: flex; gap: 10px; align-items: center; margin-bottom: 6px; }'
        . '.badge-row:last-child { margin-bottom: 0; }'
        . '.badge-row.is-dragging { opacity: 0.55; }'
        . '.badge-row .badge-row-type { min-width: 110px; font-weight: 600; }'
        . '.badge-row input[type="text"] { min-width: 120px; }'
        . '.badge-row .js-badge-drag { cursor: grab; user-select: none; color: #768d99; font-weight: bold; padding: 0 4px; }'
        . '.badge-row .js-badge-drag:active { cursor: grabbing; }'
        . '.badge-row .js-badge-drag svg { display: block; width: 16px; height: 16px; }'
        . '.badge-row .js-badge-remove { flex-shrink: 0; }'
    ))
    ->show();

function getItemNameView(CWidgetFormView $form, $field, string $hint = ''): array
{
    $view = $form->registerField(new CWidgetFieldTextBoxView($field));
    $label = new CLabel($field->getLabel(), $field->getName());

    if ($hint === '') {
        $hint = 'Prefer the exact item name. Partial names are only used when they match one item uniquely; otherwise the widget shows No data.';
    }
    $label->addItem(makeHelpIcon($hint));

    return [
        $label,
        new CFormField($view->getView()),
    ];
}

function getPatternView(CWidgetFormView $form, $field, string $hint = ''): array
{
    $view = $form->registerField(new CWidgetFieldTextBoxView($field));
    $label = new CLabel($field->getLabel(), $field->getName());

    if ($hint !== '') {
        $label->addItem(makeHelpIcon($hint));
    }

    return [
        $label,
        new CFormField($view->getView()),
    ];
}

function getCheckBoxView(CWidgetFormView $form, $field, string $hint = ''): array
{
    $view = $form->registerField(new CWidgetFieldCheckBoxView($field));
    $label = new CLabel($field->getLabel(), $field->getName());

    if ($hint !== '') {
        $label->addItem(makeHelpIcon($hint));
    }

    return [
        $label,
        new CFormField($view->getView()),
    ];
}

function getSelectView(CWidgetFormView $form, $field, string $hint = ''): array
{
    $view = $form->registerField(new CWidgetFieldSelectView($field));
    $label = new CLabel($field->getLabel(), $field->getName());

    if ($hint !== '') {
        $label->addItem(makeHelpIcon($hint));
    }

    return [
        $label,
        new CFormField($view->getView()),
    ];
}

function getInterfaceHighViews(CWidgetFormView $form, array $fields): array
{
    $interfaces_unit = $form->registerField(
        new CWidgetFieldRadioButtonListView($fields['interfaces_unit'])
    );
    $interfaces_high = $form->registerField(new CWidgetFieldIntegerBoxView($fields['interfaces_high']));

    return [
        new CLabel(_('Interfaces High'), 'interfaces_unit'),
        new CFormField(new CHorList([
            $interfaces_high->getView(),
            $interfaces_unit->getView(),
        ])),
    ];
}

function getThresholdHighViews(CWidgetFormView $form, array $fields): array
{
    $th_num_1 = $form->registerField(
        new CWidgetFieldIntegerBoxView($fields['th_num_1'])
    );
    $th_color_1 = $form->registerField(
        new CWidgetFieldColorView($fields['th_color_1'])
    );

    return [
        new CLabel(_('High'), 'th_num_1'),
        new CFormField(new CHorList([
            $th_num_1->getView(),
            $th_color_1->getView(),
        ])),
    ];
}

function getThresholdMediumViews(CWidgetFormView $form, array $fields): array
{
    $th_num_2 = $form->registerField(
        new CWidgetFieldIntegerBoxView($fields['th_num_2'])
    );
    $th_color_2 = $form->registerField(
        new CWidgetFieldColorView($fields['th_color_2'])
    );

    return [
        new CLabel(_('Medium'), 'th_num_2'),
        new CFormField(new CHorList([
            $th_num_2->getView(),
            $th_color_2->getView(),
        ])),
    ];
}

function getBadgeHostnameLinkViews(CWidgetFormView $form, $field): array
{
    return getSelectView(
        $form,
        $field,
        _('Choose where the Hostname badge should open when clicked.')
    );
}

function getBadgeUptimeItemViews(CWidgetFormView $form, $field): array
{
    return getItemNameView(
        $form,
        $field,
        _('Enter the exact uptime item name, for example "System uptime". Partial names are only used when they match one item uniquely; otherwise the badge shows —.')
    );
}

function getBadgeProblemsScopeViews(CWidgetFormView $form, $field): array
{
    return getSelectView(
        $form,
        $field,
        _('Choose whether the Problems badge counts any active problems or only unacknowledged ones.')
    );
}

function getFreshnessThresholdViews(CWidgetFormView $form, array $fields): array
{
    $freshness_warn = $form->registerField(
        new CWidgetFieldIntegerBoxView($fields['freshness_warn'])
    );
    $freshness_stale = $form->registerField(
        new CWidgetFieldIntegerBoxView($fields['freshness_stale'])
    );

    $label = new CLabel(_('Liveliness thresholds'), 'freshness_warn');
    $label->addItem(makeHelpIcon(
        _('Age in seconds since the host last reported data. Warn applies first, then Stale.')
    ));

    return [
        $label,
        new CFormField(new CHorList([
            new CSpan(_('Warn')),
            $freshness_warn->getView(),
            new CSpan(_('Stale')),
            $freshness_stale->getView(),
        ])),
    ];
}

function getBadgeTypeOptions(): array
{
    $options = [];

    foreach (CWidgetFieldBadgesList::BADGE_TYPE_LABELS as $value => $label) {
        $options[] = [
            'value' => (string) $value,
            'label' => _($label),
        ];
    }

    return $options;
}

function getBadgesListView(CWidgetFieldBadgesList $field): array
{
    $badges = $field->getBadges();
    $left_rows = (new CDiv())
        ->addClass('badge-lane-rows')
        ->addClass('js-badge-lane-rows')
        ->setAttribute('data-side', CWidgetFieldBadgesList::SIDE_LEFT);
    $right_rows = (new CDiv())
        ->addClass('badge-lane-rows')
        ->addClass('js-badge-lane-rows')
        ->setAttribute('data-side', CWidgetFieldBadgesList::SIDE_RIGHT);

    foreach ($badges as $badge) {
        $side = $badge['side'] ?? CWidgetFieldBadgesList::SIDE_LEFT;
        $row = createBadgeRow($badge);

        if ($side === CWidgetFieldBadgesList::SIDE_RIGHT) {
            $right_rows->addItem($row);
        }
        else {
            $left_rows->addItem($row);
        }
    }

    $left_lane = createBadgeLane(
        CWidgetFieldBadgesList::SIDE_LEFT,
        $left_rows,
        (new CTag('input'))
            ->setAttribute('type', 'hidden')
            ->setAttribute('name', 'badges')
            ->setAttribute('id', 'badges-json')
            ->setAttribute('value', json_encode($badges))
    );
    $right_lane = createBadgeLane(CWidgetFieldBadgesList::SIDE_RIGHT, $right_rows);
    $left_lane->addItem(createBadgeRowTemplate());

    return [
        [new CLabel(_('Left'), 'badges-json'), new CFormField($left_lane)],
        [new CLabel(_('Right')), new CFormField($right_lane)],
    ];
}

function createBadgeRowTemplate(): CTag
{
    return (new CTag('template', true))
        ->setAttribute('id', 'badge-row-template')
        ->addItem(createBadgeRow([
            'type' => CWidgetFieldBadgesList::BADGE_TEXT,
            'text' => '',
            'url' => '',
        ]));
}

function createBadgeLane(string $side, CDiv $rows, ?CTag $hidden_input = null): CDiv
{
    $add_name = $side === CWidgetFieldBadgesList::SIDE_RIGHT ? 'badge_add_right' : 'badge_add_left';

    return (new CDiv())
        ->addClass('badge-lane')
        ->setAttribute('data-side', $side)
        ->addItem($hidden_input)
        ->addItem($rows)
        ->addItem(
            (new CDiv())
                ->addClass('badge-add-wrap')
                ->addItem(
                    (new CButton($add_name, _('Add')))
                        ->addClass('btn-link')
                        ->addClass('js-badge-add')
                        ->setAttribute('data-side', $side)
                        ->setAttribute('aria-haspopup', 'true')
                        ->setAttribute('aria-expanded', 'false')
                )
        );
}

function createBadgeRow(array $badge): CDiv
{
    $type = (int) ($badge['type'] ?? CWidgetFieldBadgesList::BADGE_HOSTNAME);
    $type_label = CWidgetFieldBadgesList::BADGE_TYPE_LABELS[$type] ?? CWidgetFieldBadgesList::BADGE_TYPE_LABELS[CWidgetFieldBadgesList::BADGE_HOSTNAME];
    $drag_handle = (new CSpan())
        ->addClass('js-badge-drag')
        ->setAttribute('draggable', 'true')
        ->setAttribute('title', _('Drag to reorder'));
    $drag_handle->addItem(
        (new CTag('svg', true))
            ->setAttribute('xmlns', 'http://www.w3.org/2000/svg')
            ->setAttribute('width', '24')
            ->setAttribute('height', '24')
            ->setAttribute('viewBox', '0 0 24 24')
            ->setAttribute('fill', 'none')
            ->setAttribute('stroke', 'currentColor')
            ->setAttribute('stroke-width', '2')
            ->setAttribute('stroke-linecap', 'round')
            ->setAttribute('stroke-linejoin', 'round')
            ->addClass('lucide lucide-grip-vertical-icon lucide-grip-vertical')
            ->addItem((new CTag('circle', true))->setAttribute('cx', '9')->setAttribute('cy', '12')->setAttribute('r', '1'))
            ->addItem((new CTag('circle', true))->setAttribute('cx', '9')->setAttribute('cy', '5')->setAttribute('r', '1'))
            ->addItem((new CTag('circle', true))->setAttribute('cx', '9')->setAttribute('cy', '19')->setAttribute('r', '1'))
            ->addItem((new CTag('circle', true))->setAttribute('cx', '15')->setAttribute('cy', '12')->setAttribute('r', '1'))
            ->addItem((new CTag('circle', true))->setAttribute('cx', '15')->setAttribute('cy', '5')->setAttribute('r', '1'))
            ->addItem((new CTag('circle', true))->setAttribute('cx', '15')->setAttribute('cy', '19')->setAttribute('r', '1'))
    );
    $type_badge = (new CSpan(_($type_label)))
        ->addClass('badge-row-type');

    $show_text = in_array($type, [CWidgetFieldBadgesList::BADGE_TEXT, CWidgetFieldBadgesList::BADGE_LINK]);
    $show_url = ($type === CWidgetFieldBadgesList::BADGE_LINK);
    $text_input = (new CTextBox('', $badge['text'] ?? ''))
        ->setAttribute('placeholder', _('Display text'))
        ->addClass('js-badge-text');

    if (!$show_text) {
        $text_input->setAttribute('style', 'display: none');
    }

    $url_input = (new CTextBox('', $badge['url'] ?? ''))
        ->setAttribute('placeholder', _('https://example.com or /path'))
        ->addClass('js-badge-url');

    if (!$show_url) {
        $url_input->setAttribute('style', 'display: none');
    }

    $remove_btn = (new CButton('', _('Remove')))
        ->addClass('btn-link')
        ->addClass('js-badge-remove');

    return (new CDiv())
        ->addClass('badge-row')
        ->setAttribute('data-type', (string) $type)
        ->addItem($drag_handle)
        ->addItem($type_badge)
        ->addItem($text_input)
        ->addItem($url_input)
        ->addItem($remove_btn);
}
