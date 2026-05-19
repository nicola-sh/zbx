<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainCharts\Includes;

use JsonException;
use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldCheckBox;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectHost;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectOverrideHost;
use Zabbix\Widgets\Fields\CWidgetFieldRadioButtonList;
use Zabbix\Widgets\Fields\CWidgetFieldTextBox;

class WidgetForm extends CWidgetForm
{
    public const CHART_TYPE_LINE = 0;
    public const CHART_TYPE_AREA = 1;
    public const CHART_TYPE_BAR  = 2;

    public const LEGEND_TOP    = 0;
    public const LEGEND_BOTTOM = 1;
    public const LEGEND_HIDDEN = 2;

    public const DEFAULT_PERIOD = '3h';

    public function addFields(): self
    {
        return $this
            ->addField(
                (new CWidgetFieldMultiSelectHost('hostid', 'Host'))
            )
            ->addField(
                new CWidgetFieldMultiSelectOverrideHost()
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('chart_period', 'Period'))
                    ->setDefault(self::DEFAULT_PERIOD)
                    ->setValues([
                        ['value' => '1h', 'label' => '1 hour'],
                        ['value' => '3h', 'label' => '3 hours'],
                        ['value' => '12h', 'label' => '12 hours'],
                        ['value' => '1d', 'label' => '1 day'],
                        ['value' => '3d', 'label' => '3 days'],
                        ['value' => '1w', 'label' => '1 week'],
                        ['value' => '30d', 'label' => '30 days'],
                    ])
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('chart_type', 'Chart type'))
                    ->setDefault((string) self::CHART_TYPE_LINE)
                    ->setValues([
                        ['value' => (string) self::CHART_TYPE_LINE, 'label' => 'Line'],
                        ['value' => (string) self::CHART_TYPE_AREA, 'label' => 'Area'],
                        ['value' => (string) self::CHART_TYPE_BAR, 'label' => 'Bar'],
                    ])
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('legend_position', 'Legend'))
                    ->setDefault((string) self::LEGEND_TOP)
                    ->setValues([
                        ['value' => (string) self::LEGEND_TOP, 'label' => 'Top'],
                        ['value' => (string) self::LEGEND_BOTTOM, 'label' => 'Bottom'],
                        ['value' => (string) self::LEGEND_HIDDEN, 'label' => 'Hidden'],
                    ])
            )
            ->addField(
                (new CWidgetFieldCheckBox('chart_stacked', 'Stacked (area/bar)'))
                    ->setDefault(0)
            )
            ->addField(
                (new CWidgetFieldCheckBox('chart_fill', 'Fill under line'))
                    ->setDefault(1)
            )
            ->addField(
                (new CWidgetFieldCheckBox('show_grid', 'Show grid'))
                    ->setDefault(1)
            )
            ->addField(
                (new CWidgetFieldTextBox('chart_series', 'Series (JSON)'))
                    ->setDefault(ChartSeriesHelper::encode(ChartSeriesHelper::defaults()))
            );
    }

    public function validate(bool $strict = false): array
    {
        $errors = parent::validate($strict);

        if (!self::hasConfiguredValue($this->getFieldValue('hostid'))
                && !self::hasConfiguredValue($this->getFieldValue('override_hostid'))) {
            $this->addFieldError($errors, 'hostid', 'cannot be empty');
        }

        $raw = trim((string) $this->getFieldValue('chart_series'));

        if ($raw === '') {
            return $errors;
        }

        try {
            json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        }
        catch (JsonException) {
            $this->addFieldError($errors, 'chart_series', 'must be valid JSON');
        }

        $series = ChartSeriesHelper::parse($raw);

        foreach ($series as $index => $entry) {
            if (trim($entry['item_name']) === '') {
                $this->addFieldError(
                    $errors,
                    'chart_series',
                    'series '.($index + 1).': item name cannot be empty'
                );
            }
        }

        return $errors;
    }

    private function addFieldError(array &$errors, string $field_name, string $message): void
    {
        $errors[] = sprintf(
            'Invalid parameter "%s": %s.',
            $this->getField($field_name)->getErrorLabel(),
            $message
        );
    }

    private static function hasConfiguredValue($value): bool
    {
        if (is_array($value)) {
            if (array_key_exists(CWidgetField::FOREIGN_REFERENCE_KEY, $value)) {
                return trim((string) $value[CWidgetField::FOREIGN_REFERENCE_KEY]) !== '';
            }

            foreach ($value as $entry) {
                if ((string) $entry !== '') {
                    return true;
                }
            }

            return false;
        }

        return trim((string) $value) !== '';
    }
}
