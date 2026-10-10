# Sprint 017 Report - One Click Research Flow Mock

Date: 2026-07-01

## Objective

Create a safe one-click mock research flow from the AI Orchestrator page.

The flow creates or reuses a `Running` experiment, creates a `GenerateStrategyIdea` AI job, runs the mock worker once for that job, converts the mock AI output into a Strategy V2 Draft, and links the created records.

This sprint does not add real AI API calls, an optimizer, auto trading, live trading, trading logic changes, or backtest calculation changes.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/ResearchMockRunOnceService.php` | Orchestrates the one-click mock research flow across experiments, AI jobs, worker output, Strategy V2 draft creation, and experiment item linking. |
| `tests/research_mock_run_once_smoke.php` | Smoke-tests experiment creation/reuse, AI job completion, Strategy V2 draft creation, provenance links, experiment item links, and safety flags. |
| `Sprint_017_Report.md` | Documents Sprint 017 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `lib/AIJobs/AIJobWorker.php` | Added `runJob()` and shared processing helper. | Lets the research flow run the newly created job without consuming an unrelated pending job. |
| `lib/AIJobs/AIJobStrategyDraftService.php` | Added `source_experiment_id` to created draft settings. | Keeps the opened Strategy V2 Draft traceable to the linked experiment. |
| `api.php` | Added `research_mock_run_once` route and factory. | Exposes the one-click mock research flow as an API action. |
| `index.php` | Added `Chạy nghiên cứu thử` button in AI Orchestrator. | Gives users a single UI action for the mock research flow. |
| `assets/app.js` | Added research-running state, API call, AI job selection, experiment state update, Strategy V2 response handling, and navigation to the draft. | Connects the UI button to the backend flow and opens the created draft. |
| `tests/ai_job_ui_contract_smoke.php` | Added UI/API contract checks for the research button/action. | Protects the new UI surface. |
| `tests/run.php` | Added the research mock smoke test to the AI Orchestrator smoke bundle. | Ensures the main suite validates Sprint 017 behavior. |

## API Action Added

| Action | Purpose |
| --- | --- |
| `research_mock_run_once` | Runs the safe one-click mock research flow and returns the experiment, AI job, Strategy V2 Draft, and linked experiment item. |

## Flow Behavior

1. Finds the latest `Running` experiment.
2. If none exists, creates a `Mock research run` experiment and starts it.
3. Creates a `GenerateStrategyIdea` AI job linked to the experiment.
4. Runs the mock worker only for the created AI job.
5. Converts completed mock output into a Strategy V2 `Draft`.
6. Links the strategy draft back into AI job `output_json`.
7. Adds a `StrategyV2Draft` experiment item with `Pending` status.

## UI Behavior

On `AI Orchestrator`:

- New button: `Chạy nghiên cứu thử`.
- After click, the app selects the created AI job.
- The linked experiment is selected in experiment state.
- The app opens the created Strategy V2 Draft.
- The draft detail shows source AI job and source experiment IDs when available.

## Test Results

Syntax:

- `php -l lib/ResearchMockRunOnceService.php` passed.
- `php -l lib/AIJobs/AIJobWorker.php` passed.
- `php -l lib/AIJobs/AIJobStrategyDraftService.php` passed.
- `php -l api.php` passed.
- `php -l index.php` passed.
- `php -l tests/research_mock_run_once_smoke.php` passed.
- `php -l tests/ai_job_ui_contract_smoke.php` passed.
- `node --check assets/app.js` passed.

Smoke tests:

- `php tests/research_mock_run_once_smoke.php` passed.
- `php tests/ai_worker_smoke.php` passed.
- `php tests/ai_job_strategy_draft_smoke.php` passed.
- `php tests/ai_job_ui_contract_smoke.php` passed.

Main suite:

- `php tests/run.php` passed.
- Result: `Passed 11 tests.`

## Scope Guardrails Confirmed

- No real AI API call added.
- No optimizer added.
- No auto trading added.
- No automatic live trading added.
- No trading logic changed.
- No backtest calculation changed.
- Created experiment items remain `Pending` until manual testing/review.
