# Architecture Review Report

Project: AI Trade  
Review role: Lead Software Architect  
Review type: Pre-V2 architecture assessment  
Date: 2026-07-01  
Scope: Inspect the existing project and document architecture, status, risks, technical debt, and V2 readiness. No application source code was modified.

## 1. Project Overview

AI Trade is a local-first trading research, strategy training, and backtesting workstation. It is built primarily as a PHP application served from XAMPP or PHP's built-in server, with a vanilla JavaScript frontend and JSON-file persistence.

The product currently combines several domains:

- Strategy definition and versioning.
- Manual training case review.
- MT5 market-data import through a Python bridge.
- Server-side deterministic backtesting.
- Chart viewing, chart drawing, replay, overlays, and paper-trade-style UI tools.
- Calibration reports and calibrated preset generation.
- TradeBrain-style strategy evolution with memory, proposals, decisions, and safety gates.
- A dry-run multi-agent scaffold for future automation.

The current system is best described as a strong local research prototype. It contains meaningful domain logic and a surprisingly broad set of workflows, especially around H4 supply/demand zones, M15 confirmation, strategy versions, manual calibration, and backtest diagnostics. It is not yet production trading software.

Primary architectural character:

- Backend: PHP controller plus service classes.
- Frontend: single HTML/PHP page plus one very large JavaScript application file.
- Storage: local JSON and JSONL files under `data/`.
- MT5 integration: Python bridge for status and historical data fetch only.
- AI: mock/service scaffolding, usage gates, strategy evolution pipeline, but no real AI decision engine connected by default.
- Trading: backtesting and preview tools only; no live order execution was found.

## 2. Folder Structure

### Complete Application Tree

The project also contains multiple `.chrome-profile*` generated browser-profile directories and screenshot artifacts from visual checks. Those are runtime/debug artifacts rather than application source modules. The application-owned structure is:

```text
.
|-- .env.example
|-- api.php
|-- index.php
|-- README.md
|-- PROJECT_STATUS.md
|-- BACKTEST_ENGINE_STATUS.md
|-- MULTI_AGENT_SYSTEM.md
|-- TASKS.md
|-- start-server.bat
|-- trainer-check.png
|-- strategy-check.png
|-- playbook-check.png
|-- mt5-check.png
|-- chart-tools-check.png
|-- chart-tools-check-2.png
|-- agents
|   |-- agent.config.json
|   |-- types.ts
|   |-- adapters
|   |   |-- coderAdapter.ts
|   |   |-- directorAdapter.ts
|   |   `-- reviewerAdapter.ts
|   |-- prompts
|   |   |-- coder.prompt.md
|   |   |-- director.prompt.md
|   |   `-- reviewer.prompt.md
|   |-- runs
|   |   |-- latest.json
|   |   |-- 20260608153227-c6b834
|   |   |   |-- coder-report.json
|   |   |   |-- commands.log
|   |   |   |-- review.json
|   |   |   |-- run.json
|   |   |   `-- task.json
|   |   |-- 20260608153416-dbda3e
|   |   |   |-- coder-report.json
|   |   |   |-- commands.log
|   |   |   |-- review.json
|   |   |   |-- run.json
|   |   |   `-- task.json
|   |   `-- 20260608153545-9b4a84
|   |       |-- coder-report.json
|   |       |-- commands.log
|   |       |-- review.json
|   |       |-- run.json
|   |       `-- task.json
|   `-- tasks
|       |-- current-coder-task.md
|       `-- current-director-task.json
|-- assets
|   |-- app.js
|   |-- help-texts.js
|   `-- styles.css
|-- config
|   `-- defaults.php
|-- data
|   |-- accounts.json
|   |-- candidate_cases.json
|   |-- strategy.json
|   |-- strategy_summary.json
|   |-- strategy_versions.json
|   |-- backtests
|   |   |-- backtest_runs.json
|   |   `-- backtest_trades.json
|   |-- cases
|   |-- exports
|   |   |-- debug_samples_*.csv
|   |   `-- debug_samples_*.json
|   |-- market_data
|   |   |-- AUDJPY_H4.json
|   |   |-- AUDJPY_M15.json
|   |   |-- AUDUSD_H4.json
|   |   |-- AUDUSD_M15.json
|   |   |-- EURAUD_H4.json
|   |   |-- EURAUD_M15.json
|   |   |-- EURCAD_H4.json
|   |   |-- EURCAD_M15.json
|   |   |-- EURJPY_H4.json
|   |   |-- EURJPY_M15.json
|   |   |-- EURNZD_H4.json
|   |   |-- EURNZD_M15.json
|   |   |-- EURUSD_H4.json
|   |   |-- EURUSD_M15.json
|   |   |-- GBPAUD_H4.json
|   |   |-- GBPAUD_M15.json
|   |   |-- GBPCAD_H4.json
|   |   |-- GBPCAD_M15.json
|   |   |-- GBPCHF_H4.json
|   |   |-- GBPCHF_M15.json
|   |   |-- GBPJPY_H4.json
|   |   |-- GBPJPY_M15.json
|   |   |-- GBPNZD_H4.json
|   |   |-- GBPNZD_M15.json
|   |   |-- GBPUSD_H4.json
|   |   |-- GBPUSD_M15.json
|   |   |-- NZDUSD_H4.json
|   |   |-- NZDUSD_M15.json
|   |   |-- USDCAD_H4.json
|   |   |-- USDCAD_M15.json
|   |   |-- USDCHF_H4.json
|   |   |-- USDCHF_M15.json
|   |   |-- USDJPY_H4.json
|   |   `-- USDJPY_M15.json
|   `-- tradebrain
|       |-- ai_decisions.json
|       |-- ai_usage.json
|       |-- strategy_evaluations.json
|       |-- strategy_upgrade_proposals.json
|       |-- tradebrain_memory.json
|       `-- tradebrain_profile.json
|-- lib
|   |-- AiUsageTracker.php
|   |-- BacktestEngine.php
|   |-- CalibratedPresetService.php
|   |-- CalibrationAdvisor.php
|   |-- CalibrationQueueBuilder.php
|   |-- CalibrationReport.php
|   |-- DataQualityChecker.php
|   |-- JsonStore.php
|   |-- M15EntryConfirmation.php
|   |-- ManualReviewService.php
|   |-- MarketStructureDetector.php
|   |-- Mt5Bridge.php
|   |-- RiskCalculator.php
|   |-- RuleScorer.php
|   |-- StrategyAiReviewer.php
|   |-- StrategyEvaluator.php
|   |-- StrategyRuleParser.php
|   |-- StrategyVersionService.php
|   |-- SupplyDemandZoneDetector.php
|   |-- TradeBrainAiService.php
|   |-- TradeBrainLiveSupervisor.php
|   `-- TradeBrainService.php
|-- scripts
|   |-- agent-orchestrator.ts
|   |-- batch_backtest.php
|   |-- calibration_report.php
|   |-- demo_backtest.php
|   |-- export_debug_samples.php
|   |-- export_manual_reviews.php
|   |-- generate_tuned_preset.php
|   |-- import_manual_reviews.php
|   `-- mt5_bridge.py
`-- tests
    `-- run.php
