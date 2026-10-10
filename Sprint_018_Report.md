# Sprint 018 Report - LLM Provider Framework

Date: 2026-07-01

## Objective

Implement the first LLM provider framework slice from `DOC-004_LLM_Gateway_Architecture.md`.

This sprint adds provider registration, factory creation, routing, and a deterministic mock provider only. It does not connect OpenAI, Codex, Claude, Gemini, Ollama, OpenRouter, SDKs, HTTP clients, credentials, or network calls.

## Files Created

| File | Purpose |
| --- | --- |
| `config/ai_providers.php` | Defines supported provider keys, mock defaults, placeholder provider config, and mock routing policy. |
| `lib/AI/Gateway/ProviderInterface.php` | Provider-neutral contract for identity, health, capabilities, model alias resolution, and completion. |
| `lib/AI/Gateway/ProviderFactory.php` | Creates provider adapters from config and exposes supported provider keys. |
| `lib/AI/Gateway/ProviderRegistry.php` | Registers provider adapters and exposes lookup, health, enabled, and implemented provider lists. |
| `lib/AI/Gateway/ProviderRouter.php` | Routes provider-neutral requests to enabled implemented providers, defaulting to `mock`. |
| `lib/AI/Providers/MockProvider.php` | Deterministic no-network provider for tests and local gateway development. |
| `lib/AI/Providers/PlaceholderProvider.php` | Inert placeholder adapter for non-implemented real providers. |
| `tests/llm_provider_framework_smoke.php` | Gateway provider framework smoke test. |
| `Sprint_018_Report.md` | Documents Sprint 018 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `tests/run.php` | Added LLM provider framework smoke test to the main suite. | Ensures the mock provider and provider framework remain validated. |

## Providers Supported By Configuration

| Provider key | Status |
| --- | --- |
| `mock` | Implemented, enabled, deterministic, no network. |
| `openai_chat` | Placeholder only, disabled. |
| `openai_codex` | Placeholder only, disabled. |
| `claude` | Placeholder only, disabled. |
| `gemini` | Placeholder only, disabled. |
| `ollama` | Placeholder only, disabled. |
| `openrouter` | Placeholder only, disabled. |

## Mock Provider Behavior

- Returns deterministic responses for identical requests.
- Supports plain text, JSON object, JSON array, schema-bound, markdown report, and code review report modes.
- Estimates input and output tokens deterministically.
- Reports zero USD cost.
- Records `network: false` in metadata and capabilities.
- Marks trading-related structured output as proposal-only.

## Safety Guardrails

- No real provider implementation added.
- No API keys or secrets added.
- No HTTP clients or SDKs added.
- No network calls added.
- Placeholder providers return `configuration_error`.
- Router blocks disabled or unimplemented providers.
- Default route is `mock`.

## Validation Results

Syntax checks:

- `php -l config/ai_providers.php` passed.
- `php -l lib/AI/Gateway/ProviderInterface.php` passed.
- `php -l lib/AI/Gateway/ProviderFactory.php` passed.
- `php -l lib/AI/Gateway/ProviderRegistry.php` passed.
- `php -l lib/AI/Gateway/ProviderRouter.php` passed.
- `php -l lib/AI/Providers/MockProvider.php` passed.
- `php -l lib/AI/Providers/PlaceholderProvider.php` passed.
- `php -l tests/llm_provider_framework_smoke.php` passed.

Gateway smoke test:

- `php tests/llm_provider_framework_smoke.php` passed.

Main suite:

- `php tests/run.php` passed.
- Result: `Passed 12 tests.`
