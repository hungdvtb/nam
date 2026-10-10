<?php

declare(strict_types=1);

return new class implements StorageMigrationInterface
{
    public function version(): string
    {
        return '003_backtest_results';
    }

    public function description(): string
    {
        return 'Create V2 backtest_results table for normalized backtest result storage.';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS backtest_results (
                id TEXT PRIMARY KEY,
                strategy_version_id TEXT,
                symbol TEXT,
                timeframe TEXT,
                higher_timeframe TEXT,
                start_date TEXT,
                end_date TEXT,
                initial_balance REAL,
                final_balance REAL,
                net_profit REAL,
                win_rate REAL,
                average_rr REAL,
                profit_factor REAL,
                max_drawdown REAL,
                total_trades INTEGER,
                winning_trades INTEGER,
                losing_trades INTEGER,
                settings_json TEXT NOT NULL DEFAULT "{}",
                metrics_json TEXT NOT NULL DEFAULT "{}",
                trades_json TEXT NOT NULL DEFAULT "[]",
                report_path TEXT,
                created_at TEXT NOT NULL,
                created_by TEXT NOT NULL,
                notes TEXT
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_backtest_results_strategy_version
             ON backtest_results(strategy_version_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_backtest_results_symbol_timeframe
             ON backtest_results(symbol, timeframe, higher_timeframe)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_backtest_results_created_at
             ON backtest_results(created_at)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_backtest_results_profit_factor
             ON backtest_results(profit_factor)'
        );
    }
};
