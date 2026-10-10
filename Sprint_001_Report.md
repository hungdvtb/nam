# Sprint 001 Report

Project: AI Trade V2  
Sprint goal: Repository Layer and SQLite foundation  
Date: 2026-07-01

## Summary

Sprint 001 added the V2 storage foundation without changing the current UI, trading logic, backtest behavior, or existing JSON-backed feature flows.

The current application remains backward compatible because the existing runtime still uses `JsonStore` directly. The new repository, SQLite, migration, and JSON compatibility files are present but not wired into `api.php`, `index.php`, `assets/app.js`, or the trading engine.

## Files Added

### Repository Contracts

| File | Why it was added |
|---|---|
| `lib/Repositories/Contracts/AiTradeRepositoryInterface.php` | Base repository contract for V2 repository health and storage identity. |
| `lib/Repositories/Contracts/AccountRepositoryInterface.php` | Future storage boundary for account profiles and risk settings. |
| `lib/Repositories/Contracts/StrategyRepositoryInterface.php` | Future storage boundary for current strategy and strategy summary/playbook data. |
| `lib/Repositories/Contracts/StrategyVersionRepositoryInterface.php` | Future storage boundary for strategy versions, parent links, statuses, and lifecycle events. |
| `lib/Repositories/Contracts/BacktestRepositoryInterface.php` | Future storage boundary for backtest runs and trade rows. |
| `lib/Repositories/Contracts/MarketDataRepositoryInterface.php` | Future storage boundary for MT5 market-data files and range queries. |
| `lib/Repositories/Contracts/ManualReviewRepositoryInterface.php` | Future storage boundary for candidate, trade, and zone reviews. |
| `lib/Repositories/Contracts/TradeBrainRepositoryInterface.php` | Future storage boundary for TradeBrain profile, memory, proposals, and decisions. |
| `lib/Repositories/Contracts/AuditLogRepositoryInterface.php` | Future storage boundary for append-style audit events. |
| `lib/Repositories/Contracts/ArtifactRepositoryInterface.php` | Future storage boundary for generated exports, reports, and debug artifacts. |
| `lib/Repositories/Contracts/DocumentRepositoryInterface.php` | Generic document repository contract for early low-risk V2 storage experiments. |

### JSON Compatibility Layer

| File | Why it was added |
|---|---|
| `lib/Storage/JsonCompatibilityAdapter.php` | Wraps the existing `JsonStore` and maps stable V2 dataset names to current JSON/JSONL files. |
| `lib/Repositories/Json/JsonDocumentRepository.php` | Provides a repository-shaped adapter over existing JSON datasets. |

### SQLite Foundation

| File | Why it was added |
|---|---|
| `lib/Storage/StoragePaths.php` | Centralizes V2 storage paths for data directory, SQLite database, and migrations. |
| `lib/Storage/SqliteConnection.php` | Creates the PDO SQLite connection and applies SQLite runtime pragmas. |
| `lib/Storage/StorageMigrationInterface.php` | Defines the migration contract. |
| `lib/Storage/MigrationRunner.php` | Discovers, runs, skips, and records storage migrations. |
| `lib/Storage/SqliteInitializer.php` | Coordinates SQLite connection and migration execution. |
| `lib/Repositories/Sqlite/SqliteDocumentRepository.php` | Provides the first SQLite-backed repository implementation for generic documents. |
| `database/migrations/001_storage_foundation.php` | Creates foundational tables for migrations, documents, events, and artifact metadata. |
| `scripts/init_sqlite.php` | CLI initializer for creating the V2 SQLite database and applying migrations. |

### Documentation

| File | Why it was added |
|---|---|
| `DOC-003_Storage_Layer_Architecture.md` | Documents the new storage-layer architecture, compatibility approach, migration framework, and future migration order. |
| `Sprint_001_Report.md` | Records Sprint 001 deliverables, validation, and future migration plan. |

## Files Modified

No existing source files were modified.

Specifically unchanged:

- `api.php`
- `index.php`
- `assets/app.js`
- `assets/styles.css`
- `lib/JsonStore.php`
- `lib/BacktestEngine.php`
- Trading detector/entry/evaluation services
- Existing JSON data files

## Compatibility Notes

Backward compatibility is preserved because:

- Existing app code still reads and writes through `JsonStore`.
- Existing JSON files remain in place.
- The new repository layer is additive only.
- The SQLite database is not required by current features yet.
- No data migration was performed.
- No API route behavior was changed.
- No UI behavior was changed.
- No trading or backtest behavior was changed.

