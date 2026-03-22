<?php

/*
 * MIT License
 * Copyright (c) 2026 ObviousAIChicken
 * github.com/obviousaichicken/zabbix_widgets
 */

namespace Modules\HostOverview\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;
use Modules\HostOverview\Includes\CWidgetFieldBadgesList;
use Modules\HostOverview\Includes\WidgetForm;

class WidgetView extends CControllerDashboardWidgetView
{
    private int $latest_clock = 0;

    protected function doAction(): void
    {
        // Decode badges config once for the entire request
        $badges = $this->decodeBadges();

        // Collect metrics
        $metrics = $this->collectMetrics($badges);

        $enabled = $this->fields_values['metrics_show'] ?? [];

        // Process metrics
        $cpu          = in_array(WidgetForm::METRIC_CPU, $enabled) ? $this->computePercent('item_name_cpu', $metrics) : 0;
        $ram          = in_array(WidgetForm::METRIC_RAM, $enabled) ? $this->computePercent('item_name_ram', $metrics) : 0;
        $swap         = in_array(WidgetForm::METRIC_SWAP, $enabled) ? $this->computeSwap($metrics) : 0;
        $load_percent = in_array(WidgetForm::METRIC_LOAD, $enabled) ? $this->computeLoadPercent($metrics) : 0;
        $disks        = in_array(WidgetForm::METRIC_DISKS, $enabled) ? $this->buildWildcardRows('item_name_disk', 'disks_exclude', $metrics) : [];
        $interfaces   = in_array(WidgetForm::METRIC_INTERFACES, $enabled) ? $this->buildInterfaces($metrics) : [];
        $partitions   = in_array(WidgetForm::METRIC_PARTITIONS, $enabled) ? $this->buildWildcardRows('item_name_partition', 'partitions_exclude', $metrics) : [];

        // Host info badges — build data per configured badge instance.
        $badge_data = $this->buildBadgeData($metrics, $badges);

        // Resolved item map so JS can request sparkline data from the exact item used by the bar.
        $item_map = [
            'cpu'  => $this->toSparklineItemRef($this->findMetric($metrics, $this->fields_values['item_name_cpu'])),
            'ram'  => $this->toSparklineItemRef($this->findMetric($metrics, $this->fields_values['item_name_ram'])),
            'load' => $this->toSparklineItemRef($this->findMetric($metrics, $this->fields_values['item_name_load'])),
            'swap' => $this->toSparklineItemRef($this->findMetric($metrics, $this->fields_values['item_name_swap'])),
        ];

        // Format response
        $this->setResponse(new CControllerResponseData([
            'name'         => $this->getInput('name', $this->widget->getName()),
            'cpu'          => $cpu,
            'ram'          => $ram,
            'load_percent' => $load_percent,
            'swap'         => $swap,
            'badge_data'   => $badge_data,
            'interfaces'   => $interfaces,
            'disks'        => $disks,
            'partitions'   => $partitions,
            'item_map'     => $item_map,
            'config'       => $this->fields_values,
        ]));
    }

    ###############################################
    ### FUNCTIONS
    ###############################################

    // Decode the badges JSON field once
    private function decodeBadges(): array
    {
        $badges_raw = $this->fields_values['badges'] ?? '[]';

        return is_string($badges_raw) ? (json_decode($badges_raw, true) ?: []) : [];
    }

