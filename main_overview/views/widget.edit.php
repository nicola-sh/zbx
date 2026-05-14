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

$form
    ->addItem(
        (new CDiv())
            ->addClass('main-overview-per-host-root')
            ->addItem(
                (new CDiv())
                    ->addClass('main-overview-per-host-hint')
                    ->addItem(_(
                        'For each host you select in the field above, a collapsible section is created here — similar to the global groups below, but only for that host. Leave a field empty to use the global default.'
                    ))
            )
            ->addItem(
                (new CDiv())
                    ->addClass('js-host-accordion-mount')
                    ->setAttribute('id', 'js-host-accordion-mount')
            )
    );

$form
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Stored host profiles (auto-synced)')))
            ->addClass('js-host-profiles-sync-fieldset')
            ->addItem([
                (new CLabel($data['fields']['host_profiles']->getLabel(), $data['fields']['host_profiles']->getName()))
                    ->addItem(makeHelpIcon(_(
                        'This JSON is rebuilt automatically from the host list above when you save. You can still edit it manually if needed.'
                    ))),
                new CFormField(
                    $form->registerField(new CWidgetFieldTextBoxView($data['fields']['host_profiles']))->getView()
                ),
            ])
    )
    ->addField(
        (new CWidgetFieldCheckBoxListView($data['fields']['metrics_show']))
            ->setColumns(3)
    )
    ->addItem(getCheckBoxView($form, $data['fields']['open_links_same_window'],
        'Open metric and badge links in the current browser tab instead of a new tab.'
    ))
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Badges')))
            ->addItem(getBadgesListView($data['fields']['badges']))
            ->addItem(getBadgeUptimeItemViews($form, $data['fields']['badge_uptime_item_name']))
            ->addItem(getBadgeLivelinessItemViews($form, $data['fields']['badge_liveliness_item_name']))
            ->addItem(getFreshnessThresholdViews($form, $data['fields']))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_hide_acknowledged'],
                'Exclude acknowledged problems from the Problems badge count.'
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_hide_suppressed'],
                'Exclude suppressed problems from the Problems badge count.'
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_pulse'],
                'Animate the problems badge with a pulsing effect when there are active problems.'
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Processor, Memory and Load')))
            ->addItem(getItemNameView($form, $data['fields']['item_name_cpu'],
                'Enter the exact CPU item name, for example "CPU utilization". Partial names are only used when they match one item uniquely; otherwise the widget shows No data.',
                (string) WidgetForm::METRIC_CPU
            ))
            ->addItem(getMetricThresholdViews($form, $data['fields'],
                _('Processor thresholds'),
                'th_cpu_1',
                'th_cpu_2',
                _('Used for the processor utilization bar. High and Medium share the colors from the Style section.')
            ))
            ->addItem(getItemNameView($form, $data['fields']['item_name_ram'],
                'Enter the exact memory item name, for example "Memory utilization". Partial names are only used when they match one item uniquely; otherwise the widget shows No data.',
                (string) WidgetForm::METRIC_RAM
            ))
            ->addItem(getMetricThresholdViews($form, $data['fields'],
                _('Memory thresholds'),
                'th_ram_1',
                'th_ram_2',
                _('Used for the memory utilization bar. High and Medium share the colors from the Style section.')
            ))
            ->addItem(getItemNameView($form, $data['fields']['item_name_load'],
                'Enter the exact load item name, for example "Load average (5m avg)". The widget displays the raw load value and scales the bar using the Load ceiling setting below. Partial names are only used when they match one item uniquely; otherwise the widget shows No data.',
                (string) WidgetForm::METRIC_LOAD
            ))
            ->addItem(getMetricThresholdViews($form, $data['fields'],
                _('Load thresholds'),
                'th_load_1',
                'th_load_2',
                _('Applied to the load bar after it is scaled against the Load ceiling. The displayed value remains the raw load. High and Medium share the colors from the Style section.')
            ))
            ->addItem(getLoadCeilingViews($form, $data['fields']['load_high']))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Swap')))
            ->addItem(getItemNameView($form, $data['fields']['item_name_swap'],
                'Enter the exact swap item name, for example "Free swap space in %". Partial names are only used when they match one item uniquely; otherwise the widget shows No data.',
                (string) WidgetForm::METRIC_SWAP
            ))
            ->addItem(getMetricThresholdViews($form, $data['fields'],
                _('Swap thresholds'),
                'th_swap_1',
                'th_swap_2',
                _('Used for the swap utilization bar after any inversion setting is applied. High and Medium share the colors from the Style section.')
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['item_swap_invert'],
                'Enable if the swap item reports free % instead of used %. The value will be inverted (100 − value).'
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Interfaces')))
            ->addItem(getPatternView($form, $data['fields']['item_name_interface'],
                'Use * as wildcard. First * = interface name, second * = direction. Direction is automatically labeled RX (received/in) or TX (sent/out).',
                [
                    'mode' => 'wildcard',
                    'metric_type' => 'interface',
                    'metric_value' => (string) WidgetForm::METRIC_INTERFACES,
                    'exclude_field_name' => 'interfaces_exclude',
                    'button_text' => _('Test'),
                ]
            ))
            ->addItem(getPatternView($form, $data['fields']['interfaces_exclude'],
                'Comma-separated list of interface names to hide. Wildcards * and ? are supported.'
            ))
            ->addItem(getInterfaceCeilingViews($form, $data['fields']))
            ->addItem(getMetricThresholdViews($form, $data['fields'],
                _('Interface thresholds'),
                'th_iface_1',
                'th_iface_2',
                _('Used for interface bars after throughput is converted to a percentage of the Interface ceiling. High and Medium share the colors from the Style section.')
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Disk utilization')))
            ->addItem(getPatternView($form, $data['fields']['item_name_disk'],
                'Use * as wildcard for the disk name.',
                [
                    'mode' => 'wildcard',
                    'metric_type' => 'disk',
                    'metric_value' => (string) WidgetForm::METRIC_DISKS,
                    'exclude_field_name' => 'disks_exclude',
                    'button_text' => _('Test'),
                ]
            ))
            ->addItem(getPatternView($form, $data['fields']['disks_exclude'],
                'Comma-separated list of disk names to hide. Wildcards * and ? are supported.'
            ))
            ->addItem(getMetricThresholdViews($form, $data['fields'],
                _('Disk thresholds'),
                'th_disk_1',
                'th_disk_2',
                _('Used for disk utilization rows. High and Medium share the colors from the Style section.')
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Partitions')))
            ->addItem(getPatternView($form, $data['fields']['item_name_partition'],
                'Use * as wildcard for the partition/mount path.',
                [
                    'mode' => 'wildcard',
                    'metric_type' => 'partition',
                    'metric_value' => (string) WidgetForm::METRIC_PARTITIONS,
                    'exclude_field_name' => 'partitions_exclude',
                    'button_text' => _('Test'),
                ]
            ))
            ->addItem(getPatternView($form, $data['fields']['partitions_exclude'],
                'Comma-separated list of partition paths to hide. Wildcards * and ? are supported.'
            ))
            ->addItem(getMetricThresholdViews($form, $data['fields'],
                _('Partition thresholds'),
                'th_partition_1',
                'th_partition_2',
                _('Used for partition utilization rows. High and Medium share the colors from the Style section.')
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Style')))
            ->addField(
                new CWidgetFieldRadioButtonListView($data['fields']['color_scheme'])
            )
            ->addItem(getThresholdColorView($form, $data['fields']['th_color_1'], _('High color'), 'js-threshold-color-row'))
            ->addItem(getThresholdColorView($form, $data['fields']['th_color_2'], _('Medium color'), 'js-threshold-color-row'))
            ->addItem(getThresholdColorView($form, $data['fields']['th_color_3'], _('Regular color'), 'js-threshold-color-row'))
            ->addItem(getThresholdColorView($form, $data['fields']['fill_color'], _('Solid color'), 'js-solid-color-row'))
            ->addField(
                new CWidgetFieldRadioButtonListView($data['fields']['corners'])
            )
            ->addField(
                new CWidgetFieldRadioButtonListView($data['fields']['label_length'])
            )
            ->addField(
                new CWidgetFieldRadioButtonListView($data['fields']['bar_height'])
            )
    )
    ->includeJsFile('widget.edit.js')
    ->addJavaScript('form.init(' . json_encode([
        'color_picker_class' => $color_picker_class,
        'badge_type_options' => CWidgetFieldBadgesList::getBadgeTypeOptions(),
        'badge_multiple_types' => CWidgetFieldBadgesList::getMultipleBadgeTypes(),
        'badge_types_with_text' => CWidgetFieldBadgesList::getTextFieldBadgeTypes(),
        'badge_types_with_url' => CWidgetFieldBadgesList::getUrlFieldBadgeTypes(),
        'item_lookup_action' => 'widget.main_overview.lookup',
        'per_host_labels' => [
            'empty' => _('Select one or more hosts in the field above.'),
            'section_display' => _('Display & badges'),
            'section_proc' => _('Processor, Memory and Load'),
            'section_swap' => _('Swap'),
            'section_if' => _('Interfaces'),
            'section_disk' => _('Disk utilization'),
            'section_part' => _('Partitions'),
            'section_adv' => _('More overrides (JSON)'),
            'label_alias' => _('Display alias'),
            'label_badges' => _('Badges'),
            'bp_summary' => _('Next to name (summary row)'),
            'bp_detail' => _('Only in detail view'),
            'label_cpu' => _('Processor item'),
            'label_ram' => _('Memory item'),
            'label_load' => _('Load item'),
            'label_load_high' => _('Load ceiling'),
            'label_swap' => _('Swap item'),
            'label_swap_inv' => _('Invert swap'),
            'label_iface' => _('Interface pattern'),
            'label_iface_ex' => _('Interface filter'),
            'label_iface_high' => _('Interface ceiling'),
            'label_iface_unit' => _('Interface unit'),
            'label_disk' => _('Disk pattern'),
            'label_disk_ex' => _('Disk filter'),
            'label_part' => _('Partition pattern'),
            'label_part_ex' => _('Partition filter'),
            'label_extras' => _('Additional keys as JSON (merged into overrides)'),
            'placeholder_extras' => _('Example: {"metrics_show":["0","1"]}'),
        ],
    ], JSON_THROW_ON_ERROR) . ');')
    ->show();

function getItemNameView(CWidgetFormView $form, $field, string $hint = '', ?string $metric_value = null): array
{
    $view = $form->registerField(new CWidgetFieldTextBoxView($field));
    $label = new CLabel($field->getLabel(), $field->getName());
    $field_view = $view->getView();

    if ($hint === '') {
        $hint = 'Prefer the exact item name. Partial names are only used when they match one item uniquely; otherwise the widget shows No data.';
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
                        (new CButton(null, _('Test')))
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
                        (new CButton(null, (string) ($assistant['button_text'] ?? _('Test'))))
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
    $label = new CLabel(_('Load ceiling'), $field->getName());
    $label->addItem(makeHelpIcon(
        _('Maximum load value used to scale the load bar and sparkline. The displayed value remains the raw load.')
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
    $label = new CLabel(_('Interface ceiling'), 'interfaces_high');
    $label->addItem(makeHelpIcon(
        _('Maximum expected interface throughput used to scale interface bars and sparklines. The value uses the selected unit.')
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
            new CSpan(_('Medium')),
            $medium->getView(),
            new CSpan(_('High')),
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
        _('Enter the exact uptime item name, for example "System uptime". Partial names are only used when they match one item uniquely; otherwise the badge shows No uptime.'),
        ''
    );
}

function getBadgeLivelinessItemViews(CWidgetFormView $form, $field): array
{
    return getItemNameView(
        $form,
        $field,
        _('Enter the exact liveliness item name, for example "Zabbix agent ping". Partial names are only used when they match one item uniquely; otherwise the badge shows No ping.'),
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

    $label = new CLabel(_('Liveliness thresholds'), 'freshness_warn');
    $label->addItem(makeHelpIcon(
        _('Age in seconds since the configured liveliness item last reported data. Warn applies first, then Stale.')
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
    $left_label = new CLabel(_('Left'), 'badges-json');

    return [
        [$left_label, new CFormField($left_lane)],
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
    $drag_handle->addItem(render_icon('grip-vertical'));
    $type_badge = (new CSpan(_($type_label)))
        ->addClass('badge-row-type');

    $show_text = CWidgetFieldBadgesList::badgeTypeUsesTextField($type);
    $show_url = CWidgetFieldBadgesList::badgeTypeUsesUrlField($type);
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
