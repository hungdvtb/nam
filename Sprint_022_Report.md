# Sprint 022 Report - OpenAI Provider Connector

Date: 2026-07-02

## Objective

Implement an OpenAI Chat provider connector behind the existing LLM Gateway while keeping Mock as the default provider.

Reference used: OpenAI Chat Completions API reference, `https://platform.openai.com/docs/api-reference/chat/create`.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/AI/Providers/OpenAIChatProvider.php` | OpenAI Chat provider adapter implementing `ProviderInterface`. |
| `tests/openai_chat_provider_smoke.php` | Validates OpenAI provider disabled-by-default behavior, config validation, health checks, and secret redaction. |
| `Sprint_022_Report.md` | Documents Sprint 022 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `lib/AI/Gateway/ProviderFactory.php` | Registers `OpenAIChatProvider` for `openai_chat`. | Enables the provider behind the existing gateway boundary. |
| `config/ai_providers.php` | Marks `openai_chat` implemented but disabled by default, adds endpoint, env names, timeout, and capabilities. | Keeps Mock as default and stores no API key in source. |
| `lib/AI/Gateway/LLMGatewayApiController.php` | Allows `llm_gateway_test_call` to choose `mock` or `openai_chat`; returns clearer OpenAI setup errors. | Supports Prompt AI provider selector without exposing secrets. |
| `index.php` | Adds provider selector to Prompt AI test panel. | Lets users select Mock or OpenAI Chat for prompt testing. |
| `assets/app.js` | Tracks selected provider, updates button/summary text, and sends `preferred_provider`. | Connects Prompt AI UI to selected gateway provider. |
| `tests/llm_provider_framework_smoke.php` | Updates expectations so `openai_chat` is implemented and disabled, while other cloud providers remain placeholders. | Protects provider registry behavior. |
| `tests/llm_gateway_core_smoke.php` | Adds unavailable OpenAI test-call coverage. | Verifies clear normalized configuration failure. |
| `tests/prompt_template_ui_contract_smoke.php` | Adds provider selector contract checks. | Protects Prompt AI UI provider selection. |
| `tests/run.php` | Adds OpenAI Chat provider smoke test to the main suite. | Keeps Sprint 022 covered by regression tests. |

## Provider Behavior

`OpenAIChatProvider` supports:

- API key from `OPENAI_API_KEY` or config injection.
- Default model from `OPENAI_MODEL_DEFAULT` or config injection.
- Disabled-by-default config.
- Health statuses: `disabled`, `missing_api_key`, `missing_model`, `ready`.
- Chat Completions request payload with normalized messages.
- Backend-only Authorization header.
- Timeout handling.
- Usage token normalization.
- Error categories: `configuration_error`, `authentication_error`, `rate_limit`, `timeout`, `network_error`, `provider_error`, `invalid_response`, `content_blocked`.

## Safety Guardrails

- `mock` remains the default provider.
- `openai_chat` is implemented but disabled by default.
- No API key is stored in source code.
- No API key is exposed to frontend JavaScript or API responses.
- No Codex provider was added.
- No auto trading, optimizer loop, live trading, trading logic, or backtest calculation code was changed.

## Validation Results

Syntax checks:

- `php -l lib/AI/Providers/OpenAIChatProvider.php` passed.
- `php -l lib/AI/Gateway/ProviderFactory.php` passed.
- `php -l lib/AI/Gateway/LLMGatewayApiController.php` passed.
- `php -l config/ai_providers.php` passed.
- `php -l tests/openai_chat_provider_smoke.php` passed.
- `php -l tests/llm_provider_framework_smoke.php` passed.
- `php -l tests/llm_gateway_core_smoke.php` passed.
- `php -l index.php` passed.
- `node --check assets/app.js` passed.

Focused smoke tests:

- `php tests/openai_chat_provider_smoke.php` passed.
- `php tests/llm_provider_framework_smoke.php` passed.
- `php tests/llm_gateway_core_smoke.php` passed.
- `php tests/prompt_template_ui_contract_smoke.php` passed.

Main suite:

- `php tests/run.php` passed.
- Result: `Passed 15 tests.`
