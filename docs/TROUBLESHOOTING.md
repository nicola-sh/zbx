# Troubleshooting

Module versions referenced below: **AOverview 0.7.2**, **ACharts 0.4.1**.

## Widget does not appear in dashboard picker

- Confirm the module is **enabled** under Administration → Modules.
- Clear Zabbix frontend cache: `php artisan zabbix:clear-cache` (if used) or restart PHP-FPM and reload the UI.
- Check `modules/AOverview/manifest.json` / `modules/ACharts/manifest.json` are readable by the web server.

## Empty overview or chart

- **AOverview:** Select at least one host in the widget configuration.
- **ACharts:** Configure at least one series with item name or itemid; use **Find item**, **Browse items**, or **Quick add** in the editor.
- Verify items exist on the host and the Zabbix user has permission to read them.

## Editor hints or thresholds (AOverview)

- Global thresholds and bar colors live under the collapsible **Appearance** section (not a separate top-level block).
- Per-host thresholds in the host accordion: empty fields inherit global values from Appearance.
- **?** icons require JavaScript `widget.edit.js`; if hints never appear after changing hosts, click **Обновить** (refresh host panels) or re-open the editor.

## Period / validation errors (ACharts)

- Period must be a preset code (`1h`, `3h`, …) or dashboard time when **Use dashboard time range** is enabled.
- After upgrading from legacy `main_charts`, re-add the widget; old widget type ids are not migrated automatically.
- Multi-host mode: each series needs `hostid` or `host` matching a host selected in the widget.

## Sparkline shows wrong thresholds (multi-host)

- Ensure module version includes the `data-a-overview-config` / `dataset.aOverviewConfig` fix (0.7.x+).
- Hard-refresh the browser (Ctrl+F5) after module update.

## Multi-host list on dashboard

- There is no host search/filter box on the dashboard list; scroll the list or reduce selected hosts in the editor.
- Detail panel opens on row click; use **Back to list** to return.

## Chart history returns no data

- Open browser devtools → Network → `widget.acharts.history` response.
- Confirm series `hostid` matches a host selected in the widget.
- Check item value type: non-numeric types may render empty series.

## Item lookup / browse (ACharts)

- **Browse items** calls `widget.acharts.lookup` with `mode=browse`; host must be selected on the series row first.
- Rate limit: ~120 JSON requests per minute per session; avoid very short dashboard refresh with many widgets.

## Rate limit messages (both widgets)

- JSON actions (lookup, history, sparkline) share the per-session limiter. Reduce dashboard refresh interval or number of widgets.

## Logs

- Zabbix server: `/var/log/zabbix/zabbix_server.log`
- PHP-FPM / Apache / nginx error log for module PHP fatals
- Enable debug in browser console for widget JS errors