    // Collect last values for relevant items on the selected host
    private function collectMetrics(array $badges): array
    {
        // Name filters; API will match any of them
        $name_filters = [
            $this->fields_values['item_name_load'],
            $this->fields_values['item_name_ram'],
            $this->fields_values['item_name_cpu'],
            $this->fields_values['item_name_swap'],
        ];

        // Add uptime item names from badges config
        foreach ($badges as $b) {
            if ((int) ($b['type'] ?? -1) === CWidgetFieldBadgesList::BADGE_UPTIME) {
                $name_filters[] = $b['item_name'] ?? CWidgetFieldBadgesList::DEFAULT_ITEM_UPTIME;
            }
        }

        // Wildcard patterns — extract literal segments for API search
        $wildcard_fields = ['item_name_disk', 'item_name_partition', 'item_name_interface'];
        foreach ($wildcard_fields as $field) {
            $parts = array_filter(explode('*', $this->fields_values[$field]), fn($s) => trim($s) !== '');
            foreach ($parts as $part) {
                $name_filters[] = trim($part);
            }
        }

        $name_filters = array_values(array_unique($name_filters));

        // Retrieve from API
        $items = API::Item()->get([
            'output'      => ['itemid', 'name', 'lastvalue', 'lastclock', 'value_type'],
            'hostids'     => $this->fields_values['hostid'],
            'search'      => ['name' => $name_filters],
            'searchByAny' => true,
        ]);

        // Build metrics so downstream builders can filter by key
        $metrics = [];
        $latest_clock = 0;
        foreach ($items as $item) {
            $name  = $item['name'];
            $clock = (int) ($item['lastclock'] ?? 0);
            $val   = $item['lastvalue'] ?? null;
            // lastclock = 0 means the item has never collected data;
            // treat its lastvalue (typically "0") as absent.
            $num            = ($clock > 0 && is_numeric($val)) ? (float) $val : null;
            $metrics[$name] = [
                'itemid'     => $item['itemid'],
                'name'       => $name,
                'value_type' => (int) ($item['value_type'] ?? 0),
                'value'      => $num,
                'raw'        => $val,
            ];

            if ($clock > $latest_clock) {
                $latest_clock = $clock;
            }
        }

        $this->latest_clock = $latest_clock;

        return $metrics;
    }

    // Build dynamic badge data keyed by badge index so duplicate badge types remain independent
    private function buildBadgeData(array $metrics, array $badges): array
    {
        $badge_data = [];
        $hostname = null;
        $freshness = null;
        $problems_cache = [];

        foreach ($badges as $index => $badge) {
            $type = (int) ($badge['type'] ?? CWidgetFieldBadgesList::BADGE_HOSTNAME);

            switch ($type) {
                case CWidgetFieldBadgesList::BADGE_HOSTNAME:
                    $hostname ??= $this->fetchHostname();
                    $badge_data[$index] = [
                        'type' => $type,
                        'hostname' => $hostname,
                    ];
                    break;

                case CWidgetFieldBadgesList::BADGE_UPTIME:
                    $item_name = $badge['item_name'] ?? CWidgetFieldBadgesList::DEFAULT_ITEM_UPTIME;
                    $badge_data[$index] = [
                        'type' => $type,
                        'uptime' => $this->computeUptime($metrics, $item_name),
                    ];
                    break;

                case CWidgetFieldBadgesList::BADGE_LIVELINESS:
                    $freshness ??= $this->computeFreshness();
                    $badge_data[$index] = [
                        'type' => $type,
                        'freshness' => $freshness,
                    ];
                    break;

                case CWidgetFieldBadgesList::BADGE_PROBLEMS:
                    $scope = (int) ($badge['scope'] ?? CWidgetFieldBadgesList::SCOPE_ALL);

                    if (!array_key_exists($scope, $problems_cache)) {
                        $problems_cache[$scope] = $this->fetchProblems($scope);
                    }

                    $badge_data[$index] = [
                        'type' => $type,
                        'problems' => $problems_cache[$scope],
                        'scope' => $scope,
                    ];
                    break;
            }
        }

        return $badge_data;
    }

    // Compute a simple percentage metric by field name
    private function computePercent(string $field_name, array $metrics): ?int
    {
        $item_name = $this->fields_values[$field_name];
        $metric = $this->findMetric($metrics, $item_name);

        if ($metric === null || $metric['value'] === null) {
            return null;
        }

        return $this->clampPercent($metric['value']);
    }

