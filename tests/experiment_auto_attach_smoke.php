<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Backtests/BacktestV2StorageAdapter.php';

function testNoRunningExperimentDoesNotAttach(): void
{
    $context = sqliteContext();
    $context['experiments']->createExperiment([
        'id' => 'exp_draft_only',
        'name' => 'Draft only',
        'status' => ExperimentStatus::DRAFT,
        'created_by' => 'smoke-test',
    ]);

    $status = $context['adapter']->storeAfterLegacyBacktest(
        sampleEngineOutput('bt_auto_no_running', 72.0, 2.3, 2.4, 8.0, 350),
        sampleStrategyVersion(),
        ['symbol' => 'EURUSD', 'dataSplit' => 'train']
    );

    assertTrue($status['ok'], 'no running experiment: V2 save should still succeed');
    assertTrue(!$status['experiment_attached'], 'no running experiment: nothing should be attached');
    assertSame(0, experimentItemCount($context['pdo']), 'no running experiment: no item should be created');
    assertSame('no_running_experiment', $status['experiment_attach']['reason'], 'no running experiment: status reason should explain no attach');
}

function testRunningExperimentCreatesItem(): void
{
    $context = sqliteContext();
    $experiment = $context['experiments']->createExperiment([
        'id' => 'exp_running_attach',
        'name' => 'Running attach',
        'status' => ExperimentStatus::DRAFT,
        'created_by' => 'smoke-test',
    ]);
    $context['experiments']->startExperiment((string)$experiment['id']);

    $status = $context['adapter']->storeAfterLegacyBacktest(
        sampleEngineOutput('bt_auto_running_passed', 72.0, 2.3, 2.4, 8.0, 350),
        sampleStrategyVersion(),
        ['symbol' => 'EURUSD', 'dataSplit' => 'train']
    );

    assertTrue($status['ok'], 'running experiment: V2 save should succeed');
    assertTrue($status['experiment_attached'], 'running experiment: item should be attached');

    $items = $context['experiments']->listItems('exp_running_attach');
    assertSame(1, count($items), 'running experiment: one item should be created');
    assertSame('BacktestResult', $items[0]['item_type'], 'running experiment: item type should be BacktestResult');
    assertSame('strategy_auto_attach', $items[0]['strategy_version_id'], 'running experiment: strategy version should be linked');
    assertSame('bt_auto_running_passed', $items[0]['backtest_result_id'], 'running experiment: backtest result should be linked');
    assertSame(ExperimentItemStatus::PASSED, $items[0]['status'], 'running experiment: Passed evaluation should map to Passed item');
    assertSame(100.0, $items[0]['score'], 'running experiment: score should come from evaluation');
    assertTrue(isset($items[0]['metrics_json']['metrics']), 'running experiment: metrics payload should include evaluation metrics');
    assertTrue(isset($items[0]['metrics_json']['reasons']), 'running experiment: metrics payload should include reasons');
    assertTrue(isset($items[0]['metrics_json']['recommendations']), 'running experiment: metrics payload should include recommendations');
}

