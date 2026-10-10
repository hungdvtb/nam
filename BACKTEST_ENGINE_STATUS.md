# BACKTEST_ENGINE_STATUS

Audit date: 2026-06-17

## Files handling backtest

- `lib/BacktestEngine.php`
  - Main server-side deterministic backtest engine.
  - Loads M15/H4 candle payloads through `JsonStore`.
  - Normalizes `rulesJson`, detects H4 context, creates simulated trades, persists runs/trades.
- `lib/StrategyRuleParser.php`
  - Normalizes the H4/M15 price-action `rulesJson` schema.
  - Migrates older fields such as `rrMin`, `zoneStrengthMin`, `filters.avoidSideway`.
- `lib/MarketStructureDetector.php`
  - Detects confirmed swing high/low, HH/HL/LH/LL, BOS, CHoCH, and trend state.
  - Uses `confirmedAt` so the backtest can avoid look-ahead.
- `lib/SupplyDemandZoneDetector.php`
  - Detects H4 supply/demand zones from market-structure breaks.
  - Tracks freshness, touches, broken state, impulse strength, and strength score.
- `lib/M15EntryConfirmation.php`
  - Checks M15 reaction, M15 structure break, optional fake-break/QML proxy, dirty sideway, entry, SL, TP, and RR.
- `api.php?action=run_backtest`
  - Fetches the selected strategy version, calls `BacktestEngine::run`, stores summaries.
- `scripts/demo_backtest.php`
  - CLI/demo entry point for a symbol and split.
- `assets/app.js`
  - Shows server backtest results, chart overlays, and legacy client-side chart demo runs.

## EMA/ATR usage

- Server-side primary strategy logic no longer uses EMA/ATR for entries.
- `lib/BacktestEngine.php` still contains older private helper methods (`entrySignal`, `contextIndicators`, `ema`, `atr`, `aiScoreProxy`) as legacy dead code. They are not called by the new `simulate` flow.
- `assets/app.js` still contains `BACKTEST_RUNS` and `simulateBacktestRun`, a client-side chart demo based on EMA presets. This is UI/demo overlay logic, not the TradeBrain/server backtest source of truth.
- Chart indicators such as EMA/ATR remain available for visual analysis only.

## Current input/output

- Input:
  - Strategy version with normalized `rulesJson`.
  - M15 entry timeframe candles from `data/market_data/{SYMBOL}_M15.json`.
  - H4 context candles from `data/market_data/{SYMBOL}_H4.json`.
  - Backtest settings: symbol, split/date range, balance, spread/slippage/fee, max bars in trade.
- Output:
  - Run summary with PnL, drawdown, winrate, PF, average RR, rejected/skipped count.
  - Simulated trades with entry/SL/TP/exit, `reasonEnter`, `reasonExit`, `reasonSkipped`, `reasonCode`, tags, zone metadata.
  - `breakdowns`: winrate by symbol/setup/H4 trend/zone strength/tags, common loss reasons, common skipped reasons.
  - `debug`: detected H4 zones, H4 structure, M15 swings/events for chart overlays.

## Simulated trade creation

- Pending setup creation happens in `BacktestEngine::createPendingLimit`.
- Pending fill/cancel happens in `BacktestEngine::resolvePendingLimit`.
- Closed trade records are created in `BacktestEngine::closeTrade`.
- Skipped setup records are created in `BacktestEngine::skippedTrade`.
- Runs/trades are persisted by `BacktestEngine::persistRun` into:
  - `data/backtests/backtest_runs.json`
  - `data/backtests/backtest_trades.json`

## Metrics

- Core metrics are calculated in `BacktestEngine::summary`.
- Chart-compatible stats are calculated in `BacktestEngine::chartStats`.
- Tag/group breakdowns are calculated in `BacktestEngine::breakdowns`.
- Strategy compare/evolution still consumes summaries through `lib/StrategyEvaluator.php`.

## Proxy/mock parts remaining

- QML/fake-break detection in `M15EntryConfirmation::fakeBreakOrQml` is intentionally basic, not a full discretionary QML parser.
- News filter remains a placeholder; there is no economic-calendar feed.
- Opposite-zone TP is not fully implemented yet; TP currently resolves to fixed RR from entry unless later extended.
- Spread/slippage are simplified point-based costs.
- Same-candle SL/TP order is conservative: SL is chosen first.
- Client-side EMA demo runs in `assets/app.js` remain for visual experimentation and are not server authority.
- Mock AI reviewer/evolution remains mock; no OpenAI API is called.

## Previous mismatches vs H4/M15 supply-demand/QML

- Previous server engine entered from EMA20/EMA50 cross rather than H4 zone return.
- H4 trend/zone strength was an EMA/ATR context proxy.
- No real pending limit lifecycle existed for H4 zone entries.
- M15 confirmation was not checking reaction at zone, structure break, dirty sideway, or explicit skip reasons.
- Trade tags did not identify setup type, H4 trend, zone strength bucket, or skip/loss reason breakdowns.

## Current coverage after upgrade

- H4 market structure: swing high/low, HH/HL/LH/LL, BOS, CHoCH, trend.
- H4 zones: demand/supply, impulse strength, fresh/touch/broken state, score and reason.
- M15 confirmation: zone reaction, directional structure break, optional fake-break/QML proxy, dirty sideway filter.
- Entry: pending LIMIT only, no market entry.
- Management: cancel unfilled limit after configured candles, SL/TP simulation, conservative ambiguous candle handling, move SL to BE after configured R.
- Debug: server run exposes H4 zones and M15 swings for chart overlay toggles.
