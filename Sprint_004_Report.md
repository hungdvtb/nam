# Sprint 004 Report

Project: AI Trade V2  
Sprint goal: Experiment Manager foundation  
Date: 2026-07-01

## Summary

Sprint 004 implemented the Experiment Manager foundation without changing the current UI, trading logic, backtest engine, or existing backtest behavior.

The new module groups strategy versions and backtest results into research experiments for future comparison, evaluation, AI research loops, and optimizer workflows. It is currently isolated infrastructure:

- No current API route uses it yet.
- No UI uses it yet.
- No backtest engine behavior was changed.
- No AI or optimizer was implemented.
- No existing data was migrated.

## Files Created

| File | Purpose |
|---|---|
| `lib/Experiments/ExperimentStatus.php` | Defines experiment lifecycle statuses: Draft, Running, Paused, Completed, Failed, Archived. |
| `lib/Experiments/ExperimentItemStatus.php` | Defines experiment item statuses: Pending, Testing, Passed, Failed, Rejected. |
| `lib/Experiments/ExperimentRecord.php` | Normalizes experiment payloads across snake_case, camelCase, and JSON fields. |
| `lib/Experiments/ExperimentItemRecord.php` | Normalizes experiment item payloads and aliases. |
| `lib/Repositories/Contracts/ExperimentRepositoryInterface.php` | Defines the V2 repository contract for experiments and experiment items. |
| `lib/Repositories/Json/JsonExperimentRepository.php` | JSON-backed experiment repository using `JsonCompatibilityAdapter`. |
| `lib/Repositories/Sqlite/SQLiteExperimentRepository.php` | SQLite-backed experiment repository for `experiments` and `experiment_items`. |
| `lib/ExperimentService.php` | Service facade with Sprint 004 required lifecycle and item functions. |
| `database/migrations/004_experiments.php` | Creates the V2 `experiments` and `experiment_items` tables and indexes. |
| `tests/experiment_repository_smoke.php` | Standalone smoke tests for JSON and SQLite experiment repositories. |
| `Sprint_004_Report.md` | Sprint report. |

## Files Modified

| File | Why it changed |
|---|---|
| `lib/Storage/JsonCompatibilityAdapter.php` | Added `experiments` and `experiment_items` dataset mappings for JSON compatibility. |

No UI files were modified.

No backtest engine files were modified.

No trading logic was modified.

## Database Changes

Migration added:

```text
database/migrations/004_experiments.php
```

Created table:

```text
experiments
```

Columns:

```text
id
name
description
goal_json
status
created_by
created_at
updated_at
started_at
completed_at
best_strategy_version_id
best_backtest_result_id
summary_json
notes
```

Created table:

```text
experiment_items
```

Columns:

```text
id
experiment_id
strategy_version_id
backtest_result_id
item_type
status
score
metrics_json
created_at
notes
```

Indexes:

- `experiments.status`
- `experiments.created_at`
- `experiment_items.experiment_id`
- `experiment_items.status`
- `experiment_items.score`
- `experiment_items.strategy_version_id`
- `experiment_items.backtest_result_id`

Migration status:

```text
001_storage_foundation: skipped, already applied
002_strategy_versions: skipped, already applied
003_backtest_results: skipped, already applied
004_experiments: applied
pending migrations: none
```

Second migration run:

```text
001_storage_foundation: skipped
002_strategy_versions: skipped
003_backtest_results: skipped
004_experiments: skipped
pending migrations: none
```

Current project SQLite row count:

```text
experiments = 0
experiment_items = 0
```

No existing data was migrated.

## Repository and Service

Repository contract:

```text
ExperimentRepositoryInterface
```

Implementations:

```text
JsonExperimentRepository
SQLiteExperimentRepository
```

Service:

```text
ExperimentService
```

Service methods:

```text
createExperiment()
updateExperiment()
startExperiment()
pauseExperiment()
completeExperiment()
archiveExperiment()
addItem()
updateItem()
listExperiments()
findExperiment()
listItems()
getBestItem()
```

## Architecture Decisions

### 1. Experiments are isolated research containers