## Validation Performed

### PHP Syntax

All new PHP files passed `php -l`.

Validated files:

- Repository interfaces.
- JSON compatibility adapter.
- JSON document repository.
- SQLite connection.
- Migration interface.
- Migration runner.
- SQLite initializer.
- SQLite document repository.
- First migration.
- SQLite init script.

### JSON Compatibility Smoke Test

The JSON document repository successfully read the existing `accounts` dataset through:

```text
JsonStore -> JsonCompatibilityAdapter -> JsonDocumentRepository
```

Result:

```text
json_adapter=ok accounts=2
```

### Runtime Isolation Check

The current app entry points are not wired to the new storage foundation.

Checked:

- `api.php`
- `index.php`
- `assets/`

No references were found to:

- `JsonCompatibilityAdapter`
- `Sqlite`
- `RepositoryInterface`
- `StoragePaths`

### SQLite Initialization Status

The initializer was executed:

```text
php scripts/init_sqlite.php
```

Result:

```text
SQLite initialization failed: The pdo_sqlite extension is required for AI Trade V2 storage.
```

Environment finding:

- PHP CLI is available: PHP 8.4.16.
- `pdo_sqlite` is not enabled.
- `sqlite3` is not enabled.
- No `data/ai_trade_v2.sqlite` file was created.

The code foundation is ready, but the local PHP environment must enable SQLite support before the database can be created.

## New Storage Foundation

Default future SQLite path:

```text
data/ai_trade_v2.sqlite
```

Initializer command:

```text
php scripts/init_sqlite.php
```

First migration:

```text
database/migrations/001_storage_foundation.php
```

Foundation tables created after SQLite is enabled:

- `schema_migrations`
- `storage_documents`
- `storage_events`
- `storage_artifacts`

## Why Each Change Was Made

The repository contracts were added to define stable storage boundaries before any migration starts. This prevents future services from depending directly on SQLite details or legacy JSON file paths.

The JSON compatibility adapter was added so future repositories can read current JSON data safely during migration. This keeps the existing project usable while V2 storage is introduced.

The SQLite connection and initializer were added to establish the new local database foundation described in DOC-002.

The migration framework was added so every future schema change is explicit, ordered, repeatable, and recorded.

The first migration was intentionally small. It creates only foundation tables and does not attempt to migrate strategy versions, backtests, trades, market bars, or TradeBrain data yet.

The storage architecture document was added so future sprints have a practical implementation map.

## Future Migration Plan

### Environment Step

Enable PHP SQLite support before the next storage sprint:

- Enable `pdo_sqlite`.
- Prefer enabling `sqlite3` too if available.
- Re-run `php scripts/init_sqlite.php`.
- Confirm `data/ai_trade_v2.sqlite` is created.

### Phase 1: Repository Implementations

Build JSON-backed and SQLite-backed implementations for:

1. Strategy versions.
2. Backtest runs.
3. Backtest trades.
4. Manual reviews.
5. TradeBrain data.

### Phase 2: Data Migration Scripts

Create explicit migration scripts for:

1. `data/strategy_versions.json`
2. `data/backtests/backtest_runs.json`
3. `data/backtests/backtest_trades.json`
4. `data/manual_reviews.json`
5. `data/tradebrain/*.json`

Each migration should be idempotent and should not delete the source JSON file.

### Phase 3: Service Wiring

Switch one service at a time from `JsonStore` to repository interfaces:

1. `StrategyVersionService`
2. `BacktestEngine` persistence boundary
3. `ManualReviewService`
4. `StrategyEvaluator`
5. `TradeBrainService`

Each service migration must preserve API output compatibility.

### Phase 4: Query and Pagination

After high-volume data is in SQLite:

- Add paginated run queries.
- Add trade filtering by run, symbol, outcome, and reason.
- Add version filtering by status and parent.
- Add manual review filtering.
- Add TradeBrain decision/proposal filtering.

### Phase 5: Audit and Artifact Management

Use the foundation tables to introduce:

- Audit events for strategy changes.
- Audit events for AI proposals.
- Artifact metadata for debug exports.
- Retention policy for generated files.

## Sprint 001 Result

Sprint 001 is complete at the code-foundation level.

The only blocker is environmental: SQLite cannot be initialized until PHP's SQLite extension is enabled. No existing application behavior was changed.