    // Compute swap utilization (optionally inverted — default item reports free %, we show used %)
    private function computeSwap(array $metrics): ?int
    {
        $item_name = $this->fields_values['item_name_swap'];
        $metric = $this->findMetric($metrics, $item_name);

        if ($metric === null || $metric['value'] === null) {
            return null;
        }

        $invert = (int) ($this->fields_values['item_swap_invert'] ?? 1);

        return $this->clampPercent($invert ? 100 - $metric['value'] : $metric['value']);
    }

    // Fetch active problems grouped by severity
    private function fetchProblems(int $scope = CWidgetFieldBadgesList::SCOPE_ALL): array
    {
        $params = [
            'output'       => ['eventid', 'severity'],
            'hostids'      => $this->fields_values['hostid'],
            'recent'       => true,
            'sortfield'    => 'eventid',
            'sortorder'    => 'DESC',
        ];

        if ($scope === CWidgetFieldBadgesList::SCOPE_UNACK) {
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
            $sev = (int) ($event['severity'] ?? 0);
            $key = $severity_map[$sev] ?? 'not_classified';
            $counts[$key]++;

            if ($sev > $max_severity) {
                $max_severity = $sev;
            }
        }

        $counts['total']        = count($events);
        $counts['max_severity'] = $max_severity;

        return $counts;
    }

    // Fetch hostname from the Zabbix host record
    private function fetchHostname(): ?string
    {
        $hostid = $this->fields_values['hostid'][0] ?? null;

        if ($hostid === null) {
            return null;
        }

        $hosts = API::Host()->get([
            'output' => ['name'],
            'hostids' => [$hostid],
            'limit' => 1,
        ]);

        return $hosts[0]['name'] ?? null;
    }

