<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainOverview\Actions;

require_once __DIR__ . '/../includes/format.func.php';

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;
use Modules\MainOverview\Includes\CWidgetFieldBadgesList;
use Modules\MainOverview\Includes\HostProfilesHelper;
use Modules\MainOverview\Includes\MetricMatcher;
use Modules\MainOverview\Includes\WildcardMetricResolver;
use Modules\MainOverview\Includes\WidgetForm;

use function Modules\MainOverview\Includes\format_freshness;
use function Modules\MainOverview\Includes\format_problems;
use function Modules\MainOverview\Includes\format_uptime;
use function Modules\MainOverview\Includes\format_tags;
use function Modules\MainOverview\Includes\format_display_text;
use function Modules\MainOverview\Includes\format_display_value;
use function Modules\MainOverview\Includes\format_empty_text;
use function Modules\MainOverview\Includes\format_empty_value;
use function Modules\MainOverview\Includes\freshness_state_classes;
use function Modules\MainOverview\Includes\problems_state_classes;

class WidgetView extends CControllerDashboardWidgetView
{
    private array $badges = [];
    private array $metrics = [];
    private ?array $host_details = null;
    private ?array $problems = null;
    private ?MetricMatcher $metric_matcher = null;
    private ?WildcardMetricResolver $wildcard_metric_resolver = null;

    private string $cell_id_prefix = '';

    private ?string $context_hostid = null;