function testEvaluationStatusMapsToExperimentItemStatus(): void
{
    $context = sqliteContext();
    $experiment = $context['experiments']->createExperiment([
        'id' => 'exp_status_mapping',
        'name' => 'Status mapping',
        'status' => ExperimentStatus::DRAFT,
        'created_by' => 'smoke-test',
    ]);
    $context['experiments']->startExperiment((string)$experiment['id']);

    $context['adapter']->storeAfterLegacyBacktest(
        sampleEngineOutput('bt_status_passed', 72.0, 2.3, 2.4, 8.0, 350),
        sampleStrategyVersion(),
        ['symbol' => 'EURUSD', 'dataSplit' => 'train']
    );
    $context['adapter']->storeAfterLegacyBacktest(
        sampleEngineOutput('bt_status_warning', 68.0, 2.3, 2.4, 8.0, 350),
        sampleStrategyVersion(),
        ['symbol' => 'EURUSD', 'dataSplit' => 'train']
    );
    $context['adapter']->storeAfterLegacyBacktest(
        sampleEngineOutput('bt_status_failed', 35.0, 0.8, 0.9, 24.0, 75),
        sampleStrategyVersion(),
        ['symbol' => 'EURUSD', 'dataSplit' => 'train']
    );

    $items = $context['experiments']->listItems('exp_status_mapping', 0);
    $statusesByBacktest = [];
    foreach ($items as $item) {
        $statusesByBacktest[(string)$item['backtest_result_id']] = $item['status'];
    }

    assertSame(ExperimentItemStatus::PASSED, $statusesByBacktest['bt_status_passed'] ?? null, 'Passed evaluation should map to Passed item');
    assertSame(ExperimentItemStatus::TESTING, $statusesByBacktest['bt_status_warning'] ?? null, 'Warning evaluation should map to Testing item');
    assertSame(ExperimentItemStatus::FAILED, $statusesByBacktest['bt_status_failed'] ?? null, 'Failed evaluation should map to Failed item');
}

function testExperimentAttachFailureDoesNotBreakV2BacktestFlow(): void
{
    $context = sqliteContext(false);
    $adapter = new BacktestV2StorageAdapter(
        new BacktestResultMapper(),
        new BacktestResultService($context['backtests']),
        new EvaluationService($context['evaluations']),
        true,
        new ExperimentAutoAttachService(new ExperimentService(new ThrowingExperimentRepository()))
    );

    $status = $adapter->storeAfterLegacyBacktest(
        sampleEngineOutput('bt_auto_attach_failure', 72.0, 2.3, 2.4, 8.0, 350),
        sampleStrategyVersion(),
        ['symbol' => 'EURUSD', 'dataSplit' => 'train']
    );

    assertTrue($status['ok'], 'attach failure: V2 backtest/evaluation save should still report ok');
    assertTrue($status['backtest_saved'], 'attach failure: backtest result should still be saved');
    assertTrue($status['evaluation_saved'], 'attach failure: evaluation result should still be saved');
    assertTrue(!$status['experiment_attached'], 'attach failure: experiment should not be attached');
    assertSame('attach_failed', $status['experiment_attach']['reason'], 'attach failure: status should report attach failure');
    assertTrue($context['backtests']->find('bt_auto_attach_failure') !== null, 'attach failure: backtest result should exist');
    assertTrue($context['evaluations']->find('eval_bt_auto_attach_failure') !== null, 'attach failure: evaluation result should exist');
}

function sqliteContext(bool $withExperiments = true): array
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    applyMigration($pdo, '001_storage_foundation.php');
    applyMigration($pdo, '003_backtest_results.php');
    if ($withExperiments) {
        applyMigration($pdo, '004_experiments.php');
    }
    applyMigration($pdo, '005_evaluation_results.php');

    $backtests = new SQLiteBacktestResultRepository($pdo);
    $evaluations = new SQLiteEvaluationResultRepository($pdo);
    $experiments = $withExperiments ? new ExperimentService(new SQLiteExperimentRepository($pdo)) : null;

    return [
        'pdo' => $pdo,
        'backtests' => $backtests,
        'evaluations' => $evaluations,
        'experiments' => $experiments,
        'adapter' => new BacktestV2StorageAdapter(
            new BacktestResultMapper(),
            new BacktestResultService($backtests),
            new EvaluationService($evaluations),
            true,
            $experiments === null ? null : new ExperimentAutoAttachService($experiments)
        ),
    ];
}

function applyMigration(PDO $pdo, string $file): void
{
    $migration = require __DIR__ . '/../database/migrations/' . $file;
    $migration->up($pdo);
}

function experimentItemCount(PDO $pdo): int
{
    return (int)$pdo->query('SELECT COUNT(*) FROM experiment_items')->fetchColumn();
}

