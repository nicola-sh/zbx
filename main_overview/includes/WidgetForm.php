<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainOverview\Includes;

use JsonException;
use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldCheckBoxList;
use Zabbix\Widgets\Fields\CWidgetFieldColor;
use Zabbix\Widgets\Fields\CWidgetFieldIntegerBox;
use Zabbix\Widgets\Fields\CWidgetFieldCheckBox;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectHost;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectOverrideHost;
use Zabbix\Widgets\Fields\CWidgetFieldRadioButtonList;
use Zabbix\Widgets\Fields\CWidgetFieldTextBox;

class WidgetForm extends CWidgetForm
{
    public const COLOR_SCHEME_THRESHOLD = 0;
    public const COLOR_SCHEME_SOLID     = 1;

    public const CORNERS_ROUNDED = 0;
    public const CORNERS_SQUARE  = 1;

    public const LABELS_FULL  = 0;
    public const LABELS_SHORT = 1;

    public const DEFAULT_BAR_HEIGHT = 8;

    public const INTERFACES_UNIT_KBPS = 0;
    public const INTERFACES_UNIT_MBPS = 1;
    public const INTERFACES_UNIT_GBPS = 2;

    public const METRIC_CPU        = 0;
    public const METRIC_RAM        = 1;
    public const METRIC_LOAD       = 2;
    public const METRIC_SWAP       = 3;
    public const METRIC_INTERFACES = 4;
    public const METRIC_DISKS      = 5;
    public const METRIC_PARTITIONS = 6;

    public const DEFAULT_COLOR_FILL             = '458ADC';
    public const DEFAULT_COLOR_THRESHOLD_HIGH   = 'FF4136';
    public const DEFAULT_COLOR_THRESHOLD_MEDIUM = 'FF851B';
    public const DEFAULT_COLOR_THRESHOLD_LOW    = '4C9F38';

    public const DEFAULT_THRESHOLD_HIGH        = 85;
    public const DEFAULT_THRESHOLD_MEDIUM      = 70;
    public const DEFAULT_THRESHOLD_SWAP_HIGH   = 10;
    public const DEFAULT_THRESHOLD_SWAP_MEDIUM = 5;
    public const DEFAULT_FRESHNESS_WARN        = 90;
    public const DEFAULT_FRESHNESS_STALE       = 300;

    public const DEFAULT_LOAD_HIGH       = 2;
    public const DEFAULT_INTERFACES_HIGH = 1;

    public const DEFAULT_ITEM_CPU      = 'CPU utilization';
    public const DEFAULT_ITEM_RAM      = 'Memory utilization';
    public const DEFAULT_ITEM_LOAD     = 'Load average (5m avg)';
    public const DEFAULT_ITEM_SWAP     = 'Free swap space in %';

    public const DEFAULT_ITEM_DISK        = '*:: Disk utilization by idle time';
    public const DEFAULT_ITEM_PARTITION   = 'FS [*]: Space: Used, in %';
    public const DEFAULT_ITEM_INTERFACE   = 'Interface *: Bits *';

    public const MULTI_HOST_BADGES_SUMMARY = 0;

    public const MULTI_HOST_BADGES_DETAIL_ONLY = 1;

    private const THRESHOLD_METRIC_FIELDS = [
        'cpu',
        'ram',
        'load',
        'swap',
        'iface',
        'disk',
        'partition',
    ];

    /** @var list<string> */
    private const SNAPSHOT_FIELD_NAMES = [
        'hostid',
        'override_hostid',
        'host_profiles',
        'badges',
        'badge_uptime_item_name',
        'badge_liveliness_item_name',
        'problems_hide_acknowledged',
        'fill_color',
        'th_color_1',
        'th_color_2',
        'th_color_3',
        'th_num_1',
        'th_num_2',
        'th_cpu_1',
        'th_cpu_2',
        'th_ram_1',
        'th_ram_2',
        'th_load_1',
        'th_load_2',
        'th_swap_1',
        'th_swap_2',
        'th_iface_1',
        'th_iface_2',
        'th_disk_1',
        'th_disk_2',
        'th_partition_1',
        'th_partition_2',
        'metrics_show',
        'load_high',
        'interfaces_high',
        'interfaces_unit',
        'color_scheme',
        'corners',
        'label_length',
        'bar_height',
        'open_links_same_window',
        'problems_pulse',
        'freshness_warn',
        'freshness_stale',
        'problems_hide_suppressed',
        'interfaces_exclude',
        'disks_exclude',
        'partitions_exclude',
        'item_name_cpu',
        'item_name_ram',
        'item_name_load',
        'item_name_swap',
        'item_swap_invert',
        'item_name_disk',
        'item_name_partition',
        'item_name_interface',
    ];

