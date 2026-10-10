# Sprint 009 Report - Experiments UI Page

Date: 2026-07-01

## Objective

Create a Vietnamese Experiment Manager UI that uses the Sprint 008 `experiment_*` API actions.

This sprint does not change trading logic, backtest calculations, AI behavior, optimizer behavior, or existing backtest engine behavior.

## Files Created

| File | Purpose |
| --- | --- |
| `tests/experiment_ui_contract_smoke.php` | Verifies the Experiment Manager UI contract: tab markup, required containers, and frontend references to required API actions. |
| `Sprint_009_Report.md` | Documents sprint implementation, validation, limitations, and future integration points. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `index.php` | Added the `Thí nghiệm AI` navigation tab, Experiment Manager tab panel, create form, detail area, lifecycle action buttons, best item area, and experiment items area. | Gives users a UI entry point for Sprint 008 Experiment Manager APIs while preserving existing screens. |
| `assets/app.js` | Added experiment state, Vietnamese status labels, render functions, API loading functions, create action, lifecycle actions, tab lazy-load behavior, and safe refresh after backtest when experiments are already loaded. | Connects the new page to `experiment_list`, `experiment_find`, `experiment_create`, `experiment_start`, `experiment_pause`, `experiment_complete`, `experiment_archive`, `experiment_list_items`, and `experiment_best_item`. |
| `assets/styles.css` | Added small list/action styling for experiment list and item rows using existing panel/list conventions. | Keeps the new page visually consistent with the current UI style. |

## UI Added

| UI Area | Capability |
| --- | --- |
| Experiment list | View experiments and open one experiment detail. |
| Create form | Create a new experiment with name, goal, and notes. |
| Lifecycle buttons | Start, pause, complete, and archive the selected experiment. |
| Detail panel | View status, goal, timestamps, and notes. |
| Best item panel | View the current best experiment item. |
| Items panel | View experiment items attached by V2 storage/auto-attach flows. |

## API Actions Used

- `experiment_list`
- `experiment_find`
- `experiment_create`
- `experiment_start`
- `experiment_pause`
- `experiment_complete`
- `experiment_archive`
- `experiment_list_items`
- `experiment_best_item`

`experiment_update` remains backend-ready from Sprint 008 but was not exposed in this UI sprint because the requested UI actions did not include edit metadata.

## Architecture Decisions

- The page follows the existing static tab pattern in `index.php`.
- Frontend code stays in `assets/app.js`, matching the current vanilla JavaScript architecture.
- The experiment page lazy-loads data when the tab is opened, avoiding extra required work during the initial dashboard bootstrap.
- Existing API client behavior is reused; no new HTTP abstraction was introduced.
- Backtest flow is not changed. If experiment data was already loaded, the UI asks the Experiment Manager to refresh after a backtest so auto-attached items can appear.
- Empty states are intentionally short and operational, matching the existing dashboard style.

## Database Changes

No database schema changes were made in Sprint 009.

The UI consumes the existing Sprint 004/Sprint 008 experiment storage and API foundation.

## Test Results

Syntax and UI contract:

- `php -l api.php` passed.
- `php -l index.php` passed.
- `node --check assets/app.js` passed.
- `php tests/experiment_ui_contract_smoke.php` passed.

Regression and experiment smoke tests:

- `php tests/run.php` passed 9 tests.
- `php tests/experiment_repository_smoke.php` passed.
- `php tests/experiment_auto_attach_smoke.php` passed.
- `php tests/experiment_api_smoke.php` passed.

## Backward Compatibility

- Existing UI tabs remain in place.
- Existing API actions remain unchanged.
- Existing trading logic was not modified.
- Existing backtest calculation behavior was not modified.
- No AI or optimizer behavior was added.
- The new UI only calls Experiment Manager APIs.

## Future Integration Points

- Add edit support using `experiment_update`.
- Add filtering by status once the experiment list grows.
- Add pagination when the number of experiments or items becomes large.
- Link experiment items to detailed backtest result views when a Backtest Result UI exists.
- Add richer item metrics display after evaluation reports become user-facing.

## Known Limitations

- The UI does not yet edit existing experiment metadata.
- There is no dedicated browser automation test harness for visual layout.
- The best item display is summary-level only.
- The page relies on the Sprint 008 SQLite-backed Experiment API being available.
