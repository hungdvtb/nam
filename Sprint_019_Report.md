# Sprint 019 Report - Prompt Manager Foundation

Date: 2026-07-01

## Objective

Implement the Prompt Manager foundation for AI Trade V2 based on `DOC-004_LLM_Gateway_Architecture.md` and the Sprint 018 provider framework.

This sprint adds prompt template storage, rendering, API actions, and a Vietnamese Prompt AI management page. It does not call real AI providers and does not connect any provider SDK or network client.

## Files Created

| File | Purpose |
| --- | --- |
| `database/migrations/015_prompt_templates.php` | Creates `ai_prompt_templates`, indexes, status checks, unique key/version constraint, and default template seeds. |
| `lib/AI/Prompts/PromptTemplateRecord.php` | Normalizes and validates prompt template records and statuses. |
| `lib/AI/Prompts/PromptRenderer.php` | Renders `{{variable}}` placeholders and fails on missing required variables. |
| `lib/AI/Prompts/PromptManager.php` | Coordinates template creation, updates, activation, archival, default seeding, active lookup, and rendering. |
| `lib/AI/Prompts/PromptTemplateApiController.php` | Handles Prompt Manager API actions. |
| `lib/Repositories/Contracts/PromptTemplateRepositoryInterface.php` | Defines the provider-neutral prompt template repository contract. |
| `lib/Repositories/Sqlite/SQLitePromptTemplateRepository.php` | SQLite implementation for prompt template persistence. |
| `lib/Repositories/Json/JsonPromptTemplateRepository.php` | JSON implementation for local fallback and tests. |
| `tests/prompt_manager_smoke.php` | Tests create, render, missing variable failure, active lookup, API actions, and JSON repository rendering. |
| `tests/prompt_template_ui_contract_smoke.php` | Protects the Prompt AI page and client-side API contract. |
| `Sprint_019_Report.md` | Documents Sprint 019 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `api.php` | Added Prompt Manager requires, `prompt_template_*` routing, SQLite setup, and JSON fallback. | Exposes prompt template management through API actions. |
| `index.php` | Added `Prompt AI` navigation tab, page panel, template list, template form, activate/archive buttons, and asset cache bump. | Gives users a UI to manage prompt templates. |
| `assets/app.js` | Added prompt template state, render/load/save/activate/archive handlers, tab lazy-loading, and event bindings. | Connects the Prompt AI page to the backend API. |
| `tests/run.php` | Added Prompt Manager smoke tests to the main suite. | Keeps Sprint 019 covered by regression tests. |

## Database

Created table: `ai_prompt_templates`

Fields:

- `id`
- `template_key`
- `version`
- `name`
- `description`
- `owner_module`
- `status`
- `system_prompt`
- `developer_prompt`
- `user_prompt_template`
- `required_variables_json`
- `optional_variables_json`
- `output_format`
- `parser_mode`
- `safety_level`
- `created_at`
- `updated_at`
- `notes`

Allowed statuses:

- `Draft`
- `Review`
- `Approved`
- `Active`
- `Deprecated`
- `Archived`

## Default Templates Seeded

| Template key | Owner |
| --- | --- |
| `strategy.idea.generate` | `StrategyAI` |
| `strategy.version.improve` | `StrategyAI` |
| `backtest.summary` | `AnalysisAI` |
| `experiment.compare` | `OptimizerAI` |

## API Actions Added

| Action | Purpose |
| --- | --- |
| `prompt_template_list` | List prompt templates with optional filters. |
| `prompt_template_find` | Find by id, or active template by `template_key`. |
| `prompt_template_create` | Create a new template version. |
| `prompt_template_update` | Update an existing template. |
| `prompt_template_activate` | Mark one template version active and deprecate other active versions for the same key. |
| `prompt_template_archive` | Archive a template. |

## UI

Added Vietnamese page: `Prompt AI`

User can:

- View prompt templates.
- Create a template.
- Edit a template.
- Activate a template.
- Archive a template.

## Safety Guardrails

- No real AI provider calls added.
- No network calls added.
- No API keys or provider credentials added.
- Prompt rendering is local string interpolation only.
- Missing required variables fail before any future provider call.
- Default prompts keep trading output proposal-only.

## Validation Results

Syntax and parser checks:

- `php -l database/migrations/015_prompt_templates.php` passed.
- `php -l lib/AI/Prompts/PromptTemplateRecord.php` passed.
- `php -l lib/AI/Prompts/PromptRenderer.php` passed.
- `php -l lib/AI/Prompts/PromptManager.php` passed.
- `php -l lib/AI/Prompts/PromptTemplateApiController.php` passed.
- `php -l lib/Repositories/Contracts/PromptTemplateRepositoryInterface.php` passed.
- `php -l lib/Repositories/Sqlite/SQLitePromptTemplateRepository.php` passed.
- `php -l lib/Repositories/Json/JsonPromptTemplateRepository.php` passed.
- `php -l tests/prompt_manager_smoke.php` passed.
- `php -l tests/prompt_template_ui_contract_smoke.php` passed.
- `php -l api.php` passed.
- `php -l index.php` passed.
- `node --check assets/app.js` passed.

Smoke tests:

- `php tests/prompt_manager_smoke.php` passed.
- `php tests/prompt_template_ui_contract_smoke.php` passed.

Main suite:

- `php tests/run.php` passed.
- Result: `Passed 13 tests.`

SQLite initialization:

- `php scripts/init_sqlite.php` passed.
- Applied pending local migrations: `014_ai_jobs`, `015_prompt_templates`.
- Migration status: no pending migrations.

