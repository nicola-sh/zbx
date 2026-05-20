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
    /** @var array<string, mixed> Temporary overrides while validate() runs. */
    private array $field_value_overrides = [];

    public const CHART_TYPE_LINE = 0;
    public const CHART_TYPE_AREA = 1;
    public const CHART_TYPE_BAR  = 2;

    public const LEGEND_TOP    = 0;
    public const LEGEND_BOTTOM = 1;
    public const LEGEND_HIDDEN = 2;

    public const DEFAULT_PERIOD = '3h';

    /** @var array<string, string> Period code => label */
    private const PERIOD_OPTIONS = [
        '1h' => '1 hour',
        '3h' => '3 hours',
        '12h' => '12 hours',
        '1d' => '1 day',
        '3d' => '3 days',
        '1w' => '1 week',
        '30d' => '30 days',
    ];

    /** Legacy radio indices from older editor builds. */
    private const PERIOD_INDEX_TO_CODE = [
        '0' => '1h',
        '1' => '3h',
        '2' => '12h',
        '3' => '1d',
        '4' => '3d',
        '5' => '1w',
        '6' => '30d',
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
                (new CWidgetFieldMultiSelectHost('hostid', 'Hosts'))
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
                (new CWidgetFieldCheckBox('chart_use_dashboard_time', 'Use dashboard time range'))
                    ->setDefault(0)
            )
            ->addField(
                (new CWidgetFieldTextBox('chart_series', 'Series (JSON)'))
                    ->setDefault(ChartSeriesHelper::encode(ChartSeriesHelper::defaults()))
            );
    }

    /**
     * Zabbix 7.x radio fields must use simple stored values (0, 1, 2…).
     * Using period codes (1h, 3h) as radio values makes some builds submit labels instead.
     */
    private function makeRadioButtonField(
        string $name,
        string $label,
        array $options,
        mixed $default
    ): CWidgetFieldRadioButtonList {
        $field = new CWidgetFieldRadioButtonList($name, $label);

        if (!method_exists($field, 'setValues')) {
            return $field->setDefault($default);
        }

        $values = [];

        foreach ($options as $value => $option_label) {
            $values[] = [
                'value' => (string) $value,
                'label' => (string) $option_label,
            ];
        }

        return $field
            ->setValues($values)
            ->setDefault((string) $default);
    }

    protected function normalizeValues(array $values): array
    {
        $values = parent::normalizeValues($values);

        if (array_key_exists('chart_period', $values)) {
            $values['chart_period'] = self::normalizePeriodForStorage($values['chart_period']);
        }

        $hostids = self::resolveHostIdsFromValues($values);

        if (count($hostids) === 1 && array_key_exists('chart_series', $values)) {
            $values['chart_series'] = self::bindSeriesToHost(
                (string) $values['chart_series'],
                $hostids[0]
            );
        }

        return $values;
    }

    /**
     * Converts stored widget value (code, index, or label) to a history period code.
     */
    public static function normalizePeriodForStorage(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return self::DEFAULT_PERIOD;
        }

        if (isset(self::PERIOD_INDEX_TO_CODE[$value])) {
            return self::PERIOD_INDEX_TO_CODE[$value];
        }

        if (array_key_exists($value, self::PERIOD_OPTIONS)) {
            return $value;
        }

        foreach (self::PERIOD_OPTIONS as $code => $label) {
            if ($value === $label) {
                return $code;
            }
        }

        return self::DEFAULT_PERIOD;
    }

    public function getFieldValue(string $field_name)
    {
        if (array_key_exists($field_name, $this->field_value_overrides)) {
            return $this->field_value_overrides[$field_name];
        }

        return parent::getFieldValue($field_name);
    }

    public function validate(bool $strict = false): array
    {
        $this->field_value_overrides['chart_period'] = self::normalizePeriodForStorage(
            parent::getFieldValue('chart_period')
        );

        try {
            $errors = parent::validate($strict);
        }
        finally {
            $this->field_value_overrides = [];
        }

        if (!self::hasConfiguredValue($this->getFieldValue('hostid'))
                && !self::hasConfiguredValue($this->getFieldValue('override_hostid'))) {
            $this->addFieldError($errors, 'hostid', 'cannot be empty');
        }

        $selected_hostids = $this->resolveSelectedHostIds();

        $raw = trim((string) $this->getFieldValue('chart_series'));

        if ($raw === '') {
            return $errors;
        }

        $parsed = ChartSeriesHelper::parseForValidation($raw);

        if ($parsed['error'] !== null) {
            $this->addFieldError($errors, 'chart_series', (string) $parsed['error']);
        }

        if ($parsed['truncated']) {
            $this->addFieldError(
                $errors,
                'chart_series',
                'supports at most ' . ChartSeriesHelper::MAX_SERIES . ' series; extra entries were ignored'
            );
        }

        $series = $parsed['series'];

        foreach ($series as $index => $entry) {
            $item_name = trim((string) ($entry['item_name'] ?? ''));
            $itemid = trim((string) ($entry['itemid'] ?? ''));
            $series_hostid = trim((string) ($entry['hostid'] ?? ''));

            if ($item_name === '' && $itemid === '') {
                $this->addFieldError(
                    $errors,
                    'chart_series',
                    'series '.($index + 1).': item name or itemid is required'
                );

                continue;
            }

            if (count($selected_hostids) > 1
                    && $series_hostid === ''
                    && trim((string) ($entry['host'] ?? '')) === '') {
                $this->addFieldError(
                    $errors,
                    'chart_series',
                    'series '.($index + 1).': select a host for this metric'
                );
            }

            if ($series_hostid !== '' && !in_array($series_hostid, $selected_hostids, true)) {
                $this->addFieldError(
                    $errors,
                    'chart_series',
                    'series '.($index + 1).': host must be one of the hosts selected above'
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
    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    private static function resolveHostIdsFromValues(array $values): array
    {
        $override = self::normalizeHostIds($values['override_hostid'] ?? []);

        if ($override !== []) {
            return $override;
        }

        return self::normalizeHostIds($values['hostid'] ?? []);
    }

    private static function bindSeriesToHost(string $raw, string $hostid): string
    {
        $series = ChartSeriesHelper::parse($raw);

        foreach ($series as $index => $entry) {
            $series[$index]['hostid'] = $hostid;
            $series[$index]['host'] = '';
        }

        return ChartSeriesHelper::encode($series);
    }

    private static function normalizeHostIds(mixed $value): array
    {
        if (!is_array($value)) {
            $hostid = trim((string) $value);

            return $hostid !== '' ? [$hostid] : [];
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
