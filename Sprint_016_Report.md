# Sprint 016 Report - AI Output to Strategy Draft

Date: 2026-07-01

## Objective

Allow completed `GenerateStrategyIdea` and `ImproveStrategy` AI jobs to be converted into Strategy V2 Draft versions.

This sprint does not add real AI API calls, an optimizer, auto trading, trading logic changes, or backtest calculation changes.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/AIJobs/AIJobStrategyDraftService.php` | Converts eligible completed AI job output into a new Strategy V2 Draft and links the created version back to the AI job output. |
| `tests/ai_job_strategy_draft_smoke.php` | Smoke-tests GenerateStrategyIdea conversion, ImproveStrategy conversion, non-completed rejection, invalid-output rejection, idempotent linked output, and no overwrite of existing versions. |
| `Sprint_016_Report.md` | Documents Sprint 016 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `lib/AIJobService.php` | Added `updateJobOutput()`. | Allows the converter to link `strategy_version_id` back into `output_json` without changing lifecycle status. |
| `lib/AIJobs/AIJobApiController.php` | Added optional `AIJobStrategyDraftService` dependency and `ai_job_create_strategy_draft` action. | Exposes draft conversion through the AI job API surface. |
| `lib/AIJobs/AIJobWorker.php` | Adjusted mock `GenerateStrategyIdea` rules to use canonical Strategy V2 rule keys. | Makes generated mock strategy output cleaner for Strategy V2 draft creation. |
| `api.php` | Added shared AI job controller factory with Strategy V2 service injection. | Lets AI job APIs create Strategy V2 drafts through the existing SQLite-backed service. |
| `index.php` | Added `Tạo bản nháp chiến lược` button to AI Orchestrator job detail. | Lets users convert eligible completed AI jobs from the UI. |
| `assets/app.js` | Added draft-button gating, `ai_job_create_strategy_draft` call, AI job state update, Strategy V2 response handling, and navigation to the created draft. | Connects completed strategy-like AI output to the Strategy V2 page. |
| `tests/ai_job_ui_contract_smoke.php` | Added UI/API contract checks for draft creation. | Protects the new button and frontend action reference. |
| `tests/run.php` | Added the strategy-draft smoke to the AI Orchestrator smoke bundle. | Ensures `php tests/run.php` validates Sprint 016 behavior. |

## API Action Added

| Action | Purpose |
| --- | --- |
| `ai_job_create_strategy_draft` | Reads a completed AI job, validates strategy-like output, creates a Strategy V2 Draft, and links the new `strategy_version_id` back into AI job `output_json`. |

## Conversion Rules

Eligible job types:

- `GenerateStrategyIdea`
- `ImproveStrategy`

Required job state:

- `Completed`

Accepted rules fields:

- `GenerateStrategyIdea`: `rules_json`, `rulesJson`, or `rules`
- `ImproveStrategy`: `changed_rules_json`, `changedRulesJson`, `rules_json`, or `rulesJson`

Created Strategy V2 records:

- Status: `Draft`
- Created by: `ai`
- Rules: normalized through the existing Strategy V2 service
- Description: filled from AI explanation/reason plus source AI job id
- Settings: stores source metadata for traceability

The AI job `output_json` is updated with:

- `strategy_version_id`
- `strategyVersionId`
- `strategy_draft_created_at`

If the AI job output already links to an existing Strategy V2 version, the API returns that version instead of creating a duplicate.

## UI Added

In `AI Orchestrator` job detail:

- Shows `Tạo bản nháp chiến lược` only when the selected job is `Completed` and has strategy-like output.
- Clicking the button creates or opens the linked Strategy V2 Draft.
- The UI switches to `Phiên bản chiến lược` and selects the created draft.

## Test Results

Syntax:

- `php -l lib/AIJobs/AIJobStrategyDraftService.php` passed.
- `php -l lib/AIJobService.php` passed.
- `php -l lib/AIJobs/AIJobApiController.php` passed.
- `php -l lib/AIJobs/AIJobWorker.php` passed.
- `php -l api.php` passed.
- `php -l index.php` passed.
- `php -l tests/ai_job_strategy_draft_smoke.php` passed.
- `php -l tests/ai_job_ui_contract_smoke.php` passed.
- `php -l tests/run.php` passed.
- `node --check assets/app.js` passed.

Smoke tests:

- `php tests/ai_job_strategy_draft_smoke.php` passed.
- `php tests/ai_worker_smoke.php` passed.
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
