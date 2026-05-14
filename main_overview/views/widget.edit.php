<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

require_once __DIR__ . '/components/layout.icons.php';

use Modules\MainOverview\Includes\CWidgetFieldBadgesList;
use Modules\MainOverview\Includes\WidgetForm;

use function Modules\MainOverview\Includes\render_icon;

// Backwards compatibility
// The ZBX_STYLE_COLOR_PICKER constant disappeared in Zabbix 7.4
$color_picker_class = defined('ZBX_STYLE_COLOR_PICKER') ? ZBX_STYLE_COLOR_PICKER : null;

$form = new CWidgetFormView($data);

$form
    ->addField(
        new CWidgetFieldMultiSelectHostView($data['fields']['hostid'])
    )
    ->addField($data['templateid'] === null
        ? new CWidgetFieldMultiSelectOverrideHostView($data['fields']['override_hostid'])
        : null
    );

$hidden_metrics_wrap = (new CDiv())->addClass('main-overview-hidden-metrics');
$metrics_field_view = (new CWidgetFieldCheckBoxListView($data['fields']['metrics_show']))->setColumns(3);

foreach ($form->registerField($metrics_field_view)->getView() as $metrics_view_part) {
    $hidden_metrics_wrap->addItem($metrics_view_part);
}

$global_appearance_fieldset = (new CWidgetFormFieldsetCollapsibleView(_m('Global: appearance')))
    ->addItem(getCheckBoxView($form, $data['fields']['open_links_same_window'],
        _m('Open metric and badge links in the current browser tab.')
    ))
    ->addFieldsGroup(new CWidgetFieldsGroupView('', [
        new CWidgetFieldRadioButtonListView($data['fields']['color_scheme']),
    ]))
    ->addItem(getThresholdColorView($form, $data['fields']['th_color_1'], _m('Color: high'), 'js-threshold-color-row'))
    ->addItem(getThresholdColorView($form, $data['fields']['th_color_2'], _m('Color: medium'), 'js-threshold-color-row'))
    ->addItem(getThresholdColorView($form, $data['fields']['th_color_3'], _m('Color: normal'), 'js-threshold-color-row'))
    ->addItem(getThresholdColorView($form, $data['fields']['fill_color'], _m('Solid color'), 'js-solid-color-row'))
    ->addFieldsGroup(new CWidgetFieldsGroupView('', [
        new CWidgetFieldRadioButtonListView($data['fields']['corners']),
        new CWidgetFieldRadioButtonListView($data['fields']['label_length']),
        new CWidgetFieldRadioButtonListView($data['fields']['bar_height']),
    ]));

