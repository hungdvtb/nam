<?php

declare(strict_types=1);

return new class implements StorageMigrationInterface
{
    public function version(): string
    {
        return '011_kpi_profiles';
    }

    public function description(): string
    {
        return 'Create KPI profiles table for configurable evaluation targets.';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS kpi_profiles (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT,
                is_default INTEGER NOT NULL DEFAULT 0,
                win_rate_min REAL NOT NULL,
                average_rr_min REAL NOT NULL,
                profit_factor_min REAL NOT NULL,
                max_drawdown_max REAL NOT NULL,
                total_trades_min INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                notes TEXT
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_kpi_profiles_default
             ON kpi_profiles(is_default)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_kpi_profiles_updated_at
             ON kpi_profiles(updated_at)'
        );
    }
};
