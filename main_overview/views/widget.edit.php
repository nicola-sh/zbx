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

$global_threshold_matrix = (new CDiv())->addClass('ho-threshold-matrix');

foreach ([
    ['По умолчанию', 'th_num_1', 'th_num_2', 'default'],
    ['CPU', 'th_cpu_1', 'th_cpu_2', 'cpu'],
    ['Память', 'th_ram_1', 'th_ram_2', 'ram'],
    ['Load', 'th_load_1', 'th_load_2', 'load'],
    ['Swap', 'th_swap_1', 'th_swap_2', 'swap'],
    ['IF', 'th_iface_1', 'th_iface_2', 'iface'],
    ['Диск', 'th_disk_1', 'th_disk_2', 'disk'],
    ['Раздел', 'th_partition_1', 'th_partition_2', 'partition'],
] as [$label, $high, $medium, $metric]) {
    $global_threshold_matrix->addItem(
        createGlobalThresholdGroup($form, $data['fields'], $label, $high, $medium, '', $metric)
    );
}

$global_thresholds_fieldset = (new CWidgetFormFieldsetCollapsibleView('Пороги и цвета полос'))
    ->addClass('main-overview-global-thresholds-fieldset')
    ->addFieldsGroup(new CWidgetFieldsGroupView('', [
        new CWidgetFieldRadioButtonListView($data['fields']['color_scheme']),
    ]))
    ->addItem(
        (new CDiv())
            ->addClass('ho-threshold-colors-inline js-threshold-colors-grid')
            ->addItem(getThresholdColorView($form, $data['fields']['th_color_3'], 'Зелёный', 'js-threshold-color-row'))
            ->addItem(getThresholdColorView($form, $data['fields']['th_color_2'], 'Жёлтый', 'js-threshold-color-row'))
            ->addItem(getThresholdColorView($form, $data['fields']['th_color_1'], 'Красный', 'js-threshold-color-row'))
            ->addItem(getThresholdColorView($form, $data['fields']['fill_color'], 'Сплошной', 'js-solid-color-row'))
    )
    ->addItem($global_threshold_matrix);