function sampleEngineOutput(
    string $runId,
    float $winRate,
    float $averageRr,
    float $profitFactor,
    float $maxDrawdownPercent,
    int $totalTrades
): array {
    $wins = (int)round($totalTrades * $winRate / 100);
    $losses = max(0, $totalTrades - $wins);
    $summary = [
        'backtestRunId' => $runId,
        'id' => $runId,
        'strategyVersionId' => 'strategy_auto_attach',
        'strategyVersionName' => 'Auto Attach Strategy',
        'symbol' => 'EURUSD',
        'timeframe' => 'M15',
        'contextTimeframe' => 'H4',
        'rangeType' => 'train',
        'dataSplit' => 'train',
        'startDate' => '2026-01-01T00:00:00+00:00',
        'endDate' => '2026-06-30T00:00:00+00:00',
        'createdAt' => '2026-07-01T00:00:00+00:00',
        'settings' => ['initialBalance' => 10000, 'rulesPreset' => 'balanced'],
        'initialBalance' => 10000,
        'finalBalance' => 12000,
        'netProfit' => 2000,
        'totalTrades' => $totalTrades,
        'winTrades' => $wins,
        'lossTrades' => $losses,
        'winRate' => $winRate,
        'averageRR' => $averageRr,
        'profitFactor' => $profitFactor,
        'maxDrawdown' => $maxDrawdownPercent * 100,
        'maxDrawdownPercent' => $maxDrawdownPercent,
        'summaryNotes' => 'Experiment auto attach smoke test summary.',
    ];

    return [
        ...$summary,
        'summary' => $summary,
        'trades' => [
            ['id' => $runId . '_tr_1', 'backtestRunId' => $runId, 'result' => 'WIN', 'rrResult' => 3.0],
            ['id' => $runId . '_tr_2', 'backtestRunId' => $runId, 'result' => 'LOSS', 'rrResult' => -1.0],
        ],
    ];
}

function sampleStrategyVersion(): array
{
    return [
        'id' => 'strategy_auto_attach',
        'name' => 'Auto Attach Strategy',
        'rulesJson' => [
            'symbols' => ['EURUSD'],
            'entryTimeframe' => 'M15',
            'contextTimeframe' => 'H4',
        ],
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
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

final class ThrowingExperimentRepository implements ExperimentRepositoryInterface
{
    public function storageName(): string
    {
        return 'throwing_experiments';
    }

    public function health(): array
    {
        return ['readable' => false];
    }

    public function createExperiment(array $experiment): array
    {
        throw new RuntimeException('forced experiment failure');
    }

    public function updateExperiment(string $id, array $changes): array
    {
        throw new RuntimeException('forced experiment failure');
    }

    public function findExperiment(string $id): ?array
    {
        throw new RuntimeException('forced experiment failure');
    }

    public function listExperiments(int $limit = 100, array $filters = []): array
    {
        throw new RuntimeException('forced experiment failure');
    }

    public function addItem(array $item): array
    {
        throw new RuntimeException('forced experiment failure');
    }

    public function updateItem(string $id, array $changes): array
    {
        throw new RuntimeException('forced experiment failure');
    }

    public function findItem(string $id): ?array
    {
        throw new RuntimeException('forced experiment failure');
    }

    public function listItems(string $experimentId, int $limit = 100, array $filters = []): array
    {
        throw new RuntimeException('forced experiment failure');
    }

    public function bestItem(string $experimentId): ?array
    {
        throw new RuntimeException('forced experiment failure');
    }
}

testNoRunningExperimentDoesNotAttach();
testRunningExperimentCreatesItem();
testEvaluationStatusMapsToExperimentItemStatus();
testExperimentAttachFailureDoesNotBreakV2BacktestFlow();

echo 'Experiment auto attach smoke tests passed.' . PHP_EOL;
