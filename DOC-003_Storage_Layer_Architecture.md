# DOC-003 Storage Layer Architecture

Project: AI Trade V2  
Sprint: 001  
Purpose: Repository layer, SQLite foundation, migration framework, and JSON compatibility design  
Date: 2026-07-01

## 1. Objective

Sprint 001 introduces the storage foundation for AI Trade V2 without changing current application behavior.

The current project remains JSON-backed. Existing features continue to use `JsonStore` through the existing code paths. The new V2 storage layer is added beside the current system so future sprints can migrate data safely, one repository at a time.

## 2. Design Rules

- Do not change UI behavior.
- Do not change trading logic.
- Do not change backtest behavior.
- Do not migrate all data yet.
- Keep existing JSON files readable.
- Add SQLite as the future primary storage layer.
- Keep the first SQLite schema small and foundational.
- Use repository interfaces before implementation-specific repositories.
- Keep migration execution explicit through a script.

## 3. New Folder Structure

```text
lib/
|-- Repositories/
|   |-- Contracts/
|   |   |-- AccountRepositoryInterface.php
|   |   |-- AiTradeRepositoryInterface.php
|   |   |-- ArtifactRepositoryInterface.php
|   |   |-- AuditLogRepositoryInterface.php
|   |   |-- BacktestRepositoryInterface.php
|   |   |-- DocumentRepositoryInterface.php
|   |   |-- ManualReviewRepositoryInterface.php
|   |   |-- MarketDataRepositoryInterface.php
|   |   |-- StrategyRepositoryInterface.php
|   |   |-- StrategyVersionRepositoryInterface.php
|   |   `-- TradeBrainRepositoryInterface.php
|   |-- Json/
|   |   `-- JsonDocumentRepository.php
|   `-- Sqlite/
|       `-- SqliteDocumentRepository.php
|-- Storage/
|   |-- JsonCompatibilityAdapter.php
|   |-- MigrationRunner.php
|   |-- SqliteConnection.php
|   |-- SqliteInitializer.php
|   |-- StorageMigrationInterface.php
|   `-- StoragePaths.php
database/
`-- migrations/
    `-- 001_storage_foundation.php
scripts/
`-- init_sqlite.php
```

## 4. Repository Layer

Repository contracts define the storage boundary for V2.

The first set of contracts covers:

- Accounts.
- Strategies.
- Strategy versions.
- Backtests.
- Market data.
- Manual reviews.
- TradeBrain.
- Audit events.
- Artifacts.
- Generic documents.

The contracts are intentionally not wired into the existing application yet. Future sprints will migrate one feature at a time from direct `JsonStore` usage to repository usage.

## 5. JSON Compatibility Adapter

`JsonCompatibilityAdapter` wraps the existing `JsonStore` and maps V1 JSON files to stable dataset names.

Examples:

| Dataset name | Existing file |
|---|---|
| `accounts` | `data/accounts.json` |
| `strategy` | `data/strategy.json` |
| `strategy_versions` | `data/strategy_versions.json` |
| `backtest_runs` | `data/backtests/backtest_runs.json` |
| `backtest_trades` | `data/backtests/backtest_trades.json` |
| `tradebrain_memory` | `data/tradebrain/tradebrain_memory.json` |
| `ai_usage` | `data/tradebrain/ai_usage.json` |

This adapter is the bridge between V1 and V2. It allows future repositories to read existing JSON data during migration without spreading file-path knowledge through the new architecture.

## 6. SQLite Foundation

SQLite is introduced as the future local research database.

Default database path:

```text
data/ai_trade_v2.sqlite
```

Initialization command:

```text
php scripts/init_sqlite.php
```

The initializer:

1. Creates the SQLite database directory if needed.
2. Opens a PDO SQLite connection.
3. Enables foreign keys.
4. Applies pending migrations.
5. Prints migration status as JSON.

## 7. Migration Framework

The migration framework consists of:

- `StorageMigrationInterface`
- `MigrationRunner`
- `SqliteInitializer`
- `database/migrations/*.php`

Each migration file returns an object implementing `StorageMigrationInterface`.

The migration runner:

- Creates `schema_migrations` if missing.
- Discovers migrations in filename order.
- Skips already-applied versions.
- Runs each pending migration in a transaction.
- Records version, description, and applied timestamp.

## 8. First Migration

`001_storage_foundation.php` creates foundational tables only.

It does not migrate current application data.

Foundation tables:

| Table | Purpose |
|---|---|
| `schema_migrations` | Migration ledger managed by `MigrationRunner`. |
| `storage_documents` | Generic JSON document storage for low-risk early V2 repository experiments. |
| `storage_events` | Append-style event/audit foundation for future lifecycle records. |
| `storage_artifacts` | Metadata for generated files and future managed artifacts. |

Domain tables such as strategy versions, backtest runs, trades, and market bars are intentionally deferred to later sprints.

## 9. Current Runtime Compatibility

The current app still works because:

- `api.php` was not changed.
- `index.php` was not changed.
- `assets/app.js` was not changed.
- `BacktestEngine.php` was not changed.
- `JsonStore.php` was not changed.
- Existing JSON files were not removed or migrated.

The new storage layer is available for future use but does not affect current feature behavior.

## 10. Future Migration Order

Recommended migration sequence:

1. Strategy versions.
2. Backtest runs.
3. Backtest trades.
4. Manual reviews.
5. TradeBrain memory, proposals, decisions, evaluations, and usage.
6. Market data metadata.
7. Market bars.
8. Accounts and current strategy.
9. Artifacts and export metadata.
10. Audit events and approval workflow.

This order moves the highest-volume and highest-risk data first while leaving low-risk configuration data until later.

## 11. Safety Notes

- SQLite is not used by current features until a future sprint explicitly wires repositories into services.
- JSON remains the source of truth for the current app.
- The migration framework is append-only at the schema level.
- No data deletion behavior is introduced.
- No AI features are introduced.
- No MT5 order execution is introduced.
- No trading or backtest behavior is changed.

## 12. Future Repository Implementation Plan

For each domain repository:

1. Implement a JSON-backed version using `JsonCompatibilityAdapter`.
2. Implement a SQLite-backed version.
3. Add tests comparing JSON and SQLite behavior.
4. Add migration script for that domain.
5. Switch one service to the repository interface.
6. Verify UI and API behavior are unchanged.
7. Repeat for the next domain.

This keeps V2 migration incremental and reversible.
