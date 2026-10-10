# Sprint 006 Report - Backtest Integration Adapter

Date: 2026-07-01

## Objective

Connect current `BacktestEngine` output to the V2 storage layer by adapter/dual-write only.

This sprint does not change UI, trading logic, backtest calculations, or legacy backtest persistence behavior.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/Backtests/BacktestResultMapper.php` | Converts current `BacktestEngine` output into a `BacktestResultService` payload. |
| `lib/Backtests/BacktestV2StorageAdapter.php` | Saves mapped backtest results to V2 storage, evaluates them with `EvaluationService`, and saves evaluation results. |
| `tests/backtest_v2_storage_adapter_smoke.php` | Verifies V2 backtest save, evaluation save, and fail-open behavior when V2 storage fails. |
| `Sprint_006_Report.md` | Documents sprint implementation, integration decisions, validation, and limitations. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `lib/BacktestEngine.php` | Added optional `BacktestV2StorageAdapter` dependency and post-legacy-persist V2 hook. | Enables safe dual-write after existing JSON persistence completes. |
| `api.php` | Constructs the V2 adapter from config and passes it into `BacktestEngine`. | Enables V2 storage for normal `run_backtest` API flow. |
| `scripts/batch_backtest.php` | Constructs the V2 adapter from config and passes it into `BacktestEngine`. | Enables V2 storage for persisted CLI batch runs. |
| `scripts/demo_backtest.php` | Constructs the V2 adapter from config and passes it into `BacktestEngine`. | Enables V2 storage for persisted demo runs. |
| `config/defaults.php` | Added `enable_v2_backtest_storage => true`. | Provides the requested feature flag. |

## Integration Design

Dual-write order:

1. `BacktestEngine` runs the existing calculation path.
2. `BacktestEngine::persistRun()` saves legacy JSON exactly as before.
3. `BacktestEngine::persistV2Copy()` calls `BacktestV2StorageAdapter`.
4. `BacktestResultMapper` maps the engine result to V2 result payload.
5. `BacktestResultService` saves the V2 backtest result.
6. `EvaluationService` evaluates the saved V2 backtest result.
7. `EvaluationResultRepository` saves the evaluation result.

Fail-open rule:

- If V2 storage fails, the adapter catches the error.
- `BacktestEngine` also wraps the V2 hook defensively.
- Legacy backtest output and JSON persistence continue working.

## Mapping Notes

The V2 result ID is the existing legacy `backtestRunId`.

Important field mappings:

| Legacy field | V2 field |
| --- | --- |
| `backtestRunId` | `id` |
| `strategyVersionId` | `strategy_version_id` |
| `contextTimeframe` | `higher_timeframe` |
| `winRate` | `win_rate` |
| `averageRR` | `average_rr` |
| `profitFactor` | `profit_factor` |
| `maxDrawdownPercent` | `max_drawdown` |
| `totalTrades` | `total_trades` |
| `winTrades` | `winning_trades` |
| `lossTrades` | `losing_trades` |
| `summary` | `metrics_json` |
| `trades` | `trades_json` |

`maxDrawdownPercent` is intentionally mapped to `max_drawdown` because Sprint 005 KPI evaluation expects drawdown as a percentage.

## Database Changes

No new database schema was added in Sprint 006.

This sprint uses existing tables:

- `backtest_results` from Sprint 003.
- `evaluation_results` from Sprint 005.

Migration verification:

- `php scripts/init_sqlite.php`
- Result: no pending migrations.

## Backward Compatibility

- Existing legacy JSON backtest storage remains active.
- The legacy run ID remains unchanged.
- The API response shape is not changed by this sprint.
- UI files were not modified.
- Backtest calculation logic was not modified.
- Trading logic was not modified.
- V2 storage is optional through `enable_v2_backtest_storage`.

## Test Results

Syntax checks passed:

- `php -l lib/Backtests/BacktestResultMapper.php`
- `php -l lib/Backtests/BacktestV2StorageAdapter.php`
- `php -l lib/BacktestEngine.php`
- `php -l api.php`
- `php -l scripts/batch_backtest.php`
- `php -l scripts/demo_backtest.php`
- `php -l config/defaults.php`
- `php -l tests/backtest_v2_storage_adapter_smoke.php`

Migration check:

- `php scripts/init_sqlite.php`
- Result: all migrations skipped, no pending migrations.

Smoke tests:

- `php tests/backtest_v2_storage_adapter_smoke.php` passed.
- `php tests/backtest_result_repository_smoke.php` passed.
- `php tests/evaluation_repository_smoke.php` passed.
- `php tests/experiment_repository_smoke.php` passed.

Existing project suite:

- `php tests/run.php` passed 9 tests.
- Existing backtest smoke coverage passed inside this suite.

## Validation Coverage

The new smoke test verifies:

- Current engine output maps into a V2 backtest result payload.
- V2 backtest result is saved.
- Evaluation result is generated and saved.
- Evaluation references the saved backtest result.
- Forced V2 storage failure does not stop legacy `BacktestEngine` persistence.

## Known Limitations

- V2 result and evaluation writes are not yet wrapped in one transaction.
- V2 storage failures are not yet persisted to an audit table.
- Runtime integration uses SQLite repositories, not the JSON V2 repository.
- Historical legacy backtest data is not migrated.
- No UI or API endpoint was added for listing V2 backtest/evaluation records.
- KPI defaults still come from `EvaluationService`, not a configurable KPI profile table.
