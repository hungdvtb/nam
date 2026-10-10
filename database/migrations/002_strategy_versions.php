<?php

declare(strict_types=1);

return new class implements StorageMigrationInterface
{
    public function version(): string
    {
        return '002_strategy_versions';
    }

    public function description(): string
    {
        return 'Create V2 strategy_versions table for the Strategy Version Manager foundation.';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS strategy_versions (
                id TEXT PRIMARY KEY,
                version_code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT,
                parent_version_id TEXT,
                status TEXT NOT NULL CHECK(status IN ('Draft', 'Testing', 'Passed', 'Rejected', 'Production', 'Archived')),
                created_by TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                performance_json TEXT NOT NULL DEFAULT '{}',
                rules_json TEXT NOT NULL DEFAULT '{}',
                settings_json TEXT NOT NULL DEFAULT '{}',
                win_rate REAL,
                average_rr REAL,
                profit_factor REAL,
                max_drawdown REAL,
                total_trades INTEGER,
                backtest_start TEXT,
                backtest_end TEXT,
                approved INTEGER NOT NULL DEFAULT 0,
                approved_at TEXT,
                FOREIGN KEY(parent_version_id) REFERENCES strategy_versions(id) ON DELETE SET NULL
            )"
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_strategy_versions_parent
             ON strategy_versions(parent_version_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_strategy_versions_status
             ON strategy_versions(status)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_strategy_versions_created_at
             ON strategy_versions(created_at)'
        );

        $pdo->exec(
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_strategy_versions_single_production
             ON strategy_versions(status)
             WHERE status = 'Production'"
        );
    }
};
