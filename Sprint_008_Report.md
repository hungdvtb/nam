# Sprint 008 Report - Experiment API Backend

Date: 2026-07-01

## Objective

Expose backend API actions for the Experiment Manager so a future UI sprint can create, manage, and inspect experiments.

This sprint does not change UI, trading logic, backtest calculations, AI, or optimizer behavior.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/Experiments/ExperimentApiController.php` | Handles experiment API actions with validation and `ExperimentService` calls. |
| `tests/experiment_api_smoke.php` | Smoke-tests experiment API action behavior against an in-memory SQLite repository. |
| `Sprint_008_Report.md` | Documents sprint implementation, validation, and limitations. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `api.php` | Added SQLite experiment dependencies, `experiment_*` action routing, and lazy `createExperimentApiService()`. | Exposes Experiment Manager backend actions without changing existing actions. |

## API Actions Added

| Action | Purpose |
| --- | --- |
| `experiment_list` | List experiments with optional filters. |
| `experiment_find` | Find one experiment by ID. |
| `experiment_create` | Create a new experiment. |
| `experiment_update` | Update experiment metadata. |
| `experiment_start` | Set experiment status to `Running`. |
| `experiment_pause` | Set experiment status to `Paused`. |
| `experiment_complete` | Complete experiment and store best item summary. |
| `experiment_archive` | Archive experiment. |
| `experiment_add_item` | Add an experiment item. |
| `experiment_list_items` | List items for an experiment. |
| `experiment_best_item` | Return the best scored item for an experiment. |

## Architecture Decisions

- `api.php` delegates experiment actions to `ExperimentApiController`.
- `ExperimentApiController` uses `ExperimentService`, not repositories directly.
- Runtime API storage uses `SQLiteExperimentRepository` by default.
- Experiment service creation is lazy and only happens for `experiment_*` actions, so existing API actions are not blocked by experiment storage startup.
- Input supports both snake_case and camelCase keys where existing records already support aliases.
- Responses follow existing API style with `ok: true` and action-specific payload keys.
- Errors are thrown as exceptions and handled by the existing `api.php` catch block, returning `ok: false` and a clear error message.

## Validation Rules

- `experiment_create` requires a non-empty `name`.
- Actions targeting an experiment require `id`, `experiment_id`, or `experimentId`.
- JSON-like fields such as `goal_json`, `summary_json`, and `metrics_json` must be arrays or valid JSON objects.
- Item creation requires at least a strategy version, backtest result, or explicit item type.
- Status values are validated by existing experiment status classes.

## Database Changes

No new database schema was added in Sprint 008.

The API uses existing Sprint 004 tables:

- `experiments`
- `experiment_items`

Migration verification:

- `php scripts/init_sqlite.php`
- Result: all migrations skipped, no pending migrations.

## Test Results

Syntax:

- `php -l api.php` passed.
- `php -l lib/Experiments/ExperimentApiController.php` passed.
- `php -l tests/experiment_api_smoke.php` passed.

New smoke test:

- `php tests/experiment_api_smoke.php` passed.

Required validation:

- `php -l api.php` passed.
- `php tests/run.php` passed 9 tests.
- `php tests/experiment_repository_smoke.php` passed.
- `php tests/experiment_auto_attach_smoke.php` passed.

Additional regression checks:

- `php tests/backtest_v2_storage_adapter_smoke.php` passed.
- `php scripts/init_sqlite.php` reported no pending migrations.

## Backward Compatibility

- No UI files were modified.
- Existing API actions remain in place.
- Backtest calculation code was not modified.
- Trading logic was not modified.
- The new API route block only handles actions beginning with `experiment_`.
- Existing tests and backtest smoke coverage still pass.

## Known Limitations

- No UI consumes these API actions yet.
- No authentication/authorization policy was added in this sprint.
- API actions use SQLite storage only, not JSON repository fallback.
- No pagination cursor support yet; list actions use a numeric limit.
- No dedicated HTTP integration test harness exists yet, so the smoke test validates the API controller directly.
- Experiment creation and lifecycle controls are backend-ready but still need a future UI sprint.
