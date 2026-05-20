# TODO - zbx widgets

Status is tracked with checkboxes:

- `[x]` done
- `[ ]` planned / in progress

## Completed

- [x] Align widget runtime with Zabbix 7.4 lifecycle behavior.
- [x] Fix threshold field normalization and legacy field fallback in `main_overview`.
- [x] Simplify `main_overview` traffic-light threshold editing UI (compact table layout).
- [x] Add robust per-host/multi-host handling in `main_overview` rendering flow.
- [x] Add multi-host + multi-item series model in `main_charts`.
- [x] Scope item resolution to host context in chart history loader path.
- [x] Limit history/trend fetch volume and keep downsampling deterministic.
- [x] Improve missing-series diagnostics (`missing_reason` and host-aware messages).
- [x] Fix known widget editor/runtime 500 errors caused by API compatibility differences.

## Planned next

- [ ] Add GitHub Actions workflow for PHP and JS lint checks on PR/push.
- [ ] Add optional static checks (`phpstan`, `eslint`) with baseline config.
- [ ] Add manual smoke-test checklist for Zabbix 7.4:
  - [ ] add widget
  - [ ] open editor
  - [ ] save and reopen
  - [ ] verify multi-host chart series
  - [ ] verify threshold color transitions
- [ ] Add short troubleshooting section (cache clear, logs, common misconfigurations).
