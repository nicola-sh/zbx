# zbx

Zabbix dashboard widgets for fast host health visibility and cross-host charting.

This repository contains two widgets:

- **AOverview** (v0.7.2) — host health cards with traffic-light metrics, badges, and sparklines.
- **ACharts** (v0.4.1) — time-series charts where each series can point to a specific host and item.

**Author:** nicola

## Requirements

- Primary target: Zabbix **7.4**.
- Backward-compatibility fallbacks are kept for Zabbix 7.x form/runtime APIs where possible.
- Host items should match your templates (exact names or wildcard templates are configurable).

## Operations

- Smoke test: [docs/SMOKE_TEST.md](docs/SMOKE_TEST.md)
- Troubleshooting: [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)
- Known gaps and fix tracker: [fix.md](fix.md)

## Install

1. Copy `AOverview` and `ACharts` into your Zabbix modules directory.
2. In Zabbix, open **Administration → General → Modules** and enable both modules.
3. Add widgets **AOverview** and **ACharts** to a dashboard and configure fields in the widget editor.

> **Migration:** If you used the old modules `main_overview` / `main_charts`, remove them from Modules, copy the new folders, enable **AOverview** / **ACharts**, and re-add widgets on dashboards (widget type names changed).

## Detailed functionality (step-by-step)

### 1) AOverview — host status board

Purpose: show host health in compact cards with clear status colors, optional badges, and quick drill-down.

#### A. Editor setup flow (step-by-step)

1. **Select hosts**
   - Pick one or multiple hosts in the host multiselect.
   - Optional: **Override host** (dashboard context) when not using a template widget.
2. **Per-host accordion** (multi-host)
   - For each host: which metrics to show, display alias, badge placement (summary vs detail-only), item names/templates, and per-metric thresholds (empty fields inherit from **Appearance**).
   - Use **?** icons for field hints; **Test** on item rows to verify names on the host.
3. **Badges**
   - Visual editor (left/right lanes): uptime, liveliness, problems, tags, text badges, etc.
   - Configure uptime/liveliness item names and freshness thresholds.
4. **Appearance** (collapsible section)
   - Links, corners, label length, bar height.
   - **Thresholds and bar colors**: color mode (threshold vs solid), green/yellow/red colors, global threshold table (default, CPU, RAM, load, swap, interfaces, disk, partition).
5. **Host profiles (sync)** (advanced)
   - Hidden/synced JSON rebuilt from the accordion on save; manual edit only if needed.
6. **Save and verify**
   - Re-open the editor and confirm values match the dashboard.

#### B. Runtime behavior flow (step-by-step)

1. Resolve selected hosts and host context.
2. Resolve enabled metric item mappings (exact name or wildcard templates).
3. Load current values and optional short history for sparklines.
4. Compute per-metric status using threshold priority:
   - per-host threshold override,
   - metric threshold,
   - default threshold fallback.
5. Render host cards (single panel) or multi-host list + detail panel on click.
6. Preserve opened host detail panel on refresh/re-render.
7. On deactivation/clear, release listeners/observers cleanly (Zabbix 7.4 lifecycle-safe).

#### C. Behavior guarantees

- No hidden mandatory fields in the main editor flow.
- Server validation: invalid threshold order (red must be greater than yellow).
- Stable rendering in single-host and multi-host modes (no client-side host list filter on the dashboard).

---

### 2) ACharts — mixed host/item chart widget

Purpose: build one chart from multiple series, where each series can come from a different host and item.

#### A. Editor setup flow (step-by-step)

1. **Select hosts**
   - Choose one or multiple hosts as the available scope.
2. **Define chart series**
   - Per series: label, host (when multi-host), color, and data item.
   - Pick items via **Find** (type + lookup), **Browse items** (list on host), or **Quick add** presets (CPU, Memory, Load, Disk).
   - Advanced: edit `chart_series` JSON directly if needed.
3. **Chart options**
   - Chart type, period preset, grid, legend, **Use dashboard time range**, etc.
4. **Validate and save**
   - Multi-host: each series must identify host (`hostid` or `host`).
   - Empty or invalid series JSON shows validation errors on save.
5. **Re-open and verify**
   - Confirm series and chart settings persist.

#### B. Runtime behavior flow (step-by-step)

1. Parse widget config and normalize series definitions.
2. Resolve host context for selected hosts.
3. Resolve each series item strictly within its host scope (`SeriesHostResolver`).
4. Fetch history/trends with safe limits.
5. Downsample points for responsiveness.
6. Build datasets with host-aware legend labels.
7. Render chart; ignore stale async responses from older fetch generations.
8. Return informative `missing_reason` for unresolved series.

#### C. Behavior guarantees

- Host-scoped item resolution (no cross-host item mix-up).
- Stable refresh without race-condition overwrites.
- Predictable mixed-host, mixed-item charts.

## TODO and progress

Detailed roadmap and status are tracked in [TODO.md](TODO.md).

## Repository layout

- `AOverview/` — AOverview widget module.
- `ACharts/` — ACharts widget module.

## License

MIT — see [LICENSE](LICENSE).
