<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/EvaluationResultRepositoryInterface.php';
require_once __DIR__ . '/../lib/Evaluations/EvaluationStatus.php';
require_once __DIR__ . '/../lib/Evaluations/EvaluationResultRecord.php';
require_once __DIR__ . '/../lib/Repositories/Json/JsonEvaluationResultRepository.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteEvaluationResultRepository.php';
require_once __DIR__ . '/../lib/EvaluationService.php';

testSQLiteEvaluationRepository();
testJsonEvaluationRepository();

echo 'Evaluation repository smoke tests passed.' . PHP_EOL;

function testSQLiteEvaluationRepository(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $foundation = require __DIR__ . '/../database/migrations/001_storage_foundation.php';
    $foundation->up($pdo);
    $migration = require __DIR__ . '/../database/migrations/005_evaluation_results.php';
    $migration->up($pdo);

    exerciseEvaluationService(new EvaluationService(new SQLiteEvaluationResultRepository($pdo)), 'sqlite');
}

function testJsonEvaluationRepository(): void
{
    $defaults = require __DIR__ . '/../config/defaults.php';
    $dir = sys_get_temp_dir() . '/ai_trade_evaluations_' . bin2hex(random_bytes(4));
    $store = new JsonStore($dir, $defaults);
    $adapter = new JsonCompatibilityAdapter($store);

    assertTrue($adapter->hasDataset('evaluation_results'), 'JSON adapter should expose evaluation_results dataset');

    exerciseEvaluationService(new EvaluationService(new JsonEvaluationResultRepository($adapter)), 'json');
}

function exerciseEvaluationService(EvaluationService $service, string $label): void
{
    $passing = $service->evaluateBacktestResult(
        sampleBacktestResult('bt_eval_' . $label . '_pass', 72.0, 2.3, 2.4, 8.0, 350),
        [],
        sampleContext('eval_' . $label . '_pass', '2026-01-01T00:00:00+00:00')
    );
    $savedPassing = $service->saveEvaluation($passing);
    assertSame(EvaluationStatus::PASSED, $savedPassing['status'], $label . ': passing result should be Passed');
    assertTrue($savedPassing['passed'], $label . ': passing result should set passed true');
    assertSame(100.0, $savedPassing['score'], $label . ': passing result should score 100');

    $warning = $service->evaluateBacktestResult(
        sampleBacktestResult('bt_eval_' . $label . '_warning', 68.0, 2.3, 2.4, 8.0, 350),
        [],
        sampleContext('eval_' . $label . '_warning', '2026-01-02T00:00:00+00:00')
    );
    $savedWarning = $service->saveEvaluation($warning);
    assertSame(EvaluationStatus::WARNING, $savedWarning['status'], $label . ': near miss should be Warning');
    assertTrue(!$savedWarning['passed'], $label . ': warning result should set passed false');
    assertTrue(count($savedWarning['recommendations_json']) > 0, $label . ': warning result should generate recommendations');

    $failed = $service->evaluateBacktestResult(
        sampleBacktestResult('bt_eval_' . $label . '_failed', 35.0, 0.8, 0.9, 24.0, 75),
        [],
        sampleContext('eval_' . $label . '_failed', '2026-01-03T00:00:00+00:00')
    );
    $savedFailed = $service->saveEvaluation($failed);
    assertSame(EvaluationStatus::FAILED, $savedFailed['status'], $label . ': weak result should be Failed');

    $found = $service->findEvaluation('eval_' . $label . '_pass');
    assertTrue($found !== null, $label . ': saved evaluation should be found');
    assertSame('bt_eval_' . $label . '_pass', $found['backtest_result_id'], $label . ': backtest result id should round-trip');

    $strategyEvaluations = $service->listByStrategyVersion('strategy_eval_v1');
    assertSame(3, count($strategyEvaluations), $label . ': listByStrategyVersion should return three rows');

    $experimentEvaluations = $service->listByExperiment('experiment_eval_1');
    assertSame(3, count($experimentEvaluations), $label . ': listByExperiment should return three rows');

    $latest = $service->getLatestEvaluation('strategy_eval_v1', 'experiment_eval_1');
    assertTrue($latest !== null, $label . ': latest evaluation should exist');
    assertSame('eval_' . $label . '_failed', $latest['id'], $label . ': latest evaluation should use created_at order');

    $best = $service->getBestEvaluation('strategy_eval_v1', 'experiment_eval_1');
    assertTrue($best !== null, $label . ': best evaluation should exist');
    assertSame('eval_' . $label . '_pass', $best['id'], $label . ': best evaluation should use highest score');
}

function sampleBacktestResult(
    string $id,
    float $winRate,
    float $averageRr,
    float $profitFactor,
    float $maxDrawdown,
    int $totalTrades
): array {
    return [
        'id' => $id,
        'strategy_version_id' => 'strategy_eval_v1',
        'symbol' => 'EURUSD',
        'timeframe' => 'M15',
        'higher_timeframe' => 'H4',
        'win_rate' => $winRate,
        'average_rr' => $averageRr,
        'profit_factor' => $profitFactor,
        'max_drawdown' => $maxDrawdown,
        'total_trades' => $totalTrades,
        'metrics_json' => [
            'winRate' => $winRate,
            'averageRR' => $averageRr,
            'profitFactor' => $profitFactor,
            'maxDrawdown' => $maxDrawdown,
            'totalTrades' => $totalTrades,
        ],
    ];
}

function sampleContext(string $id, string $createdAt): array
{
    return [
        'id' => $id,
        'experiment_id' => 'experiment_eval_1',
        'created_at' => $createdAt,
        'created_by' => 'smoke-test',
        'notes' => 'Evaluation smoke test',
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
