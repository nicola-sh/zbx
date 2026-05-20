# Smoke test runbook (Zabbix 7.4)

## Prerequisites

- Zabbix 7.4 with modules directory writable
- At least two monitored hosts with CPU/Memory items
- User role: Zabbix User or higher

## Install

1. Copy `AOverview` and `ACharts` into the Zabbix modules path.
2. Enable both modules under **Administration → General → Modules**.
3. Create a test dashboard.

## AOverview

1. Add widget **AOverview**, select one host, save.
2. Confirm metrics render with traffic-light colors.
3. Open widget editor, change a threshold, save, reload dashboard.
4. Add a second host; confirm multi-host list, search filter, and detail panel.
5. Click a metric bar; confirm sparkline opens and respects per-host config (swap invert, load cap).
6. Use **Test** on an item row in the editor; confirm lookup feedback.

## ACharts

1. Add widget **ACharts**, select one or more hosts.
2. Add two series with different items, save.
3. Confirm chart loads; change period preset, reload.
4. Enable **Use dashboard time range**, align dashboard time selector, confirm chart range updates on refresh.
5. Multi-host: assign each series to a host, save, confirm mixed series chart.
6. Remove all series JSON content; confirm validation error on save.

## Regression checks

- No PHP 500 in `zabbix_server.log` / PHP-FPM log during edit/save/view.
- Browser console: no uncaught errors on dashboard refresh.
- Rapid dashboard refresh: chart does not show stale data from previous fetch.
