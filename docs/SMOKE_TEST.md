# Smoke test runbook (Zabbix 7.4)

Target module versions: **AOverview 0.7.2**, **ACharts 0.4.2**.

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
3. Open editor → **Appearance** → change a global threshold → save → reload dashboard; confirm bar colors update.
4. Hover **?** on a field; confirm hint text appears.
5. Add a second host; on dashboard confirm multi-host **list** (traffic light + name), click a row → **detail** panel, **Back to list**.
6. In per-host accordion, toggle a metric off for one host; save; confirm that host hides the metric in detail view.
7. **Badges**: add a text badge, save; confirm it shows on the card.
8. Click a metric bar; confirm sparkline opens (per-host config: swap invert, load cap).
9. Use **Test** on an item row in the accordion; confirm lookup feedback.

## ACharts

1. Add widget **ACharts**, select **one** host, save.
2. Add two series (e.g. CPU and Memory on that host), save; confirm chart loads.
3. Use **Browse items** on a series; pick an item from the list; save.
4. Use **Quick add** (e.g. CPU) on another series; confirm item name is filled.
5. Change period preset; reload dashboard.
6. Enable **Use dashboard time range**; align dashboard time selector; refresh widget; confirm range follows dashboard.
7. Try selecting two hosts in the editor; confirm validation error or only one host kept on save.
8. Clear all series / invalid JSON; confirm validation error on save.

## Regression checks

- No PHP 500 in `zabbix_server.log` / PHP-FPM log during edit/save/view.
- Browser console: no uncaught errors on dashboard refresh.
- Rapid dashboard refresh: chart does not show stale data from a previous fetch.
- After module update: hard refresh (Ctrl+F5) if sparkline thresholds look wrong on multi-host.