An experiment groups strategy versions and backtest results without changing how those versions or backtests are currently produced.

### 2. Items are flexible

`experiment_items` supports:

- `strategy_version_id`
- `backtest_result_id`
- `item_type`
- `score`
- `metrics_json`

This lets future sprints attach strategy versions, backtest results, optimizer candidates, or manual research candidates.

### 3. No hard foreign keys to strategy/backtest entities yet

`experiment_items.experiment_id` uses a foreign key to `experiments`.

`strategy_version_id` and `backtest_result_id` are indexed but not foreign-key constrained yet because existing strategy versions and backtest results have not been fully migrated into SQLite.

### 4. Best item is score-based

`getBestItem()` currently selects the item with the highest `score`.

Future sprints can replace or extend this with objective functions such as:

- Profit factor.
- Net profit.
- Drawdown-adjusted score.
- Validation consistency.
- Custom optimizer objective.

### 5. JSON compatibility is additive

The JSON adapter now supports:

```text
experiments -> experiments.json
experiment_items -> experiment_items.json
```

The smoke tests used a temporary JSON store, so project files were not created:

```text
data/experiments.json: not created
data/experiment_items.json: not created
```

## Test Results

### PHP Syntax

All new and modified PHP files passed `php -l`:

- `lib/Experiments/ExperimentStatus.php`
- `lib/Experiments/ExperimentItemStatus.php`
- `lib/Experiments/ExperimentRecord.php`
- `lib/Experiments/ExperimentItemRecord.php`
- `lib/Repositories/Contracts/ExperimentRepositoryInterface.php`
- `lib/Repositories/Json/JsonExperimentRepository.php`
- `lib/Repositories/Sqlite/SQLiteExperimentRepository.php`
- `lib/ExperimentService.php`
- `database/migrations/004_experiments.php`
- `lib/Storage/JsonCompatibilityAdapter.php`
- `tests/experiment_repository_smoke.php`

### Smoke Tests

Command:

```text
php tests/experiment_repository_smoke.php
```

Result:

```text
Experiment repository smoke tests passed.
```

Covered:

- JSON repository works.
- SQLite repository works.
- Create experiment.
- Update experiment.
- Start experiment.
- Pause experiment.
- Complete experiment.
- Archive experiment.
- Add item.
- Update item.
- List experiments.
- Find experiment.
- List items.
- Get best item by score.

### Existing Test Suite

Command:

```text
php tests/run.php
```

Result:

```text
Passed 9 tests.
```

This confirms existing trading and backtest behavior still passes.

## Future Integration Plan

### Phase 1: Connect Strategy Version Manager

Allow experiments to reference real strategy versions after strategy version migration is complete.

### Phase 2: Connect Backtest Result Repository

Allow experiments to add backtest result items after current backtest outputs are mapped into `BacktestResultService`.

### Phase 3: Experiment API

Add API endpoints for:

- Create experiment.
- Update experiment.
- Start/pause/complete/archive experiment.
- Add experiment item.
- Update experiment item.
- List items.
- Get best item.

No UI change should happen until a dedicated UI sprint.

### Phase 4: Evaluation Integration

Connect experiment items to scoring and evaluation outputs so the system can rank candidates consistently.

### Phase 5: Future AI and Optimizer Integration

Future AI/optimizer modules can use experiments to:

- Group generated strategy versions.
- Run backtest batches.
- Compare candidates.
- Track best candidate.
- Preserve experiment goals and summaries.

## Known Limitations

- No current API routes use `ExperimentService`.
- No UI uses experiment data yet.
- No existing strategy versions or backtests were migrated into experiments.
- Best item selection is currently score-only.
- `strategy_version_id` and `backtest_result_id` are not foreign-key constrained yet.
- There is no soft delete or experiment event history yet.
- No AI or optimizer workflow was implemented.

## Sprint 004 Result

Sprint 004 is complete.

The Experiment Manager foundation is now present, SQLite-ready, JSON-compatible, smoke-tested, and ready for future integration with strategy versions and backtest results. Current project behavior remains unchanged.
