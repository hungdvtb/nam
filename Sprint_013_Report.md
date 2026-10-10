# Sprint 013 Report - Strategy Backtest Link

Date: 2026-07-01

## Objective

Allow a user to run a backtest using a selected Strategy V2 version and keep the Strategy V2 id linked through V2 backtest storage, evaluation, and experiment auto-attach.

This sprint does not change trading logic, backtest calculations, legacy strategy editing, or AI behavior.

## Files Created

| File | Purpose |
| --- | --- |
| `tests/strategy_v2_backtest_link_smoke.php` | Smoke-tests Strategy V2 backtest link persistence through backtest result, evaluation, and experiment item. |
| `Sprint_013_Report.md` | Documents Sprint 013 implementation and validation. |

## Files Modified

| File | Change | Reason |
| --- | --- | --- |
| `api.php` | `run_backtest` now accepts `strategy_version_id` and `strategyVersionSource`; it can resolve Strategy V2 records through SQLite while preserving legacy lookup. | Enables selected Strategy V2 versions to use the current backtest flow. |
| `index.php` | Added `Kiểm thử phiên bản này` button to the Strategy V2 detail panel. | Lets the user run a backtest directly from the selected Strategy V2 version. |
| `assets/app.js` | Added Strategy V2 backtest action, V2 source payload, Backtest V2 filter support for V2 versions, and clickable Strategy Version references in Backtest V2 detail. | Connects the Strategy V2 UI to existing backtest execution and result navigation. |
| `assets/styles.css` | Added `.inline-link` styling for clickable version references. | Makes linked Strategy Version references readable inside detail rows. |
| `tests/run.php` | Added the Strategy V2 backtest link smoke to the main runner. | Ensures `php tests/run.php` validates Sprint 013 behavior. |
| `tests/strategy_v2_ui_contract_smoke.php` | Added checks for the new run-backtest button, V2 source flag, and clickable reference renderer. | Protects the UI contract for Strategy V2 backtest linking. |

## Behavior Added

In `Phiên bản chiến lược`:

- User can select a Strategy V2 version.
- User can click `Kiểm thử phiên bản này`.
- The frontend sends the selected id as both `strategyVersionId` and `strategy_version_id`, with `strategyVersionSource: 'v2'`.
- The existing `run_backtest` action resolves the Strategy V2 record and calls `BacktestEngine` unchanged.

Persistence chain:

- V2 backtest result stores `strategy_version_id`.
- Automatic evaluation references the same `strategy_version_id`.
- Running experiment auto-attach creates/updates an experiment item with the same `strategy_version_id`.

In `Kết quả kiểm thử V2`:

- Result detail shows a clickable Strategy Version reference when a `strategy_version_id` is present.
- Clicking the reference loads Strategy V2 records, selects the matching version if available, and opens `Phiên bản chiến lược`.
- The Backtest V2 version filter now includes loaded Strategy V2 versions as well as legacy versions.

## Backward Compatibility

- Existing legacy strategy backtests still resolve through the old JSON-backed StrategyVersionService path.
- Backtests without a V2 `strategy_version_id` still persist V2 results and evaluations with `strategy_version_id = null`.
- Legacy strategy UI remains present and unchanged.

## Test Results

Syntax:

- `php -l api.php` passed.
- `php -l index.php` passed.
- `php -l tests/strategy_v2_backtest_link_smoke.php` passed.
- `php -l tests/strategy_v2_ui_contract_smoke.php` passed.
- `node --check assets/app.js` passed.

Smoke tests:

- `php tests/strategy_v2_backtest_link_smoke.php` passed.
- `php tests/strategy_v2_ui_contract_smoke.php` passed.
- `php tests/strategy_v2_api_smoke.php` passed.

Main suite:

- `php tests/run.php` passed.
- Result: `Passed 10 tests.`

## Scope Guardrails Confirmed

- No trading engine logic changed.
- No backtest calculation changed.
- No AI implementation added.
- Existing legacy strategy UI remains intact.
