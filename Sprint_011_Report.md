# Sprint 011 Report - KPI Profile Manager

Date: 2026-07-01

## Objective

Create KPI Profile Manager so Evaluation Engine targets are configurable instead of only hardcoded.

This sprint does not change trading logic, backtest calculations, AI behavior, optimizer behavior, or legacy backtest behavior.

## Files Created

| File | Purpose |
| --- | --- |
| `database/migrations/011_kpi_profiles.php` | Creates the `kpi_profiles` SQLite table and indexes. |
| `lib/KPIProfiles/KPIProfileRecord.php` | Normalizes and validates KPI profile records. |
| `lib/KPIProfiles/KPIProfileApiController.php` | Handles KPI profile API actions. |
| `lib/Repositories/Contracts/KPIProfileRepositoryInterface.php` | Defines the KPI profile repository contract. |
| `lib/Repositories/Sqlite/SQLiteKPIProfileRepository.php` | SQLite-backed KPI profile repository. |
| `lib/Repositories/Json/JsonKPIProfileRepository.php` | JSON-compatible KPI profile repository. |
| `lib/KPIProfileService.php` | Service layer for creating, updating, listing, defaulting, and deleting KPI profiles. |
| `tests/kpi_profile_repository_smoke.php` | Smoke-tests SQLite/JSON repositories and EvaluationService default-profile integration. |
| `tests/kpi_profile_api_smoke.php` | Smoke-tests required KPI profile API actions. |
| `tests/kpi_profile_ui_contract_smoke.php` | Verifies UI contract and frontend API action references. |
| `Sprint_011_Report.md` | Documents Sprint 011 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `lib/EvaluationService.php` | Added optional `KPIProfileService` dependency and default-profile lookup with hardcoded fallback. | EvaluationService now uses configured KPI targets when available. |
| `lib/Backtests/BacktestV2StorageAdapter.php` | Passes a SQLite-backed KPIProfileService into EvaluationService. | Automatic V2 evaluations use the current default KPI profile. |
| `api.php` | Added KPI profile dependencies, API routing, service factory, V2 evaluation KPI integration, and import mapping for `kpi_profiles`. | Exposes KPI profile management while preserving existing API actions. |
| `lib/Storage/JsonCompatibilityAdapter.php` | Added `kpi_profiles` dataset mapping. | Keeps JSON compatibility available for KPI profile storage. |
| `index.php` | Added `Cấu hình KPI` navigation tab and profile manager UI markup. | Gives users a UI to manage KPI targets. |
| `assets/app.js` | Added KPI profile state, rendering, lazy loading, create/update/default/delete API calls, and form handling. | Connects UI to KPI profile APIs. |
| `assets/styles.css` | Added KPI profile list class to existing list styling groups. | Keeps the new UI consistent with the current interface. |

## Database Changes

Migration added:

`011_kpi_profiles`

Table added:

`kpi_profiles`

Fields:

- `id`
- `name`
- `description`
- `is_default`
- `win_rate_min`
- `average_rr_min`
- `profit_factor_min`
- `max_drawdown_max`
- `total_trades_min`
- `created_at`
- `updated_at`
- `notes`

Indexes:

- `is_default`
- `updated_at`

Migration result:

- `php scripts/init_sqlite.php` applied `011_kpi_profiles`.
- No pending migrations remain.

## API Actions Added

| Action | Purpose |
| --- | --- |
| `kpi_profile_list` | List KPI profiles and return the default profile. |
| `kpi_profile_create` | Create a KPI profile. |
| `kpi_profile_update` | Update profile metadata and target values. |
| `kpi_profile_set_default` | Mark one profile as the default and clear previous defaults. |
| `kpi_profile_delete` | Delete a KPI profile. |

## UI Added

Page: `Cấu hình KPI`

The user can:

- View KPI profiles.
- Create a profile.
- Edit KPI targets.
- Set the default profile.
- Delete a profile.

Targets exposed:

- Win rate minimum.
- Average RR minimum.
- Profit factor minimum.
- Max drawdown maximum.
- Total trades minimum.

## Architecture Decisions

- KPI profiles follow the repository/service pattern used by prior V2 modules.
- SQLite is the runtime storage layer.
- JSON compatibility is available through `JsonCompatibilityAdapter`.
- `EvaluationService` accepts `KPIProfileService` optionally.
- If no default profile exists, EvaluationService falls back to the existing hardcoded defaults.
- If KPI profile storage is unavailable, EvaluationService also falls back to hardcoded defaults.
- Existing tests and callers that construct `EvaluationService` without KPI profiles still work.

## Test Results

Migration:

- `php scripts/init_sqlite.php` applied `011_kpi_profiles`.

Syntax:

- `php -l api.php` passed.
- `php -l index.php` passed.
- `node --check assets/app.js` passed.
- `php -l lib/KPIProfileService.php` passed.
- `php -l lib/EvaluationService.php` passed.
- `php -l lib/Backtests/BacktestV2StorageAdapter.php` passed.
- `php -l database/migrations/011_kpi_profiles.php` passed.
- New KPI profile PHP files passed syntax checks.

Smoke tests:

- `php tests/kpi_profile_repository_smoke.php` passed.
- `php tests/kpi_profile_api_smoke.php` passed.
- `php tests/kpi_profile_ui_contract_smoke.php` passed.
- `php tests/evaluation_repository_smoke.php` passed.
- `php tests/backtest_v2_storage_adapter_smoke.php` passed.
- `php tests/backtest_v2_api_smoke.php` passed.
- `php tests/experiment_auto_attach_smoke.php` passed.

Existing suite:

- `php tests/run.php` passed 9 tests.

## Backward Compatibility

- Existing hardcoded KPI behavior remains the fallback.
- Existing backtest calculations were not modified.
- Existing trading logic was not modified.
- Existing UI pages remain available.
- Existing EvaluationService constructor calls remain valid.
- No AI or optimizer behavior was added.

## Future Integration Points

- Add KPI profile selection per experiment.
- Add KPI profile selection per strategy version.
- Store the profile ID on evaluation results.
- Show which KPI profile produced each evaluation.
- Add validation presets for prop firm, personal account, and research-only profiles.

## Known Limitations

- Evaluation results store KPI targets but not the source KPI profile ID yet.
- UI has no delete confirmation modal.
- No browser automation visual test exists yet.
- KPI profiles are global defaults, not scoped per symbol/timeframe/strategy yet.
