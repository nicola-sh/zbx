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
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Processor, Memory and Load')))
            ->addItem(getItemNameView($form, $data['fields']['item_name_cpu']))
            ->addItem(getItemNameView($form, $data['fields']['item_name_ram']))
            ->addItem(getItemNameView($form, $data['fields']['item_name_load']))
            ->addField(
                new CWidgetFieldIntegerBoxView($data['fields']['load_high'])
            )
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_('Swap')))
            ->addItem(getItemNameView($form, $data['fields']['item_name_swap']))
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
                new CWidgetFieldRadioButtonListView($data['fields']['badge_size'])
            )
            ->addField(
                new CWidgetFieldSelectView($data['fields']['bar_height'])
            )
            ->addItem(getCheckBoxView($form, $data['fields']['problems_pulse'],
                'Animate the problems badge with a pulsing effect when there are active problems.'
            ))
    )
    ->includeJsFile('widget.edit.js')
    ->addJavaScript('form.init(' . json_encode([
        'color_picker_class' => $color_picker_class,
    ], JSON_THROW_ON_ERROR) . ');')
    ->addItem(new CTag('style', true,
        '.badge-lanes { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 10px; margin-bottom: 6px; }'
        . '.badge-lane { background: rgba(0, 0, 0, 0.15); border-radius: 4px; padding: 8px; min-height: 52px; }'
        . '.badge-lane-title { font-weight: bold; margin-bottom: 6px; padding-left:5px; }'
        . '.badge-lane-rows { min-height: 28px; }'
        . '.badge-lane .js-badge-add { margin-top: 4px; }'
        . '.badge-row { display: flex; gap: 10px; align-items: center; margin-bottom: 6px; }'
        . '.badge-row.is-dragging { opacity: 0.55; }'
        . '.badge-row select { min-width: 110px; }'
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
        $hint = 'Matches any item name containing this text (substring search).';
    }
    $label->addItem(makeHelpIcon($hint));

    return [
        $label,
        new CFormField(new CHorList([
            new CSpan('%'),
            $view->getView(),
            new CSpan('%'),
        ])),
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

function getBadgesListView(CWidgetFieldBadgesList $field): array
{
    $badges = $field->getBadges();

    $container = (new CDiv())->setId('badges-list');
    $lanes = (new CDiv())->addClass('badge-lanes');
    $left_rows = (new CDiv())
        ->addClass('badge-lane-rows')
        ->addClass('js-badge-lane-rows')
        ->setAttribute('data-side', CWidgetFieldBadgesList::SIDE_LEFT);
    $right_rows = (new CDiv())
        ->addClass('badge-lane-rows')
        ->addClass('js-badge-lane-rows')
        ->setAttribute('data-side', CWidgetFieldBadgesList::SIDE_RIGHT);

    // Hidden input carries the JSON for form submission
    $container->addItem(
        (new CTag('input'))
            ->setAttribute('type', 'hidden')
            ->setAttribute('name', 'badges')
            ->setAttribute('id', 'badges-json')
            ->setAttribute('value', json_encode($badges))
    );

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

    $lanes->addItem(
        (new CDiv())
            ->addClass('badge-lane')
            ->setAttribute('data-side', CWidgetFieldBadgesList::SIDE_LEFT)
            ->addItem((new CDiv('Left'))->addClass('badge-lane-title'))
            ->addItem($left_rows)
            ->addItem(
                (new CButton('badge_add_left', _('Add')))
                    ->addClass('btn-link')
                    ->addClass('js-badge-add')
                    ->setAttribute('data-side', CWidgetFieldBadgesList::SIDE_LEFT)
            )
    );
    $lanes->addItem(
        (new CDiv())
            ->addClass('badge-lane')
            ->setAttribute('data-side', CWidgetFieldBadgesList::SIDE_RIGHT)
            ->addItem((new CDiv('Right'))->addClass('badge-lane-title'))
            ->addItem($right_rows)
            ->addItem(
                (new CButton('badge_add_right', _('Add')))
                    ->addClass('btn-link')
                    ->addClass('js-badge-add')
                    ->setAttribute('data-side', CWidgetFieldBadgesList::SIDE_RIGHT)
            )
    );

    $container->addItem($lanes);

    $label = new CLabel($field->getLabel(), 'badges-list');
    $label->addItem(makeHelpIcon(_('Link badges allow http://, https://, or relative URLs such as zabbix.php?action=...')));

    return [
        $label,
        new CFormField($container),
    ];
}

function createBadgeRow(array $badge): CDiv
{
    $type = (int) ($badge['type'] ?? CWidgetFieldBadgesList::BADGE_HOSTNAME);
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

    $type_select = new CTag('select', true);
    $type_select->addClass('js-badge-type');

    foreach (CWidgetFieldBadgesList::BADGE_TYPE_LABELS as $val => $label) {
        $option = (new CTag('option', true, _($label)))->setAttribute('value', $val);

        if ((int) $val === $type) {
            $option->setAttribute('selected', 'selected');
        }

        $type_select->addItem($option);
    }

    $show_text = in_array($type, [CWidgetFieldBadgesList::BADGE_TEXT, CWidgetFieldBadgesList::BADGE_LINK]);
    $show_url = ($type === CWidgetFieldBadgesList::BADGE_LINK);
    $show_scope = ($type === CWidgetFieldBadgesList::BADGE_PROBLEMS);
    $show_item_name = ($type === CWidgetFieldBadgesList::BADGE_UPTIME);
    $show_hostname_link = ($type === CWidgetFieldBadgesList::BADGE_HOSTNAME);
    $show_text_label = ($type === CWidgetFieldBadgesList::BADGE_TEXT);
    $hostname_link_label = (new CSpan('...links to..'))->addClass('js-badge-hostname-link-label');
    $scope_label = (new CSpan('...with a status of...'))->addClass('js-badge-scope-label');
    $uptime_item_label = (new CSpan('...taken from item...'))->addClass('js-badge-item-name-label');
    $text_label = (new CSpan('...with value...'))->addClass('js-badge-text-label');

    $hostname_link_select = new CTag('select', true);
    $hostname_link_select->addClass('js-badge-hostname-link');
    $cur_hostname_link = (int) ($badge['link'] ?? CWidgetFieldBadgesList::HOSTNAME_LINK_LATEST);
    foreach (CWidgetFieldBadgesList::HOSTNAME_LINK_LABELS as $val => $label) {
        $opt = (new CTag('option', true, _($label)))->setAttribute('value', $val);
        if ((int) $val === $cur_hostname_link) {
            $opt->setAttribute('selected', 'selected');
        }
        $hostname_link_select->addItem($opt);
    }
    if (!$show_hostname_link) {
        $hostname_link_label->setAttribute('style', 'display: none');
        $hostname_link_select->setAttribute('style', 'display: none');
    }

    if (!$show_item_name) {
        $uptime_item_label->setAttribute('style', 'display: none');
    }

    if (!$show_scope) {
        $scope_label->setAttribute('style', 'display: none');
    }

    if (!$show_text_label) {
        $text_label->setAttribute('style', 'display: none');
    }

    $scope_select = new CTag('select', true);
    $scope_select->addClass('js-badge-scope');
    $cur_scope = (int) ($badge['scope'] ?? CWidgetFieldBadgesList::SCOPE_ALL);
    foreach (CWidgetFieldBadgesList::SCOPE_LABELS as $val => $label) {
        $opt = (new CTag('option', true, _($label)))->setAttribute('value', $val);
        if ((int) $val === $cur_scope) {
            $opt->setAttribute('selected', 'selected');
        }
        $scope_select->addItem($opt);
    }
    if (!$show_scope) {
        $scope_select->setAttribute('style', 'display: none');
    }

    $item_name_input = (new CTextBox('', $badge['item_name'] ?? CWidgetFieldBadgesList::DEFAULT_ITEM_UPTIME))
        ->setAttribute('placeholder', _('Uptime item name'))
        ->addClass('js-badge-item-name');

    if (!$show_item_name) {
        $item_name_input->setAttribute('style', 'display: none');
    }

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
        ->addItem($drag_handle)
        ->addItem($type_select)
        ->addItem($hostname_link_label)
        ->addItem($hostname_link_select)
        ->addItem($scope_label)
        ->addItem($scope_select)
        ->addItem($uptime_item_label)
        ->addItem($item_name_input)
        ->addItem($text_label)
        ->addItem($text_input)
        ->addItem($url_input)
        ->addItem($remove_btn);
}
