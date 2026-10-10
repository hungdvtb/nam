# Sprint 003 Report

Project: AI Trade V2  
Sprint goal: Backtest Result Repository foundation  
Date: 2026-07-01

## Summary

Sprint 003 implemented the Backtest Result Repository foundation without changing the current UI, trading logic, backtest engine, or existing backtest behavior.

The new module is ready for a future sprint to connect current backtest output into structured storage. For now, it is isolated infrastructure:

- No current backtest writes were redirected.
- No existing JSON backtest files were migrated.
- No UI/API route was changed.
- No AI, optimizer, or evaluator automation was added.

## Files Created

| File | Purpose |
|---|---|
| `lib/Backtests/BacktestResultRecord.php` | Normalizes backtest result payloads from snake_case, camelCase, and current summary-style keys. |
| `lib/Repositories/Contracts/BacktestResultRepositoryInterface.php` | Defines the V2 repository contract for saving, finding, listing, ranking, and deleting backtest results. |
| `lib/Repositories/Json/JsonBacktestResultRepository.php` | JSON-backed repository using `JsonCompatibilityAdapter` for backward-compatible storage. |
| `lib/Repositories/Sqlite/SQLiteBacktestResultRepository.php` | SQLite-backed repository for the new `backtest_results` table. |
| `lib/BacktestResultService.php` | Service facade with Sprint 003 required functions. |
| `database/migrations/003_backtest_results.php` | Adds the V2 `backtest_results` table and indexes. |
| `tests/backtest_result_repository_smoke.php` | Standalone smoke tests for JSON and SQLite repositories. |
| `Sprint_003_Report.md` | Sprint report. |

## Files Modified

| File | Why it changed |
|---|---|
| `lib/Storage/JsonCompatibilityAdapter.php` | Added `backtest_results` dataset mapping to `backtests/backtest_results.json` for future JSON-compatible result storage. |

No UI files were modified.

No current backtest engine files were modified.

No trading logic was modified.

## Database Changes

Migration added:

```text
database/migrations/003_backtest_results.php
```

Created table:

```text
backtest_results
```

Columns:

```text
id
strategy_version_id
symbol
timeframe
higher_timeframe
start_date
end_date
initial_balance
final_balance
net_profit
win_rate
average_rr
profit_factor
max_drawdown
total_trades
winning_trades
losing_trades
settings_json
metrics_json
trades_json
report_path
created_at
created_by
notes
```

Indexes:

- `strategy_version_id`
- `symbol`, `timeframe`, `higher_timeframe`
- `created_at`
- `profit_factor`

Migration status:

```text
001_storage_foundation: skipped, already applied
002_strategy_versions: skipped, already applied
003_backtest_results: applied
pending migrations: none
```

Second migration run:

```text
001_storage_foundation: skipped
002_strategy_versions: skipped
003_backtest_results: skipped
pending migrations: none
```

Current project SQLite row count:

```text
backtest_results = 0
```

No existing backtest data was migrated.

## Repository and Service

Repository contract:

```text
BacktestResultRepositoryInterface
```

Implementations:

```text
JsonBacktestResultRepository
SQLiteBacktestResultRepository
```

Service:

```text
BacktestResultService
```

Service methods:

```text
saveResult()
findResult()
listResults()
listByStrategyVersion()
getLatestResult()
getBestResult()
deleteResult()
```

## Architecture Decisions

### 1. Current backtest engine remains untouched

The current `BacktestEngine.php` continues to persist and list results exactly as before. This sprint only prepares a new repository/service layer for a future integration sprint.

### 2. JSON compatibility is additive

The new JSON repository uses a new dataset mapping:

```text
backtest_results -> backtests/backtest_results.json
```

This file is not created by normal app behavior yet. The smoke tests used a temporary store, so no project `data/backtests/backtest_results.json` file was created during this sprint.

### 3. SQLite stores queryable summary columns plus raw JSON

The table stores searchable columns such as:

- Profit factor.
- Win rate.
- Average RR.
- Drawdown.
- Trade count.
- Symbol and timeframe.
- Strategy version ID.

It also keeps:

- `settings_json`
- `metrics_json`
- `trades_json`

This preserves raw backtest detail for future AI evaluation and comparison.

### 4. No foreign key to strategy versions yet

`strategy_version_id` is indexed but not constrained by a foreign key. This is intentional because existing strategy versions have not been migrated into SQLite yet. A future migration can add stricter integrity once strategy version migration is complete.

### 5. Delete is implemented as a repository operation

`deleteResult()` physically deletes a repository row. Future production/audit requirements may replace this with archival or soft delete, but Sprint 003 follows the requested service contract.

## Test Results

### PHP Syntax

All new and modified PHP files passed `php -l`:

- `lib/Backtests/BacktestResultRecord.php`
- `lib/Repositories/Contracts/BacktestResultRepositoryInterface.php`
- `lib/Repositories/Json/JsonBacktestResultRepository.php`
- `lib/Repositories/Sqlite/SQLiteBacktestResultRepository.php`
- `lib/BacktestResultService.php`
- `database/migrations/003_backtest_results.php`
- `lib/Storage/JsonCompatibilityAdapter.php`
- `tests/backtest_result_repository_smoke.php`

### Smoke Tests

Command:

```text
php tests/backtest_result_repository_smoke.php
```

Result:

```text
Backtest result repository smoke tests passed.
```

Covered:

- Save result.
- Find result.
- List by strategy version.
- Get latest result.
- Get best result by profit factor.
- Delete result.
- JSON compatibility works.
- SQLite works.

### Existing Test Suite

Command:

```text
php tests/run.php
```

Result:

```text
Passed 9 tests.
```

This confirms current backtest behavior and legacy project behavior remain intact.

## Future Integration Plan

### Phase 1: Adapter From Current Backtest Output

Create a mapper that converts current `BacktestEngine` run summaries and trades into `BacktestResultRecord` payloads.

The mapper should read:

- Run summary.
- Trades.
- Settings.
- Symbol/timeframes.
- Date range.
- Strategy version ID.

It should not change simulation behavior.

### Phase 2: Optional Dual Write

After mapper validation, future sprint can dual-write:

- Existing JSON run/trade stores for backward compatibility.
- New `BacktestResultService` for V2 storage.

Dual write should be behind a clear feature flag or compatibility mode.

### Phase 3: Query API

Add API endpoints for:

- List backtest results.
- List by strategy version.
- Find result.
- Best result by metric.
- Latest result by strategy version.

Existing UI should remain unchanged until a dedicated UI sprint.

### Phase 4: Strategy Evaluation Integration

`StrategyEvaluator` can later read normalized result records instead of scanning legacy JSON payloads.

### Phase 5: AI Evaluation Integration

Future AI modules can use:

- `metrics_json` for detailed performance.
- `trades_json` for trade-level review.
- Summary columns for ranking and filtering.
- `strategy_version_id` for version comparison.

## Known Limitations

- Existing backtest runs and trades were not migrated.
- Current backtest engine is not connected to this repository yet.
- No API routes use `BacktestResultService` yet.
- No UI uses the new result repository yet.
- No AI or optimizer integration was added.
- `trades_json` can become large; future storage may need separate trade rows for high-volume analysis.
- `strategy_version_id` is not a SQLite foreign key yet because strategy version data has not been migrated.
- `deleteResult()` is hard delete for now; future audit requirements may need soft delete.

## Sprint 003 Result

Sprint 003 is complete.

The Backtest Result Repository foundation is now present, SQLite-ready, JSON-compatible, smoke-tested, and ready for future connection to current backtest output. Current project behavior remains unchanged.
