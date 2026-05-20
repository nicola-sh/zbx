# Troubleshooting

## Widget does not appear in dashboard picker

- Confirm the module is **enabled** under Administration → Modules.
- Clear Zabbix frontend cache: `php artisan zabbix:clear-cache` (if used) or restart PHP-FPM and reload the UI.
- Check `modules/AOverview/manifest.json` / `modules/ACharts/manifest.json` are readable by the web server.

## Empty overview or chart

- **AOverview:** Select at least one host in the widget configuration.
- **ACharts:** Configure at least one series with item name or itemid; use **Find item** in the editor.
- Verify items exist on the host and the Zabbix user has permission to read them.

## Period / validation errors (ACharts)

- Period must be a preset code (`1h`, `3h`, …) or dashboard time when **Use dashboard time range** is enabled.
- After upgrading from legacy `main_charts`, re-add the widget; old widget type ids are not migrated automatically.

## Sparkline shows wrong thresholds (multi-host)

- Ensure module version includes the `data-a-overview-config` / `dataset.aOverviewConfig` fix.
- Hard-refresh the browser (Ctrl+F5) after module update.

## Chart history returns no data

- Open browser devtools → Network → `widget.acharts.history` response.
- Confirm series `hostid` matches a host selected in the widget.
- Check item value type: non-numeric types may render empty series.

## Rate limit messages

- JSON actions allow about 120 requests per minute per session. Reduce dashboard refresh interval or number of widgets.

## Logs

- Zabbix server: `/var/log/zabbix/zabbix_server.log`
- PHP-FPM / Apache / nginx error log for module PHP fatals
- Enable debug in browser console for widget JS errors