    protected function doAction(): void
    {
        $this->fields_values['hostid'] = $this->resolveSelectedHostIds();

        $hostids = $this->normalizeHostIds($this->fields_values['hostid'] ?? []);

        if (count($hostids) > 1) {
            $this->renderMultiHostDashboard($hostids);

            return;
        }

        $this->cell_id_prefix = '';
        $this->context_hostid = null;

        $this->badges = $this->decodeBadges();
        $this->metrics = $this->collectMetrics();

        $badges = $this->buildBadgeModels();
        $rows = $this->buildOverviewRows();

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'multi_host' => false,
            'config' => $this->fields_values,
            'badges' => $badges,
            'rows' => $rows,
            'values' => $this->buildValues($badges, $rows),
            'layout_signature' => $this->buildLayoutSignature($badges, $rows),
        ]));
    }

    /**
     * @param list<string> $hostids
     */
    private function renderMultiHostDashboard(array $hostids): void
    {
        $saved_fields = $this->fields_values;

        $profiles = HostProfilesHelper::syncWithHostOrder(
            HostProfilesHelper::parse($saved_fields['host_profiles'] ?? '[]'),
            $hostids
        );

        $hosts_payload = [];
        $values_hosts = [];
        $layout_chunks = [];

        foreach ($profiles as $profile) {
            $hostid = $profile['hostid'];
            $merged = HostProfilesHelper::mergeProfile($saved_fields, $profile);

            $this->fields_values = $merged;
            $this->cell_id_prefix = $hostid . ':';
            $this->context_hostid = $hostid;

            $this->badges = $this->decodeBadges();
            $this->metrics = $this->collectMetrics();
            $this->host_details = null;
            $this->problems = null;

            $badges = $this->buildBadgeModels();
            $rows = $this->buildOverviewRows();

            $light = $this->computeTrafficLightLevel($rows, $this->fetchProblems());
            $layout_signature = $this->buildLayoutSignature($badges, $rows);

            $alias = trim((string) ($profile['alias'] ?? ''));
            $zbx_name = trim($this->fetchHostName($hostid)) ?: $hostid;
            $display_label = $alias !== '' ? $alias : $zbx_name;
            $bp = (int) ($profile['badges_placement'] ?? 0);
            $bp = $bp === WidgetForm::MULTI_HOST_BADGES_DETAIL_ONLY
                ? WidgetForm::MULTI_HOST_BADGES_DETAIL_ONLY
                : WidgetForm::MULTI_HOST_BADGES_SUMMARY;
            $summary_badges = $bp === WidgetForm::MULTI_HOST_BADGES_SUMMARY ? $badges : [];
            $detail_badges = $bp === WidgetForm::MULTI_HOST_BADGES_DETAIL_ONLY ? $badges : [];

            $hosts_payload[] = [
                'hostid' => $hostid,
                'name' => $zbx_name,
                'display_label' => $display_label,
                'light' => $light,
                'summary_badges' => $summary_badges,
                'detail_badges' => $detail_badges,
                'badges' => $badges,
                'rows' => $rows,
                'layout_signature' => $layout_signature,
                'config' => $merged,
            ];

            $values_hosts[$hostid] = [
                'light' => $light,
                'badges' => $this->buildBadgeValues($badges),
                'cells' => $this->buildCellValues($rows),
            ];

            $layout_chunks[] = $layout_signature;
        }

        $hosts_payload = $this->sortHostsByTrafficLight($hosts_payload);

        $this->fields_values = $saved_fields;
        $this->cell_id_prefix = '';
        $this->context_hostid = null;

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'multi_host' => true,
            'config' => $saved_fields,
            'hosts' => $hosts_payload,
            'values' => [
                'hosts' => $values_hosts,
            ],
            'layout_signature' => md5(implode('|', $layout_chunks)),
        ]));
    }

    private function fetchHostName(string $hostid): string
    {
        $hosts = API::Host()->get([
            'output' => ['name'],
            'hostids' => [$hostid],
            'limit' => 1,
        ]);

        return (string) (($hosts[0]['name'] ?? '') ?: '');
    }

    /**
     * @param list<array<string, mixed>> $hosts_payload
     * @return list<array<string, mixed>>
     */
    private function sortHostsByTrafficLight(array $hosts_payload): array
    {
        $priority = [
            'red' => 0,
            'yellow' => 1,
            'green' => 2,
        ];

        usort($hosts_payload, static function (array $left, array $right) use ($priority): int {
            $left_rank = $priority[(string) ($left['light'] ?? 'green')] ?? 3;
            $right_rank = $priority[(string) ($right['light'] ?? 'green')] ?? 3;

            if ($left_rank !== $right_rank) {
                return $left_rank <=> $right_rank;
            }

            return strnatcasecmp(
                (string) ($left['display_label'] ?? $left['name'] ?? ''),
                (string) ($right['display_label'] ?? $right['name'] ?? '')
            );
        });

        return $hosts_payload;
    }

    /**
     * @return 'green'|'yellow'|'red'
     */
    private function computeTrafficLightLevel(array $rows, array $problems): string
    {
        $metric_level = 0;

        foreach ($rows as $row) {
            foreach ((array) ($row['cells'] ?? []) as $cell) {
                $metric_level = max($metric_level, $this->metricCellWorstLevel($cell));
            }
        }

        $max_severity = (int) ($problems['max_severity'] ?? -1);
        $problem_level = 0;

        if ($max_severity >= 4) {
            $problem_level = 2;
        }
        elseif ($max_severity >= 2) {
            $problem_level = 1;
        }
        elseif ($max_severity >= 0 && (int) ($problems['total'] ?? 0) > 0) {
            $problem_level = 1;
        }

        $worst = max($metric_level, $problem_level);

        return match ($worst) {
            2 => 'red',
            1 => 'yellow',
            default => 'green',
        };
    }

    private function metricCellWorstLevel(array $cell): int
    {
        if (($cell['state'] ?? '') === 'empty') {
            return 2;
        }

        $pct = $cell['bar']['percent'] ?? null;

        if ($pct === null || $pct === '') {
            return 0;
        }

        $group = (string) ($cell['bar']['threshold_group'] ?? '');
        $high = $this->getThresholdValue($group, 1);
        $medium = $this->getThresholdValue($group, 2);

        if ((int) $pct > $high) {
            return 2;
        }

        if ((int) $pct > $medium) {
            return 1;
        }

        return 0;
    }

    private function makeCellId(string $local_id): string
    {
        return $this->cell_id_prefix === '' ? $local_id : $this->cell_id_prefix . $local_id;
    }

    private function makeBadgeId(int $index): string
    {
        if ($this->context_hostid === null || $this->context_hostid === '') {
            return 'badge:' . $index;
        }

        return 'badge:' . $this->context_hostid . ':' . $index;
    }

    private function getSparklineContextHostId(): ?string
    {
        return $this->context_hostid ?? $this->getPrimaryHostId();
    }

    // =============================================================================
    // Dynamic value builders (for JS updates)
    // =============================================================================

    private function buildValues(array $badges, array $rows): array
    {
        return [
            'badges' => $this->buildBadgeValues($badges),
            'cells' => $this->buildCellValues($rows),
        ];
    }

    private function buildBadgeValues(array $badges): array
    {
        $values = [];

        foreach ($badges as $badge) {
            $id = (string) ($badge['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $values[$id] = [
                'text' => (string) ($badge['text'] ?? ''),
                'hidden' => (bool) ($badge['hidden'] ?? false),
                'state_classes' => array_values(array_filter((array) ($badge['state_classes'] ?? []), 'is_string')),
            ];
        }

        return $values;
    }

    private function buildCellValues(array $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            foreach ((array) ($row['cells'] ?? []) as $cell) {
                $cell_id = (string) ($cell['cell_id'] ?? '');

                if ($cell_id === '') {
                    continue;
                }

                $values[$cell_id] = [
                    'state' => (string) ($cell['state'] ?? 'ok'),
                    'state_reason' => (string) ($cell['state_reason'] ?? ''),
                    'display' => [
                        'kind' => (string) ($cell['display']['kind'] ?? 'percent'),
                        'value' => $cell['display']['value'] ?? null,
                        'prefix' => $cell['display']['prefix'] ?? null,
                        'value_text' => (string) ($cell['display']['value_text'] ?? ''),
                        'text' => (string) ($cell['display']['text'] ?? ''),
                        'empty_text' => (string) ($cell['display']['empty_text'] ?? 'No data'),
                    ],
                    'bar' => [
                        'percent' => $cell['bar']['percent'] ?? null,
                        'color' => $cell['bar']['color'] ?? null,
                    ],
                ];
            }
        }

        return $values;
    }

    // =============================================================================
    // Badge model builders (data only, no DOM)
    // =============================================================================

    private function buildBadgeModels(): array
    {
        $models = [];

        foreach ($this->badges as $index => $badge) {
            $model = $this->buildBadgeModel($index, $badge);

            if ($model !== null) {
                $models[] = $model;
            }
        }

        return $models;
    }

    private function buildBadgeModel(int $index, array $badge): ?array
    {
        $type = (int) ($badge['type'] ?? CWidgetFieldBadgesList::BADGE_HOSTNAME);
        $side = $this->normalizeSide($badge['side'] ?? CWidgetFieldBadgesList::SIDE_LEFT);
        $id = $this->makeBadgeId($index);

        return match ($type) {
            CWidgetFieldBadgesList::BADGE_HOSTNAME => [
                'id' => $id,
                'type' => $type,
                'side' => $side,
                'text' => trim((string) ($this->fetchHostDetails()['name'] ?? '')) ?: 'Hostname missing',
                'hostid' => $this->getPrimaryHostId(),
            ],

            CWidgetFieldBadgesList::BADGE_UPTIME => $this->buildUptimeBadgeModel($id, $type, $side),

            CWidgetFieldBadgesList::BADGE_LIVELINESS => $this->buildFreshnessBadgeModel($id, $type, $side),

            CWidgetFieldBadgesList::BADGE_MAINTENANCE => $this->buildMaintenanceBadgeModel($id, $type, $side),

            CWidgetFieldBadgesList::BADGE_TAGS => $this->buildTagsBadgeModel($id, $type, $side),

            CWidgetFieldBadgesList::BADGE_PROBLEMS => $this->buildProblemsBadgeModel($id, $type, $side),

            CWidgetFieldBadgesList::BADGE_TEXT => [
                'id' => $id,
                'type' => $type,
                'side' => $side,
                'text' => (string) ($badge['text'] ?? ''),
            ],

            CWidgetFieldBadgesList::BADGE_LINK => [
                'id' => $id,
                'type' => $type,
                'side' => $side,
                'text' => (string) ($badge['text'] ?? ''),
                'link' => ($url = CWidgetFieldBadgesList::sanitizeLinkUrl($badge['url'] ?? null)) !== null
                    ? $this->buildLinkModel($url)
                    : null,
            ],

            default => null,
        };
    }

    private function buildUptimeBadgeModel(string $id, int $type, string $side): array
    {
        $metric = $this->findMetric(
            trim((string) ($this->fields_values['badge_uptime_item_name']
                ?? CWidgetFieldBadgesList::DEFAULT_ITEM_UPTIME))
        );
        $seconds = $metric['value'] ?? null;
        $text = format_uptime($seconds !== null ? (int) $seconds : null);

        return [
            'id' => $id,
            'type' => $type,
            'side' => $side,
            'text' => $text ?? 'No uptime',
            'state_classes' => $text === null ? ['empty'] : [],
        ];
    }

    private function buildFreshnessBadgeModel(string $id, int $type, string $side): array
    {
        $metric = $this->findMetric(
            trim((string) ($this->fields_values['badge_liveliness_item_name']
                ?? CWidgetFieldBadgesList::DEFAULT_ITEM_LIVELINESS))
        );
        $freshness = $this->computeFreshness($metric);
        $warn_threshold = max(0, (int) ($this->fields_values['freshness_warn'] ?? WidgetForm::DEFAULT_FRESHNESS_WARN));
        $stale_threshold = max(
            $warn_threshold,
            (int) ($this->fields_values['freshness_stale'] ?? WidgetForm::DEFAULT_FRESHNESS_STALE)
        );
        $state_classes = freshness_state_classes($freshness, $warn_threshold, $stale_threshold);

        if ($freshness === null) {
            $state_classes[] = 'empty';
        }

        return [
            'id' => $id,
            'type' => $type,
            'side' => $side,
            'text' => format_freshness($freshness),
            'state_classes' => $state_classes,
        ];
    }

    private function buildTagsBadgeModel(string $id, int $type, string $side): array
    {
        $text = format_tags($this->fetchHostDetails()['tags'] ?? []);

        return [
            'id' => $id,
            'type' => $type,
            'side' => $side,
            'text' => $text,
            'state_classes' => $text === 'No tags' ? ['empty'] : [],
        ];
    }

    private function buildMaintenanceBadgeModel(string $id, int $type, string $side): array
    {
        $status = (int) ($this->fetchHostDetails()['maintenance_status'] ?? 0);

        return [
            'id' => $id,
            'type' => $type,
            'side' => $side,
            'text' => $status === 1 ? _m('Maintenance') : '',
            'hidden' => $status !== 1,
        ];
    }

    private function buildProblemsBadgeModel(string $id, int $type, string $side): array
    {
        $hostid = $this->getPrimaryHostId();
        $problems = $this->fetchProblems();
        $total = (int) ($problems['total'] ?? 0);
        $max_severity = (int) ($problems['max_severity'] ?? -1);

        return [
            'id' => $id,
            'type' => $type,
            'side' => $side,
            'text' => format_problems($total),
            'hidden' => $total === 0,
            'state_classes' => problems_state_classes($total, $max_severity),
            'link' => $hostid !== null
                ? $this->buildLinkModel('zabbix.php?action=problem.view&hostids%5B%5D=' . urlencode($hostid))
                : null,
        ];
    }

    private function normalizeSide(mixed $side): string
    {
        return $side === CWidgetFieldBadgesList::SIDE_RIGHT
            ? CWidgetFieldBadgesList::SIDE_RIGHT
            : CWidgetFieldBadgesList::SIDE_LEFT;
    }

    // =============================================================================
    // Row/cell model builders (data only, no DOM)
    // =============================================================================

    private function buildOverviewRows(): array
    {
        $rows = [];
        $enabled = array_map('intval', (array) ($this->fields_values['metrics_show'] ?? []));
        $labels_short = (int) ($this->fields_values['label_length'] ?? WidgetForm::LABELS_FULL)
            === WidgetForm::LABELS_SHORT;

        if (in_array(WidgetForm::METRIC_CPU, $enabled, true)) {
            $item_name = (string) ($this->fields_values['item_name_cpu'] ?? '');
            $match = $this->resolveMetricMatch($item_name);

            $rows[] = $this->buildSingleMetricRow(
                'cpu',
                $labels_short ? 'CPU' : 'Processor',
                $match['resolved'],
                $this->valueFromMetric($match['resolved']),
                'percent',
                'cpu',
                [],
                $match['status'],
                $item_name
            );
        }

        if (in_array(WidgetForm::METRIC_RAM, $enabled, true)) {
            $item_name = (string) ($this->fields_values['item_name_ram'] ?? '');
            $match = $this->resolveMetricMatch($item_name);

            $rows[] = $this->buildSingleMetricRow(
                'ram',
                $labels_short ? 'RAM' : 'Memory',
                $match['resolved'],
                $this->normalizePercentValue($this->valueFromMetric($match['resolved'])),
                'percent',
                'ram',
                [],
                $match['status'],
                $item_name
            );
        }

        if (in_array(WidgetForm::METRIC_LOAD, $enabled, true)) {
            $item_name = (string) ($this->fields_values['item_name_load'] ?? '');
            $match = $this->resolveMetricMatch($item_name);
            $value = $this->valueFromMetric($match['resolved']);

            $rows[] = $this->buildSingleMetricRow(
                'load',
                'Load',
                $match['resolved'],
                $value !== null ? (float) $value : null,
                'load',
                'load',
                [
                    'axis_max' => $this->getLoadCeiling(),
                ],
                $match['status'],
                $item_name
            );
        }

        if (in_array(WidgetForm::METRIC_SWAP, $enabled, true)) {
            $item_name = (string) ($this->fields_values['item_name_swap'] ?? '');
            $match = $this->resolveMetricMatch($item_name);
            $raw = $this->valueFromMetric($match['resolved']);
            $invert = (int) ($this->fields_values['item_swap_invert'] ?? 1) === 1;
            $value = $raw !== null
                ? $this->normalizePercentValue($invert ? 100 - $raw : $raw)
                : null;

            $rows[] = $this->buildSingleMetricRow(
                'swap',
                'Swap',
                $match['resolved'],
                $value,
                'percent',
                'swap',
                [
                    'invert_percent' => $invert,
                ],
                $match['status'],
                $item_name
            );
        }

        if (in_array(WidgetForm::METRIC_INTERFACES, $enabled, true)) {
            $rows[] = $this->buildInterfacesRow($labels_short ? 'NICs' : 'Interfaces');
        }

        if (in_array(WidgetForm::METRIC_DISKS, $enabled, true)) {
            $rows[] = $this->buildCollectionRow(
                'disks',
                $labels_short ? 'Disks' : 'Disk util.',
                $this->getWildcardMetricResolver()->buildSingleWildcardRows(
                    $this->metrics,
                    (string) ($this->fields_values['item_name_disk'] ?? ''),
                    (string) ($this->fields_values['disks_exclude'] ?? '')
                ),
                'disk'
            );
        }

        if (in_array(WidgetForm::METRIC_PARTITIONS, $enabled, true)) {
            $rows[] = $this->buildCollectionRow(
                'partitions',
                $labels_short ? 'Parts' : 'Partitions',
                $this->getWildcardMetricResolver()->buildSingleWildcardRows(
                    $this->metrics,
                    (string) ($this->fields_values['item_name_partition'] ?? ''),
                    (string) ($this->fields_values['partitions_exclude'] ?? '')
                ),
                'partition'
            );
        }

        return $rows;
    }

    private function buildSingleMetricRow(
        string $row_id,
        string $row_label,
        ?array $metric,
        float|int|null $value,
        string $display_kind,
        string $threshold_group,
        array $options = [],
        string $match_status = MetricMatcher::STATUS_EXACT,
        string $item_search = ''
    ): array {
        $item_ref = $this->toItemRef($metric);
        $cell = $this->buildCellModel([
            'cell_id' => $this->makeCellId($row_id),
            'cell_label' => $row_label,
            'display_kind' => $display_kind,
            'value' => $value,
            'prefix' => null,
            'bar_percent' => $display_kind === 'load'
                ? $this->calculateLoadBarPercent($value)
                : $this->normalizePercent($value),
            'threshold_group' => $threshold_group,
            'item_ref' => $item_ref,
            'axis_min' => 0,
            'axis_max' => $options['axis_max'] ?? ($display_kind === 'percent' ? 100 : null),
            'invert_percent' => (bool) ($options['invert_percent'] ?? false),
            'match_status' => $match_status,
            'item_search' => $item_search,
        ]);

        return [
            'row_id' => $row_id,
            'kind' => 'single',
            'label' => $row_label,
            'label_link' => null,
            'cells' => [$cell],
        ];
    }

    private function buildCollectionRow(string $row_id, string $row_label, array $rows, string $family): array
    {
        $cells = [];

        foreach ($rows as $row) {
            $cell_label = trim((string) ($row['label'] ?? $row['name'] ?? $row['key'] ?? ''));
            $cell_key = trim((string) ($row['key'] ?? $row['name'] ?? $cell_label));

            if ($cell_key === '' || $cell_label === '') {
                continue;
            }

            $cells[] = $this->buildCellModel([
                'cell_id' => $this->makeCellId($family . ':' . $cell_key),
                'cell_label' => $cell_label,
                'display_kind' => 'percent',
                'value' => $row['percent'] ?? null,
                'prefix' => $cell_label,
                'bar_percent' => $this->normalizePercent($row['percent'] ?? null),
                'threshold_group' => $family,
                'item_ref' => $row['item_ref'] ?? null,
                'axis_min' => 0,
                'axis_max' => 100,
                'invert_percent' => false,
            ]);
        }

        return [
            'row_id' => $row_id,
            'kind' => 'multi',
            'label' => $row_label,
            'label_link' => null,
            'cells' => $cells,
        ];
    }

    private function buildInterfacesRow(string $row_label): array
    {
        $cells = [];
        $capacity = $this->calculateInterfaceCapacity();
        $rows = $this->getWildcardMetricResolver()->buildInterfaceRows(
            $this->metrics,
            (string) ($this->fields_values['item_name_interface'] ?? ''),
            (string) ($this->fields_values['interfaces_exclude'] ?? ''),
            $capacity
        );

        foreach ($rows as $row) {
            $cell_label = trim((string) ($row['label'] ?? ''));
            $cell_key = trim((string) ($row['key'] ?? $cell_label));

            if ($cell_label === '' || $cell_key === '') {
                continue;
            }

            $cells[] = $this->buildCellModel([
                'cell_id' => $this->makeCellId('iface:' . $cell_key),
                'cell_label' => $cell_label,
                'display_kind' => 'interface',
                'value' => $row['bps'] ?? null,
                'prefix' => $cell_label,
                'bar_percent' => $this->normalizePercent($row['percent'] ?? null),
                'threshold_group' => 'iface',
                'item_ref' => $row['item_ref'] ?? null,
                'axis_min' => 0,
                'axis_max' => $capacity > 0 ? $capacity : null,
                'invert_percent' => false,
            ]);
        }

        return [
            'row_id' => 'interfaces',
            'kind' => 'multi',
            'label' => $row_label,
            'label_link' => null,
            'cells' => $cells,
        ];
    }

    private function buildCellModel(array $options): array
    {
        $value = $options['value'] ?? null;
        $display_kind = (string) ($options['display_kind'] ?? 'percent');
        $prefix = $options['prefix'] ?? null;
        $item_ref = $options['item_ref'] ?? null;
        $match_status = (string) ($options['match_status'] ?? MetricMatcher::STATUS_EXACT);
        $item_search = trim((string) ($options['item_search'] ?? ''));
        [$state, $state_reason, $empty_text] = $this->resolveCellPresentation(
            $value,
            $match_status,
            $item_search,
            $prefix
        );
        $bar_percent = $options['bar_percent'] ?? null;
        $threshold_group = (string) ($options['threshold_group'] ?? '');

        return [
            'cell_id' => (string) ($options['cell_id'] ?? ''),
            'cell_label' => (string) ($options['cell_label'] ?? ''),
            'item_ref' => is_array($item_ref) ? $item_ref : null,
            'state' => $state,
            'state_reason' => $state_reason,
            'display' => [
                'kind' => $display_kind,
                'value' => $value === null ? null : (float) $value,
                'prefix' => $prefix,
                'value_text' => $value === null
                    ? format_empty_value()
                    : format_display_value($display_kind, (float) $value),
                'text' => $value === null
                    ? $empty_text
                    : format_display_text($display_kind, (float) $value, $prefix),
                'empty_text' => $empty_text,
            ],
            'bar' => [
                'percent' => $bar_percent,
                'threshold_group' => $threshold_group,
                'color' => $this->resolveBarColor($bar_percent, $threshold_group),
            ],
            'links' => [
                'latest_data' => $this->buildLatestDataLink($item_ref),
            ],
            'sparkline' => [
                'enabled' => $item_ref !== null,
                'spec' => $item_ref !== null
                    ? [
                        'item_ref' => $item_ref,
                        'hostid' => $this->getSparklineContextHostId(),
                        'display_kind' => $display_kind,
                        'axis' => [
                            'min' => $options['axis_min'] ?? 0,
                            'max' => $options['axis_max'] ?? null,
                        ],
                        'transform' => [
                            'invert_percent' => (bool) ($options['invert_percent'] ?? false),
                        ],
                    ]
                    : null,
            ],
        ];
    }

    // =============================================================================
    // Link builders
    // =============================================================================

    private function buildLatestDataLink(?array $item_ref): ?array
    {
        $hostid = $this->getPrimaryHostId();

        if ($hostid === null || $item_ref === null) {
            return null;
        }

        $item_name = trim((string) ($item_ref['name'] ?? ''));

        if ($item_name === '') {
            return null;
        }

        return $this->buildLinkModel(
            'zabbix.php?action=latest.view&hostids%5B%5D=' . urlencode($hostid)
            . '&name=' . urlencode($item_name)
            . '&filter_set=1'
        );
    }

    private function buildLinkModel(string $href): array
    {
        $link_target = $this->getLinkTarget();
        $link = [
            'href' => $href,
            'target' => $link_target,
        ];

        if ($link_target === '_blank') {
            $link['rel'] = 'noopener';
        }

        return $link;
    }

    private function getLinkTarget(): string
    {
        return (int) ($this->fields_values['open_links_same_window'] ?? 0) === 1 ? '_self' : '_blank';
    }

    // =============================================================================
    // Data fetchers and computations
    // =============================================================================

    private function decodeBadges(): array
    {
        return CWidgetFieldBadgesList::decodeStored($this->fields_values['badges'] ?? '[]');
    }

    private function collectMetrics(): array
    {
        $name_filters = [
            (string) ($this->fields_values['item_name_load'] ?? ''),
            (string) ($this->fields_values['item_name_ram'] ?? ''),
            (string) ($this->fields_values['item_name_cpu'] ?? ''),
            (string) ($this->fields_values['item_name_swap'] ?? ''),
        ];

        if ($this->hasBadgeType(CWidgetFieldBadgesList::BADGE_UPTIME)) {
            $name_filters[] = trim((string) ($this->fields_values['badge_uptime_item_name']
                ?? CWidgetFieldBadgesList::DEFAULT_ITEM_UPTIME));
        }

        if ($this->hasBadgeType(CWidgetFieldBadgesList::BADGE_LIVELINESS)) {
            $name_filters[] = trim((string) ($this->fields_values['badge_liveliness_item_name']
                ?? CWidgetFieldBadgesList::DEFAULT_ITEM_LIVELINESS));
        }

        foreach (['item_name_disk', 'item_name_partition', 'item_name_interface'] as $field) {
            foreach ($this->getWildcardMetricResolver()->extractSearchTerms((string) ($this->fields_values[$field] ?? '')) as $part) {
                $name_filters[] = trim($part);
            }
        }

        $name_filters = array_values(array_unique(array_filter(
            array_map('trim', $name_filters),
            static fn(string $value): bool => $value !== ''
        )));

        $collection = $this->getMetricMatcher()->collect((array) ($this->fields_values['hostid'] ?? []), $name_filters);

        return $collection['metrics'] ?? [];
    }

    private function findMetric(string $search): ?array
    {
        return $this->getMetricMatcher()->resolve($this->metrics, trim($search));
    }

    /**
     * @return array{status: string, resolved: ?array, matches: list<array>}
     */
    private function resolveMetricMatch(string $search): array
    {
        return $this->getMetricMatcher()->matchMetrics($this->metrics, trim($search));
    }

    private function valueFromMetric(?array $metric): ?float
    {
        if ($metric === null || $metric['value'] === null) {
            return null;
        }

        return (float) $metric['value'];
    }

    private function normalizePercentValue(?float $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $this->clampPercent($value);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveCellPresentation(
        mixed $value,
        string $match_status,
        string $item_search,
        ?string $prefix
    ): array {
        if ($value !== null) {
            return ['ok', '', format_empty_text($prefix)];
        }

        if ($match_status === MetricMatcher::STATUS_AMBIGUOUS) {
            $label = $item_search !== '' ? $item_search : 'item';

            return [
                'ambiguous',
                sprintf('Several items match "%s". Use the exact item name.', $label),
                'Ambiguous item',
            ];
        }

        if ($match_status === MetricMatcher::STATUS_NONE) {
            $label = $item_search !== '' ? $item_search : 'item';

            return [
                'missing',
                sprintf('Item "%s" was not found on this host.', $label),
                'Item not found',
            ];
        }

        if ($match_status === MetricMatcher::STATUS_EMPTY || $item_search === '') {
            return [
                'missing',
                'Item name is not configured.',
                'No item configured',
            ];
        }

        return [
            'empty',
            'No recent data for this item.',
            format_empty_text($prefix),
        ];
    }

    private function computePercent(string $item_name): ?int
    {
        $metric = $this->findMetric($item_name);

        if ($metric === null || $metric['value'] === null) {
            return null;
        }

        return $this->clampPercent($metric['value']);
    }

    private function computeSwap(): ?int
    {
        $metric = $this->findMetric((string) ($this->fields_values['item_name_swap'] ?? ''));

        if ($metric === null || $metric['value'] === null) {
            return null;
        }

        $invert = (int) ($this->fields_values['item_swap_invert'] ?? 1);

        return $this->clampPercent($invert ? 100 - $metric['value'] : $metric['value']);
    }

    private function computeLoad(): ?float
    {
        $metric = $this->findMetric((string) ($this->fields_values['item_name_load'] ?? ''));

        if ($metric === null || $metric['value'] === null) {
            return null;
        }

        return (float) $metric['value'];
    }

    private function fetchHostDetails(): ?array
    {
        if ($this->host_details !== null) {
            return $this->host_details;
        }

        $hostid = $this->getPrimaryHostId();

        if ($hostid === null) {
            return null;
        }

        $hosts = API::Host()->get([
            'output' => ['name', 'maintenance_status'],
            'selectTags' => ['tag', 'value'],
            'hostids' => [$hostid],
            'limit' => 1,
        ]);

        $this->host_details = $hosts[0] ?? null;

        return $this->host_details;
    }

    private function computeFreshness(?array $metric = null): ?int
    {
        $clock = (int) ($metric['lastclock'] ?? 0);

        if ($clock <= 0) {
            return null;
        }

        return max(0, time() - $clock);
    }

    private function fetchProblems(): array
    {
        if ($this->problems !== null) {
            return $this->problems;
        }

        $params = [
            'output' => ['eventid', 'severity'],
            'hostids' => (array) ($this->fields_values['hostid'] ?? []),
            'recent' => true,
            'sortfield' => 'eventid',
            'sortorder' => 'DESC',
            'limit' => 1000,
        ];

        if ((int) ($this->fields_values['problems_hide_suppressed'] ?? 0) === 1) {
            $params['suppressed'] = false;
        }

        if ((int) ($this->fields_values['problems_hide_acknowledged'] ?? 0) === 1) {
            $params['acknowledged'] = false;
        }

        $events = API::Problem()->get($params);
        $severity_map = [
            5 => 'disaster',
            4 => 'high',
            3 => 'average',
            2 => 'warning',
            1 => 'information',
            0 => 'not_classified',
        ];
        $counts = array_fill_keys(array_values($severity_map), 0);
        $max_severity = -1;

        foreach ($events as $event) {
            $severity = (int) ($event['severity'] ?? 0);
            $key = $severity_map[$severity] ?? 'not_classified';
            $counts[$key]++;

            if ($severity > $max_severity) {
                $max_severity = $severity;
            }
        }

        $counts['total'] = count($events);
        $counts['max_severity'] = $max_severity;
        $this->problems = $counts;

        return $this->problems;
    }

    // =============================================================================
    // Threshold and color resolution
    // =============================================================================

    private function resolveBarColor(mixed $bar_percent, string $threshold_group): string
    {
        $fill_color = '#' . ((string) ($this->fields_values['fill_color'] ?? WidgetForm::DEFAULT_COLOR_FILL));

        if ((int) ($this->fields_values['color_scheme'] ?? WidgetForm::COLOR_SCHEME_THRESHOLD)
                === WidgetForm::COLOR_SCHEME_SOLID) {
            return $fill_color;
        }

        $percent = $this->normalizePercent($bar_percent) ?? 0;
        $high_threshold = $this->getThresholdValue($threshold_group, 1);
        $medium_threshold = $this->getThresholdValue($threshold_group, 2);

        if ($percent > $high_threshold) {
            return '#' . ((string) ($this->fields_values['th_color_1'] ?? WidgetForm::DEFAULT_COLOR_THRESHOLD_HIGH));
        }

        if ($percent > $medium_threshold) {
            return '#' . ((string) ($this->fields_values['th_color_2'] ?? WidgetForm::DEFAULT_COLOR_THRESHOLD_MEDIUM));
        }

        return '#' . ((string) ($this->fields_values['th_color_3'] ?? WidgetForm::DEFAULT_COLOR_THRESHOLD_LOW));
    }

    private function getThresholdValue(string $threshold_group, int $level): int
    {
        $group_field = 'th_' . $threshold_group . '_' . $level;
        $legacy_group_field = 'th_m' . $threshold_group . '_' . $level;
        $legacy_group_field_v2 = 'th_m' . $threshold_group . '_m' . $level;
        $fallback_field = 'th_num_' . $level;
        $legacy_fallback_field = 'th_num_m' . $level;
        $value = $this->fields_values[$group_field]
            ?? $this->fields_values[$legacy_group_field]
            ?? $this->fields_values[$legacy_group_field_v2]
            ?? $this->fields_values[$fallback_field]
            ?? $this->fields_values[$legacy_fallback_field]
            ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    // =============================================================================
    // Utility methods
    // =============================================================================

    private function calculateLoadBarPercent(float|int|null $load): ?int
    {
        if ($load === null) {
            return null;
        }

        $load_ceiling = $this->getLoadCeiling();

        if ($load_ceiling <= 0) {
            return 0;
        }

        return $this->clampPercent(((float) $load / $load_ceiling) * 100);
    }

    private function getLoadCeiling(): ?float
    {
        $load_high = $this->fields_values['load_high'] ?? WidgetForm::DEFAULT_LOAD_HIGH;

        return is_numeric($load_high) && (float) $load_high > 0
            ? (float) $load_high
            : null;
    }

    private function calculateInterfaceCapacity(): int
    {
        $interfaces_high = (int) ($this->fields_values['interfaces_high'] ?? 0);
        $interfaces_unit = (int) ($this->fields_values['interfaces_unit'] ?? WidgetForm::INTERFACES_UNIT_GBPS);

        $factor = match ($interfaces_unit) {
            WidgetForm::INTERFACES_UNIT_GBPS => 1_000_000_000,
            WidgetForm::INTERFACES_UNIT_MBPS => 1_000_000,
            default => 1_000,
        };

        return $interfaces_high > 0 ? $interfaces_high * $factor : 0;
    }

    private function getPrimaryHostId(): ?string
    {
        $hostid = $this->fields_values['hostid'][0] ?? null;

        if (!is_scalar($hostid)) {
            return null;
        }

        $hostid = trim((string) $hostid);

        return $hostid !== '' ? $hostid : null;
    }

    private function hasBadgeType(int $type): bool
    {
        foreach ($this->badges as $badge) {
            if ((int) ($badge['type'] ?? -1) === $type) {
                return true;
            }
        }

        return false;
    }

    private function normalizePercent(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $this->clampPercent((float) $value) : null;
    }

    private function clampPercent(float|int $value): int
    {
        return max(0, min(100, (int) round($value)));
    }

    private function toItemRef(?array $metric): ?array
    {
        if ($metric === null || !array_key_exists('itemid', $metric) || !array_key_exists('value_type', $metric)) {
            return null;
        }

        return [
            'itemid' => (string) $metric['itemid'],
            'name' => $metric['name'] ?? null,
            'value_type' => (int) $metric['value_type'],
        ];
    }

    private function buildLayoutSignature(array $badges, array $rows): string
    {
        $layout_model = [
            'style' => [
                'label_length' => (int) ($this->fields_values['label_length'] ?? WidgetForm::LABELS_FULL),
                'bar_height' => (int) ($this->fields_values['bar_height'] ?? WidgetForm::DEFAULT_BAR_HEIGHT),
                'fill_color' => (string) ($this->fields_values['fill_color'] ?? WidgetForm::DEFAULT_COLOR_FILL),
                'corners' => (int) ($this->fields_values['corners'] ?? WidgetForm::CORNERS_ROUNDED),
            ],
            'toolbar' => array_map(static function(array $badge): array {
                return [
                    'id' => $badge['id'] ?? '',
                    'type' => $badge['type'] ?? '',
                    'side' => $badge['side'] ?? '',
                    'hostid' => $badge['hostid'] ?? null,
                    'link' => $badge['link'] ?? null,
                ];
            }, $badges),
            'rows' => array_map(static function(array $row): array {
                return [
                    'row_id' => $row['row_id'] ?? '',
                    'kind' => $row['kind'] ?? '',
                    'label' => $row['label'] ?? '',
                    'label_link' => $row['label_link'] ?? null,
                    'cells' => array_map(static function(array $cell): array {
                        return [
                            'cell_id' => $cell['cell_id'] ?? '',
                            'cell_label' => $cell['cell_label'] ?? '',
                            'display_kind' => $cell['display']['kind'] ?? '',
                            'latest_data' => $cell['links']['latest_data'] ?? null,
                            'sparkline' => $cell['sparkline'] ?? null,
                        ];
                    }, $row['cells'] ?? []),
                ];
            }, $rows),
        ];

        return md5((string) json_encode($layout_model));
    }

    private function resolveSelectedHostIds(): array
    {
        $override_hostids = $this->normalizeHostIds($this->fields_values['override_hostid'] ?? []);

        if ($override_hostids !== []) {
            return $override_hostids;
        }

        return $this->normalizeHostIds($this->fields_values['hostid'] ?? []);
    }

    private function normalizeHostIds(mixed $value): array
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

    private function getMetricMatcher(): MetricMatcher
    {
        if ($this->metric_matcher === null) {
            $this->metric_matcher = new MetricMatcher();
        }

        return $this->metric_matcher;
    }

    private function getWildcardMetricResolver(): WildcardMetricResolver
    {
        if ($this->wildcard_metric_resolver === null) {
            $this->wildcard_metric_resolver = new WildcardMetricResolver();
        }

        return $this->wildcard_metric_resolver;
    }
}
