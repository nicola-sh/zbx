<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

use Modules\MainCharts\Includes\ChartSeriesHelper;

$form = new CWidgetFormView($data);

$form
    ->addField(
        new CWidgetFieldMultiSelectHostView($data['fields']['hostid'])
    )
    ->addField($data['templateid'] === null
        ? new CWidgetFieldMultiSelectOverrideHostView($data['fields']['override_hostid'])
        : null
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView('Chart'))
            ->addFieldsGroup(new CWidgetFieldsGroupView('', [
                new CWidgetFieldRadioButtonListView($data['fields']['chart_period']),
            ]))
            ->addFieldsGroup(new CWidgetFieldsGroupView('', [
                new CWidgetFieldRadioButtonListView($data['fields']['chart_type']),
            ]))
            ->addFieldsGroup(new CWidgetFieldsGroupView('', [
                new CWidgetFieldRadioButtonListView($data['fields']['legend_position']),
            ]))
            ->addItem(getCheckBoxView($form, $data['fields']['chart_stacked'],
                'Stack series when using area or bar charts.'
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['chart_fill'],
                'Fill the area under line charts.'
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['show_grid'],
                'Draw horizontal and vertical grid lines.'
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView('Data series'))
            ->addItem([
                (new CLabel($data['fields']['chart_series']->getLabel(), $data['fields']['chart_series']->getName()))
                    ->addItem(makeHelpIcon(
                        'JSON array of series. Each entry needs label, item_name (exact Zabbix item name), and optional key and color as RRGGBB hex.'
                    )),
                (new CDiv())->addItem(
                    $form->registerField(new CWidgetFieldTextBoxView($data['fields']['chart_series']))->getView()
                ),
            ])
            ->addItem(
                (new CDiv())
                    ->addClass('main-charts-series-hint')
                    ->addItem('Default preset includes CPU and Memory utilization for typical Linux/Windows agent templates.')
            )
            ->addItem(
                (new CButton(null, 'Reset series to defaults'))
                    ->addClass('js-charts-reset-series')
                    ->setAttribute('type', 'button')
                    ->setAttribute('data-default-series', ChartSeriesHelper::encode(ChartSeriesHelper::defaults()))
            )
    );

$form->includeJsFile('widget.edit.js.php');
$form->show();

function getCheckBoxView(CWidgetFormView $form, $field, string $hint = ''): array
{
    $label = (new CLabel($field->getLabel(), $field->getName()));
    $view = $form->registerField(new CWidgetFieldCheckBoxView($field));

    if ($hint !== '') {
        $label->addItem(makeHelpIcon($hint));
    }

    return [
        $label,
        new CFormField($view->getView()),
    ];
}
