# Sprint 012 Report - Strategy Version UI + API

Date: 2026-07-01

## Objective

Expose the Strategy Version Manager through API actions and a Vietnamese UI page.

This sprint does not change trading logic, backtest calculations, legacy strategy editing, or AI behavior.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/StrategyVersions/StrategyVersionApiController.php` | Handles Strategy V2 API actions through `StrategyVersionService`. |
| `tests/strategy_v2_api_smoke.php` | Smoke-tests Strategy V2 API actions against an in-memory SQLite repository. |
| `tests/strategy_v2_ui_contract_smoke.php` | Verifies UI IDs, frontend action references, and legacy strategy UI preservation. |
| `Sprint_012_Report.md` | Documents Sprint 012 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `api.php` | Added Strategy V2 controller/repository dependencies, `strategy_v2_*` routing, and a SQLite-backed service factory. | Exposes Strategy Version Manager actions through API. |
| `lib/StrategyVersionService.php` | Added `findVersion()` for direct repository-backed V2 lookup. | Keeps `strategy_v2_find` accurate without scanning a limited list. |
| `index.php` | Added the `Phiên bản chiến lược` navigation tab and manager page markup. | Gives users a dedicated Vietnamese UI for Strategy V2 records. |
| `assets/app.js` | Added Strategy V2 state, lazy loading, list/detail rendering, JSON form handling, and create/clone/update/approve/production/archive calls. | Connects the new UI page to the API. |
| `assets/styles.css` | Added small badge/grid styles for Strategy V2 status and JSON details. | Keeps the new page consistent with the existing UI. |
| `tests/run.php` | Added Strategy V2 smoke tests to the main runner. | Ensures `php tests/run.php` validates Sprint 012 behavior. |

## Database Changes

No new migration was added.

Sprint 012 uses the existing `002_strategy_versions` migration and `SQLiteStrategyVersionRepository`.

## API Actions Added

| Action | Purpose |
| --- | --- |
| `strategy_v2_list` | List Strategy V2 records with optional filters. |
| `strategy_v2_find` | Find a Strategy V2 record by id. |
| `strategy_v2_create` | Create a Strategy V2 record. |
| `strategy_v2_clone` | Clone an existing Strategy V2 record. |
| `strategy_v2_update` | Update metadata, status, rules, settings, or performance JSON. |
| `strategy_v2_archive` | Archive a Strategy V2 record. |
| `strategy_v2_approve` | Mark a Strategy V2 record as approved/Passed. |
| `strategy_v2_latest` | Return the latest Strategy V2 record. |
| `strategy_v2_production` | Return current Production, or promote a provided id to Production. |

## UI Added

Page: `Phiên bản chiến lược`

The user can:

- View Strategy V2 records.
- Create a version.
- Clone a version.
- View rules/settings/performance/history.
- Approve a version.
- Promote a version to Production.
- Archive a version.

The legacy `Chiến lược` page and its existing JSON-backed version controls remain in place.

## Architecture Decisions

- V2 API actions use `StrategyVersionService` with `SQLiteStrategyVersionRepository`.
- Legacy strategy-version actions continue using the existing JSON-backed service path.
- The new frontend state is separate: `strategyV2Versions` does not replace `strategyVersions`.
- `strategy_v2_production` supports both read and promote behavior so the UI has a dedicated Production action while still exposing current Production lookup.
- Clone omits a duplicate version code from the UI payload so the service can generate a fresh code.

## Test Results

Syntax:

- `php -l api.php` passed.
- `php -l index.php` passed.
- `php -l lib/StrategyVersionService.php` passed.
- `php -l lib/StrategyVersions/StrategyVersionApiController.php` passed.
- `php -l tests/strategy_v2_api_smoke.php` passed.
- `php -l tests/strategy_v2_ui_contract_smoke.php` passed.
- `node --check assets/app.js` passed.

Smoke tests:

- `php tests/strategy_v2_api_smoke.php` passed.
- `php tests/strategy_v2_ui_contract_smoke.php` passed.

Main suite:

- `php tests/run.php` passed.
- Result: `Passed 10 tests.`

## Scope Guardrails Confirmed

- No trading engine logic changed.
- No backtest calculation changed.
- No AI implementation added.
- Existing legacy strategy UI remains present and covered by UI contract smoke checks.
