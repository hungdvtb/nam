<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/BacktestResultRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/EvaluationResultRepositoryInterface.php';
require_once __DIR__ . '/../lib/Backtests/BacktestResultRecord.php';
require_once __DIR__ . '/../lib/Evaluations/EvaluationStatus.php';
require_once __DIR__ . '/../lib/Evaluations/EvaluationResultRecord.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteBacktestResultRepository.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteEvaluationResultRepository.php';
require_once __DIR__ . '/../lib/BacktestResultService.php';
require_once __DIR__ . '/../lib/EvaluationService.php';
require_once __DIR__ . '/../lib/Backtests/BacktestV2ApiController.php';

$pdo = sqliteMemoryWithV2Results();
$backtests = new BacktestResultService(new SQLiteBacktestResultRepository($pdo));
$evaluations = new EvaluationService(new SQLiteEvaluationResultRepository($pdo));
$controller = new BacktestV2ApiController($backtests, $evaluations);

$resultA = $backtests->saveResult(sampleBacktestResult('bt_v2_api_1', 'strategy_v2_api', 72.0, 2.3, 2.4, 8.0, 350, '2026-01-01T00:00:00+00:00'));
$resultB = $backtests->saveResult(sampleBacktestResult('bt_v2_api_2', 'strategy_v2_api', 35.0, 0.8, 0.9, 24.0, 75, '2026-01-02T00:00:00+00:00'));
$backtests->saveResult(sampleBacktestResult('bt_v2_api_other', 'strategy_other', 80.0, 2.5, 3.0, 5.0, 500, '2026-01-03T00:00:00+00:00'));

$evaluations->saveEvaluation($evaluations->evaluateBacktestResult($resultA, [], [
    'id' => 'eval_v2_api_1',
    'created_at' => '2026-01-01T00:05:00+00:00',
    'created_by' => 'api-smoke',
]));
$evaluations->saveEvaluation($evaluations->evaluateBacktestResult($resultB, [], [
    'id' => 'eval_v2_api_2',
    'created_at' => '2026-01-02T00:05:00+00:00',
    'created_by' => 'api-smoke',
]));

$listedBacktests = $controller->handle('backtest_v2_list', ['strategy_version_id' => 'strategy_v2_api']);
assertSame(2, count($listedBacktests['backtest_results']), 'backtest_v2_list should filter by strategy version');
assertSame('bt_v2_api_2', $listedBacktests['backtest_results'][0]['id'], 'backtest_v2_list should order newest first');

$limitedBacktests = $controller->handle('backtest_v2_list', ['limit' => 1]);
assertSame(1, count($limitedBacktests['backtest_results']), 'backtest_v2_list should respect limit');

$foundBacktest = $controller->handle('backtest_v2_find', ['id' => 'bt_v2_api_1']);
assertSame('EURUSD', $foundBacktest['backtest_result']['symbol'], 'backtest_v2_find should return result detail');

$listedEvaluations = $controller->handle('evaluation_list', ['strategy_version_id' => 'strategy_v2_api']);
assertSame(2, count($listedEvaluations['evaluations']), 'evaluation_list should filter by strategy version');

$backtestEvaluations = $controller->handle('evaluation_list', ['backtest_result_id' => 'bt_v2_api_1']);
assertSame(1, count($backtestEvaluations['evaluations']), 'evaluation_list should filter by backtest result');

$foundEvaluation = $controller->handle('evaluation_find', ['id' => 'eval_v2_api_1']);
assertSame(EvaluationStatus::PASSED, $foundEvaluation['evaluation']['status'], 'evaluation_find should return saved evaluation');

$latestEvaluation = $controller->handle('evaluation_latest', ['strategy_version_id' => 'strategy_v2_api']);
assertSame('eval_v2_api_2', $latestEvaluation['evaluation']['id'], 'evaluation_latest should use newest created_at');

$bestEvaluation = $controller->handle('evaluation_best', ['strategy_version_id' => 'strategy_v2_api']);
assertSame('eval_v2_api_1', $bestEvaluation['evaluation']['id'], 'evaluation_best should use highest score');

echo 'Backtest V2 API smoke tests passed.' . PHP_EOL;

function sqliteMemoryWithV2Results(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    foreach (['001_storage_foundation.php', '003_backtest_results.php', '005_evaluation_results.php'] as $file) {
        $migration = require __DIR__ . '/../database/migrations/' . $file;
        $migration->up($pdo);
    }

    return $pdo;
}

function sampleBacktestResult(
    string $id,
    string $strategyVersionId,
    float $winRate,
    float $averageRr,
    float $profitFactor,
    float $maxDrawdown,
    int $totalTrades,
    string $createdAt
): array {
    return [
        'id' => $id,
        'strategy_version_id' => $strategyVersionId,
        'symbol' => 'EURUSD',
        'timeframe' => 'M15',
        'higher_timeframe' => 'H4',
        'start_date' => '2026-01-01T00:00:00+00:00',
        'end_date' => '2026-01-31T00:00:00+00:00',
        'initial_balance' => 10000,
        'final_balance' => 11000,
        'net_profit' => 1000,
        'win_rate' => $winRate,
        'average_rr' => $averageRr,
        'profit_factor' => $profitFactor,
        'max_drawdown' => $maxDrawdown,
        'total_trades' => $totalTrades,
        'winning_trades' => (int)round($totalTrades * ($winRate / 100)),
        'losing_trades' => (int)round($totalTrades * (1 - ($winRate / 100))),
        'settings_json' => ['preset' => 'balanced'],
        'metrics_json' => [
            'winRate' => $winRate,
            'averageRR' => $averageRr,
            'profitFactor' => $profitFactor,
            'maxDrawdown' => $maxDrawdown,
            'totalTrades' => $totalTrades,
        ],
        'trades_json' => [],
        'created_at' => $createdAt,
        'created_by' => 'api-smoke',
        'notes' => 'Backtest V2 API smoke result',
    ];
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}