    public function addFields(): self
    {
        $this
            ->addField(
                (new CWidgetFieldMultiSelectHost('hostid', _m('Hosts')))
                    ->setMultiple(true)
            )
            ->addField(
                new CWidgetFieldMultiSelectOverrideHost()
            )
            ->addField(
                (new CWidgetFieldTextBox('host_profiles', _m('Per-host overrides')))
                    ->setDefault('[]')
            )
            ->addField(
                (new CWidgetFieldBadgesList('badges'))
            )
            ->addField(
                (new CWidgetFieldTextBox('badge_uptime_item_name', _m('Uptime item')))
                    ->setDefault(CWidgetFieldBadgesList::DEFAULT_ITEM_UPTIME)
            )
            ->addField(
                (new CWidgetFieldTextBox('badge_liveliness_item_name', _m('Liveliness item')))
                    ->setDefault(CWidgetFieldBadgesList::DEFAULT_ITEM_LIVELINESS)
            )
            ->addField(
                (new CWidgetFieldCheckBox('problems_hide_acknowledged', _m('Ignore acknowledged problems')))
                    ->setDefault(0)
            )
            ->addField(
                (new CWidgetFieldColor('fill_color', _m('Solid')))
                    ->setDefault(self::DEFAULT_COLOR_FILL)
            )
            ->addField(
                (new CWidgetFieldColor('th_color_1', null))
                    ->setDefault(self::DEFAULT_COLOR_THRESHOLD_HIGH)
            )
            ->addField(
                (new CWidgetFieldColor('th_color_2', null))
                    ->setDefault(self::DEFAULT_COLOR_THRESHOLD_MEDIUM)
            )
            ->addField(
                (new CWidgetFieldColor('th_color_3', _m('Normal')))
                    ->setDefault(self::DEFAULT_COLOR_THRESHOLD_LOW)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_num_1', null, 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_HIGH)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_num_2', null, 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_MEDIUM)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_cpu_1', _m('CPU: high'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_HIGH)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_cpu_2', _m('CPU: medium'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_MEDIUM)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_ram_1', _m('Memory: high'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_HIGH)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_ram_2', _m('Memory: medium'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_MEDIUM)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_load_1', _m('Load: high'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_HIGH)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_load_2', _m('Load: medium'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_MEDIUM)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_swap_1', _m('Swap: high'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_SWAP_HIGH)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_swap_2', _m('Swap: medium'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_SWAP_MEDIUM)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_iface_1', _m('Interfaces: high'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_HIGH)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_iface_2', _m('Interfaces: medium'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_MEDIUM)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_disk_1', _m('Disk: high'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_HIGH)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_disk_2', _m('Disk: medium'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_MEDIUM)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_partition_1', _m('Partition: high'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_HIGH)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('th_partition_2', _m('Partition: medium'), 1, 100))
                    ->setDefault(self::DEFAULT_THRESHOLD_MEDIUM)
            )
            ->addField(
                (new CWidgetFieldCheckBoxList('metrics_show', _m('Metrics'), [
                    self::METRIC_CPU        => _m('CPU'),
                    self::METRIC_RAM        => _m('Memory'),
                    self::METRIC_LOAD       => _m('Load'),
                    self::METRIC_SWAP       => _m('Swap'),
                    self::METRIC_INTERFACES => _m('Interfaces'),
                    self::METRIC_DISKS      => _m('Disk utilization'),
                    self::METRIC_PARTITIONS => _m('Partitions'),
                ]))
                    ->setDefault([
                        self::METRIC_CPU,
                        self::METRIC_RAM,
                        self::METRIC_LOAD,
                        self::METRIC_SWAP,
                        self::METRIC_INTERFACES,
                        self::METRIC_DISKS,
                        self::METRIC_PARTITIONS,
                    ])
            )
            ->addField(
                (new CWidgetFieldIntegerBox('load_high', _m('Load ceiling'), 1, 1000))
                    ->setDefault(self::DEFAULT_LOAD_HIGH)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('interfaces_high', _m('Interface ceiling'), 1, 10000))
                    ->setDefault(self::DEFAULT_INTERFACES_HIGH)
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('interfaces_unit', null, [
                    self::INTERFACES_UNIT_KBPS => _m('Kbps'),
                    self::INTERFACES_UNIT_MBPS => _m('Mbps'),
                    self::INTERFACES_UNIT_GBPS => _m('Gbps'),
                ]))
                    ->setDefault(self::INTERFACES_UNIT_GBPS)
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('color_scheme', _m('Color scheme'), [
                    self::COLOR_SCHEME_THRESHOLD => _m('By thresholds'),
                    self::COLOR_SCHEME_SOLID     => _m('Solid fill'),
                ]))
                    ->setDefault(self::COLOR_SCHEME_THRESHOLD)
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('corners', _m('Corners'), [
                    self::CORNERS_ROUNDED => _m('Rounded'),
                    self::CORNERS_SQUARE  => _m('Square'),
                ]))
                    ->setDefault(self::CORNERS_ROUNDED)
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('label_length', _m('Labels'), [
                    self::LABELS_FULL  => _m('Full'),
                    self::LABELS_SHORT => _m('Short'),
                ]))
                    ->setDefault(self::LABELS_FULL)
            )
            ->addField(
                (new CWidgetFieldRadioButtonList('bar_height', _m('Bar height'), [
                    4  => '4px',
                    5  => '5px',
                    6  => '6px',
                    7  => '7px',
                    8  => '8px',
                    9  => '9px',
                    10 => '10px',
                ]))
                    ->setDefault(self::DEFAULT_BAR_HEIGHT)
            )
            ->addField(
                (new CWidgetFieldCheckBox('open_links_same_window', _m('Open links in this tab')))
                    ->setDefault(0)
            )
            ->addField(
                (new CWidgetFieldCheckBox('problems_pulse', _m('Problems badge pulse')))
                    ->setDefault(1)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('freshness_warn', _m('Liveliness: warning'), 1, 86400))
                    ->setDefault(self::DEFAULT_FRESHNESS_WARN)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('freshness_stale', _m('Liveliness: stale'), 1, 86400))
                    ->setDefault(self::DEFAULT_FRESHNESS_STALE)
            )
            ->addField(
                (new CWidgetFieldCheckBox('problems_hide_suppressed', _m('Ignore suppressed problems')))
                    ->setDefault(0)
            )
            ->addField(
                (new CWidgetFieldTextBox('interfaces_exclude', _m('Interface filter')))
                    ->setDefault('')
            )
            ->addField(
                (new CWidgetFieldTextBox('disks_exclude', _m('Disk filter')))
                    ->setDefault('')
            )
            ->addField(
                (new CWidgetFieldTextBox('partitions_exclude', _m('Partition filter')))
                    ->setDefault('')
            )
            ->addField(
                (new CWidgetFieldTextBox('item_name_cpu', _m('Item: CPU')))
                    ->setDefault(self::DEFAULT_ITEM_CPU)
            )
            ->addField(
                (new CWidgetFieldTextBox('item_name_ram', _m('Item: memory')))
                    ->setDefault(self::DEFAULT_ITEM_RAM)
            )
            ->addField(
                (new CWidgetFieldTextBox('item_name_load', _m('Item: load')))
                    ->setDefault(self::DEFAULT_ITEM_LOAD)
            )
            ->addField(
                (new CWidgetFieldTextBox('item_name_swap', _m('Item: swap')))
                    ->setDefault(self::DEFAULT_ITEM_SWAP)
            )
            ->addField(
                (new CWidgetFieldCheckBox('item_swap_invert', _m('Invert swap')))
                    ->setDefault(1)
            )
            ->addField(
                (new CWidgetFieldTextBox('item_name_disk', _m('Disk template')))
                    ->setDefault(self::DEFAULT_ITEM_DISK)
            )
            ->addField(
                (new CWidgetFieldTextBox('item_name_partition', _m('Partition template')))
                    ->setDefault(self::DEFAULT_ITEM_PARTITION)
            )
            ->addField(
                (new CWidgetFieldTextBox('item_name_interface', _m('Interface template')))
                    ->setDefault(self::DEFAULT_ITEM_INTERFACE)
            );

        return $this;
    }

    protected function normalizeValues(array $values): array
    {
        foreach (self::THRESHOLD_METRIC_FIELDS as $metric) {
            $high_field = 'th_m' . $metric . '_1';
            $medium_field = 'th_m' . $metric . '_2';

            if (!array_key_exists($high_field, $values) && array_key_exists('th_num_1', $values)) {
                $values[$high_field] = $values['th_num_1'];
            }

            if (!array_key_exists($medium_field, $values) && array_key_exists('th_num_2', $values)) {
                $values[$medium_field] = $values['th_num_2'];
            }
        }

        $values = parent::normalizeValues($values);

        $ordered = self::collectOrderedHostIdsFromValues($values);
        $profiles_raw = $values['host_profiles'] ?? '[]';

        try {
            $profiles = HostProfilesHelper::parse($profiles_raw);
            $values['host_profiles'] = HostProfilesHelper::encode(
                HostProfilesHelper::syncWithHostOrder($profiles, $ordered)
            );
        }
        catch (JsonException) {
            $values['host_profiles'] = HostProfilesHelper::encode(
                HostProfilesHelper::syncWithHostOrder([], $ordered)
            );
        }

        return $values;
    }

    public function validate(bool $strict = false): array
    {
        $errors = parent::validate($strict);

        if (!self::hasConfiguredValue($this->getFieldValue('hostid'))
                && !self::hasConfiguredValue($this->getFieldValue('override_hostid'))) {
            $this->addFieldError($errors, 'hostid', _m('cannot be empty'));
        }

        $profiles_raw = trim((string) $this->getFieldValue('host_profiles'));

        if ($profiles_raw === '') {
            $profiles_raw = '[]';
        }

        try {
            json_decode($profiles_raw, true, 512, JSON_THROW_ON_ERROR);
        }
        catch (JsonException) {
            $this->addFieldError($errors, 'host_profiles', _m('must be valid JSON'));

            return $errors;
        }

        $profiles = HostProfilesHelper::parse($profiles_raw);

        $ordered = $this->resolveOrderedHostIdsFromForm();

        if ($ordered !== [] && count($profiles) !== count($ordered)) {
            $this->addFieldError($errors, 'host_profiles', _m('must contain one entry per selected host'));
        }

        foreach ($profiles as $index => $profile) {
            $pos = $index + 1;
            $host_label = (string) ($profile['hostid'] ?? $pos);

            if ($ordered !== [] && !in_array($profile['hostid'], $ordered, true)) {
                $this->addFieldErrorToHostProfiles(
                    $errors,
                    _ms('Host %1$s: not in the current selection.', $host_label)
                );
            }
        }

        $base = $this->snapshotFieldValues();

        foreach ($profiles as $index => $profile) {
            $pos = $index + 1;
            $merged = HostProfilesHelper::mergeProfile($base, $profile);
            $this->validateProfileConfiguration($errors, $merged, $pos);
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $merged
     */
    private function validateProfileConfiguration(array &$errors, array $merged, int $position): void
    {
        $enabled_metrics = array_map('intval', (array) ($merged['metrics_show'] ?? []));

        foreach ([
            self::METRIC_CPU => 'item_name_cpu',
            self::METRIC_RAM => 'item_name_ram',
            self::METRIC_LOAD => 'item_name_load',
            self::METRIC_SWAP => 'item_name_swap',
        ] as $metric => $field_name) {
            if (in_array($metric, $enabled_metrics, true)) {
                $this->validateRequiredTextFieldMerged($errors, $merged, $field_name, $position);
            }
        }

        if (in_array(self::METRIC_DISKS, $enabled_metrics, true)) {
            $this->validateWildcardPatternFieldMerged($errors, $merged, 'item_name_disk', 1, $position);
        }

        if (in_array(self::METRIC_PARTITIONS, $enabled_metrics, true)) {
            $this->validateWildcardPatternFieldMerged($errors, $merged, 'item_name_partition', 1, $position);
        }

        if (in_array(self::METRIC_INTERFACES, $enabled_metrics, true)) {
            $this->validateWildcardPatternFieldMerged($errors, $merged, 'item_name_interface', 2, $position);
        }

        if ($this->hasBadgeTypeInMerged($merged, CWidgetFieldBadgesList::BADGE_UPTIME)) {
            $this->validateRequiredTextFieldMerged($errors, $merged, 'badge_uptime_item_name', $position);
        }

        if ($this->hasBadgeTypeInMerged($merged, CWidgetFieldBadgesList::BADGE_LIVELINESS)) {
            $this->validateRequiredTextFieldMerged($errors, $merged, 'badge_liveliness_item_name', $position);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFieldValues(): array
    {
        $snapshot = [];

        foreach (self::SNAPSHOT_FIELD_NAMES as $name) {
            $snapshot[$name] = $this->getFieldValue($name);
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $merged
     */
    private function hasBadgeTypeInMerged(array $merged, int $type): bool
    {
        $badges_raw = $merged['badges'] ?? '[]';
        $badges = is_string($badges_raw) ? (json_decode($badges_raw, true) ?: []) : [];

        if (!is_array($badges)) {
            return false;
        }

        foreach ($badges as $badge) {
            if ((int) ($badge['type'] ?? -1) === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $merged
     */
    private function validateRequiredTextFieldMerged(
        array &$errors,
        array $merged,
        string $field_name,
        int $position
    ): void {
        if (trim((string) ($merged[$field_name] ?? '')) === '') {
            $this->addFieldErrorToHostProfiles(
                $errors,
                _ms('Host row %1$s: field "%2$s" cannot be empty.', $position, $this->getField($field_name)->getErrorLabel())
            );
        }
    }

    /**
     * @param array<string, mixed> $merged
     */
    private function validateWildcardPatternFieldMerged(
        array &$errors,
        array $merged,
        string $field_name,
        int $required_wildcards,
        int $position
    ): void {
        $value = trim((string) ($merged[$field_name] ?? ''));

        if ($value === '') {
            $this->addFieldErrorToHostProfiles(
                $errors,
                _ms('Host row %1$s: field "%2$s" cannot be empty.', $position, $this->getField($field_name)->getErrorLabel())
            );

            return;
        }

        if (substr_count($value, '*') < $required_wildcards) {
            $message = $required_wildcards === 1
                ? _m('must contain at least one "*" character')
                : _ms('requires at least %1$s "*" characters', $required_wildcards);

            $this->addFieldErrorToHostProfiles(
                $errors,
                _ms(
                    'Host row %1$s: invalid parameter "%2$s": %3$s.',
                    $position,
                    $this->getField($field_name)->getErrorLabel(),
                    $message
                )
            );
        }
    }

    private function addFieldErrorToHostProfiles(array &$errors, string $message): void
    {
        $errors[] = _ms(
            'Invalid parameter "%1$s": %2$s.',
            $this->getField('host_profiles')->getErrorLabel(),
            $message
        );
    }

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    private static function collectOrderedHostIdsFromValues(array $values): array
    {
        $override = self::normalizeHostIdsScalarList($values['override_hostid'] ?? []);

        if ($override !== []) {
            return $override;
        }

        return self::normalizeHostIdsScalarList($values['hostid'] ?? []);
    }

    /**
     * @return list<string>
     */
    private function resolveOrderedHostIdsFromForm(): array
    {
        $override = self::normalizeHostIdsScalarList($this->getFieldValue('override_hostid'));

        if ($override !== []) {
            return $override;
        }

        return self::normalizeHostIdsScalarList($this->getFieldValue('hostid'));
    }

    /**
     * @return list<string>
     */
    private static function normalizeHostIdsScalarList(mixed $value): array
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

    private function addFieldError(array &$errors, string $field_name, string $message): void
    {
        $errors[] = _ms(
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
