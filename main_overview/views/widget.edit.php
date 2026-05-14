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

$form
    ->addItem(
        (new CDiv())
            ->addClass('main-overview-add-host-row')
            ->addItem(
                (new CDiv())
                    ->addClass('main-overview-per-host-hint')
                    ->addItem(_(
                        'После выбора одного или нескольких узлов ниже появится блок настроек для каждого (метрики, элементы, пороги). Если панелей нет — нажмите «Обновить панели узлов».'
                    ))
            )
            ->addItem(
                (new CButton(null, _('Обновить панели узлов')))
                    ->addClass('js-ho-refresh-host-panels')
            )
    )
    ->addItem(
        (new CDiv())
            ->addClass('js-host-accordion-mount')
            ->setAttribute('id', 'js-host-accordion-mount')
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Общие: значки')))
            ->addItem(getBadgesListView($data['fields']['badges']))
            ->addItem(getBadgeUptimeItemViews($form, $data['fields']['badge_uptime_item_name']))
            ->addItem(getBadgeLivelinessItemViews($form, $data['fields']['badge_liveliness_item_name']))
            ->addItem(getFreshnessThresholdViews($form, $data['fields']))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_hide_acknowledged'],
                _('Не учитывать подтверждённые проблемы в счётчике значка «Проблемы».')
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_hide_suppressed'],
                _('Не учитывать подавленные проблемы в счётчике значка «Проблемы».')
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['problems_pulse'],
                _('Анимировать значок проблем при наличии активных инцидентов.')
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Общие: оформление')))
            ->addItem(getCheckBoxView($form, $data['fields']['open_links_same_window'],
                _('Открывать ссылки метрик и значков в текущей вкладке браузера.')
            ))
            ->addField(
                new CWidgetFieldRadioButtonListView($data['fields']['color_scheme'])
            )
            ->addItem(getThresholdColorView($form, $data['fields']['th_color_1'], _('Цвет: высокий'), 'js-threshold-color-row'))
            ->addItem(getThresholdColorView($form, $data['fields']['th_color_2'], _('Цвет: средний'), 'js-threshold-color-row'))
            ->addItem(getThresholdColorView($form, $data['fields']['th_color_3'], _('Цвет: обычный'), 'js-threshold-color-row'))
            ->addItem(getThresholdColorView($form, $data['fields']['fill_color'], _('Сплошной цвет'), 'js-solid-color-row'))
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
    ->addItem($hidden_metrics_wrap)
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Профили узлов (синхронизация)')))
            ->addClass('js-host-profiles-sync-fieldset')
            ->addItem([
                (new CLabel($data['fields']['host_profiles']->getLabel(), $data['fields']['host_profiles']->getName()))
                    ->addItem(makeHelpIcon(_(
                        'JSON пересобирается автоматически из списка узлов при сохранении. При необходимости его можно править вручную.'
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
            ['value' => (string) WidgetForm::METRIC_CPU, 'label' => _('Процессор')],
            ['value' => (string) WidgetForm::METRIC_RAM, 'label' => _('Память')],
            ['value' => (string) WidgetForm::METRIC_LOAD, 'label' => _('Нагрузка')],
            ['value' => (string) WidgetForm::METRIC_SWAP, 'label' => _('Своп')],
            ['value' => (string) WidgetForm::METRIC_INTERFACES, 'label' => _('Интерфейсы')],
            ['value' => (string) WidgetForm::METRIC_DISKS, 'label' => _('Загрузка диска')],
            ['value' => (string) WidgetForm::METRIC_PARTITIONS, 'label' => _('Разделы')],
        ],
        'lookup_ui' => [
            'test' => _('Проверить'),
            'stale_wildcard' => _('Шаблон или исключения изменились. Нажмите «Проверить», чтобы обновить предпросмотр.'),
            'stale_single' => _('Ввод изменён. Нажмите «Проверить», чтобы обновить предпросмотр.'),
            'pick_host' => _('Сначала выберите узел.'),
            'checking' => _('Поиск совпадений…'),
            'lookup_failed' => _('Не удалось выполнить проверку совпадений.'),
            'lookup_empty_response' => _('Сервер вернул пустой ответ.'),
            'lookup_html_error' => _('Вместо JSON получена HTML-страница.'),
            'read_response_error' => _('Не удалось прочитать ответ сервера.'),
            'exact_fmt' => _('Точное совпадение: %s.'),
            'unique_partial_fmt' => _('Единственное частичное совпадение: %s.'),
            'ambiguous_fmt' => _('Найдено совпадений по имени: %s. Выберите точное имя элемента:'),
            'none_partial' => _('Точного или единственного частичного совпадения пока нет. Укажите точное имя элемента:'),
            'none_no_items' => _('Подходящих имён элементов не найдено.'),
            'enter_name' => _('Введите имя элемента для предпросмотра.'),
            'refine_candidates' => _('Сузьте запрос, чтобы укоротить список.'),
            'refine_rows' => _('Показаны только первые строки. Уточните шаблон, чтобы сузить список.'),
            'apply_fmt' => _('Подставлено точное имя элемента: %s.'),
            'matches_heading_fmt' => _('Совпадения (%s)'),
            'filtered_heading' => _('Исключено фильтром'),
            'wildcard_no_disk' => _('Совпадающих дисков не найдено.'),
            'wildcard_no_partition' => _('Совпадающих разделов не найдено.'),
            'wildcard_no_interface' => _('Совпадающих интерфейсов не найдено.'),
            'wildcard_no_default' => _('Совпадений не найдено.'),
            'wildcard_invalid_iface' => _('Для интерфейсов используйте не менее двух символов «*» в шаблоне.'),
            'wildcard_invalid_other' => _('Добавьте хотя бы один символ «*» в шаблон.'),
            'wildcard_too_broad' => _('Добавьте фиксированный текст вокруг «*», чтобы сузить список совпадений.'),
            'wildcard_empty_disk' => _('Введите шаблон с «*» для предпросмотра дисков.'),
            'wildcard_empty_partition' => _('Введите шаблон с «*» для предпросмотра разделов.'),
            'wildcard_empty_interface' => _('Введите шаблон с «*» для предпросмотра интерфейсов.'),
            'wildcard_empty_default' => _('Введите шаблон с «*» для предпросмотра.'),
            'wildcard_empty_single' => _('Введите имя элемента для предпросмотра.'),
        ],
        'per_host_labels' => [
            'empty' => _('Выберите один или несколько узлов в поле выше.'),
            'section_metrics' => _('Показывать метрики'),
            'section_badges_json' => _('Свой список значков (необязательно)'),
            'label_badges_json_hint' => _(
                'Оставьте пустым, чтобы использовать общие значки. Вставьте JSON в том же формате, что и глобальный список, чтобы заменить значки только для этого узла.'
            ),
            'section_display' => _('Отображение'),
            'section_proc' => _('Процессор, память и нагрузка'),
            'section_swap' => _('Своп'),
            'section_if' => _('Интерфейсы'),
            'section_disk' => _('Загрузка диска'),
            'section_part' => _('Разделы'),
            'section_adv' => _('Доп. переопределения (JSON)'),
            'label_alias' => _('Псевдоним'),
            'label_badges' => _('Значки'),
            'bp_summary' => _('Рядом с именем (сводка)'),
            'bp_detail' => _('Только в детализации'),
            'label_cpu' => _('Элемент: процессор'),
            'label_ram' => _('Элемент: память'),
            'label_load' => _('Элемент: нагрузка'),
            'label_load_high' => _('Потолок нагрузки'),
            'label_swap' => _('Элемент: своп'),
            'label_swap_inv' => _('Инвертировать своп'),
            'label_iface' => _('Шаблон интерфейса'),
            'label_iface_ex' => _('Фильтр интерфейсов'),
            'label_iface_high' => _('Потолок интерфейса'),
            'label_iface_unit' => _('Единица интерфейса'),
            'label_disk' => _('Шаблон диска'),
            'label_disk_ex' => _('Фильтр дисков'),
            'label_part' => _('Шаблон раздела'),
            'label_part_ex' => _('Фильтр разделов'),
            'label_extras' => _('Дополнительные поля JSON (сливаются с переопределениями)'),
            'placeholder_extras' => _('Пример: {"metrics_show":["0","1"]}'),
        ],
    ], JSON_THROW_ON_ERROR) . ');')
    ->show();

function getItemNameView(CWidgetFormView $form, $field, string $hint = '', ?string $metric_value = null): array
{
    $view = $form->registerField(new CWidgetFieldTextBoxView($field));
    $label = new CLabel($field->getLabel(), $field->getName());
    $field_view = $view->getView();

    if ($hint === '') {
        $hint = _(
            'Предпочтительно точное имя элемента. Частичное имя используется только при единственном совпадении; иначе отображается «Нет данных».'
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
                        (new CButton(null, _('Проверить')))
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
                        (new CButton(null, (string) ($assistant['button_text'] ?? _('Проверить'))))
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
    $label = new CLabel(_('Потолок нагрузки'), $field->getName());
    $label->addItem(makeHelpIcon(
        _('Максимальное значение нагрузки для масштаба полосы и спарклайна. На экране по-прежнему показывается фактическая нагрузка.')
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
    $label = new CLabel(_('Потолок интерфейса'), 'interfaces_high');
    $label->addItem(makeHelpIcon(
        _('Ожидаемая максимальная пропускная способность для масштаба полос интерфейсов. Учитывается выбранная единица измерения.')
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
            new CSpan(_('Средний')),
            $medium->getView(),
            new CSpan(_('Высокий')),
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
        _('Укажите точное имя элемента аптайма, например «System uptime». Частичное имя — только при единственном совпадении; иначе значок не покажет аптайм.'),
        ''
    );
}

function getBadgeLivelinessItemViews(CWidgetFormView $form, $field): array
{
    return getItemNameView(
        $form,
        $field,
        _('Укажите точное имя элемента «живости», например «Zabbix agent ping». Частичное имя — только при единственном совпадении; иначе значок не отобразится.'),
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

    $label = new CLabel(_('Пороги «живости»'), 'freshness_warn');
    $label->addItem(makeHelpIcon(
        _('Возраст в секундах с момента последних данных выбранного элемента «живости». Сначала срабатывает предупреждение, затем «устарело».')
    ));

    return [
        $label,
        new CFormField(new CHorList([
            new CSpan(_('Предупр.')),
            $freshness_warn->getView(),
            new CSpan(_('Устарело')),
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
    $left_label = new CLabel(_('Слева'), 'badges-json');

    return [
        [$left_label, new CFormField($left_lane)],
        [new CLabel(_('Справа')), new CFormField($right_lane)],
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
                    (new CButton($add_name, _('Добавить')))
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
        ->setAttribute('title', _('Перетащите для изменения порядка'));
    $drag_handle->addItem(render_icon('grip-vertical'));
    $type_badge = (new CSpan(_($type_label)))
        ->addClass('badge-row-type');

    $show_text = CWidgetFieldBadgesList::badgeTypeUsesTextField($type);
    $show_url = CWidgetFieldBadgesList::badgeTypeUsesUrlField($type);
    $text_input = (new CTextBox('', $badge['text'] ?? ''))
        ->setAttribute('placeholder', _('Текст на значке'))
        ->addClass('js-badge-text');

    if (!$show_text) {
        $text_input->setAttribute('style', 'display: none');
    }

    $url_input = (new CTextBox('', $badge['url'] ?? ''))
        ->setAttribute('placeholder', _('https://example.com или /path'))
        ->addClass('js-badge-url');

    if (!$show_url) {
        $url_input->setAttribute('style', 'display: none');
    }

    $remove_btn = (new CButton('', _('Удалить')))
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