$form
    ->addItem(
        (new CDiv())
            ->addClass('main-overview-add-host-row')
            ->addItem(
                (new CDiv())
                    ->addClass('main-overview-per-host-hint')
                    ->addItem(_m(
                        'After you select one or more hosts, a per-host settings block appears below (metrics, items, thresholds). If no panels appear, click "Refresh host panels".'
                    ))
            )
            ->addItem(
                (new CButton(null, _m('Refresh host panels')))
                    ->addClass('js-ho-refresh-host-panels')
            )
    )
    ->addItem(
        (new CDiv())
            ->addClass('js-host-accordion-mount')
            ->setAttribute('id', 'js-host-accordion-mount')
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_m('Global: badges')))
            ->addItem(getBadgesListView($data['fields']['badges']))
            ->addItem(getBadgeUptimeItemViews($form, $data['fields']['badge_uptime_item_name']))
            ->addItem(getBadgeLivelinessItemViews($form, $data['fields']['badge_liveliness_item_name']))
            ->addItem(getFreshnessThresholdViews($form, $data['fields']))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_hide_acknowledged'],
                _m('Do not count acknowledged problems in the Problems badge tally.')
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_hide_suppressed'],
                _m('Do not count suppressed problems in the Problems badge tally.')
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_pulse'],
                _m('Animate the problems badge when there are active incidents.')
            ))
    )
    ->addFieldset($global_appearance_fieldset)
    ->addItem($hidden_metrics_wrap)
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_m('Host profiles (sync)')))
            ->addClass('js-host-profiles-sync-fieldset')
            ->addItem([
                (new CLabel($data['fields']['host_profiles']->getLabel(), $data['fields']['host_profiles']->getName()))
                    ->addItem(makeHelpIcon(_m(
                        'JSON is rebuilt automatically from the host list when you save. You can still edit it manually if needed.'
                    ))),
                new CFormField(
                    $form->registerField(new CWidgetFieldTextBoxView($data['fields']['host_profiles']))->getView()
                ),
            ])
    )
    ->includeJsFile('widget.edit.js.php')
    ->addJavaScript('form.init(' . json_encode([
        'color_picker_class' => $color_picker_class,
        'badge_type_options' => CWidgetFieldBadgesList::getBadgeTypeOptions(),
        'badge_multiple_types' => CWidgetFieldBadgesList::getMultipleBadgeTypes(),
        'badge_types_with_text' => CWidgetFieldBadgesList::getTextFieldBadgeTypes(),
        'badge_types_with_url' => CWidgetFieldBadgesList::getUrlFieldBadgeTypes(),
        'item_lookup_action' => 'widget.main_overview.lookup',
        'metric_checkbox_rows' => [
            ['value' => (string) WidgetForm::METRIC_CPU, 'label' => _m('CPU')],
            ['value' => (string) WidgetForm::METRIC_RAM, 'label' => _m('Memory')],
            ['value' => (string) WidgetForm::METRIC_LOAD, 'label' => _m('Load')],
            ['value' => (string) WidgetForm::METRIC_SWAP, 'label' => _m('Swap')],
            ['value' => (string) WidgetForm::METRIC_INTERFACES, 'label' => _m('Interfaces')],
            ['value' => (string) WidgetForm::METRIC_DISKS, 'label' => _m('Disk utilization')],
            ['value' => (string) WidgetForm::METRIC_PARTITIONS, 'label' => _m('Partitions')],
        ],
        'lookup_ui' => [
            'test' => _m('Test'),
            'stale_wildcard' => _m('Template or exclusions changed. Click "Test" to refresh the preview.'),
            'stale_single' => _m('Input changed. Click "Test" to refresh the preview.'),
            'pick_host' => _m('Select a host first.'),
            'checking' => _m('Looking up matches…'),
            'lookup_failed' => _m('Could not run the match check.'),
            'lookup_empty_response' => _m('Server returned an empty response.'),
            'lookup_html_error' => _m('Received an HTML page instead of JSON.'),
            'read_response_error' => _m('Could not read the server response.'),
            'exact_fmt' => _m('Exact match: %s.'),
            'unique_partial_fmt' => _m('Single partial match: %s.'),
            'ambiguous_fmt' => _m('Name matches found: %s. Pick an exact item name:'),
            'none_partial' => _m('No exact or single partial match yet. Enter an exact item name:'),
            'none_no_items' => _m('No suitable item names were found.'),
            'enter_name' => _m('Enter an item name for preview.'),
            'refine_candidates' => _m('Narrow the query to shorten the list.'),
            'refine_rows' => _m('Only the first rows are shown. Refine the template to narrow the list.'),
            'apply_fmt' => _m('Inserted exact item name: %s.'),
            'matches_heading_fmt' => _m('Matches (%s)'),
            'filtered_heading' => _m('Excluded by filter'),
            'wildcard_no_disk' => _m('No matching disks.'),
            'wildcard_no_partition' => _m('No matching partitions.'),
            'wildcard_no_interface' => _m('No matching interfaces.'),
            'wildcard_no_default' => _m('No matches found.'),
            'wildcard_invalid_iface' => _m('For interfaces use at least two "*" characters in the template.'),
            'wildcard_invalid_other' => _m('Add at least one "*" character to the template.'),
            'wildcard_too_broad' => _m('Add fixed text around "*" to narrow the match list.'),
            'wildcard_empty_disk' => _m('Enter a template with "*" to preview disks.'),
            'wildcard_empty_partition' => _m('Enter a template with "*" to preview partitions.'),
            'wildcard_empty_interface' => _m('Enter a template with "*" to preview interfaces.'),
            'wildcard_empty_default' => _m('Enter a template with "*" for preview.'),
            'wildcard_empty_single' => _m('Enter an item name for preview.'),
        ],
        'per_host_labels' => [
            'empty' => _m('Pick one or more hosts in the field above.'),
            'section_metrics' => _m('Show metrics'),
            'section_badges_json' => _m('Custom badge list (optional)'),
            'label_badges_json_hint' => _m(
                'Leave empty to use global badges. Paste JSON in the same format as the global list to override badges for this host only.'
            ),
            'section_display' => _m('Display'),
            'section_proc' => _m('CPU, memory, and load'),
            'section_swap' => _m('Swap'),
            'section_if' => _m('Interfaces'),
            'section_disk' => _m('Disk utilization'),
            'section_part' => _m('Partitions'),
            'section_adv' => _m('Extra overrides (JSON)'),
            'label_alias' => _m('Alias'),
            'label_badges' => _m('Badges'),
            'bp_summary' => _m('Next to the name (summary)'),
            'bp_detail' => _m('Details only'),
            'label_cpu' => _m('Item: CPU'),
            'label_ram' => _m('Item: memory'),
            'label_load' => _m('Item: load'),
            'label_load_high' => _m('Load ceiling'),
            'label_swap' => _m('Item: swap'),
            'label_swap_inv' => _m('Invert swap'),
            'label_iface' => _m('Interface template'),
            'label_iface_ex' => _m('Interface filter'),
            'label_iface_high' => _m('Interface ceiling'),
            'label_iface_unit' => _m('Interface unit'),
            'label_disk' => _m('Disk template'),
            'label_disk_ex' => _m('Disk filter'),
            'label_part' => _m('Partition template'),
            'label_part_ex' => _m('Partition filter'),
            'label_extras' => _m('Extra JSON fields (merged with overrides)'),
            'placeholder_extras' => _m('Example: {"metrics_show":["0","1"]}'),
        ],
    ], JSON_THROW_ON_ERROR) . ');')
    ->show();

