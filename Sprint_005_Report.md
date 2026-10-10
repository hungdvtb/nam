# Sprint 005 Report - Evaluation Engine

Date: 2026-07-01

## Objective

Build the Evaluation Engine foundation for AI Trade V2 without changing UI, trading logic, or current backtest behavior.

The sprint adds repository-backed KPI evaluation storage so future services and AI agents can evaluate backtest results, compare strategy versions, and support approval decisions.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/Evaluations/EvaluationStatus.php` | Defines supported evaluation statuses: Passed, Failed, Warning. |
| `lib/Evaluations/EvaluationResultRecord.php` | Normalizes and validates evaluation result records across SQLite and JSON storage. |
| `lib/Repositories/Contracts/EvaluationResultRepositoryInterface.php` | Defines the storage contract for evaluation results. |
| `lib/Repositories/Json/JsonEvaluationResultRepository.php` | Stores evaluation results in legacy-compatible JSON format. |
| `lib/Repositories/Sqlite/SQLiteEvaluationResultRepository.php` | Stores evaluation results in the new SQLite table. |
| `lib/EvaluationService.php` | Provides KPI evaluation, scoring, persistence, lookup, listing, latest, and best-result operations. |
| `database/migrations/005_evaluation_results.php` | Creates the SQLite `evaluation_results` table and indexes. |
| `tests/evaluation_repository_smoke.php` | Verifies EvaluationService behavior against both SQLite and JSON repositories. |
| `Sprint_005_Report.md` | Documents the sprint output, database changes, decisions, tests, and future plan. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `lib/Storage/JsonCompatibilityAdapter.php` | Added the `evaluation_results` dataset mapped to `evaluation_results.json`. | Keeps JSON compatibility available for the new repository foundation. |

## Database Changes

Migration added:

`005_evaluation_results`

Table added:

`evaluation_results`

Columns:

- `id`
- `strategy_version_id`
- `backtest_result_id`
- `experiment_id`
- `status`
- `score`
- `passed`
- `kpi_json`
- `metrics_json`
- `reasons_json`
- `recommendations_json`
- `created_at`
- `created_by`
- `notes`

Indexes added:

- `strategy_version_id`
- `backtest_result_id`
- `experiment_id`
- `status`
- `score`
- `created_at`

Verification:

- Migration applied to `data/ai_trade_v2.sqlite`.
- No pending migrations remain.
- `evaluation_results` table exists.
- Current row count is `0`.

## Architecture Decisions

- The Evaluation Engine is repository-first, matching the Sprint 001 storage architecture.
- SQLite is the primary future storage layer.
- JSON storage remains supported through `JsonCompatibilityAdapter`.
- The service does not call or modify the current backtest engine.
- Evaluations are saved independently so future sprints can connect backtest output without changing existing backtest behavior.
- No foreign key constraints were added yet. This keeps the foundation tolerant while Strategy Version, Backtest Result, and Experiment records continue to be migrated incrementally.
- KPI scoring is intentionally simple and explainable for the foundation sprint.
- Evaluation results include both snake_case and compatibility aliases where existing project conventions require them.

## Default KPI

The default KPI targets are:

| Metric | Target |
| --- | --- |
| Win rate | `>= 70` |
| Average RR | `>= 2` |
| Profit factor | `>= 2` |
| Max drawdown | `<= 10` |
| Total trades | `>= 300` |

## Scoring

The service creates a score from `0` to `100`.

Each KPI contributes up to `20` points:

- Win rate
- Average RR
- Profit factor
- Max drawdown
- Total trades

The result also stores:

- `passed`
- `status`
- `reasons_json`
- `recommendations_json`

Status rules:

- `Passed`: all KPI targets pass.
- `Warning`: one or more KPI targets fail, but total score is at least `70`.
- `Failed`: one or more KPI targets fail and total score is below `70`.

## Test Results

Syntax checks:

- `php -l lib/Evaluations/EvaluationStatus.php` passed.
- `php -l lib/Evaluations/EvaluationResultRecord.php` passed.
- `php -l lib/Repositories/Contracts/EvaluationResultRepositoryInterface.php` passed.
- `php -l lib/Repositories/Json/JsonEvaluationResultRepository.php` passed.
- `php -l lib/Repositories/Sqlite/SQLiteEvaluationResultRepository.php` passed.
- `php -l lib/EvaluationService.php` passed.
- `php -l database/migrations/005_evaluation_results.php` passed.
- `php -l tests/evaluation_repository_smoke.php` passed.

Migration:

- `php scripts/init_sqlite.php` applied `005_evaluation_results`.

Smoke tests:

- `php tests/evaluation_repository_smoke.php` passed.
- `php tests/backtest_result_repository_smoke.php` passed.
- `php tests/experiment_repository_smoke.php` passed.

Existing test suite:

- `php tests/run.php` passed 9 tests.

## Future Integration Plan

1. Connect current backtest output to `BacktestResultService`.
2. Pass saved backtest records into `EvaluationService::evaluateBacktestResult()`.
3. Save evaluation results through `EvaluationService::saveEvaluation()`.
4. Attach evaluations to experiments through `experiment_id`.
5. Use evaluation score and status in future Strategy Version promotion rules.
6. Add comparison views after UI changes are allowed.
7. Allow AI agents to read evaluations before cloning, improving, or promoting strategy versions.
8. Add configurable KPI profiles per strategy, symbol, timeframe, or experiment.

## Known Limitations

- The Evaluation Engine is not yet connected to the current backtest engine.
- No UI changes were made.
- No AI, optimizer, or autonomous decision logic was implemented.
- Existing historical data was not migrated into `evaluation_results`.
- Scoring is equal-weight and deliberately simple.
- KPI targets are service defaults, not yet user-configurable through storage or UI.
- Evaluation persistence is not yet wrapped in the same transaction as backtest result persistence.
