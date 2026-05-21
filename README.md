# zbx

Zabbix dashboard widgets for fast host health visibility and cross-host charting.

This repository contains a shared library module and two dashboard widgets:

- **ZbxCommon** — shared PHP (`MetricMatcher`, rate limiter) for both widgets.
- **AOverview** (v0.7.2) — host health cards with traffic-light metrics, badges, and sparklines.
- **ACharts** (v0.4.3) — Chart.js graph: each series = **one host + one metric** (e.g. zabbix-server CPU + Memory on one chart, or CPU from host A and RAM from host B).

Editors use **vanilla JavaScript** and Zabbix form fields (no React/Vue). See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).

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

1. Copy **`ZbxCommon`**, **`AOverview`**, and **`ACharts`** into your Zabbix UI modules directory (e.g. `/usr/share/zabbix/ui/modules/`).
2. **Administration → General → Modules** — enable **Zbx Common** first, then **AOverview** and **ACharts**.
3. Add widgets to a dashboard and configure fields in the widget editor.

Details: [docs/SHARED_MODULE.md](docs/SHARED_MODULE.md) (layout in Zabbix, autoload, official doc links).

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

### 2) ACharts — host + metric per series

Purpose: one chart where **each line is a concrete metric (item) on a concrete host**. Typical case: one host (e.g. `zabbix-server`) and several series (CPU, Memory). Also supported: several hosts, each series with its own host and item.

#### A. Editor setup flow (step-by-step)

1. **Select host(s)**
   - One host: all series use that host automatically (column Host is hidden).
   - Several hosts: each series row has a **Host** dropdown — pick the node before choosing the item.
2. **Define chart series**
   - Per series: label, color, **data item** (Find / Browse items / Quick add scoped to that row’s host).
3. **Chart options** — type, period, legend, dashboard time, etc.
4. **Save** — validation requires item per series; with multiple hosts, host per series is required.

**Example (one node, two metrics):** Host = `zabbix-server` → series 1 label `CPU`, item `CPU utilization` → series 2 label `Memory`, item `Memory utilization`.

#### B. Runtime

Resolve host + item per series (`SeriesHostResolver`), load history per item on that host, render Chart.js. Multi-host legends show `Host name / Label`.

#### C. Guarantees

- Item lookup and history always use the series host — no accidental cross-host item.
- Stable refresh without stale overwrites.

## TODO and progress

Detailed roadmap and status are tracked in [TODO.md](TODO.md).

## Repository layout

- `ZbxCommon/` — shared PHP library module (enable before widgets).
- `AOverview/` — AOverview widget module.
- `ACharts/` — ACharts widget module.
- `docs/` — development, shared module, smoke test, troubleshooting.

## License

MIT — see [LICENSE](LICENSE).
