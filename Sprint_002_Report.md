# Sprint 002 Report

Project: AI Trade V2  
Sprint goal: Strategy Version Manager foundation  
Date: 2026-07-01

## Summary

Sprint 002 implemented the foundation for the V2 Strategy Version Manager without changing UI, trading logic, backtest behavior, or current API feature behavior.

The existing legacy strategy version flow remains compatible:

- `api.php` still constructs `StrategyVersionService($store)`.
- Existing UI-facing methods such as `list()`, `createFromParent()`, `updateStatus()`, `attachBacktestSummary()`, and `attachSplitSummary()` remain available.
- Existing lowercase legacy statuses continue to work for current code paths.

The new V2 foundation adds:

- `strategy_versions` SQLite table.
- V2 status model: `Draft`, `Testing`, `Passed`, `Rejected`, `Production`, `Archived`.
- JSON and SQLite strategy version repositories.
- Additive V2 service methods for create, clone, update, archive, approve, production lookup, latest lookup, and listing.
- Version history through repository events.

No AI, optimization, or backtesting features were implemented.

## Files Created

| File | Purpose |
|---|---|
| `lib/StrategyVersions/StrategyVersionStatus.php` | Defines the V2 status model and maps legacy statuses to V2 statuses. |
| `lib/StrategyVersions/StrategyVersionRecord.php` | Normalizes strategy version records across JSON, SQLite, legacy camelCase, and V2 snake_case fields. |
| `lib/Repositories/Json/JsonStrategyVersionRepository.php` | JSON-backed strategy version repository using the Sprint 001 JSON compatibility adapter. |
| `lib/Repositories/Sqlite/SQLiteStrategyVersionRepository.php` | SQLite-backed strategy version repository for the new `strategy_versions` table. |
| `database/migrations/002_strategy_versions.php` | Adds the V2 `strategy_versions` table and indexes. |
| `Sprint_002_Report.md` | Sprint implementation report. |

## Files Modified

| File | Why it changed |
|---|---|
| `lib/Repositories/Contracts/StrategyVersionRepositoryInterface.php` | Expanded the contract for create, update, lookup by version code, latest, production, uniqueness check, and history events. |
| `lib/StrategyVersionService.php` | Added V2 Strategy Version Manager methods while preserving existing legacy methods and behavior. |

No UI files were modified.

No trading engine files were modified.

No backtest behavior was modified.

## Database Changes

Migration added:

```text
database/migrations/002_strategy_versions.php
```

Created table:

```text
strategy_versions
```

Columns:

```text
id
version_code
name
description
parent_version_id
status
created_by
created_at
updated_at
performance_json
rules_json
settings_json
win_rate
average_rr
profit_factor
max_drawdown
total_trades
backtest_start
backtest_end
approved
approved_at
```

Indexes:

- Parent version index.
- Status index.
- Created-at index.
- Unique `version_code`.
- Unique partial production index so only one `Production` version can exist in SQLite at a time.

Migration status:

```text
001_storage_foundation: skipped, already applied
002_strategy_versions: applied
pending migrations: none
```

No existing JSON strategy versions were migrated.

Current project SQLite row count:

```text
strategy_versions = 0
```

## Architecture Decisions

### 1. V2 is additive, not disruptive

The new repository layer is available, but the current UI and API continue to run through existing code paths.

### 2. Legacy JSON remains compatible

`JsonStrategyVersionRepository` reads and writes through `JsonCompatibilityAdapter`, which keeps the current `data/strategy_versions.json` path readable.

### 3. V2 status model is separate but mapped

V2 supports:

- `Draft`
- `Testing`
- `Passed`
- `Rejected`
- `Production`
- `Archived`

Legacy statuses are mapped when needed:

- `draft` -> `Draft`
- `backtested` -> `Testing`
- `validation_passed` -> `Passed`
- `final_test_passed` -> `Passed`
- `approved` -> `Passed`
- `live` -> `Production`
- `retired` -> `Archived`

Legacy methods still return their old lowercase status values to avoid breaking the current UI/API.

### 4. History uses repository events

SQLite history is stored in the existing Sprint 001 `storage_events` table with:

```text
aggregate_type = strategy_version
aggregate_id = version id
```

JSON history is stored in a `history` array on the version record.

### 5. Production is exclusive in SQLite

