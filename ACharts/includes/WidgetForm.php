<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\ACharts\Includes;

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

    private const PERIOD_OPTIONS = [
        '1h' => '1 hour',
        '3h' => '3 hours',
        '12h' => '12 hours',
        '1d' => '1 day',
        '3d' => '3 days',
        '1w' => '1 week',
        '30d' => '30 days',
    ];

    private const CHART_TYPE_OPTIONS = [
        '0' => 'Line',
        '1' => 'Area',
        '2' => 'Bar',
    ];

    private const LEGEND_OPTIONS = [
        '0' => 'Top',
        '1' => 'Bottom',
        '2' => 'Hidden',
    ];

    public function addFields(): self
    {
        return $this
            ->addField(
                (new CWidgetFieldMultiSelectHost('hostid', 'Host'))
                    ->setMultiple(true)
            )
            ->addField(
                new CWidgetFieldMultiSelectOverrideHost()
            )
            ->addField(
                $this->makeRadioButtonField(
                    'chart_period',
                    'Period',
                    self::PERIOD_OPTIONS,
                    self::DEFAULT_PERIOD
                )
            )
            ->addField(
                $this->makeRadioButtonField(
                    'chart_type',
                    'Chart type',
                    self::CHART_TYPE_OPTIONS,
                    (string) self::CHART_TYPE_LINE
                )
            )
            ->addField(
                $this->makeRadioButtonField(
                    'legend_position',
                    'Legend',
                    self::LEGEND_OPTIONS,
                    (string) self::LEGEND_TOP
                )
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

    /**
     * Some Zabbix builds use radio options in constructor, others rely on setValues().
     * Keep both paths to avoid widget.edit fatals on mixed 7.x patch levels.
     */
    private function makeRadioButtonField(
        string $name,
        string $label,
        array $options,
        mixed $default
    ): CWidgetFieldRadioButtonList {
        try {
            $field = new CWidgetFieldRadioButtonList($name, $label, $options);

            return $field->setDefault($default);
        }
        catch (\Throwable $exception) {
            $field = new CWidgetFieldRadioButtonList($name, $label);

            if (!method_exists($field, 'setValues')) {
                throw $exception;
            }

            $legacy_values = [];

            foreach ($options as $value => $option_label) {
                $legacy_values[] = [
                    'value' => (string) $value,
                    'label' => (string) $option_label,
                ];
            }

            error_log(sprintf(
                '[acharts] Falling back to setValues() for "%s": %s',
                $name,
                $exception->getMessage()
            ));

            return $field
                ->setValues($legacy_values)
                ->setDefault($default);
        }
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
        $selected_hostids = $this->resolveSelectedHostIds();

        foreach ($series as $index => $entry) {
            $item_name = trim((string) ($entry['item_name'] ?? ''));
            $itemid = trim((string) ($entry['itemid'] ?? ''));

            if ($item_name === '' && $itemid === '') {
                $this->addFieldError(
                    $errors,
                    'chart_series',
                    'series '.($index + 1).': item name or itemid is required'
                );
            }

            if (count($selected_hostids) > 1
                    && trim((string) ($entry['hostid'] ?? '')) === ''
                    && trim((string) ($entry['host'] ?? '')) === '') {
                $this->addFieldError(
                    $errors,
                    'chart_series',
                    'series '.($index + 1).': hostid or host must be set when multiple hosts are selected'
                );
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function resolveSelectedHostIds(): array
    {
        $override = self::normalizeHostIds($this->getFieldValue('override_hostid'));

        if ($override !== []) {
            return $override;
        }

        return self::normalizeHostIds($this->getFieldValue('hostid'));
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

    /**
     * @return list<string>
     */
    private static function normalizeHostIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $hostids = [];

        foreach ($value as $hostid) {
            if (!is_scalar($hostid)) {
                continue;
            }

            $hostid = trim((string) $hostid);

            if ($hostid !== '') {
                $hostids[] = $hostid;
            }
        }

        return array_values(array_unique($hostids));
    }
}
