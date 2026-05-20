<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

use Modules\ACharts\Includes\ChartSeriesHelper;

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
            ->addItem(
                (new CDiv())
                    ->addClass('a-charts-series-hint')
                    ->addItem('Select one host above. Each series is a metric (item) from that host only — Find, Browse items, or Quick add (CPU, Memory, …).')
            )
            ->addItem(
                (new CDiv())
                    ->addClass('charts-series-editor')
                    ->addClass('js-charts-series-editor')
                    ->setAttribute('data-lookup-action', 'widget.acharts.lookup')
                    ->setAttribute('data-max-series', (string) ChartSeriesHelper::MAX_SERIES)
                    ->setAttribute(
                        'data-default-series',
                        ChartSeriesHelper::encode(ChartSeriesHelper::defaults())
                    )
            )
            ->addItem(
                (new CDiv())
                    ->addClass('charts-series-json-wrap')
                    ->addClass('js-charts-series-json-wrap')
                    ->addItem(
                        (new CTag('details', true))
                            ->addItem(
                                (new CTag('summary', true))
                                    ->addItem('Advanced: edit JSON')
                            )
                            ->addItem(
                                (new CDiv())->addItem(
                                    $form->registerField(new CWidgetFieldTextBoxView($data['fields']['chart_series']))->getView()
                                )
                            )
                    )
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