function getItemNameView(CWidgetFormView $form, $field, string $hint = '', ?string $metric_value = null): array
{
    $view = $form->registerField(new CWidgetFieldTextBoxView($field));
    $label = new CLabel($field->getLabel(), $field->getName());
    $field_view = $view->getView();

    if ($hint === '') {
        $hint = _m(
            'Prefer an exact item name. A partial name is used only when it matches exactly one item; otherwise "No data" is shown.'
        );
    }
    $label->addItem(makeHelpIcon($hint));

    if ($metric_value !== null) {
        $field_view = (new CDiv())
            ->addClass('item-match-assistant')
            ->addClass('js-item-match-assistant')
            ->setAttribute('data-field-name', $field->getName())
            ->setAttribute('data-metric-value', $metric_value)
            ->addItem(
                (new CDiv())
                    ->addClass('item-match-controls')
                    ->addItem($view->getView())
                    ->addItem(
                        (new CButton(null, _m('Test')))
                            ->addClass('js-item-match-test')
                    )
            )
            ->addItem(
                (new CDiv())
                    ->addClass('item-match-preview')
                    ->addClass('js-item-match-preview')
                    ->setAttribute('hidden', 'hidden')
            );
    }

    return [
        $label,
        new CFormField($field_view),
    ];
}

