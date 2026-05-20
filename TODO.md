# TODO - zbx widgets (step-by-step roadmap)

Status markers:

- `[x]` done
- `[ ]` planned

## Phase 1 - Stability baseline for Zabbix 7.4

Goal: widget add/edit/render must work without runtime 500 errors.

### Step 1.1 - Form API compatibility
- [x] Replace fragile form API usages with compatibility-safe builders.
- [x] Add method/function existence fallbacks where Zabbix minor APIs differ.

### Step 1.2 - Runtime lifecycle compatibility
- [x] Align widget JS lifecycle with activate/deactivate/clear behavior.
- [x] Ensure listeners/observers/timers are detached correctly.

### Step 1.3 - Error hardening
- [x] Fix known 500 error paths in editor/view code.
- [x] Keep fallback behavior instead of fatal failures where possible.

Acceptance checklist:
- [x] Widget opens in dashboard.
- [x] Widget editor opens and saves.
- [x] No known fatal errors from previous reports.

---

## Phase 2 - `AOverview` logic and UX cleanup

Goal: thresholds and host cards should be predictable, simple, and easy to configure.

### Step 2.1 - Threshold logic correctness
- [x] Normalize threshold keys and support legacy key names.
- [x] Keep clear fallback chain (host override -> metric -> default).

### Step 2.2 - Editor UX simplification
- [x] Replace noisy threshold UI with compact table layout.
- [x] Keep color mode toggle clear (threshold mode vs solid mode).

### Step 2.3 - Multi-host behavior quality
- [x] Preserve selected host detail panel through widget refresh.
- [x] Keep per-host override editor resources cleaned up correctly.

Acceptance checklist:
- [x] Threshold color transitions reflect configured values.
- [x] Re-opening editor keeps values intact.
- [x] Multi-host detail state is not unexpectedly reset.

---

## Phase 3 - `ACharts` multi-host/multi-item model

Goal: one chart can reliably mix series from different hosts/items.

### Step 3.1 - Series model extension
- [x] Extend series schema to support `hostid`/`host` per series.
- [x] Keep `itemid`/`item_name` host-scoped during resolution.

### Step 3.2 - Data loading safety
- [x] Add safe limits for history/trend fetches.
- [x] Keep deterministic downsampling for long periods.

### Step 3.3 - Runtime rendering stability
- [x] Prevent stale async responses from overwriting latest chart state.
- [x] Improve missing series diagnostics with host-aware reason messages.

Acceptance checklist:
- [x] Mixed-host series render in one widget.
- [x] Missing series show clear reason.
- [x] Rapid refresh does not cause stale chart overwrite.

---

## Phase 4 - CI, quality gates, and operations docs

Goal: keep quality regressions out of main branch.

### Step 4.1 - Basic CI lint pipeline
- [x] Add GitHub Actions workflow for PR/push lint checks.
- [x] Run PHP syntax checks on all `.php` files.
- [x] Run JS syntax checks on all `.js` files.

### Step 4.2 - Optional static analysis
- [ ] Add `eslint` config and baseline rules.
- [ ] Add PHP static checks (`phpstan` or `phpcs`) with practical defaults.

### Step 4.3 - Manual smoke-test runbook
- [x] Add quick test flow for Zabbix 7.4 (`docs/SMOKE_TEST.md`).

### Step 4.4 - Troubleshooting notes
- [x] Add short ops guide (`docs/TROUBLESHOOTING.md`).