    // Compute formatted uptime string
    private function computeUptime(array $metrics, string $item_name = 'System uptime'): ?string
    {
        $metric = $this->findMetric($metrics, $item_name);
        $seconds = $metric['value'] ?? null;

        if ($seconds === null || $seconds < 0) {
            return null;
        }

        $seconds = (int) $seconds;
        $days    = intdiv($seconds, 86400);
        $hours   = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $days . 'd ' . $hours . 'h';
        }

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }

        return $minutes . 'm';
    }

    // Compute data freshness in seconds
    private function computeFreshness(): ?int
    {
        if ($this->latest_clock <= 0) {
            return null;
        }

        return max(0, time() - $this->latest_clock);
    }

    // Compute load percent (relative to configured high)
    private function computeLoadPercent(array $metrics): ?int
    {
        $item_name = $this->fields_values['item_name_load'];
        $metric = $this->findMetric($metrics, $item_name);

        if ($metric === null || $metric['value'] === null) {
            return null;
        }

        $load = (float) $metric['value'];
        $load_high = (int) ($this->fields_values['load_high'] ?? 0);

        if ($load_high <= 0) {
            return 0;
        }

        return $this->clampPercent(($load / $load_high) * 100);
    }

    // Build rows for single-wildcard patterns (disks, partitions)
    private function buildWildcardRows(string $pattern_field, string $exclude_field, array $metrics): array
    {
        $rows  = [];
        $index = [];

        $pattern = $this->fields_values[$pattern_field];
        $parts = explode('*', $pattern, 2);
        if (count($parts) < 2) {
            return $rows;
        }
        $regex = '/^' . preg_quote($parts[0], '/') . '(.+?)' . preg_quote($parts[1], '/') . '$/';

        foreach ($metrics as $key => $details) {
            if (!preg_match($regex, $key, $match)) {
                continue;
            }

            $name = trim($match[1]);

            if (empty($name)) {
                $name = '?';
            }

            // Skip excluded entries
            $exclude = $this->fields_values[$exclude_field] ?? '';
            if ($this->matchesExcludePattern($name, $exclude)) {
                continue;
            }

            $percent = $this->clampPercent($details['value'] ?? 0);

            if (array_key_exists($name, $index)) {
                $row_index              = $index[$name];
                $rows[$row_index]['percent'] = $percent;
            } else {
                $index[$name] = count($rows);
                $rows[]       = [
                    'name'      => $name,
                    'percent'   => $percent,
                    'item_name' => $key,
                ];
            }
        }

        return $rows;
    }

    // Build interface bitrate rows
    private function buildInterfaces(array $metrics): array
    {
        $rows              = [];
        $alias_counter     = 1;
        $interface_aliases = [];

        $interfaces_high = (int) ($this->fields_values['interfaces_high'] ?? 0);
        $interfaces_unit = (int) ($this->fields_values['interfaces_unit'] ?? WidgetForm::INTERFACES_UNIT_KBPS);

        $factor = match ($interfaces_unit) {
            WidgetForm::INTERFACES_UNIT_GBPS => 1_000_000_000,
            WidgetForm::INTERFACES_UNIT_MBPS => 1_000_000,
            default                          => 1_000,
        };

        // Compute capacity in bps based on configured high value and unit
        $capacity = $interfaces_high > 0 ? $interfaces_high * $factor : 0;

        // Build regex from wildcard pattern: "Interface *: Bits *"
        // First * = interface name, second * = direction (received/sent)
        $iface_pattern = $this->fields_values['item_name_interface'];
        $parts = explode('*', $iface_pattern, 3);
        if (count($parts) < 3) {
            return $rows;
        }
        $iface_regex = '/^' . preg_quote($parts[0], '/') . '(.+?)'
            . preg_quote($parts[1], '/') . '(\S+)'
            . preg_quote($parts[2], '/') . '$/';

        foreach ($metrics as $key => $details) {
            // Match against the wildcard pattern
            if (!preg_match($iface_regex, $key, $match)) {
                continue;
            }

            $interface_name = $match[1];
            $direction_raw  = $match[2];

            // Determine direction from captured suffix
            if (str_contains($direction_raw, 'received') || str_contains($direction_raw, 'in')) {
                $direction = 'received';
            } elseif (str_contains($direction_raw, 'sent') || str_contains($direction_raw, 'out')) {
                $direction = 'sent';
            } else {
                continue;
            }

            // Skip excluded interfaces
            $exclude = $this->fields_values['interfaces_exclude'] ?? '';
            if ($this->matchesExcludePattern($interface_name, $exclude)) {
                continue;
            }

            // Apply short alias for long interface names
            if (strlen($interface_name) > 4) {
                $label = $interface_aliases[$interface_name] ??= 'IF' . $alias_counter++;
            } else {
                $label = $interface_name;
            }

            $bps     = $details['value'] ?? 0;
            $percent = 0;

            if ($capacity > 0 && is_numeric($bps)) {
                $percent = $this->clampPercent(($bps / $capacity) * 100);
            }

            // Build the final display name
            $suffix       = $direction === 'sent' ? 'TX' : 'RX';
            $display_name = strtoupper($label . ' ' . $suffix);

            $rows[$display_name] = [
                'bps'       => $bps,
                'percent'   => $percent,
                'item_name' => $key,
            ];
        }

        return $rows;
    }

    ###############################################
    ### HELPERS
    ###############################################

    private function clampPercent(float | int $value): int
    {
        return max(0, min(100, (int) round($value)));
    }

    // Build the exact item reference used by sparklines so the browser does not need a second fuzzy lookup
    private function toSparklineItemRef(?array $metric): ?array
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

    // Find first metric whose key contains the given substring
    private function findMetric(array $metrics, string $search): ?array
    {
        if (isset($metrics[$search])) {
            return $metrics[$search];
        }

        foreach ($metrics as $key => $details) {
            if (str_contains($key, $search)) {
                return $details;
            }
        }

        return null;
    }

    // Check if a name matches any comma-separated wildcard pattern
    private function matchesExcludePattern(string $name, string $patterns): bool
    {
        if ($patterns === '') {
            return false;
        }

        foreach (explode(',', $patterns) as $pattern) {
            $pattern = trim($pattern);

            if ($pattern !== '' && fnmatch($pattern, $name, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }

}
