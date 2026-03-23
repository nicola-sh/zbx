These widgets have been tested on Zabbix 7.0.24, 7.2.15 and 7.4.8.

## Host overview

A compact but highly configurable host summary widget for Zabbix dashboards. It turns the default Linux and Windows template items into a clean overview with badges, single-metric bars, grouped multi-row metrics, sparkline history, and direct drill-down links into Latest data. It is designed for quick at-a-glance monitoring, but the configuration dialog also includes built-in testing and preview tools so item mapping is much easier to validate.

**Features**

- Show any mix of Processor, Memory, Load, Swap, Interfaces, Disk utilization, and Partitions.
- Sensible default item names and wildcard patterns for standard Zabbix Linux and Windows templates, with easy per-widget remapping in the config dialog.
- Configurable host badges on the left and right sides: Hostname, Uptime, Liveliness, Problems, Maintenance, Tags, free text, and custom links.
- Per-metric Medium and High thresholds for CPU, Memory, Load, Swap, Interfaces, Disks, and Partitions.
- Threshold or solid color schemes, configurable colors, adjustable bar height, full or short labels, and rounded or square corners.
- Load is displayed as a raw value with a configurable Load ceiling for bar and sparkline scaling.
- Interfaces use a configurable ceiling and unit (Kbps, Mbps, or Gbps), and long interface names are shortened automatically in the widget.
- Swap can be inverted for hosts that report free % instead of used %.
- Sparkline overlays for quick historical context directly from the widget.
- Direct links from metric labels and values into Zabbix Latest data for drill-down.
- Live frontend updates for percentage and bitrate changes with animated tickers.
- Multi-item metrics are grouped into clean, uniform layouts for interfaces, disks, and partitions.
- Built-in config dialog test tools for CPU, Memory, Load, Swap, and Uptime exact-name matching.
- Wildcard preview tools for Interfaces, Disks, and Partitions, including previews of filtered-out matches.
- Problems badge options to hide acknowledged or suppressed problems and optionally pulse when active.
- Supports all Zabbix themes.

**Screenshots**

![](https://i.imgur.com/EFkPox8.png)

![](https://i.imgur.com/JOHq1fB.png)

![](https://i.imgur.com/phu19Br.png)

## Banner

A widget for creating custom visual banners on your dashboard. Displays titles, descriptive text, and background images with lots of styling options. Perfect for highlighting key information, adding aesthetic elements or adding inline comments to your dashboard.

**Features**

- BB-codes.
- Add inline images.
- Horizontal text alignment.
- Seperate font colors and sizes for the title and description.
- Background color or image.
- Three background image display options.
- Header mode that centers the title and hides everything else.
- Supports all Zabbix themes.

**Screenshots**

![](https://i.imgur.com/8EoUFPU.png)

![](https://i.imgur.com/Nttk9na.png)

**Available BB-codes**

| BB-code                                     | Result                                                                                |
| ------------------------------------------- | ------------------------------------------------------------------------------------- |
| `[b]bold text[/b]`                          | **bold text**                                                                         |
| `[u]underlined[/u]`                         | underlined                                                                            |
| `[i]italic[/i]`                             | _italic_                                                                              |
| `[s]strike[/s]`                             | ~strike~                                                                              |
| `[center]centered text[/center]`            | centered text                                                                         |
| `[color=red]colored[/color]`                | colored text                                                                          |
| `[img]https://example.com/heart.png[/img]`  | ![image](https://icons.iconarchive.com/icons/paomedia/small-n-flat/16/heart-icon.png) |
| `[link=http://example.com]hyperlink[/link]` | [hyperlink](https://www.youtube.com/watch?v=dQw4w9WgXcQ)                              |
