<?php

declare(strict_types=1);

return new class implements StorageMigrationInterface
{
    public function version(): string
    {
        return '014_ai_jobs';
    }

    public function description(): string
    {
        return 'Create AI Orchestrator job table for manual/fake AI job tracking.';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_jobs (
                id TEXT PRIMARY KEY,
                job_type TEXT NOT NULL CHECK(job_type IN ('GenerateStrategyIdea', 'ImproveStrategy', 'AnalyzeBacktest', 'EvaluateExperiment')),
                status TEXT NOT NULL CHECK(status IN ('Pending', 'Running', 'Completed', 'Failed', 'Cancelled')),
                input_json TEXT NOT NULL DEFAULT '{}',
                output_json TEXT NOT NULL DEFAULT '{}',
                error_message TEXT,
                strategy_version_id TEXT,
                experiment_id TEXT,
                created_at TEXT NOT NULL,
                started_at TEXT,
                completed_at TEXT,
                created_by TEXT NOT NULL,
                notes TEXT
            )"
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_ai_jobs_status
             ON ai_jobs(status)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_ai_jobs_type
             ON ai_jobs(job_type)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_ai_jobs_strategy_version
             ON ai_jobs(strategy_version_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_ai_jobs_experiment
             ON ai_jobs(experiment_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_ai_jobs_created_at
             ON ai_jobs(created_at)'
        );
    }
};