SQLite has a partial unique index for `Production` status. The service demotes an existing production version back to `Passed` before promoting a new production version.

### 6. Records include future-ready fields

Each repository normalizes fields for:

- Parent version lineage.
- Rules JSON.
- Settings JSON.
- Performance JSON.
- Approval state.
- Backtest summary metrics.
- History events.

This prepares the module for future AI strategy evolution without implementing AI now.

## Service Methods Added

`StrategyVersionService` now includes these V2 methods:

```text
createVersion(array $payload = []): array
cloneVersion(string $sourceVersionId, array $overrides = []): array
updateVersion(string $id, array $changes): array
archiveVersion(string $id): array
approveVersion(string $id, bool $production = false, string $approvedBy = 'user'): array
getLatestVersion(): ?array
getProductionVersion(): ?array
listVersions(int $limit = 100, array $filters = []): array
```

Existing legacy methods remain:

```text
list()
find()
createFromCurrentStrategy()
createFromParent()
saveVersion()
attachBacktestSummary()
attachSplitSummary()
updateStatus()
tree()
diff()
applyChanges()
rulesFromStrategy()
scoreSummary()
normalizeRules()
```

## Future Integration Points

### AI Strategy Evolution

Future AI can use:

- `createVersion()` to create a new AI-proposed version.
- `cloneVersion()` to branch from an existing version.
- `updateVersion()` to apply validated rule changes.
- `approveVersion()` after human/evaluator approval.

### Version Comparison

Future comparison can use:

- Parent version IDs.
- `rules_json`.
- `performance_json`.
- Backtest metric columns.
- History events.

### Production Promotion

Future production workflows can use:

- `approveVersion($id, true)` to promote one version to `Production`.
- `getProductionVersion()` to find the active production strategy.
- History events to audit promotion and replacement.

### Rollback

Rollback can be implemented by:

- Finding a previous `Passed` or `Production` version.
- Promoting it with `approveVersion($id, true)`.
- Recording a future `strategy_version.rollback` event.

### Repository Migration

Future sprints can wire the current UI/API to:

- `JsonStrategyVersionRepository` first for compatibility.
- `SQLiteStrategyVersionRepository` after data migration is explicit and tested.

## Known Limitations

- Existing JSON strategy versions were not migrated into SQLite.
- No UI uses the new repository methods yet.
- No API route exposes the V2 service methods yet.
- No version comparison UI was implemented.
- No AI version creation was implemented.
- No optimizer was implemented.
- No backtest integration was changed.
- JSON repository history is embedded in the JSON version record, while SQLite history uses `storage_events`.
- SQLite allows only one `Production` version through a partial unique index, but JSON compatibility mode does not enforce that at the database level.
- The current service constructor still requires `JsonStore` for backward compatibility, even when a SQLite repository is injected.

## Validation Results

### PHP Syntax

All new and modified PHP files passed syntax checks:

- `lib/StrategyVersions/StrategyVersionStatus.php`
- `lib/StrategyVersions/StrategyVersionRecord.php`
- `lib/Repositories/Contracts/StrategyVersionRepositoryInterface.php`
- `lib/Repositories/Json/JsonStrategyVersionRepository.php`
- `lib/Repositories/Sqlite/SQLiteStrategyVersionRepository.php`
- `database/migrations/002_strategy_versions.php`
- `lib/StrategyVersionService.php`

### SQLite Migration

Command:

```text
php scripts/init_sqlite.php
```

Result:

```text
002_strategy_versions applied
pending migrations: none
```

Second run:

```text
001_storage_foundation skipped
002_strategy_versions skipped
pending migrations: none
```

### SQLite Repository Smoke Test

An in-memory SQLite database was used to avoid touching project data.

Result:

```text
Testing history=1
```

### JSON Repository / Service Smoke Test

A temporary JSON store was used to avoid touching project data.

Result:

```text
created=SV-TEMP-1 clone=SV-TEMP-2 production=Production history=3
```

### Existing Test Suite

Command:

```text
php tests/run.php
```

Result:

```text
Passed 9 tests.
```

This confirms the legacy strategy version flow and trading/backtest behavior still pass existing checks.

## Sprint 002 Result

Sprint 002 is complete.

The Strategy Version Manager foundation is now present, repository-backed, SQLite-ready, JSON-compatible, and future-ready for AI strategy evolution. Current product behavior remains unchanged.
