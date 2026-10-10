<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/BacktestResultRepositoryInterface.php';
require_once __DIR__ . '/../lib/Backtests/BacktestResultRecord.php';
require_once __DIR__ . '/../lib/Repositories/Json/JsonBacktestResultRepository.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteBacktestResultRepository.php';
require_once __DIR__ . '/../lib/BacktestResultService.php';

testSQLiteBacktestResultRepository();
testJsonBacktestResultRepository();

echo 'Backtest result repository smoke tests passed.' . PHP_EOL;

function testSQLiteBacktestResultRepository(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $foundation = require __DIR__ . '/../database/migrations/001_storage_foundation.php';
    $foundation->up($pdo);
    $migration = require __DIR__ . '/../database/migrations/003_backtest_results.php';
    $migration->up($pdo);

    $service = new BacktestResultService(new SQLiteBacktestResultRepository($pdo));
    exerciseRepository($service, 'sqlite');
}

function testJsonBacktestResultRepository(): void
{
    $defaults = require __DIR__ . '/../config/defaults.php';
    $dir = sys_get_temp_dir() . '/ai_trade_backtest_results_' . bin2hex(random_bytes(4));
    $store = new JsonStore($dir, $defaults);
    $adapter = new JsonCompatibilityAdapter($store);

    assertTrue($adapter->hasDataset('backtest_results'), 'JSON adapter should expose backtest_results dataset');

    $service = new BacktestResultService(new JsonBacktestResultRepository($adapter));
    exerciseRepository($service, 'json');
}

function exerciseRepository(BacktestResultService $service, string $label): void
{
    $service->saveResult(sampleResult('bt_result_' . $label . '_1', 'strategy_v_a', 1.25, '2026-01-01T00:00:00+00:00'));
    $service->saveResult(sampleResult('bt_result_' . $label . '_2', 'strategy_v_a', 2.75, '2026-01-02T00:00:00+00:00'));
    $service->saveResult(sampleResult('bt_result_' . $label . '_3', 'strategy_v_b', 9.00, '2026-01-03T00:00:00+00:00'));

    $found = $service->findResult('bt_result_' . $label . '_1');
    assertTrue($found !== null, $label . ': saved result should be found');
    assertSame('strategy_v_a', $found['strategy_version_id'], $label . ': strategy version id should round-trip');

    $strategyResults = $service->listByStrategyVersion('strategy_v_a');
    assertSame(2, count($strategyResults), $label . ': listByStrategyVersion should return two rows');

    $best = $service->getBestResult('strategy_v_a');
    assertTrue($best !== null, $label . ': best result should exist');
    assertSame('bt_result_' . $label . '_2', $best['id'], $label . ': best result should use highest profit factor');

    $latest = $service->getLatestResult('strategy_v_a');
    assertTrue($latest !== null, $label . ': latest result should exist');
    assertSame('bt_result_' . $label . '_2', $latest['id'], $label . ': latest result should use created_at order');

    assertTrue($service->deleteResult('bt_result_' . $label . '_1'), $label . ': deleteResult should delete an existing row');
    assertTrue($service->findResult('bt_result_' . $label . '_1') === null, $label . ': deleted row should not be found');
}

function sampleResult(string $id, string $strategyVersionId, float $profitFactor, string $createdAt): array
{
    return [
        'id' => $id,
        'strategy_version_id' => $strategyVersionId,
        'symbol' => 'EURUSD',
        'timeframe' => 'M15',
        'higher_timeframe' => 'H4',
        'start_date' => '2026-01-01T00:00:00+00:00',
        'end_date' => '2026-01-31T00:00:00+00:00',
        'initial_balance' => 10000,
        'final_balance' => 11250,
        'net_profit' => 1250,
        'win_rate' => 55.5,
        'average_rr' => 0.42,
        'profit_factor' => $profitFactor,
        'max_drawdown' => 4.8,
        'total_trades' => 42,
        'winning_trades' => 23,
        'losing_trades' => 19,
        'settings_json' => ['riskPercent' => 1, 'preset' => 'balanced'],
        'metrics_json' => ['profitFactor' => $profitFactor, 'totalTrades' => 42],
        'trades_json' => [
            ['id' => $id . '_trade_1', 'result' => 'WIN', 'r' => 3.0],
            ['id' => $id . '_trade_2', 'result' => 'LOSS', 'r' => -1.0],
        ],
        'report_path' => null,
        'created_at' => $createdAt,
        'created_by' => 'smoke-test',
        'notes' => 'Smoke test result',
    ];
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
