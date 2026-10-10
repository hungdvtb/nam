# Sprint 021 Report - AI Job Uses LLM Gateway

Date: 2026-07-01

## Objective

Replace hardcoded AI job mock output with deterministic `LLMGateway` mock calls while preserving AI Trade V2 safety boundaries.

No real AI API calls, network calls, trading logic changes, or backtest calculation changes were added.

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `lib/AIJobs/AIJobWorker.php` | Replaced the hardcoded job-type output switch with `LLMGateway` calls. Added job type to prompt mapping and normalized output envelope. | AI jobs now use Prompt Manager, Provider Router, Mock Provider, and Response Parser. |
| `lib/AI/Providers/MockProvider.php` | Added deterministic prompt-template-specific parsed payloads for strategy idea, strategy improvement, backtest summary, and experiment comparison prompts. | Keeps mock provider first-class while giving AI jobs useful parsed mock output. |
| `lib/AIJobs/AIJobStrategyDraftService.php` | Reads strategy rules and descriptions from top-level legacy output, `parsed_output`, or `llm_response.parsed`. | Keeps existing strategy draft and research mock workflows compatible with gateway-shaped job output. |
| `lib/ResearchMockRunOnceService.php` | Added prompt variables required by `strategy.idea.generate` to the created job `input_json`. | Makes job input JSON directly usable as prompt variables. |
| `api.php` | Injects `LLMGateway` into `AIJobWorker` for AI job and research mock API paths. | Ensures web/API worker execution uses the gateway. |
| `scripts/run_ai_worker.php` | Builds a local `LLMGateway` from SQLite prompt templates and injects it into the worker. | Ensures CLI worker execution uses the gateway. |
| `config/ai_providers.php` | Added explicit `optimizer_compare` mock route. | Gives `EvaluateExperiment` an explicit deterministic mock route. |
| `assets/app.js` | Allows the AI job draft button to recognize rules inside gateway parsed output. | Keeps UI workflow usable with gateway-shaped output. |
| `tests/ai_worker_smoke.php` | Updated worker smoke coverage to assert each job type stores gateway metadata and full `llm_response`. | Validates Sprint 021 behavior. |

## Job Type Mapping

| AI job type | Prompt template |
| --- | --- |
| `GenerateStrategyIdea` | `strategy.idea.generate` |
| `ImproveStrategy` | `strategy.version.improve` |
| `AnalyzeBacktest` | `backtest.summary` |
| `EvaluateExperiment` | `experiment.compare` |

## Output JSON Structure

Completed AI jobs now store:

- `prompt_template_key`
- `provider`
- `model`
- `model_name`
- `model_alias`
- `usage`
- `parsed_output`
- `llm_response`

If the gateway returns a failed normalized response, the worker throws and the job is marked `Failed` through `AIJobService::failJob`.

## Safety Guardrails

- Provider is forced to `mock` in AI job worker calls.
- Mock provider remains deterministic and no-network.
- Prompt rendering still fails before provider calls when required variables are missing.
- Failed gateway responses mark jobs `Failed`.
- Research mock flow still creates proposal-only Strategy V2 drafts for manual review.
- No real provider SDKs, credentials, HTTP clients, or external calls were added.
- No trading execution, trading rules engine, or backtest calculation code was changed.

## Validation Results

Syntax checks:

- `php -l lib/AIJobs/AIJobWorker.php` passed.
- `php -l lib/AIJobs/AIJobStrategyDraftService.php` passed.
- `php -l lib/AI/Providers/MockProvider.php` passed.
- `php -l lib/ResearchMockRunOnceService.php` passed.
- `php -l api.php` passed.
- `php -l scripts/run_ai_worker.php` passed.
- `php -l tests/ai_worker_smoke.php` passed.
- `node --check assets/app.js` passed.

Focused smoke tests:

- `php tests/ai_worker_smoke.php` passed.
- `php tests/ai_job_strategy_draft_smoke.php` passed.
- `php tests/research_mock_run_once_smoke.php` passed.
- `php tests/llm_gateway_core_smoke.php` passed.
- `php tests/llm_provider_framework_smoke.php` passed.

Main suite:

- `php tests/run.php` passed.
- Result: `Passed 14 tests.`