$global_appearance_fieldset = (new CWidgetFormFieldsetCollapsibleView('Оформление'))
    ->addItem(getCheckBoxView($form, $data['fields']['open_links_same_window'],
        'Открывать ссылки метрик и бейджей в текущей вкладке.'
    ))
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
                (new CButton(null, 'Обновить'))
                    ->addClass('js-ho-refresh-host-panels')
            )
    )
    ->addItem(
        (new CDiv())
            ->addClass('js-host-accordion-mount')
            ->setAttribute('id', 'js-host-accordion-mount')
    )
    ->addFieldset($global_thresholds_fieldset)
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView('Бейджи'))
            ->addItem(getBadgesListView($data['fields']['badges']))
            ->addItem(getBadgeUptimeItemViews($form, $data['fields']['badge_uptime_item_name']))
            ->addItem(getBadgeLivelinessItemViews($form, $data['fields']['badge_liveliness_item_name']))
            ->addItem(getFreshnessThresholdViews($form, $data['fields']))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_hide_acknowledged'],
                'Do not count acknowledged problems in the Problems badge tally.'
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_hide_suppressed'],
                'Do not count suppressed problems in the Problems badge tally.'
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_pulse'],
                'Animate the problems badge when there are active incidents.'
            ))
    )
    ->addFieldset($global_appearance_fieldset)
    ->addItem($hidden_metrics_wrap)
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView('Host profiles (sync)'))
            ->addClass('js-host-profiles-sync-fieldset')
            ->addItem([
                (new CLabel($data['fields']['host_profiles']->getLabel(), $data['fields']['host_profiles']->getName()))
                    ->addItem(makeHelpIcon('JSON is rebuilt automatically from the host list when you save. You can still edit it manually if needed.')),
                new CFormField(
                    $form->registerField(new CWidgetFieldTextBoxView($data['fields']['host_profiles']))->getView()
                ),
            ])
    )
    ->includeJsFile('widget.edit.js.php')
    ->addJavaScript('form.init(' . json_encode([
        'color_picker_class' => $color_picker_class,
        'badge_type_options' => getBadgeTypeOptionsSafe(),
        'badge_multiple_types' => getMultipleBadgeTypesSafe(),
        'badge_types_with_text' => getTextFieldBadgeTypesSafe(),
        'badge_types_with_url' => getUrlFieldBadgeTypesSafe(),
        'item_lookup_action' => 'widget.main_overview.lookup',
        'metric_checkbox_rows' => [
            ['value' => (string) WidgetForm::METRIC_CPU, 'label' => 'CPU'],
            ['value' => (string) WidgetForm::METRIC_RAM, 'label' => 'Memory'],
            ['value' => (string) WidgetForm::METRIC_LOAD, 'label' => 'Load'],
            ['value' => (string) WidgetForm::METRIC_SWAP, 'label' => 'Swap'],
            ['value' => (string) WidgetForm::METRIC_INTERFACES, 'label' => 'Interfaces'],
            ['value' => (string) WidgetForm::METRIC_DISKS, 'label' => 'Disk utilization'],
            ['value' => (string) WidgetForm::METRIC_PARTITIONS, 'label' => 'Partitions'],
        ],
        'lookup_ui' => [
            'test' => 'Test',
            'stale_wildcard' => 'Template or exclusions changed. Click "Test" to refresh the preview.',
            'stale_single' => 'Input changed. Click "Test" to refresh the preview.',
            'pick_host' => 'Select a host first.',
            'checking' => 'Looking up matches…',
            'lookup_failed' => 'Could not run the match check.',
            'lookup_empty_response' => 'Server returned an empty response.',
            'lookup_html_error' => 'Received an HTML page instead of JSON.',
            'read_response_error' => 'Could not read the server response.',
            'exact_fmt' => 'Exact match: %s.',
            'unique_partial_fmt' => 'Single partial match: %s.',
            'ambiguous_fmt' => 'Name matches found: %s. Pick an exact item name:',
            'none_partial' => 'No exact or single partial match yet. Enter an exact item name:',
            'none_no_items' => 'No suitable item names were found.',
            'enter_name' => 'Enter an item name for preview.',
            'refine_candidates' => 'Narrow the query to shorten the list.',
            'refine_rows' => 'Only the first rows are shown. Refine the template to narrow the list.',
            'apply_fmt' => 'Inserted exact item name: %s.',
            'matches_heading_fmt' => 'Matches (%s)',
            'filtered_heading' => 'Excluded by filter',
            'wildcard_no_disk' => 'No matching disks.',
            'wildcard_no_partition' => 'No matching partitions.',
            'wildcard_no_interface' => 'No matching interfaces.',
            'wildcard_no_default' => 'No matches found.',
            'wildcard_invalid_iface' => 'For interfaces use at least two "*" characters in the template.',
            'wildcard_invalid_other' => 'Add at least one "*" character to the template.',
            'wildcard_too_broad' => 'Add fixed text around "*" to narrow the match list.',
            'wildcard_empty_disk' => 'Enter a template with "*" to preview disks.',
            'wildcard_empty_partition' => 'Enter a template with "*" to preview partitions.',
            'wildcard_empty_interface' => 'Enter a template with "*" to preview interfaces.',
            'wildcard_empty_default' => 'Enter a template with "*" for preview.',
            'wildcard_empty_single' => 'Enter an item name for preview.',
        ],
        'threshold_ui' => [
            'medium_label' => 'Жёлт. %',
            'high_label' => 'Красн. %',
            'zone_green' => 'Зелёный',
            'zone_yellow' => 'Жёлтый',
            'zone_red' => 'Красный',
            'legend_green' => '0 — %s%%',
            'legend_yellow' => '%s — %s%%',
            'legend_red' => '%s — 100%%',
            'inherit_hint' => 'Пусто — глобальные пороги',
            'invalid_order' => 'Красный порог должен быть больше жёлтого.',
        ],
        'per_host_labels' => [
            'empty' => 'Выберите один или несколько хостов в поле выше.',
            'section_metrics' => 'Показывать метрики',
            'section_badges_json' => 'Свои бейджи (JSON, опционально)',
            'label_badges_json_hint' => 'Оставьте пустым для глобальных бейджей. JSON в том же формате, что и в глобальном списке.',
            'section_display' => 'Отображение',
            'section_proc' => 'CPU, память и load',
            'section_swap' => 'Swap',
            'section_if' => 'Интерфейсы',
            'section_disk' => 'Загрузка дисков',
            'section_part' => 'Разделы',
            'section_adv' => 'Доп. overrides (JSON)',
            'label_alias' => 'Псевдоним (alias)',
            'label_badges' => 'Бейджи',
            'bp_summary' => 'Рядом с именем (сводка)',
            'bp_detail' => 'Только в деталях',
            'label_cpu' => 'Item: CPU',
            'label_ram' => 'Item: memory',
            'label_load' => 'Item: load',
            'label_load_high' => 'Load ceiling',
            'label_swap' => 'Item: swap',
            'label_swap_inv' => 'Invert swap',
            'label_iface' => 'Interface template',
            'label_iface_ex' => 'Interface filter',
            'label_iface_high' => 'Interface ceiling',
            'label_iface_unit' => 'Interface unit',
            'label_disk' => 'Disk template',
            'label_disk_ex' => 'Disk filter',
            'label_part' => 'Partition template',
            'label_part_ex' => 'Partition filter',
            'label_extras' => 'Extra JSON fields (merged with overrides)',
            'placeholder_extras' => 'Example: {"metrics_show":["0","1"]}',
        ],
    ], JSON_THROW_ON_ERROR) . ');')
    ->show();

