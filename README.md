# zbx

Zabbix dashboard widgets for fast host health visibility and cross-host charting.

This repository contains two widgets:

- `main_overview` - host health cards with traffic-light metrics, badges, and sparklines.
- `main_charts` - time-series charts where each series can point to a specific host and item.

**Author:** nicola

## Requirements

- Primary target: Zabbix **7.4**.
- Backward-compatibility fallbacks are kept for Zabbix 7.x form/runtime APIs where possible.
- Host items should match your templates (exact names or wildcard templates are configurable).

## Install

1. Copy `main_overview` and `main_charts` into your Zabbix modules directory.
2. In Zabbix, open **Administration -> General -> Modules** and enable both modules.
3. Add widgets to a dashboard and configure fields in the widget editor.

## Current widget behavior

### 1) main_overview

Purpose: compact host status board for one or many hosts.

Key behavior:

- Displays metric rows (CPU, RAM, Load, Swap, Interfaces, Disks, Partitions).
- Applies traffic-light thresholds (global + per-host override).
- Supports threshold color mode and solid color mode.
- Shows badges (uptime/liveliness/problems/custom).
- Preserves selected host detail panel on widget refresh.
- Uses Zabbix 7.4 lifecycle-safe JS hooks (activate/deactivate/clear).

Editor flow (stacked and simple):

1. Hosts
2. Metrics
3. Thresholds and colors
4. Badges
5. Appearance

### 2) main_charts

Purpose: flexible charting for mixed host/item series.

Key behavior:

- Builds chart series from JSON config.
- Supports multi-host and multi-item in one widget.
- Resolves each series with host scope (`hostid`/`host` + `itemid`/`item_name`).
- Returns clear missing reasons when data cannot be resolved.
- Limits raw history/trend reads and downsamples points to protect performance.
- Prevents stale async responses from overwriting fresh chart data.

## TODO and progress

Project TODO list is tracked in [TODO.md](TODO.md).

Quick status:

- [x] Zabbix 7.4 compatibility hardening for both widgets.
- [x] main_charts multi-host/multi-item series support.
- [x] main_overview threshold editor simplified into a compact table.
- [x] Runtime fixes for known 500 errors from form API mismatches.
- [ ] Add GitHub Actions lint workflow (PHP + JS).
- [ ] Add smoke test checklist for manual Zabbix validation.

## Repository layout

- `main_overview/` - Main Overview widget module.
- `main_charts/` - Main Charts widget module.

## License

MIT - see [LICENSE](LICENSE).
