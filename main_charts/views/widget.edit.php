<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

use Modules\MainCharts\Includes\ChartSeriesHelper;
use Modules\MainCharts\Includes\WidgetForm;

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
        (new CWidgetFormFieldsetCollapsibleView(_c('Chart')))
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
                _c('Stack series when using area or bar charts.')
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['chart_fill'],
                _c('Fill the area under line charts.')
            ))
            ->addItem(getCheckBoxView($form, $data['fields']['show_grid'],
                _c('Draw horizontal and vertical grid lines.')
            ))
    )
    ->addFieldset(
        (new CWidgetFormFieldsetCollapsibleView(_c('Data series')))
            ->addItem([
                (new CLabel($data['fields']['chart_series']->getLabel(), $data['fields']['chart_series']->getName()))
                    ->addItem(makeHelpIcon(_c(
                        'JSON array of series. Each entry needs label, item_name (exact Zabbix item name), and optional key and color (RRGGBB). Example: [{"label":"CPU","item_name":"CPU utilization","color":"458ADC"}]'
                    ))),
                (new CDiv())->addItem($form->registerField(new CWidgetFieldTextBoxView($data['fields']['chart_series']))),
            ])
            ->addItem(
                (new CDiv())
                    ->addClass('main-charts-series-hint')
                    ->addItem(_c('Default preset includes CPU and Memory utilization for typical Linux/Windows agent templates.'))
            )
            ->addItem(
                (new CButton(null, _c('Reset series to defaults')))
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

    if ($hint !== '') {
        $label->addItem(makeHelpIcon($hint));
    }

    return [
        $label,
        (new CDiv())->addItem($form->registerField(new CWidgetFieldCheckBoxView($field))),
    ];
}
