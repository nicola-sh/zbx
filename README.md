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

## Detailed functionality (step-by-step)

### 1) `main_overview` - host status board

Purpose: show host health in compact cards with clear status colors, optional badges, and quick drill-down.

#### A. Editor setup flow (step-by-step)

1. **Select hosts**
   - Pick one or multiple hosts.
   - Optional: define host overrides if different hosts need different behavior.
2. **Choose metrics**
   - Enable/disable CPU, RAM, Load, Swap, Interfaces, Disks, Partitions.
3. **Configure thresholds and colors**
   - Set yellow/red percentages in a compact threshold table.
   - Choose color mode:
     - threshold mode (green/yellow/red),
     - solid mode (single fill color).
4. **Configure badges**
   - Enable standard badges (uptime, liveliness, problems, etc.).
   - Optionally add custom badge definitions.
5. **Tune appearance**
   - Label size, corners, bar height, link behavior, and other visual options.
6. **Save widget**
   - Editor stores normalized values (including legacy key fallback).
7. **Re-open and verify**
   - Ensure values persist and match rendered output.

#### B. Runtime behavior flow (step-by-step)

1. Resolve selected hosts and host context.
2. Resolve enabled metric item mappings (exact name or wildcard templates).
3. Load current values and optional short history for sparklines.
4. Compute per-metric status using threshold priority:
   - per-host threshold override,
   - metric threshold,
   - default threshold fallback.
5. Render host cards with bars, colors, badges, and links.
6. Preserve opened host detail panel on refresh/re-render.
7. On deactivation/clear, release listeners/observers cleanly (Zabbix 7.4 lifecycle-safe).

#### C. Behavior guarantees

- No hidden mandatory fields in editor.
- Clear validation for invalid threshold order (red must be greater than yellow).
- Stable rendering in single-host and multi-host modes.

---

### 2) `main_charts` - mixed host/item chart widget

Purpose: build one chart from multiple series, where each series can come from a different host and item.

#### A. Editor setup flow (step-by-step)

1. **Select hosts**
   - Choose one or multiple hosts as the available scope.
2. **Define `chart_series`**
   - For each series, set source using one of:
     - `hostid` or `host`,
     - `itemid` or `item_name`.
3. **Set chart options**
   - Chart type, period, grid, legend placement, etc.
4. **Validate series**
   - In multi-host mode, each series must explicitly identify host source.
5. **Save widget**
   - Config is normalized and stored for runtime queries.
6. **Re-open and verify**
   - Ensure JSON structure and chart settings are preserved.

#### B. Runtime behavior flow (step-by-step)

1. Parse widget config and normalize series definitions.
2. Resolve host context for selected hosts.
3. Resolve each series item strictly within its host scope.
4. Fetch history/trends with safe limits to avoid overload.
5. Downsample points to keep chart responsive.
6. Build datasets with host-aware legend labels.
7. Render chart; ignore stale async responses from older fetch generations.
8. Return informative `missing_reason` for unresolved series.

#### C. Behavior guarantees

- Correct host-scoped item resolution (no cross-host item mix-up).
- Stable refresh/update behavior without race-condition overwrites.
- Predictable output for mixed-host mixed-item charts.

## TODO and progress

Detailed roadmap and status are tracked in [TODO.md](TODO.md).

## Repository layout

- `main_overview/` - Main Overview widget module.
- `main_charts/` - Main Charts widget module.

## License

MIT - see [LICENSE](LICENSE).
