<?php

declare(strict_types=1);

return new class implements StorageMigrationInterface
{
    public function version(): string
    {
        return '005_evaluation_results';
    }

    public function description(): string
    {
        return 'Create V2 evaluation_results table for KPI-based backtest evaluation.';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS evaluation_results (
                id TEXT PRIMARY KEY,
                strategy_version_id TEXT,
                backtest_result_id TEXT,
                experiment_id TEXT,
                status TEXT NOT NULL CHECK(status IN ('Passed', 'Failed', 'Warning')),
                score REAL NOT NULL,
                passed INTEGER NOT NULL DEFAULT 0,
                kpi_json TEXT NOT NULL DEFAULT '{}',
                metrics_json TEXT NOT NULL DEFAULT '{}',
                reasons_json TEXT NOT NULL DEFAULT '[]',
                recommendations_json TEXT NOT NULL DEFAULT '[]',
                created_at TEXT NOT NULL,
                created_by TEXT NOT NULL,
                notes TEXT
            )"
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_evaluation_results_strategy_version
             ON evaluation_results(strategy_version_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_evaluation_results_backtest_result
             ON evaluation_results(backtest_result_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_evaluation_results_experiment
             ON evaluation_results(experiment_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_evaluation_results_status
             ON evaluation_results(status)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_evaluation_results_score
             ON evaluation_results(score)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_evaluation_results_created_at
             ON evaluation_results(created_at)'
        );
    }
};