function getBadgeTypeOptionsSafe(): array
{
    if (method_exists(CWidgetFieldBadgesList::class, 'getBadgeTypeOptions')) {
        return CWidgetFieldBadgesList::getBadgeTypeOptions();
    }

    $options = [];

    foreach (CWidgetFieldBadgesList::BADGE_TYPE_LABELS as $value => $label) {
        $options[] = [
            'value' => (string) $value,
            'label' => _m($label),
        ];
    }

    return $options;
}

function getMultipleBadgeTypesSafe(): array
{
    if (method_exists(CWidgetFieldBadgesList::class, 'getMultipleBadgeTypes')) {
        return CWidgetFieldBadgesList::getMultipleBadgeTypes();
    }

    $types = [];

    foreach (CWidgetFieldBadgesList::BADGE_TYPE_LABELS as $type => $_label) {
        if (CWidgetFieldBadgesList::badgeTypeAllowsMultiple((int) $type)) {
            $types[] = (string) $type;
        }
    }

    return array_values($types);
}

function getTextFieldBadgeTypesSafe(): array
{
    if (method_exists(CWidgetFieldBadgesList::class, 'getTextFieldBadgeTypes')) {
        return CWidgetFieldBadgesList::getTextFieldBadgeTypes();
    }

    $types = [];

    foreach (CWidgetFieldBadgesList::BADGE_TYPE_LABELS as $type => $_label) {
        if (CWidgetFieldBadgesList::badgeTypeUsesTextField((int) $type)) {
            $types[] = (string) $type;
        }
    }

    return array_values($types);
}

function getUrlFieldBadgeTypesSafe(): array
{
    if (method_exists(CWidgetFieldBadgesList::class, 'getUrlFieldBadgeTypes')) {
        return CWidgetFieldBadgesList::getUrlFieldBadgeTypes();
    }

    $types = [];

    foreach (CWidgetFieldBadgesList::BADGE_TYPE_LABELS as $type => $_label) {
        if (CWidgetFieldBadgesList::badgeTypeUsesUrlField((int) $type)) {
            $types[] = (string) $type;
        }
    }

    return array_values($types);
}