```

### Important Folders

`assets/`  
Contains the whole browser application. `app.js` is a large single-file JavaScript application containing API calls, UI rendering, charting, indicators, drawing tools, replay, backtest UI, memory UI, and legacy client-side simulation helpers. `styles.css` provides the entire visual system. `help-texts.js` contains localized help content.

`config/`  
Contains default seed configuration for accounts, strategy, summary, candidate cases, and likely default parameters consumed by `JsonStore`.

`data/`  
Acts as the database. It contains user accounts, strategies, market data, backtest runs, trades, TradeBrain memory, AI decisions, evaluations, proposals, and exported debug samples. Several files are very large and are currently read/written as whole JSON documents.

`lib/`  
Contains the backend domain logic. This is the strongest architectural layer in the project. It includes market structure detection, zone detection, entry confirmation, backtesting, strategy parsing, versioning, evaluation, manual review, calibration, MT5 bridge, risk preview, and TradeBrain services.

`scripts/`  
Contains operational and research scripts for MT5 bridge, batch backtesting, demo backtesting, debug export, manual review import/export, calibration report, calibrated preset generation, and a TypeScript dry-run agent orchestrator.

`tests/`  
Contains a custom PHP test runner with broad coverage of core trading logic, detectors, lifecycle behavior, strategy versions, TradeBrain mock flow, calibration loop, and live supervisor safety.

`agents/`  
Contains an experimental multi-agent system scaffold. It is currently dry-run oriented and safety-locked.

## 3. Module Inventory

Legend:

- ✅ Complete: reasonably complete for current local prototype scope.
- 🟡 Partially Complete: useful and working, but not production-complete.
- 🔴 Needs Refactor: functional or important, but too coupled, too large, legacy, unsafe, or hard to extend safely.
- ⚪ Empty: placeholder or data store with no meaningful content yet.

### Entry Points

| Module | Status | Notes |
|---|---:|---|
| `index.php` | 🔴 Needs Refactor | Single large page containing all application tabs, forms, chart surfaces, and panels. Useful for prototype velocity, but hard to maintain for V2. |
| `api.php` | 🔴 Needs Refactor | Single controller for bootstrap, strategy, MT5, market data, backtest, calibration, TradeBrain, memory, import/export, risk preview, and scoring. Missing route separation, auth, CSRF, and method policy. |
| `start-server.bat` | 🟡 Partially Complete | Local convenience script. Not a production launcher. |

### Frontend

| Module | Status | Notes |
|---|---:|---|
| `assets/app.js` | 🔴 Needs Refactor | Very large monolith of about 7,900 lines. It owns state, API client, renderers, chart engine, drawing tools, indicators, replay, calibration UI, TradeBrain UI, and legacy client backtest logic. This is the largest V2 frontend risk. |
| `assets/styles.css` | 🟡 Partially Complete | Mature enough for current UI, but tightly coupled to one-page markup. Needs modularization when UI is split. |
| `assets/help-texts.js` | 🟡 Partially Complete | Useful localized guide layer. Needs encoding cleanup and probably structured ownership by feature. |

### Backend Services

| Module | Status | Notes |
|---|---:|---|
| `JsonStore.php` | 🟡 Partially Complete | Central JSON persistence helper with seeding, JSON/JSONL read/write, stats, snapshot export. Not enough for concurrent, large, or production data. |
| `BacktestEngine.php` | 🔴 Needs Refactor | Core engine is powerful but very large. It mixes data loading, simulation, diagnostics, persistence, scoring, and legacy helper code. |
| `MarketStructureDetector.php` | 🟡 Partially Complete | Useful fractal swing/BOS/CHoCH/trend detector with no-lookahead intent. Needs more validation against real market edge cases. |
| `SupplyDemandZoneDetector.php` | 🟡 Partially Complete | Useful H4 zone detection and lifecycle model. Needs stronger domain calibration and test expansion. |
| `M15EntryConfirmation.php` | 🟡 Partially Complete | Implements M15 confirmation, reaction candles, structure break, fake-break/QML proxy, entry, SL, TP. Some patterns are simplified. |
| `StrategyRuleParser.php` | 🟡 Partially Complete | Good normalization layer for presets and current strategy data. Needs eventual formal strategy schema/DSL. |
| `StrategyVersionService.php` | 🟡 Partially Complete | Provides version IDs, parent links, statuses, diffs, and summaries. File-backed, mutable, and not yet strong enough as a production version ledger. |
| `StrategyEvaluator.php` | 🟡 Partially Complete | Evaluates train/validation/final performance and overfit/risk gates. Needs richer statistical confidence and walk-forward logic. |
| `StrategyAiReviewer.php` | 🔴 Needs Refactor | Legacy mock reviewer references older fields and appears superseded by TradeBrain services. Candidate for retirement or rewrite. |
| `TradeBrainAiService.php` | 🟡 Partially Complete | Defines AI service interface plus mock proposal generator. Good boundary, but no real AI provider implementation. |
| `TradeBrainService.php` | 🟡 Partially Complete | Orchestrates strategy evolution with memory, usage gates, validation, version creation, and evaluation. Strong scaffold, still mock AI and file-backed. |
| `TradeBrainLiveSupervisor.php` | 🟡 Partially Complete | Safety-first fail-closed live supervisor stub. Good direction, not a real supervisor yet. |
| `AiUsageTracker.php` | 🟡 Partially Complete | Tracks usage and env-based budgets. Needs real provider accounting when real AI is introduced. |
| `ManualReviewService.php` | 🟡 Partially Complete | Normalizes and stores manual reviews for candidate, zone, and trade calibration. Needs data migration and stronger indexing. |
| `CalibrationReport.php` | 🟡 Partially Complete | Generates useful manual-review calibration statistics and error groupings. |
| `CalibrationAdvisor.php` | 🟡 Partially Complete | Produces rule recommendations from calibration stats. Heuristic only. |
| `CalibrationQueueBuilder.php` | 🟡 Partially Complete | Builds human-review queues from backtest debug candidates, trades, and zones. Potentially expensive for large data. |
| `CalibratedPresetService.php` | 🟡 Partially Complete | Creates pending calibrated presets from advisor recommendations. Needs approval workflow hardening. |
| `DataQualityChecker.php` | 🟡 Partially Complete | Checks market payloads, gaps, duplicates, invalid OHLC, volume, and split ranges. Useful but whole-file based. |
| `Mt5Bridge.php` | 🟡 Partially Complete | PHP wrapper around Python MT5 bridge. Provides status/fetch/save/list. Historical data only, no order execution. |
| `RiskCalculator.php` | 🟡 Partially Complete | Useful account/risk preview, prop-firm buffer logic. Not a complete portfolio/live risk engine. |
| `RuleScorer.php` | 🟡 Partially Complete | Offline heuristic scoring for training cases. Needs alignment with formal strategy schema and real outcome feedback. |

### Scripts

| Module | Status | Notes |
|---|---:|---|
| `scripts/mt5_bridge.py` | 🟡 Partially Complete | MT5 status and historical fetch via MetaTrader5 Python package. No trade execution. |
| `scripts/batch_backtest.php` | 🟡 Partially Complete | Useful batch runner for symbols/presets. Needs standardized CLI args and output contracts. |
| `scripts/demo_backtest.php` | 🟡 Partially Complete | Useful local demo runner. |
| `scripts/export_debug_samples.php` | 🟡 Partially Complete | Useful for calibration/debug review, but exports can become very large. |
| `scripts/calibration_report.php` | 🟡 Partially Complete | CLI access to calibration report. |
| `scripts/generate_tuned_preset.php` | 🟡 Partially Complete | CLI generation of calibrated preset from advisor output. |
| `scripts/export_manual_reviews.php` | 🟡 Partially Complete | Manual review export utility. |
| `scripts/import_manual_reviews.php` | 🟡 Partially Complete | Manual review import utility. |
| `scripts/agent-orchestrator.ts` | 🟡 Partially Complete | Dry-run multi-agent orchestration. Useful concept, not integrated into production workflow. |

### Agent Scaffold

| Module | Status | Notes |
|---|---:|---|
| `agents/agent.config.json` | 🟡 Partially Complete | Safety configuration for dry-run agents. |
| `agents/types.ts` | 🟡 Partially Complete | Type interfaces for agent tasks, runs, reviews, and command results. |
| `agents/adapters/directorAdapter.ts` | 🟡 Partially Complete | Mock Director adapter. Reads task source and prepares coder task. |
| `agents/adapters/coderAdapter.ts` | 🟡 Partially Complete | Dry-run coder adapter. Does not modify project by default. |
| `agents/adapters/reviewerAdapter.ts` | 🟡 Partially Complete | Mock reviewer that scans for command failure and forbidden trading/sensitive patterns. |
| `agents/prompts/*.md` | 🟡 Partially Complete | Prompt templates for future agent roles. |
| `agents/runs/*` | 🟡 Partially Complete | Generated run artifacts. Useful audit trail, not core source. |
| `agents/tasks/*` | 🟡 Partially Complete | Current generated agent task artifacts. |

### Data Stores

| Store | Status | Notes |
|---|---:|---|
| `data/accounts.json` | 🟡 Partially Complete | Account/risk configuration store. |
| `data/strategy.json` | 🟡 Partially Complete | Current strategy definition store. |
| `data/strategy_summary.json` | 🟡 Partially Complete | Strategy/playbook narrative summary. |
| `data/candidate_cases.json` | 🟡 Partially Complete | Training case data. |
| `data/strategy_versions.json` | 🔴 Needs Refactor | Very large file-backed version ledger. Needs database/indexing/immutability for V2. |
| `data/backtests/backtest_runs.json` | 🔴 Needs Refactor | Very large full-file JSON store. Bootstrap and listing can become slow. |
| `data/backtests/backtest_trades.json` | 🔴 Needs Refactor | Large full-file trade store. Needs queryable storage. |
| `data/market_data/*.json` | 🟡 Partially Complete | Historical H4/M15 bars for many FX pairs. Needs retention/indexing and validation metadata. |
| `data/cases/` | ⚪ Empty | Review JSONL path is expected by services, but directory currently has no listed case file. |
| `data/exports/` | 🟡 Partially Complete | Debug export files, including very large JSON samples. Should be treated as generated artifacts. |
| `data/tradebrain/ai_decisions.json` | ⚪ Empty | Empty decision log at inspection time. |
| `data/tradebrain/ai_usage.json` | ⚪ Empty | Empty usage log at inspection time. |
| `data/tradebrain/strategy_evaluations.json` | ⚪ Empty | Empty evaluation log at inspection time. |
| `data/tradebrain/strategy_upgrade_proposals.json` | ⚪ Empty | Empty proposal log at inspection time. |
| `data/tradebrain/tradebrain_memory.json` | ⚪ Empty | Empty memory log at inspection time. |
| `data/tradebrain/tradebrain_profile.json` | 🟡 Partially Complete | Small profile/config store. |

### Tests and Documentation

| Module | Status | Notes |
|---|---:|---|
| `tests/run.php` | 🟡 Partially Complete | Valuable custom test runner covering core detectors, backtest behavior, strategy versions, TradeBrain, calibration, and supervisor safety. Needs CI integration. |
| `README.md` | 🟡 Partially Complete | Local setup and product notes, but encoding issues appear in several text files. |
| `PROJECT_STATUS.md` | ✅ Complete | Helpful architecture/status document for current prototype. |
| `BACKTEST_ENGINE_STATUS.md` | ✅ Complete | Clear state of server-side backtest engine and remaining gaps. |
| `MULTI_AGENT_SYSTEM.md` | ✅ Complete | Clear description of dry-run agent scaffold and safety limitations. |
| `TASKS.md` | 🟡 Partially Complete | Task backlog/source for agent scaffold. |

## 4. Feature Inventory

### Dashboard

Current capabilities:

- Displays account/strategy/training summary.
- Shows recent review data.
- Provides risk preview integration.
- Acts as a general landing panel for the local workstation.

Status: 🟡 Partially Complete  
Main gap: dashboard depends on large bootstrap payload and is not yet separated into independently loadable widgets.

### Account and Risk Management

Current capabilities:

- Account profiles stored in JSON.
- Risk preview through `RiskCalculator`.
- Prop-firm style equity/daily buffer awareness.
- UI forms for accounts.

Status: 🟡 Partially Complete  
Main gap: no live portfolio model, no broker position sync, no correlation exposure, no account-level hard execution limits.

### Strategy Editor and Playbook

Current capabilities:

- Editable current strategy.
- Strategy summary/playbook text.
- H4 and M15 rules normalized through `StrategyRuleParser`.
- Multiple preset profiles such as strict, balanced, and loose-debug.

Status: 🟡 Partially Complete  
Main gap: strategy is not yet a formal typed DSL with schema migration, compatibility validation, and versioned compilation.

### Strategy Version Manager

Current capabilities:

- Create V1 from current strategy.
- Parent/child version tree.
- Status transitions.
- Diff between versions.
- Summary and evaluation data.

Status: 🟡 Partially Complete  
Main gap: version data is mutable JSON, too large, and lacks immutable event history or audit guarantees.

### Server-Side Backtesting

Current capabilities:

- H4 market structure and supply/demand context.
- M15 entry confirmation.
- Pending limit lifecycle.
- One pending/open trade model.
- TP/SL/BE/time-stop management.
- Train/validation/final/custom split support.
- Diagnostics, candidates, skipped setup records, debug levels, and score output.
- Persisted runs and trades.

Status: 🟡 Partially Complete  
Main gap: engine is monolithic, whole-file based, and does not yet support portfolio-level simulation, realistic execution, tick model, spread/slippage modeling, news events, or robust optimization.

### MT5 Historical Data Import

Current capabilities:

- MT5 terminal status through Python.
- Symbol/timeframe fetch.
- Saves bars to `data/market_data`.
- Supports many FX pairs in H4 and M15.

Status: 🟡 Partially Complete  
Main gap: no order execution, no live feed supervisor, no broker reconciliation, and no resilient worker process.

### Chart Workstation

Current capabilities:

- Candles, bars, line, area, Heikin Ashi, Renko-style display.
- Indicators such as SMA/EMA/RSI/ATR/MACD/Bollinger/VWAP/ATR stop.
- Overlays for H4 zones, M15 swings, trades, and backtest diagnostics.
- Drawing tools, favorites, persistence, style toolbar, undo/redo, export/import.
- Replay and paper-trade-style panel.

Status: 🟡 Partially Complete  
Main gap: chart engine is embedded in `app.js` and is difficult to test or evolve independently.

### Manual Trainer

Current capabilities:

- Candidate case review.
- Manual decision labels.
- Memory export/import.
- Rule scoring with heuristic reasons.

Status: 🟡 Partially Complete  
Main gap: feedback loops are still JSON-based and not linked to a formal training dataset/version pipeline.

### Calibration

Current capabilities:

- Builds review queues from candidates/trades/zones.
- Manual review service normalizes labels.
- Calibration reports aggregate detector errors.
- Advisor recommends rule changes.
- Calibrated preset service generates pending presets.

Status: 🟡 Partially Complete  
Main gap: calibration is heuristic and needs stronger statistics, review workflow states, approval records, and regression checks.

### TradeBrain Evolution

Current capabilities:

- Mock AI service proposes strategy changes.
- TradeBrain service runs parent train/validation and new version train/validation.
- Safety gates require manual calibration readiness and usage budgets.
- Version is accepted/rejected based on evaluator results.
- Memory, proposals, decisions, usage, and evaluations have stores.

Status: 🟡 Partially Complete  
Main gap: AI is mock, memory stores are mostly empty, and evolution lacks a robust experiment registry, rollback, and statistical confidence model.

### Live Supervisor Stub

Current capabilities:

- Fails closed.
- Rejects missing proposal fields.
- Explicitly avoids live order execution.

Status: 🟡 Partially Complete  
Main gap: not a live trading system.

### Multi-Agent Dry-Run System

Current capabilities:

- Director, Coder, Reviewer scaffolding.
- Safety locks prevent live trading, MT5 orders, and data deletion.
- Generates audit artifacts.

Status: 🟡 Partially Complete  
Main gap: mock/dry-run oriented, not integrated into regular development or CI.

## 5. UI Pages

The UI is implemented as a single page with tabbed sections in `index.php`, rendered and controlled by `assets/app.js`.

| UI Page / Tab | Purpose | Current Status |
|---|---|---:|
| Dashboard | Summary, stats, risk preview, recent reviews | 🟡 Partially Complete |
| Accounts | Account profiles and risk inputs | 🟡 Partially Complete |
| Strategy | Strategy editing, entries, management, version creation | 🟡 Partially Complete |
| Backtest | Server-side backtest form, results, diagnostics, candidates, trades | 🟡 Partially Complete |
| Calibration | Queue builder, report, advisor, calibrated preset generation | 🟡 Partially Complete |
| Evolution | TradeBrain strategy evolution workflow | 🟡 Partially Complete |
| Compare | Strategy evaluation/version comparison | 🟡 Partially Complete |
| Playbook | Strategy summary and operating playbook | 🟡 Partially Complete |
| MT5 | MT5 status/fetch, saved files, chart panel | 🟡 Partially Complete |
| Trainer | Case chart and human training decisions | 🟡 Partially Complete |
| TradeBrain Memory | Lessons, AI decisions, proposal/evaluation logs | 🟡 Partially Complete |
| Memory | Export/import/evaluate training memory | 🟡 Partially Complete |

UI architecture concerns:

- All pages share one global JS state object.
- Rendering functions are numerous and tightly coupled.
- The MT5 chart, drawing system, backtest UI, TradeBrain UI, and trainer UI all live in the same file.
- No route-level lazy loading.
- Bootstrap payload is broad.
- UI testability is low because there are no component boundaries.

## 6. Core Architecture

### Current Logical Architecture

```text
Browser
  |
  | index.php + assets/app.js + assets/styles.css
  v
api.php
  |
  | manually required PHP service classes
  v
lib/*
  |
  | JsonStore read/write
  v
data/*.json, data/*.jsonl

api.php
  |
  | Mt5Bridge.php proc_open
  v
scripts/mt5_bridge.py
  |
  | MetaTrader5 Python package
  v
Local MT5 terminal
```

### Request Flow

1. User opens `index.php`.
2. Frontend loads `assets/help-texts.js` and `assets/app.js`.
3. `app.js` calls `api.php?action=bootstrap`.
4. `api.php` creates service objects manually.
5. Services read JSON files through `JsonStore`.
6. Frontend stores response in a global `state` object.
7. UI panels render from global state.
8. Mutations call `api.php` actions.
9. Actions write whole JSON files back to `data/`.

### Core Backend Boundaries

The backend has useful domain boundaries in `lib/`:

- Persistence: `JsonStore`.
- Market data quality: `DataQualityChecker`.
- Market structure: `MarketStructureDetector`.
- Zone lifecycle: `SupplyDemandZoneDetector`.
- Entry model: `M15EntryConfirmation`.
- Simulation: `BacktestEngine`.
- Strategy schema-ish normalization: `StrategyRuleParser`.
- Strategy versioning: `StrategyVersionService`.
- Evaluation: `StrategyEvaluator`.
- Manual learning: `ManualReviewService`, `CalibrationReport`, `CalibrationAdvisor`, `CalibrationQueueBuilder`.
- TradeBrain: `TradeBrainService`, `TradeBrainAiService`, `AiUsageTracker`, `TradeBrainLiveSupervisor`.
- MT5: `Mt5Bridge` plus `mt5_bridge.py`.

This separation is a real strength. The main architectural issue is that the application entry points do not preserve those boundaries. `api.php` wires everything directly, and `app.js` combines unrelated UI domains.

### Core Frontend Boundary

The frontend currently has almost no module boundary. It has:

- Global state.
- Global API helper.
- Global render functions.
- Global chart engine.
- Global drawing state.
- LocalStorage integration.
- UI event binding.
- Legacy simulation functions.

This is acceptable for an exploratory local tool, but it is the wrong shape for V2.

## 7. Database

### Current Database Type

There is no relational database or embedded database. The database is the `data/` directory.

Storage format:

- JSON arrays/objects.
- JSONL for reviewed cases when present.
- Large market-data JSON documents.
- Large backtest run and trade JSON documents.
- Exported debug JSON/CSV files.

### Important Data Stores

| File | Role |
|---|---|
| `data/accounts.json` | Account profiles and risk settings. |
| `data/strategy.json` | Current editable strategy. |
| `data/strategy_summary.json` | Human-readable playbook/summary content. |
| `data/candidate_cases.json` | Training candidate cases. |
| `data/cases/reviewed_cases.jsonl` | Expected reviewed case stream, not listed at inspection time. |
| `data/strategy_versions.json` | Strategy version ledger, parent links, statuses, rules, summaries. |
| `data/backtests/backtest_runs.json` | Persisted backtest run summaries. |
| `data/backtests/backtest_trades.json` | Persisted trade rows and skipped setup records. |
| `data/market_data/*.json` | MT5 historical OHLCV bars by symbol and timeframe. |
| `data/tradebrain/tradebrain_profile.json` | TradeBrain profile/config. |
| `data/tradebrain/tradebrain_memory.json` | TradeBrain lessons/memory. |
| `data/tradebrain/strategy_upgrade_proposals.json` | AI strategy upgrade proposals. |
| `data/tradebrain/strategy_evaluations.json` | Strategy evolution evaluations. |
| `data/tradebrain/ai_decisions.json` | TradeBrain decision log. |
| `data/tradebrain/ai_usage.json` | AI usage accounting. |

### Database Strengths

- Very easy local setup.
- No external dependency.
- Human-inspectable.
- Good for fast prototype iteration.
- Atomic-ish writes through temp file and rename.
- Directory initialization is centralized in `JsonStore`.

### Database Weaknesses

- Large files are read/written as whole documents.
- No query engine.
- No indexes.
- No migrations.
- No transaction boundaries across files.
- No robust concurrent writer handling.
- No schema validation layer.
- No immutable append-only strategy ledger.
- No retention policy for exported debug files.
- No separation between source/config and generated runtime data.

### V2 Database Recommendation

For V2, move operational data into SQLite first, not a remote database. SQLite preserves local-first simplicity while providing indexes, transactions, query filters, migrations, and safe pagination.

Suggested V2 tables:

- `accounts`
- `strategies`
- `strategy_versions`
- `strategy_version_events`
- `market_symbols`
- `market_bars`
- `backtest_runs`
- `backtest_trades`
- `backtest_diagnostics`
- `manual_reviews`
- `calibration_reports`
- `calibrated_presets`
- `tradebrain_memory`
- `tradebrain_proposals`
- `tradebrain_evaluations`
- `tradebrain_decisions`
- `ai_usage_events`

JSON can still be used for flexible rule blobs, but not as the only database.

## 8. MT5 Integration

### Current Components

- `Mt5Bridge.php`: PHP wrapper.
- `scripts/mt5_bridge.py`: Python bridge using MetaTrader5.
- `api.php` actions: `mt5_status`, `mt5_fetch`, `market_files`, `market_data`.
- `data/market_data/*.json`: saved OHLCV payloads.
- UI tab: MT5 status, fetch form, saved files, chart panel.

### Current Flow

```text
UI MT5 action
  -> api.php action
  -> Mt5Bridge.php
  -> proc_open Python
  -> scripts/mt5_bridge.py
  -> MetaTrader5 terminal
  -> JSON result
  -> optional save to data/market_data
```

### What Works

- MT5 status check.
- Historical bar fetch by symbol/timeframe/count.
- Saves normalized bars with time, time ISO, OHLC, volume, spread, and real volume.
- Market files can be listed.
- Data can be rendered in the chart.

### What Does Not Exist Yet

- No live order execution.
- No `order_send` flow.
- No position reconciliation.
- No broker account sync beyond status.
- No streaming tick or bar worker.
- No order journal.
- No execution risk gate.
- No emergency stop.
- No reconnect supervisor.

### MT5 Risk Assessment

Current MT5 integration is appropriate for data import and research. It should not be extended to live trading until the app has authentication, a real database, a position/risk engine, audit logging, and a fail-closed execution service.

## 9. AI Related Components

### Existing AI-Labeled Components

| Component | Purpose | Status |
|---|---|---:|
| `TradeBrainAiService.php` | AI proposal interface and mock implementation | 🟡 Partially Complete |
| `TradeBrainService.php` | Strategy evolution orchestration | 🟡 Partially Complete |
| `AiUsageTracker.php` | Usage/budget accounting | 🟡 Partially Complete |
| `StrategyAiReviewer.php` | Legacy heuristic AI reviewer | 🔴 Needs Refactor |
| `RuleScorer.php` | Heuristic scoring of candidate cases | 🟡 Partially Complete |
| `StrategyEvaluator.php` | Evaluates evolved strategy performance | 🟡 Partially Complete |
| `TradeBrainLiveSupervisor.php` | Fail-closed live supervisor stub | 🟡 Partially Complete |
| `data/tradebrain/*` | Memory, proposals, decisions, evaluations, usage | ⚪ Mostly Empty |

### Current AI Reality

The project has an AI-ready architecture, but not a real AI system yet. The TradeBrain service has useful gates and a well-intentioned flow:

1. Load parent version.
2. Run parent backtests.
3. Check manual calibration readiness.
4. Check usage limits.
5. Ask AI service for a proposal.
6. Apply proposal to rules.
7. Create child version.
8. Run new train/validation tests.
9. Optionally run final test.
10. Evaluate and set version status.
11. Store proposal, decision, evaluation, and memory.

The current AI service is mock/heuristic. That is good for safety and testability, but V2 should not market this as autonomous AI trading.

### AI Readiness Strengths

- Clear service boundary for AI proposals.
- Usage budget gate exists.
- Evolution flow requires validation.
- Manual calibration readiness gate exists.
- Decisions/proposals/evaluations are persisted.
- Live supervisor fails closed.

### AI Readiness Gaps

- No provider-backed AI implementation.
- No prompt/version registry.
- No structured AI output validation beyond basic shape.
- No reproducibility record for AI context.
- No model evaluation harness.
- No experiment tracking.
- No human approval chain strong enough for live trading.
- Empty memory/evaluation/decision stores indicate the loop has not matured operationally.

## 10. Trading Engine

### Current Engine

The primary trading engine is `BacktestEngine.php`, supported by:

- `MarketStructureDetector.php`
- `SupplyDemandZoneDetector.php`
- `M15EntryConfirmation.php`
- `StrategyRuleParser.php`
- `DataQualityChecker.php`
- `StrategyEvaluator.php`

### Current Backtest Model

The server-side engine uses:

- H4 market structure.
- H4 supply/demand zones.
- M15 entry confirmation.
- Pending limit orders.
- Zone lifecycle and touch/broken/fresh checks.
- Trend and countertrend filters.
- Weekend/spread filters.
- SL/TP/BE/time-stop management.
- Train/validation/final/custom ranges.
- Diagnostic candidate records.
- Debug zone and swing output.

### Important Execution Characteristics

- Uses last closed H4 context for M15 decisions to reduce lookahead.
- Simulates at M15 bar granularity, not tick granularity.
- One pending/open trade at a time.
- Same-candle TP/SL ambiguity is handled conservatively with SL first.
- Limit order non-fill is tracked.
- Skipped setups can be persisted in compacted form.
- Runs can be persisted to JSON stores.

### Trading Engine Strengths

- Domain logic is much stronger than a simple indicator backtest.
- No-lookahead concerns are explicitly addressed.
- Diagnostics are useful for manual calibration.
- Preset support enables experiments.
- Tests cover important lifecycle behavior.

### Trading Engine Gaps

- Engine is too large and mixed-responsibility.
- Legacy EMA/ATR proxy helpers remain in the file.
- Client-side legacy backtest helpers still exist in `app.js`.
- No tick-level execution.
- No portfolio simulation.
- No multi-position simulation.
- No realistic spread/slippage/commission model.
- No news calendar integration.
- No broker rule model.
- No walk-forward optimizer.
- No Monte Carlo or robustness testing.
- Opposite-zone target logic is not fully implemented.
- QML/fake-break logic is simplified.

## 11. Technical Debt

### Highest Priority Technical Debt

1. `assets/app.js` frontend monolith  
   The single JavaScript file owns too many domains. This is the largest maintainability issue.

2. `BacktestEngine.php` backend monolith  
   The engine is powerful, but it combines too many responsibilities and contains legacy helper code.

3. `api.php` single-controller design  
   All actions live in one procedural controller. Security, validation, routing, and service wiring are tangled.

4. JSON database at current scale  
   `strategy_versions.json`, `backtest_runs.json`, `backtest_trades.json`, and debug exports are large. Whole-file access will increasingly hurt performance and data safety.

5. No authentication or CSRF protection  
   Acceptable only for a private local prototype. Dangerous if served beyond localhost.

6. Encoding/mojibake issues  
   Several docs/config/text outputs show corrupted Vietnamese characters. This can damage UX, docs, prompts, and future AI context quality.

7. No package manager or autoloading  
   PHP classes are manually required. There is no Composer autoload, no frontend package boundary, and no standard dependency metadata.

8. No standard CI pipeline  
   A custom test runner exists, but there is no normal CI/build/lint workflow.

9. Runtime/generated artifacts mixed into workspace  
   Large exports, market data, Chrome profiles, screenshots, and source files coexist in one tree.

10. Legacy/dead code  
   Old client-side EMA backtest and legacy `StrategyAiReviewer` logic are still present.

## 12. Performance

### Current Performance Risks

Bootstrap payload:

- `api.php?action=bootstrap` loads many stores at once.
- Large strategy versions/backtest data can make initial page load slower over time.

JSON persistence:

- Large JSON files are decoded entirely into memory.
- Writes rewrite entire files.
- There is no pagination at the data layer.

Market data:

- Market data endpoints return whole files.
- Backtests load full H4/M15 series.
- Data quality checks can scan all files.

Backtesting:

- Structure and zone detection compute over full ranges.
- Debug level 2/3 can generate large candidate/zone/trade payloads.
- Persisting skipped trades must remain carefully bounded.

Frontend:

- `app.js` is large and loaded as one file.
- Chart drawing, overlays, indicators, drawing tools, and replay all run in the browser main thread.
- Large localStorage drawing sets or large diagnostic overlays can degrade chart responsiveness.

Exports:

- Debug sample JSON files can become extremely large.
- Generated files should have retention, compression, or external artifact storage.

### Performance Recommendations for V2

- Move high-volume stores to SQLite.
- Add pagination to runs, trades, versions, reviews, and decisions.
- Split bootstrap into feature-specific endpoints.
- Add market-data range queries.
- Add run/trade indexes.
- Separate debug artifact storage from the main application database.
- Split frontend bundles by feature or page.
- Move heavy chart/backtest rendering transformations into workers where needed.

## 13. Security

### Current Security Posture

The application appears designed for trusted local use. It is not hardened for network exposure.

### Security Strengths

- No live order execution was found.
- MT5 integration is historical-data focused.
- TradeBrain live supervisor fails closed.
- Agent config explicitly disables live trading, MT5 order execution, and data deletion.
- `.env.example` exists and no obvious committed real secrets were identified in the inspected application files.
- Python command arguments are quoted through PHP command construction.

### Security Risks

| Risk | Severity | Notes |
|---|---:|---|
| No authentication | High | Anyone who can reach the server can use API actions. |
| No CSRF protection | High | Mutating actions can be triggered by browser context if exposed. |
| No authorization model | High | No separation between read, write, import/export, evolution, and MT5 actions. |
| File-backed import/export | High | Memory import/export can overwrite important local stores if misused. |
| Broad single API endpoint | Medium | Hard to enforce route-specific validation and security. |
| Path/file handling | Medium | Some path normalization exists, but storage access should be allow-listed by route. |
| No rate limiting | Medium | Backtests, MT5 fetches, and data-quality scans can be expensive. |
| No audit log for user actions | Medium | Strategy/data changes are not fully attributable. |
| XAMPP exposure risk | High | If Apache/PHP is bound to LAN or public interfaces, this local tool becomes unsafe. |
| No security headers/session model | Medium | Current UI is local-prototype oriented. |

### Security Recommendation

Before any V2 feature that touches live trading, real AI, external accounts, or remote access:

- Add local authentication at minimum.
- Restrict server binding to localhost by default.
- Add CSRF tokens for mutating requests.
- Add explicit route/method validation.
- Add allow-listed file access.
- Add append-only audit logs.
- Add role/permission model if multiple users are planned.
- Add an execution safety layer before any MT5 order action exists.

## 14. Code Quality

Overall score: 5.5 / 10

### Why The Score Is Not Lower

- Core trading domain classes exist and are not all jammed into the controller.
- Market structure, zone detection, M15 confirmation, versioning, evaluation, calibration, and TradeBrain are conceptually separated.
- There is a meaningful custom test suite.
- The backtest engine includes diagnostics and safety-minded no-lookahead logic.
- MT5 execution is not casually enabled.
- TradeBrain has gates and fails closed for live supervision.
- Documentation files honestly describe current limitations.

### Why The Score Is Not Higher

- Frontend is a large monolith.
- Backtest engine is a large monolith.
- API controller is procedural and overloaded.
- Persistence is JSON at a scale that is already becoming uncomfortable.
- No auth/CSRF/security boundary.
- No Composer/autoloading.
- No standard build/lint/CI.
- Legacy code remains.
- Encoding issues reduce quality.
- Runtime data and generated artifacts are mixed with source.

### Code Quality By Area

| Area | Score | Notes |
|---|---:|---|
| Domain modeling | 7/10 | Good direction in `lib/`, especially detectors/evaluator/versioning. |
| Backend structure | 5/10 | Services exist, but controller and engine need separation. |
| Frontend structure | 3/10 | Functional but monolithic and hard to test. |
| Persistence | 3/10 | Fine for prototype, weak for V2. |
| Testing | 6/10 | Good custom tests, missing CI and frontend tests. |
| Security | 2/10 | Local-only assumption, no auth/CSRF. |
| Documentation | 6/10 | Helpful docs, but encoding issues and not all docs are current. |
| Future extensibility | 5/10 | Concepts are promising, implementation needs modular V2 foundation. |

## 15. Future Readiness

### AI Strategy Builder

Status: 🟡 Partially Ready

Ready pieces:

- Strategy rules can be represented as JSON.
- `StrategyRuleParser` normalizes rules and presets.
- Version service can create child strategies.
- TradeBrain can apply proposed changes.

Missing:

- Formal strategy DSL.
- Schema validation and migration.
- AI prompt registry and output contracts.
- Human approval workflow.
- Strategy compiler/test harness.

### AI Backtesting

Status: 🟡 Partially Ready

Ready pieces:

- Deterministic server-side backtest engine.
- Train/validation/final split support.
- Diagnostics and debug candidates.
- Batch/demo scripts.

Missing:

- Scalable storage.
- Experiment registry.
- Walk-forward testing.
- Monte Carlo/robustness analysis.
- Realistic execution model.
- Queryable result database.

### AI Optimization

Status: 🟡 Early / Partially Ready

Ready pieces:

- Calibration reports.
- Calibration advisor.
- Calibrated preset generation.
- TradeBrain proposal flow.

Missing:

- Parameter search engine.
- Objective-function registry.
- Overfit-resistant optimizer.
- Statistical confidence intervals.
- Experiment comparison UI backed by proper storage.

### Strategy Evolution

Status: 🟡 Partially Ready

Ready pieces:

- Parent/child version tree.
- Evaluation gates.
- Proposal and decision stores.
- Manual calibration readiness checks.

Missing:

- Real AI provider.
- Strong version immutability.
- Rollback/audit semantics.
- Continuous evaluation queue.
- Production promotion workflow.

### TradeBrain

Status: 🟡 Scaffolded

Ready pieces:

- Profile store.
- Memory store.
- AI decision/proposal/evaluation stores.
- Usage tracker.
- Mock AI proposal service.

Missing:

- Real memory retrieval.
- Real AI call integration.
- Prompt/version traceability.
- Learning metrics.
- Human-in-the-loop review UI depth.
- Production safety certification.

### Version Manager

Status: 🟡 Partially Ready

Ready pieces:

- Version IDs.
- Parent relationships.
- Status model.
- Diff capability.
- Summary/evaluation records.

Missing:

- Database-backed immutable events.
- Version schema migrations.
- Approval signatures.
- Rollback and promotion lifecycle.
- Paginated query model.

### Production Trading

Status: 🔴 Not Ready

Missing:

- Authentication.
- Authorization.
- Real-time broker sync.
- Position/risk engine.
- Execution engine.
- Hard risk limits.
- Audit log.
- Monitoring.
- Alerting.
- Emergency stop.
- Deployment isolation.
- Security hardening.
- Regulatory/operational controls.

### Continuous Learning

Status: 🟡 Early

Ready pieces:

- Manual reviews.
- Memory import/export.
- Calibration reports.
- TradeBrain memory scaffold.

Missing:

- Dataset versioning.
- Label quality controls.
- Outcome tracking.
- Drift detection.
- Model/prompt evaluation.
- Automated retraining or proposal queue.

## 16. Refactor Candidates

### Priority 1: Split `assets/app.js`

Suggested target modules:

- `apiClient.js`
- `stateStore.js`
- `dashboardView.js`
- `accountsView.js`
- `strategyView.js`
- `backtestView.js`
- `calibrationView.js`
- `tradebrainView.js`
- `memoryView.js`
- `mt5View.js`
- `chart/core.js`
- `chart/indicators.js`
- `chart/drawingTools.js`
- `chart/replay.js`
- `chart/storage.js`
- `utils/formatters.js`

### Priority 2: Split `BacktestEngine.php`

Suggested target services:

- `BacktestDataLoader`
- `BacktestRangeResolver`
- `BacktestSimulator`
- `PendingOrderSimulator`
- `TradeManager`
- `BacktestDiagnosticsBuilder`
- `BacktestResultScorer`
- `BacktestPersistence`
- `BacktestRunRepository`

### Priority 3: Replace `api.php` With Routed Controllers

Suggested controllers:

- `BootstrapController`
- `AccountController`
- `StrategyController`
- `StrategyVersionController`
- `BacktestController`
- `MarketDataController`
- `Mt5Controller`
- `ManualReviewController`
- `CalibrationController`
- `TradeBrainController`
- `MemoryController`

### Priority 4: Introduce Repositories

Suggested repository layer:

- `AccountRepository`
- `StrategyRepository`
- `StrategyVersionRepository`
- `MarketDataRepository`
- `BacktestRunRepository`
- `TradeRepository`
- `ManualReviewRepository`
- `TradeBrainRepository`
- `AiUsageRepository`

The first implementation can wrap JSON stores, then migrate behind the same interface to SQLite.

### Priority 5: Remove or Isolate Legacy Logic

Candidates:

- Legacy client-side EMA backtest helpers in `assets/app.js`.
- Legacy/dead EMA/ATR helpers inside `BacktestEngine.php` if not used by the server engine.
- `StrategyAiReviewer.php`, unless it is intentionally revived and aligned with TradeBrain.
- Old config fields that no longer map to the current H4/M15 engine.

## 17. Missing Modules

### Missing for V2 Application Architecture

- Router.
- Request validator.
- Response formatter.
- Error handler.
- Auth/session module.
- CSRF module.
- Repository interfaces.
- Migration runner.
- Config loader.
- Logger.
- Audit logger.
- Background job runner.
- Pagination/query service.
- File/artifact retention manager.

### Missing for Trading Research

- Experiment registry.
- Walk-forward analysis module.
- Monte Carlo module.
- Parameter optimizer.
- Execution-cost model.
- Commission/slippage/spread model.
- News/calendar module.
- Portfolio/correlation risk model.
- Multi-position simulator.
- Broker rules module.
- Tick-level or intrabar model.

### Missing for AI/TradeBrain

- Real AI provider adapter.
- Prompt templates with versioning.
- Structured output validator.
- AI context builder.
- Memory retrieval/ranking module.
- AI experiment tracker.
- Human approval workflow.
- Rollback workflow.
- Learning metrics dashboard.

### Missing for Production Trading

- Live market data worker.
- Order execution service.
- Position synchronizer.
- Account synchronizer.
- Risk gate.
- Kill switch.
- Alerting/monitoring.
- Trade journal.
- Deployment profile separation.
- Secrets manager.
- Security hardening layer.

## 18. Risks

### Critical Risks

1. Accidental network exposure  
   XAMPP/PHP local apps can accidentally be served beyond localhost. With no auth/CSRF, this is the highest operational risk.

2. Data corruption from JSON storage  
   Large JSON files, full-file writes, and multi-file workflows create corruption and consistency risk.

3. V2 complexity on top of monoliths  
   Adding AI builder, optimization, or live features directly into `app.js`, `api.php`, and `BacktestEngine.php` will compound maintenance risk.

4. Misinterpreting mock AI as real AI  
   The architecture has AI labels and TradeBrain flows, but current behavior is mostly heuristic/mock.

5. Premature live trading  
   The project is not ready for live execution. Adding MT5 orders before security, risk, audit, and monitoring would be dangerous.

### High Risks

- Large bootstrap and data files degrade UI responsiveness.
- Strategy version file grows without indexing or pagination.
- Backtest diagnostics can generate very large artifacts.
- Legacy code can confuse future contributors and AI agents.
- Encoding issues may damage Vietnamese UX and AI prompt clarity.
- No CI means regressions depend on manual discipline.
- No schema migrations makes future data changes fragile.

### Medium Risks

- Chart drawing state in localStorage may become inconsistent.
- Manual review labels may drift without taxonomy governance.
- Calibration recommendations may overfit small samples.
- Data quality checks may miss broker/session-specific anomalies.
- Agent scaffold may create false confidence if promoted beyond dry-run too soon.

## 19. Strengths

1. Strong local-first prototype  
   The app is easy to run locally and avoids external infrastructure.

2. Meaningful trading-domain services  
   Market structure, supply/demand zones, M15 confirmation, versioning, and evaluation are modeled as backend services.

3. Safety-minded MT5 scope  
   MT5 integration fetches historical data and does not execute orders.

4. Backtest diagnostics  
   Setup candidates, skipped reasons, zone data, and debug outputs are valuable for improving rules.

5. Manual calibration loop  
   Manual reviews, calibration reports, advisor recommendations, and calibrated presets form a sensible learning loop.

6. TradeBrain scaffold  
   The proposal/evaluation/decision/memory architecture is a good foundation if hardened.

7. Test coverage exists  
   The custom PHP test runner covers many important backend behaviors.

8. Documentation is candid  
   Existing status docs clearly describe limitations and current safety boundaries.

9. Fail-closed mindset  
   Live supervisor and agent config are intentionally conservative.

10. Rich charting UI  
   The chart/drawing/replay/paper-trade tools provide a powerful research surface, even though they need modularization.

## 20. Executive Summary

AI Trade is a capable local trading research prototype with unusually broad coverage for a single-machine PHP/vanilla-JS app. Its strongest assets are the backend domain services, server-side H4/M15 backtest model, manual calibration loop, strategy versioning, and safety-first stance around MT5 and live trading.

The project should not move directly into V2 feature expansion without architectural cleanup. The biggest risks are the frontend monolith, the backtest engine monolith, the overloaded `api.php`, JSON persistence at growing data sizes, and missing security controls.

Recommended V2 direction:

1. Keep the current project as the reference prototype.
2. First modularize the application boundaries, not the feature set.
3. Move high-volume data to SQLite behind repository interfaces.
4. Split `api.php` into controllers with validation and security.
5. Split `assets/app.js` into feature and chart modules.
6. Split `BacktestEngine.php` into simulation, data loading, diagnostics, and persistence services.
7. Add auth, CSRF, audit logging, and localhost-only safeguards before any remote or live trading use.
8. Preserve the current safety posture: no live execution until a dedicated production trading architecture exists.

Final architectural verdict:

The project is a strong V1 research workstation and a promising base for V2, but V2 should be treated as an architecture hardening phase before it becomes an AI optimization or production trading platform. The correct next move is not more features. The correct next move is to create stable module boundaries, a real persistence layer, security controls, and testable service contracts so the existing trading intelligence can evolve without becoming fragile.
