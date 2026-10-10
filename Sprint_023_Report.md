# Sprint 023 Report - Ollama Local Provider

Date: 2026-07-02

## Objective

Add optional Ollama local provider support behind the LLM Gateway while keeping Mock as the default provider and avoiding cloud/API-key behavior.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/AI/Providers/OllamaProvider.php` | Local Ollama Chat provider adapter for the LLM Gateway. |
| `lib/AI/Gateway/OllamaProviderStatus.php` | Safe diagnostic payload builder for Ollama status. |
| `docs/Ollama_Local_Setup.md` | XAMPP setup instructions for local Ollama prompt testing. |
| `tests/ollama_provider_smoke.php` | Offline smoke coverage for disabled/default, fake successful local call, local failure, and diagnostics. |
| `Sprint_023_Report.md` | Sprint implementation and validation report. |

## Files Modified

| File | Change |
| --- | --- |
| `config/ai_providers.php` | Registered `ollama` as implemented, disabled by default, endpoint `http://localhost:11434`, model `llama3.1:8b`. |
| `lib/AI/Gateway/ProviderFactory.php` | Creates `OllamaProvider` for provider key `ollama`. |
| `lib/AI/Gateway/LLMGatewayApiController.php` | Allows Prompt AI test calls to request `ollama` and returns a clear setup error when unavailable. |
| `api.php` | Adds `ollama_provider_status` action. |
| `index.php` | Adds `Ollama Local` to the Prompt AI provider selector. |
| `assets/app.js` | Adds Ollama provider selection and labeling. |
| `.env.example` | Adds optional `OLLAMA_ENDPOINT` and `OLLAMA_MODEL_DEFAULT`. |
| `tests/run.php` | Adds Ollama provider smoke test to the main suite. |
| `tests/llm_provider_framework_smoke.php` | Updates provider expectations now that Ollama is implemented. |
| `tests/llm_gateway_core_smoke.php` | Covers disabled Ollama normalized failure through `llm_gateway_test_call`. |
| `tests/prompt_template_ui_contract_smoke.php` | Covers Ollama selector/label contract. |

## Behavior

- Provider key: `ollama`
- Default endpoint: `http://localhost:11434`
- Default model: `llama3.1:8b`
- Disabled by default
- No API key required
- Mock remains default
- Prompt AI can test an active prompt through Ollama when locally enabled
- Status API: `api.php?action=ollama_provider_status`

## Safety Guardrails

- Ollama provider accepts only local endpoints: `localhost`, `127.*`, or `::1`.
- No API key is used or exposed.
- No cloud call is made by this connector.
- Automated tests use fake injected transport, not a real Ollama daemon.
- No optimizer loop, live trading, auto trading, trading logic, or backtest calculation was changed.

## Validation Results

Syntax checks passed:

- `php -l lib/AI/Providers/OllamaProvider.php`
- `php -l lib/AI/Gateway/OllamaProviderStatus.php`
- `php -l lib/AI/Gateway/ProviderFactory.php`
- `php -l lib/AI/Gateway/LLMGatewayApiController.php`
- `php -l api.php`
- `php -l tests/ollama_provider_smoke.php`

Focused smoke tests passed:

- `php tests/ollama_provider_smoke.php`
- `php tests/llm_provider_framework_smoke.php`
- `php tests/llm_gateway_core_smoke.php`
- `php tests/prompt_template_ui_contract_smoke.php`
- `php tests/openai_chat_provider_smoke.php`
- `php tests/openai_local_setup_smoke.php`

Main suite:

- `php tests/run.php`
- Result: `Passed 17 tests.`