function getItemNameView(CWidgetFormView $form, $field, string $hint = '', ?string $metric_value = null): array
{
    $view = $form->registerField(new CWidgetFieldTextBoxView($field));
    $label = new CLabel($field->getLabel(), $field->getName());
    $field_view = $view->getView();

    if ($hint === '') {
        $hint = 'Prefer an exact item name. A partial name is used only when it matches exactly one item; otherwise "No data" is shown.';
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
                        (new CButton(null, 'Test'))
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
                        (new CButton(null, (string) ($assistant['button_text'] ?? 'Test')))
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
    $label = new CLabel('Load ceiling', $field->getName());
    $label->addItem(makeHelpIcon(
        'Maximum load for scaling the bar and sparkline. The actual load is still shown on screen.'
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
    $label = new CLabel('Interface ceiling', 'interfaces_high');
    $label->addItem(makeHelpIcon(
        'Expected maximum throughput for scaling interface bars. The selected unit is applied.'
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
            new CSpan('Жёлтый с'),
            $medium->getView(),
            new CSpan('Красный с'),
            $high->getView(),
        ])),
    ];
}

function createGlobalThresholdGroup(
    CWidgetFormView $form,
    array $fields,
    string $title,
    string $high_field_name,
    string $medium_field_name,
    string $hint = '',
    string $metric_key = ''
): CDiv {
    $wrap = (new CDiv())
        ->addClass('ho-threshold-row js-threshold-group');

    if ($metric_key !== '') {
        $wrap->setAttribute('data-threshold-metric', $metric_key);
    }

    $wrap
        ->setAttribute('data-threshold-high', $high_field_name)
        ->setAttribute('data-threshold-medium', $medium_field_name);

    $fields_row = (new CDiv())->addClass('ho-threshold-group-fields');

    foreach (getMetricThresholdViews($form, $fields, '', $high_field_name, $medium_field_name) as $part) {
        $fields_row->addItem($part);
    }

    $wrap
        ->addItem((new CDiv())->addClass('ho-threshold-row-label')->addItem($title))
        ->addItem($fields_row)
        ->addItem(
            (new CDiv())
                ->addClass('ho-threshold-scale-mount js-threshold-scale-mount')
        );

    return $wrap;
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
        'Enter the exact uptime item name, for example "System uptime". A partial name is used only when it matches exactly one item; otherwise the badge will not show uptime.',
        ''
    );
}

function getBadgeLivelinessItemViews(CWidgetFormView $form, $field): array
{
    return getItemNameView(
        $form,
        $field,
        'Enter the exact liveliness item name, for example "Zabbix agent ping". A partial name is used only when it matches exactly one item; otherwise the badge will not render.',
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

    $label = new CLabel('Liveliness thresholds', 'freshness_warn');
    $label->addItem(makeHelpIcon(
        'Seconds since the last data for the selected liveliness item. Warning triggers first, then stale.'
    ));

    return [
        $label,
        new CFormField(new CHorList([
            new CSpan('Warn'),
            $freshness_warn->getView(),
            new CSpan('Stale'),
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
    $left_label = new CLabel('Left', 'badges-json');

    return [
        [$left_label, new CFormField($left_lane)],
        [new CLabel('Right'), new CFormField($right_lane)],
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
                    (new CButton($add_name, 'Add'))
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
        ->setAttribute('title', 'Drag to reorder');
    $drag_handle->addItem(render_icon('grip-vertical'));
    $type_badge = (new CSpan($type_label))
        ->addClass('badge-row-type');

    $show_text = CWidgetFieldBadgesList::badgeTypeUsesTextField($type);
    $show_url = CWidgetFieldBadgesList::badgeTypeUsesUrlField($type);
    $text_input = (new CTextBox('', $badge['text'] ?? ''))
        ->setAttribute('placeholder', 'Badge text')
        ->addClass('js-badge-text');

    if (!$show_text) {
        $text_input->setAttribute('style', 'display: none');
    }

    $url_input = (new CTextBox('', $badge['url'] ?? ''))
        ->setAttribute('placeholder', 'https://example.com or /path')
        ->addClass('js-badge-url');

    if (!$show_url) {
        $url_input->setAttribute('style', 'display: none');
    }

    $remove_btn = (new CButton('', 'Remove'))
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
