# Sprint 007 Report - Experiment Auto Attach

Date: 2026-07-01

## Objective

Automatically attach saved V2 backtest results and evaluation results to an active experiment when one exists.

This sprint does not change UI, trading logic, backtest calculations, or legacy backtest persistence behavior.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/Experiments/ExperimentAutoAttachService.php` | Finds the active running experiment and creates or updates an experiment item for a V2 backtest/evaluation pair. |
| `tests/experiment_auto_attach_smoke.php` | Verifies no-op behavior, running experiment attach, evaluation status mapping, and fail-open attach failure behavior. |
| `Sprint_007_Report.md` | Documents sprint implementation, validation, and limitations. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `config/defaults.php` | Added `enable_experiment_auto_attach => true`. | Provides the requested feature flag. |
| `lib/Backtests/BacktestV2StorageAdapter.php` | Added optional `ExperimentAutoAttachService` dependency and post-evaluation attach step. | Hooks experiment attachment into the existing V2 dual-write flow. |
| `api.php` | Passes the auto-attach config flag into `BacktestV2StorageAdapter::sqliteDefault()`. | Enables runtime auto-attach for API backtests when configured. |
| `scripts/batch_backtest.php` | Passes the auto-attach config flag into `BacktestV2StorageAdapter::sqliteDefault()`. | Enables runtime auto-attach for persisted batch backtests when configured. |
| `scripts/demo_backtest.php` | Passes the auto-attach config flag into `BacktestV2StorageAdapter::sqliteDefault()`. | Enables runtime auto-attach for persisted demo backtests when configured. |

## Integration Design

The Sprint 006 V2 storage flow is preserved:

1. Legacy backtest runs and persists exactly as before.
2. V2 backtest result is saved.
3. Evaluation result is generated and saved.
4. Experiment auto-attach runs as an optional final step.

Auto-attach behavior:

1. Find the latest experiment with `status = Running`.
2. If no running experiment exists, do nothing.
3. If a running experiment exists, create or update an experiment item.
4. If attach fails, return attach failure metadata but keep V2 backtest/evaluation save successful.

## Experiment Item Mapping

Created item fields:

| Field | Value |
| --- | --- |
| `experiment_id` | Active running experiment ID. |
| `strategy_version_id` | Saved V2 backtest/evaluation strategy version ID. |
| `backtest_result_id` | Saved V2 backtest result ID. |
| `item_type` | `BacktestResult` |
| `status` | Derived from evaluation status. |
| `score` | Evaluation score. |
| `metrics_json` | Evaluation KPI, metrics, reasons, recommendations, score, status, and pass flag. |

Status mapping:

| Evaluation status | Experiment item status |
| --- | --- |
| `Passed` | `Passed` |
| `Warning` | `Testing` |
| `Failed` | `Failed` |

## Backward Compatibility

- No UI files were modified.
- No trading logic was modified.
- No backtest calculation code was modified.
- Legacy JSON backtest persistence still happens before V2 storage.
- No running experiment means no attach and no error.
- Experiment attach failure does not break backtest or V2 result/evaluation saving.
- The API response shape remains unchanged.

## Database Changes

No new database schema was added in Sprint 007.

This sprint uses existing Sprint 004 tables:

- `experiments`
- `experiment_items`

And existing Sprint 003/005 tables:

- `backtest_results`
- `evaluation_results`

Migration verification:

- `php scripts/init_sqlite.php`
- Result: all migrations skipped, no pending migrations.

## Test Results

Syntax checks passed:

- `php -l lib/Experiments/ExperimentAutoAttachService.php`
- `php -l lib/Backtests/BacktestV2StorageAdapter.php`
- `php -l config/defaults.php`
- `php -l api.php`
- `php -l scripts/batch_backtest.php`
- `php -l scripts/demo_backtest.php`
- `php -l tests/experiment_auto_attach_smoke.php`
- `php -l tests/backtest_v2_storage_adapter_smoke.php`

Smoke tests passed:

- `php tests/experiment_auto_attach_smoke.php`
- `php tests/backtest_v2_storage_adapter_smoke.php`
- `php tests/experiment_repository_smoke.php`
- `php tests/backtest_result_repository_smoke.php`
- `php tests/evaluation_repository_smoke.php`

Existing project suite:

- `php tests/run.php`
- Result: passed 9 tests.

## Validation Coverage

The new smoke test verifies:

- No running experiment results in no attach.
- Running experiment creates an experiment item.
- `Passed` evaluation maps to `Passed`.
- `Warning` evaluation maps to `Testing`.
- `Failed` evaluation maps to `Failed`.
- Experiment attach failure does not break V2 backtest/evaluation save flow.

The existing suite verifies:

- Current backtest smoke coverage still passes.
- Trading and strategy behavior remain stable.

## Known Limitations

- Only one active running experiment is selected, ordered by existing repository listing behavior.
- Auto-attach is runtime SQLite-only through the Sprint 006 adapter factory.
- Attach events are not yet written to an audit/event log.
- V2 backtest, evaluation, and experiment item writes are not wrapped in one transaction.
- No UI or API endpoints were added to create, start, or inspect experiments.
- Historical backtests are not retroactively attached to experiments.
