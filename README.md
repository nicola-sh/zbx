# zbx

Zabbix dashboard widgets. This tree includes **Host Overview** — a compact health summary for one or several hosts: metric bars (CPU, memory, load, swap, interfaces, disks, partitions), optional badges, sparklines, and links into Zabbix. It targets typical Linux and Windows agent templates, with sensible defaults and wildcard-friendly item mapping.

**Author:** nicola  
**Widget version:** see `host_overview/manifest.json` (semantic versioning from **0.5.1**).

## Requirements

- Zabbix **7.0**, **7.2**, or **7.4** (tested on 7.0.24, 7.2.15, 7.4.8).
- Host items aligned with common templates (names and wildcards are configurable in the widget).

## Install

1. Copy the `host_overview` directory into your Zabbix `modules` tree (same layout as other module widgets).
2. Enable the module under **Administration → General → Modules**, then add the widget on a dashboard.

## Features (high level)

- Single-host or **multi-host** mode: list with traffic-light status, optional display aliases, per-host parameters, in-widget drill-down to detail.
- Threshold bars, optional solid vs threshold colour modes, bar height, label length, rounded/square corners.
- Badges (hostname, uptime, liveliness, problems, maintenance, tags, custom text/links).
- Sparkline history overlay; item and host context menus where supported.
- Configuration helpers: item **Test** / wildcard preview, per-host accordion editor for overrides.

## Repository layout

- `host_overview/` — Host Overview widget module.

## License

MIT — see [LICENSE](LICENSE).
