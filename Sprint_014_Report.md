# Sprint 014 Report - AI Orchestrator Foundation

Date: 2026-07-01

## Objective

Build the AI Orchestrator foundation for AI job tracking without calling any real AI API.

This sprint does not add an optimizer, auto trading, trading logic changes, or backtest calculation changes.

## Files Created

| File | Purpose |
| --- | --- |
| `database/migrations/014_ai_jobs.php` | Creates the `ai_jobs` table and indexes for AI job status/type/link lookups. |
| `lib/AIJobs/AIJobStatus.php` | Defines allowed AI job statuses and normalization aliases. |
| `lib/AIJobs/AIJobType.php` | Defines allowed AI job types and normalization aliases. |
| `lib/AIJobs/AIJobRecord.php` | Normalizes AI job rows/payloads and JSON fields. |
| `lib/AIJobs/AIJobApiController.php` | Handles AI Orchestrator API actions. |
| `lib/AIJobService.php` | Implements AI job lifecycle operations. |
| `lib/Repositories/Contracts/AIJobRepositoryInterface.php` | Defines the AI job repository contract. |
| `lib/Repositories/Sqlite/SQLiteAIJobRepository.php` | Persists AI jobs in SQLite. |
| `tests/ai_job_api_smoke.php` | Smoke-tests API actions and service lifecycle behavior. |
| `tests/ai_job_ui_contract_smoke.php` | Verifies AI Orchestrator UI IDs and frontend API wiring. |
| `Sprint_014_Report.md` | Documents Sprint 014 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `api.php` | Added AI job repository/controller dependencies, `ai_job_*` routing, and a SQLite-backed service factory. | Exposes AI Orchestrator actions through the existing API entrypoint. |
| `index.php` | Added the `AI Orchestrator` navigation tab and Vietnamese job management page markup. | Lets users view jobs, create manual jobs, inspect payloads, and cancel pending jobs. |
| `assets/app.js` | Added AI job state, lazy loading, list/detail rendering, create/cancel calls, and Strategy V2/experiment selectors. | Connects the new UI page to the API. |
| `assets/styles.css` | Added AI job list styling into the existing list/card pattern. | Keeps the new page visually consistent. |
| `tests/run.php` | Added AI Orchestrator smoke tests to the main runner. | Ensures `php tests/run.php` validates Sprint 014 behavior. |

## Database Changes

New table: `ai_jobs`

Fields:

- `id`
- `job_type`
- `status`
- `input_json`
- `output_json`
- `error_message`
- `strategy_version_id`
- `experiment_id`
- `created_at`
- `started_at`
- `completed_at`
- `created_by`
- `notes`

Allowed statuses:

- `Pending`
- `Running`
- `Completed`
- `Failed`
- `Cancelled`

Allowed job types:

- `GenerateStrategyIdea`
- `ImproveStrategy`
- `AnalyzeBacktest`
- `EvaluateExperiment`

## API Actions Added

| Action | Purpose |
| --- | --- |
| `ai_job_list` | List AI jobs with optional filters. |
| `ai_job_find` | Find a single AI job by id. |
| `ai_job_create` | Create a manual/fake AI job record. |
| `ai_job_cancel` | Cancel a pending AI job. |

## Service Added

`AIJobService` implements:

- `createJob()`
- `startJob()`
- `completeJob()`
- `failJob()`
- `cancelJob()`
- `findJob()`
- `listJobs()`

Lifecycle guardrails:

- Pending jobs can start or cancel.
- Pending/running jobs can complete or fail.
- Completed, failed, and cancelled jobs cannot be cancelled through the service.

## UI Added

Page: `AI Orchestrator`

The user can:

- View AI jobs.
- Create a manual AI job.
- Link a job to a Strategy V2 version.
- Link a job to an experiment.
- View input, output, and error details.
- Cancel a pending job.

## Architecture Decisions

- No real AI client or API call was introduced.
- AI jobs use the SQLite repository path, consistent with current V2 storage.
- API payloads support snake_case and camelCase aliases for frontend/backend compatibility.
- `input_json` and `output_json` are normalized to arrays in service/controller output while stored as JSON text.
- Strategy V2 and experiment links are stored as optional ids only; no trading/backtest logic reads these jobs.

## Test Results

Syntax:

- `php -l api.php` passed.
- `php -l index.php` passed.
- `php -l database/migrations/014_ai_jobs.php` passed.
- `php -l lib/AIJobService.php` passed.
- `php -l lib/AIJobs/AIJobApiController.php` passed.
- `php -l lib/AIJobs/AIJobRecord.php` passed.
- `php -l lib/AIJobs/AIJobStatus.php` passed.
- `php -l lib/AIJobs/AIJobType.php` passed.
- `php -l lib/Repositories/Contracts/AIJobRepositoryInterface.php` passed.
- `php -l lib/Repositories/Sqlite/SQLiteAIJobRepository.php` passed.
- `php -l tests/ai_job_api_smoke.php` passed.
- `php -l tests/ai_job_ui_contract_smoke.php` passed.
- `php -l tests/run.php` passed.
- `node --check assets/app.js` passed.

Smoke tests:

- `php tests/ai_job_api_smoke.php` passed.
- `php tests/ai_job_ui_contract_smoke.php` passed.

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