function getPatternView(CWidgetFormView $form, $field, string $hint = '', ?array $assistant = null): array
{
    $view = $form->registerField(new CWidgetFieldTextBoxView($field));
    $label = new CLabel($field->getLabel(), $field->getName());
    $field_view = $view->getView();

    if ($hint !== '') {
        $label->addItem(makeHelpIcon($hint));
    }

    if ($assistant !== null) {
        $field_view = (new CDiv())
            ->addClass('item-match-assistant')
            ->addClass('js-item-match-assistant')
            ->setAttribute('data-field-name', $field->getName())
            ->setAttribute('data-metric-value', (string) ($assistant['metric_value'] ?? ''))
            ->setAttribute('data-lookup-mode', (string) ($assistant['mode'] ?? 'wildcard'))
            ->setAttribute('data-metric-type', (string) ($assistant['metric_type'] ?? ''));

        if (array_key_exists('exclude_field_name', $assistant)) {
            $field_view->setAttribute('data-exclude-field-name', (string) $assistant['exclude_field_name']);
        }

        $field_view
            ->addItem(
                (new CDiv())
                    ->addClass('item-match-controls')
                    ->addItem($view->getView())
                    ->addItem(
                        (new CButton(null, (string) ($assistant['button_text'] ?? _m('Test'))))
                            ->addClass('js-item-match-test')
                    )
            )
            ->addItem(
                (new CDiv())
                    ->addClass('item-match-preview')
                    ->addClass('js-item-match-preview')
                    ->setAttribute('hidden', 'hidden')
            );
    }

    return [
        $label,
        new CFormField($field_view),
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

function getLoadCeilingViews(CWidgetFormView $form, $field): array
{
    $view = $form->registerField(new CWidgetFieldIntegerBoxView($field));
    $label = new CLabel(_m('Load ceiling'), $field->getName());
    $label->addItem(makeHelpIcon(
        _m('Maximum load for scaling the bar and sparkline. The actual load is still shown on screen.')
    ));

    return [
        $label,
        new CFormField($view->getView()),
    ];
}

function getInterfaceCeilingViews(CWidgetFormView $form, array $fields): array
{
    $interfaces_unit = $form->registerField(
        new CWidgetFieldRadioButtonListView($fields['interfaces_unit'])
    );
    $interface_ceiling = $form->registerField(new CWidgetFieldIntegerBoxView($fields['interfaces_high']));
    $label = new CLabel(_m('Interface ceiling'), 'interfaces_high');
    $label->addItem(makeHelpIcon(
        _m('Expected maximum throughput for scaling interface bars. The selected unit is applied.')
    ));

    return [
        $label,
        new CFormField(new CHorList([
            $interface_ceiling->getView(),
            $interfaces_unit->getView(),
        ])),
    ];
}

function getMetricThresholdViews(
    CWidgetFormView $form,
    array $fields,
    string $label_text,
    string $high_field_name,
    string $medium_field_name,
    string $hint = ''
): array {
    $high = $form->registerField(
        new CWidgetFieldIntegerBoxView($fields[$high_field_name])
    );
    $medium = $form->registerField(
        new CWidgetFieldIntegerBoxView($fields[$medium_field_name])
    );

    $label = new CLabel($label_text, $medium_field_name);

    if ($hint !== '') {
        $label->addItem(makeHelpIcon($hint));
    }

    return [
        $label,
        new CFormField(new CHorList([
            new CSpan(_m('Medium')),
            $medium->getView(),
            new CSpan(_m('High')),
            $high->getView(),
        ])),
    ];
}

function getThresholdColorView(
    CWidgetFormView $form,
    $field,
    string $label_text,
    string $row_class = ''
): array
{
    $view = $form->registerField(new CWidgetFieldColorView($field));
    $label = new CLabel($label_text, $field->getName());
    $form_field = new CFormField($view->getView());

    if ($row_class !== '') {
        $label->addClass($row_class);
        $form_field->addClass($row_class);
    }

    return [
        $label,
        $form_field,
    ];
}

function getBadgeUptimeItemViews(CWidgetFormView $form, $field): array
{
    return getItemNameView(
        $form,
        $field,
        _m('Enter the exact uptime item name, for example "System uptime". A partial name is used only when it matches exactly one item; otherwise the badge will not show uptime.'),
        ''
    );
}

function getBadgeLivelinessItemViews(CWidgetFormView $form, $field): array
{
    return getItemNameView(
        $form,
        $field,
        _m('Enter the exact liveliness item name, for example "Zabbix agent ping". A partial name is used only when it matches exactly one item; otherwise the badge will not render.'),
        ''
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

    $label = new CLabel(_m('Liveliness thresholds'), 'freshness_warn');
    $label->addItem(makeHelpIcon(
        _m('Seconds since the last data for the selected liveliness item. Warning triggers first, then stale.')
    ));

    return [
        $label,
        new CFormField(new CHorList([
            new CSpan(_m('Warn')),
            $freshness_warn->getView(),
            new CSpan(_m('Stale')),
            $freshness_stale->getView(),
        ])),
    ];
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
    $left_label = new CLabel(_m('Left'), 'badges-json');

    return [
        [$left_label, new CFormField($left_lane)],
        [new CLabel(_m('Right')), new CFormField($right_lane)],
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
                    (new CButton($add_name, _m('Add')))
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
        ->setAttribute('title', _m('Drag to reorder'));
    $drag_handle->addItem(render_icon('grip-vertical'));
    $type_badge = (new CSpan(_m($type_label)))
        ->addClass('badge-row-type');

    $show_text = CWidgetFieldBadgesList::badgeTypeUsesTextField($type);
    $show_url = CWidgetFieldBadgesList::badgeTypeUsesUrlField($type);
    $text_input = (new CTextBox('', $badge['text'] ?? ''))
        ->setAttribute('placeholder', _m('Badge text'))
        ->addClass('js-badge-text');

    if (!$show_text) {
        $text_input->setAttribute('style', 'display: none');
    }

    $url_input = (new CTextBox('', $badge['url'] ?? ''))
        ->setAttribute('placeholder', _m('https://example.com or /path'))
        ->addClass('js-badge-url');

    if (!$show_url) {
        $url_input->setAttribute('style', 'display: none');
    }

    $remove_btn = (new CButton('', _m('Remove')))
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
