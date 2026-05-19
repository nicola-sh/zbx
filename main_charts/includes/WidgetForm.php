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
                (new CWidgetFieldMultiSelectHost('hostid', _c('Host')))
            )
            ->addField(
                new CWidgetFieldMultiSelectOverrideHost()
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('chart_period', _c('Period')))
                    ->setDefault(self::DEFAULT_PERIOD)
                    ->setValues([
                        ['value' => '1h', 'label' => _c('1 hour')],
                        ['value' => '3h', 'label' => _c('3 hours')],
                        ['value' => '12h', 'label' => _c('12 hours')],
                        ['value' => '1d', 'label' => _c('1 day')],
                        ['value' => '3d', 'label' => _c('3 days')],
                        ['value' => '1w', 'label' => _c('1 week')],
                        ['value' => '30d', 'label' => _c('30 days')],
                    ])
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('chart_type', _c('Chart type')))
                    ->setDefault((string) self::CHART_TYPE_LINE)
                    ->setValues([
                        ['value' => (string) self::CHART_TYPE_LINE, 'label' => _c('Line')],
                        ['value' => (string) self::CHART_TYPE_AREA, 'label' => _c('Area')],
                        ['value' => (string) self::CHART_TYPE_BAR, 'label' => _c('Bar')],
                    ])
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('legend_position', _c('Legend')))
                    ->setDefault((string) self::LEGEND_TOP)
                    ->setValues([
                        ['value' => (string) self::LEGEND_TOP, 'label' => _c('Top')],
                        ['value' => (string) self::LEGEND_BOTTOM, 'label' => _c('Bottom')],
                        ['value' => (string) self::LEGEND_HIDDEN, 'label' => _c('Hidden')],
                    ])
            )
            ->addField(
                (new CWidgetFieldCheckBox('chart_stacked', _c('Stacked (area/bar)')))
                    ->setDefault(0)
            )
            ->addField(
                (new CWidgetFieldCheckBox('chart_fill', _c('Fill under line')))
                    ->setDefault(1)
            )
            ->addField(
                (new CWidgetFieldCheckBox('show_grid', _c('Show grid')))
                    ->setDefault(1)
            )
            ->addField(
                (new CWidgetFieldTextBox('chart_series', _c('Series (JSON)')))
                    ->setDefault(ChartSeriesHelper::encode(ChartSeriesHelper::defaults()))
            );
    }

    public function validate(bool $strict = false): array
    {
        $errors = parent::validate($strict);

        if (!self::hasConfiguredValue($this->getFieldValue('hostid'))
                && !self::hasConfiguredValue($this->getFieldValue('override_hostid'))) {
            $this->addFieldError($errors, 'hostid', _c('cannot be empty'));
        }

        $raw = trim((string) $this->getFieldValue('chart_series'));

        if ($raw === '') {
            return $errors;
        }

        try {
            json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        }
        catch (JsonException) {
            $this->addFieldError($errors, 'chart_series', _c('must be valid JSON'));
        }

        $series = ChartSeriesHelper::parse($raw);

        foreach ($series as $index => $entry) {
            if (trim($entry['item_name']) === '') {
                $this->addFieldError(
                    $errors,
                    'chart_series',
                    _cs('series %1$s: item name cannot be empty', (string) ($index + 1))
                );
            }
        }

        return $errors;
    }

    private function addFieldError(array &$errors, string $field_name, string $message): void
    {
        $errors[] = _cs(
            'Invalid parameter "%1$s": %2$s.',
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
