# Sprint 020 Report - LLM Gateway Core

Date: 2026-07-01

## Objective

Implement the first LLM Gateway core slice for AI Trade V2 using the existing Provider Router, Mock Provider, Prompt Manager, and Prompt Renderer.

This sprint keeps all AI execution local and deterministic. No real provider calls, network clients, trading logic changes, or backtest calculation changes were added.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/AI/Contracts/LLMRequest.php` | Provider-neutral request contract for task type, agent role, prompt template key, variables, output format, routing preferences, and metadata. |
| `lib/AI/Contracts/LLMResponse.php` | Normalized gateway response contract for text, parsed output, provider identity, finish reason, usage, cost, prompt metadata, warnings, and errors. |
| `lib/AI/Contracts/UsageRecord.php` | Basic normalized usage structure with request id, provider, model alias/name, tokens, timestamps, and latency. |
| `lib/AI/Gateway/ResponseParser.php` | Parses plain text, JSON object, JSON array, schema-bound, markdown report, and code review report modes. |
| `lib/AI/Gateway/LLMGateway.php` | Orchestrates prompt rendering, provider routing, mock provider calls, response parsing, usage normalization, and normalized failures. |
| `lib/AI/Gateway/LLMGatewayApiController.php` | Adds API wrapper for mock prompt test calls. |
| `tests/llm_gateway_core_smoke.php` | Covers render plus mock provider call, missing variable failure, JSON parsing, provider error normalization, and API wrapper behavior. |
| `Sprint_020_Report.md` | Documents Sprint 020 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `lib/AI/Providers/MockProvider.php` | Added config-driven forced error mode. | Enables deterministic provider error testing without network calls. |
| `api.php` | Added `llm_gateway_test_call` routing and LLM Gateway factory helpers. | Exposes the gateway core through the API. |
| `index.php` | Added Prompt AI mock-test panel and bumped asset versions to Sprint 020. | Lets users test an active prompt through Mock AI. |
| `assets/app.js` | Added Prompt AI mock-test state, sample variable generation, API call, and response rendering. | Connects the UI to `llm_gateway_test_call`. |
| `assets/styles.css` | Added scoped JSON output styling for gateway test responses. | Keeps normalized responses readable in the Prompt AI page. |
| `tests/prompt_template_ui_contract_smoke.php` | Added contract checks for Mock AI test UI and API action. | Protects the new Prompt AI gateway test workflow. |
| `tests/run.php` | Added LLM Gateway core smoke test to the main suite. | Keeps Sprint 020 covered by regression tests. |

## Gateway Behavior

`LLMGateway` now:

1. Receives `task_type`, `agent_role`, `prompt_template_key`, `variables`, and `output_format`.
2. Renders the active prompt template through `PromptManager` and `PromptRenderer`.
3. Routes through `ProviderRouter`.
4. Calls `MockProvider`.
5. Parses the provider text with `ResponseParser`.
6. Returns a normalized `LLMResponse`.

## API Action

Added:

| Action | Purpose |
| --- | --- |
| `llm_gateway_test_call` | Runs a selected active prompt through the mock LLM gateway using supplied sample variables. |

The API action returns a top-level success envelope with `llm_response`, allowing the UI to display both successful and normalized failed gateway responses.

## UI

Prompt AI page now includes:

- Button: `Test prompt bằng Mock AI`
- Sample variables JSON editor.
- Normalized gateway response preview.
- Active-prompt guard before running a test.

## Safety Guardrails

- No real AI provider calls.
- No network calls.
- No SDKs or credentials added.
- Mock provider remains deterministic.
- Provider errors are simulated through local mock configuration only.
- No trading logic changed.
- No backtest calculation changed.

## Validation Coverage

Implemented tests cover:

- Render plus mock provider call works.
- Missing prompt variable returns normalized failure.
- JSON response parses into normalized `parsed` output.
- Provider error returns normalized failure.
- UI contract includes Mock AI test controls and API action.
- Main suite includes Sprint 020 smoke coverage.

## Validation Results

Syntax checks:

- `php -l lib/AI/Contracts/LLMRequest.php` passed.
- `php -l lib/AI/Contracts/LLMResponse.php` passed.
- `php -l lib/AI/Contracts/UsageRecord.php` passed.
- `php -l lib/AI/Gateway/ResponseParser.php` passed.
- `php -l lib/AI/Gateway/LLMGateway.php` passed.
- `php -l lib/AI/Gateway/LLMGatewayApiController.php` passed.
- `php -l lib/AI/Providers/MockProvider.php` passed.
- `php -l api.php` passed.
- `php -l index.php` passed.
- `php -l tests/llm_gateway_core_smoke.php` passed.
- `php -l tests/prompt_template_ui_contract_smoke.php` passed.
- `node --check assets/app.js` passed.

Smoke tests:

- `php tests/llm_gateway_core_smoke.php` passed.
- `php tests/prompt_template_ui_contract_smoke.php` passed.
- `php tests/prompt_manager_smoke.php` passed.
- `php tests/llm_provider_framework_smoke.php` passed.

Main suite:

- `php tests/run.php` passed.
- Result: `Passed 14 tests.`
