<?php

declare(strict_types=1);

return new class implements StorageMigrationInterface
{
    public function version(): string
    {
        return '004_experiments';
    }

    public function description(): string
    {
        return 'Create V2 experiments and experiment_items tables for research experiment grouping.';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS experiments (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT,
                goal_json TEXT NOT NULL DEFAULT '{}',
                status TEXT NOT NULL CHECK(status IN ('Draft', 'Running', 'Paused', 'Completed', 'Failed', 'Archived')),
                created_by TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                started_at TEXT,
                completed_at TEXT,
                best_strategy_version_id TEXT,
                best_backtest_result_id TEXT,
                summary_json TEXT NOT NULL DEFAULT '{}',
                notes TEXT
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS experiment_items (
                id TEXT PRIMARY KEY,
                experiment_id TEXT NOT NULL,
                strategy_version_id TEXT,
                backtest_result_id TEXT,
                item_type TEXT NOT NULL,
                status TEXT NOT NULL CHECK(status IN ('Pending', 'Testing', 'Passed', 'Failed', 'Rejected')),
                score REAL,
                metrics_json TEXT NOT NULL DEFAULT '{}',
                created_at TEXT NOT NULL,
                notes TEXT,
                FOREIGN KEY(experiment_id) REFERENCES experiments(id) ON DELETE CASCADE
            )"
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_experiments_status
             ON experiments(status)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_experiments_created_at
             ON experiments(created_at)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_experiment_items_experiment
             ON experiment_items(experiment_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_experiment_items_status
             ON experiment_items(status)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_experiment_items_score
             ON experiment_items(score)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_experiment_items_strategy_version
             ON experiment_items(strategy_version_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_experiment_items_backtest_result
             ON experiment_items(backtest_result_id)'
        );
    }
};
