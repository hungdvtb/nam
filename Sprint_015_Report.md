# Sprint 015 Report - AI Job Worker Mock

Date: 2026-07-01

## Objective

Create a mock AI worker that processes pending AI jobs without calling any real AI API.

This sprint does not add an optimizer, auto trading, trading logic changes, or backtest calculation changes.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/AIJobs/AIJobWorker.php` | Processes one pending AI job and generates deterministic mock output by job type. |
| `scripts/run_ai_worker.php` | CLI entrypoint for running the mock AI worker. |
| `tests/ai_worker_smoke.php` | Smoke-tests worker completion output, persistence, empty queue behavior, and forced failure handling. |
| `Sprint_015_Report.md` | Documents Sprint 015 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `api.php` | Added route for `ai_worker_run_once`. | Lets the UI trigger one mock worker pass through the existing API entrypoint. |
| `lib/AIJobs/AIJobApiController.php` | Added optional `AIJobWorker` dependency and `ai_worker_run_once` handling. | Keeps worker API behavior near the AI job API surface. |
| `index.php` | Added `Chạy worker thử` button to the AI Orchestrator page. | Lets users process one pending job from the UI. |
| `assets/app.js` | Added worker-running state, button binding, API call, list refresh, and toast feedback. | Connects the UI button to `ai_worker_run_once`. |
| `tests/ai_job_ui_contract_smoke.php` | Added checks for the worker button and frontend action reference. | Protects the UI/API contract. |
| `tests/run.php` | Added `ai_worker_smoke.php` to the AI Orchestrator smoke bundle. | Ensures the main test suite covers worker behavior. |

## Worker Behavior

`AIJobWorker::runOnce()`:

- Finds one `Pending` AI job.
- Marks it `Running`.
- Generates mock `output_json` based on `job_type`.
- Marks it `Completed`.
- Marks it `Failed` if mock processing throws an error.
- Returns `processed: false` when no pending jobs are available.

Forced failure is available for smoke testing through `input_json.force_error`, `input_json.forceError`, `input_json.mock_fail`, or `input_json.mockFail`.

## Mock Outputs

| Job Type | Output Fields |
| --- | --- |
| `GenerateStrategyIdea` | `mock_strategy_idea`, `rules_json`, `explanation` |
| `ImproveStrategy` | `mock_improvement_suggestion`, `changed_rules_json`, `reason` |
| `AnalyzeBacktest` | `mock_analysis`, `strengths`, `weaknesses`, `recommendations` |
| `EvaluateExperiment` | `mock_experiment_summary`, `best_candidate`, `next_steps` |

## CLI

Script: `scripts/run_ai_worker.php`

Default behavior processes one pending job:

```bash
php scripts/run_ai_worker.php
```

Optional flags:

- `--all`
- `--limit=N`
- `--database=path/to/db.sqlite`

The CLI initializes SQLite migrations for the selected database before running the worker.

## API/UI Added

API action:

- `ai_worker_run_once`

UI:

- Added `Chạy worker thử` on the `AI Orchestrator` page.
- The button processes one pending job and refreshes the job list/detail view.
- If no pending job exists, the UI reports that the queue is empty.

## Test Results

Syntax:

- `php -l lib/AIJobs/AIJobWorker.php` passed.
- `php -l scripts/run_ai_worker.php` passed.
- `php -l lib/AIJobs/AIJobApiController.php` passed.
- `php -l api.php` passed.
- `php -l index.php` passed.
- `php -l tests/ai_worker_smoke.php` passed.
- `php -l tests/ai_job_ui_contract_smoke.php` passed.
- `php -l tests/run.php` passed.
- `node --check assets/app.js` passed.

Smoke tests:

- `php tests/ai_worker_smoke.php` passed.
- `php tests/ai_job_api_smoke.php` passed.
- `php tests/ai_job_ui_contract_smoke.php` passed.

CLI check:

- `php scripts/run_ai_worker.php --database=data\ai_worker_sprint015.sqlite` passed against a temporary workspace database and returned an empty queue result.

Main suite:

- `php tests/run.php` passed.
- Result: `Passed 11 tests.`

## Scope Guardrails Confirmed

- No real AI API call added.
- No optimizer added.
- No auto trading added.
- No trading engine logic changed.
- No backtest calculation changed.
- Existing legacy strategy UI remains in place.
