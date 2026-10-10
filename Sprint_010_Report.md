# Sprint 010 Report - Backtest Result + Evaluation UI

Date: 2026-07-01

## Objective

Create backend API and Vietnamese UI support to view V2 backtest results and KPI evaluations.

This sprint does not change trading logic, backtest calculations, AI behavior, optimizer behavior, or legacy backtest behavior.

## Files Created

| File | Purpose |
| --- | --- |
| `lib/Backtests/BacktestV2ApiController.php` | Read-only API controller for V2 backtest results and KPI evaluations. |
| `tests/backtest_v2_api_smoke.php` | Smoke-tests Sprint 010 API actions against in-memory SQLite repositories. |
| `tests/backtest_v2_ui_contract_smoke.php` | Verifies the UI contract for the new V2 result page and frontend API action references. |
| `Sprint_010_Report.md` | Documents Sprint 010 implementation, validation, and limitations. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `api.php` | Added V2 API dependencies, route handling for `backtest_v2_*` and `evaluation_*`, and SQLite-backed controller factory. | Exposes V2 result/evaluation read APIs without changing existing actions. |
| `index.php` | Added the `Kết quả kiểm thử V2` navigation tab and page markup. | Allows users to inspect stored V2 backtest results and KPI evaluations. |
| `assets/app.js` | Added V2 result state, lazy loading, filter by strategy version, detail rendering, KPI evaluation rendering, reasons/recommendations rendering, and refresh after backtest when the page has been loaded. | Connects the new UI to Sprint 010 APIs while keeping existing screens intact. |
| `assets/styles.css` | Added V2 result list classes to existing list styling groups. | Keeps the new UI consistent with existing panels and lists. |

## API Actions Added

| Action | Purpose |
| --- | --- |
| `backtest_v2_list` | List V2 backtest results with optional filters. |
| `backtest_v2_find` | Find one V2 backtest result by ID. |
| `evaluation_list` | List KPI evaluations with optional filters. |
| `evaluation_find` | Find one evaluation by ID. |
| `evaluation_latest` | Return the latest evaluation for a filter set. |
| `evaluation_best` | Return the highest-scored evaluation for a filter set. |

## UI Added

Page: `Kết quả kiểm thử V2`

The user can view:

- Backtest result list.
- Symbol.
- Timeframe and higher timeframe.
- Strategy version.
- Win rate.
- Average RR.
- Profit factor.
- Max drawdown.
- Total trades.
- Evaluation status.
- KPI score.
- Passed/failed reasons.
- Recommendations.

## Architecture Decisions

- The API is read-only for Sprint 010.
- Runtime storage uses `BacktestResultService` and `EvaluationService`.
- Repositories are SQLite-backed by default:
  - `SQLiteBacktestResultRepository`
  - `SQLiteEvaluationResultRepository`
- The UI lazy-loads V2 results only when the tab opens.
- The page uses existing vanilla JavaScript and static tab architecture.
- Evaluation detail uses `evaluation_latest`, then confirms detail with `evaluation_find`.
- Existing backtest run behavior is untouched; the UI only refreshes V2 listings after a backtest if the V2 page was already loaded.

## Database Changes

No database schema changes were made in Sprint 010.

This sprint uses existing tables:

- `backtest_results` from Sprint 003.
- `evaluation_results` from Sprint 005.

## Test Results

Required validation:

- `php -l api.php` passed.
- `php -l index.php` passed.
- `node --check assets/app.js` passed.
- `php tests/run.php` passed 9 tests.

Additional validation:

- `php -l lib/Backtests/BacktestV2ApiController.php` passed.
- `php -l tests/backtest_v2_api_smoke.php` passed.
- `php -l tests/backtest_v2_ui_contract_smoke.php` passed.
- `php tests/backtest_v2_api_smoke.php` passed.
- `php tests/backtest_v2_ui_contract_smoke.php` passed.
- `php tests/backtest_result_repository_smoke.php` passed.
- `php tests/evaluation_repository_smoke.php` passed.

## Backward Compatibility

- Existing UI pages remain in place.
- Existing API actions remain unchanged.
- Existing backtest calculation behavior was not modified.
- Existing trading logic was not modified.
- No AI or optimizer behavior was added.
- V2 APIs are separate from legacy backtest listing.

## Future Integration Points

- Add pagination for large V2 result tables.
- Add richer filters by symbol, timeframe, evaluation status, and pass/fail state.
- Link V2 result rows to strategy version detail and experiment detail pages.
- Add chart/trade drilldown from `trades_json`.
- Add configurable KPI profiles once KPI storage exists.

## Known Limitations

- UI is read-only.
- No browser automation visual test exists yet.
- Large `trades_json` payloads are not separately paginated.
- V2 API returns raw normalized records; no dedicated DTO layer was added.
- Historical legacy backtests are visible only if already dual-written or migrated into V2 storage.
