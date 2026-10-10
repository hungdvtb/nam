<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Backtests/BacktestV2StorageAdapter.php';

testBacktestWithoutStrategyVersionStillPersists();
testBacktestWithStrategyVersionLinksBacktestEvaluationAndExperiment();

echo 'Strategy V2 backtest link smoke tests passed.' . PHP_EOL;

function testBacktestWithoutStrategyVersionStillPersists(): void
{
    $context = strategyLinkContext(false);

    $status = $context['adapter']->storeAfterLegacyBacktest(
        strategyLinkEngineOutput('bt_without_strategy_version', null),
        [],
        ['symbol' => 'EURUSD', 'dataSplit' => 'train']
    );

    assertTrue($status['ok'], 'backtest without strategy version should save successfully');

    $savedBacktest = $context['backtests']->findResult('bt_without_strategy_version');
    assertTrue($savedBacktest !== null, 'backtest without strategy version should be persisted');
    assertSame(null, $savedBacktest['strategy_version_id'], 'backtest without strategy version should keep strategy_version_id null');

    $savedEvaluation = $context['evaluations']->findEvaluation('eval_bt_without_strategy_version');
    assertTrue($savedEvaluation !== null, 'evaluation without strategy version should be persisted');
    assertSame(null, $savedEvaluation['strategy_version_id'], 'evaluation without strategy version should keep strategy_version_id null');
}

function testBacktestWithStrategyVersionLinksBacktestEvaluationAndExperiment(): void
{
    $context = strategyLinkContext(true);
    $experiment = $context['experiments']->createExperiment([
        'id' => 'exp_strategy_v2_link',
        'name' => 'Strategy V2 Link',
        'status' => ExperimentStatus::DRAFT,
        'created_by' => 'smoke-test',
    ]);
    $context['experiments']->startExperiment((string)$experiment['id']);

    $status = $context['adapter']->storeAfterLegacyBacktest(
        strategyLinkEngineOutput('bt_with_strategy_v2_link', 'strategy_v2_linked'),
        strategyLinkVersion('strategy_v2_linked'),
        ['symbol' => 'EURUSD', 'dataSplit' => 'train', 'strategy_version_id' => 'strategy_v2_linked']
    );

    assertTrue($status['ok'], 'linked backtest should save successfully');
    assertTrue($status['experiment_attached'], 'linked backtest should auto-attach to running experiment');

    $savedBacktest = $context['backtests']->findResult('bt_with_strategy_v2_link');
    assertSame('strategy_v2_linked', $savedBacktest['strategy_version_id'], 'backtest result should store strategy_version_id');

    $savedEvaluation = $context['evaluations']->findEvaluation('eval_bt_with_strategy_v2_link');
    assertSame('strategy_v2_linked', $savedEvaluation['strategy_version_id'], 'evaluation should reference strategy_version_id');
    assertSame('bt_with_strategy_v2_link', $savedEvaluation['backtest_result_id'], 'evaluation should reference backtest result id');

    $items = $context['experiments']->listItems('exp_strategy_v2_link');
    assertSame(1, count($items), 'running experiment should receive one linked item');
    assertSame('strategy_v2_linked', $items[0]['strategy_version_id'], 'experiment item should reference strategy_version_id');
    assertSame('bt_with_strategy_v2_link', $items[0]['backtest_result_id'], 'experiment item should reference backtest result id');
}

function strategyLinkContext(bool $withExperimentAutoAttach): array
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    foreach (['001_storage_foundation.php', '003_backtest_results.php', '005_evaluation_results.php', '004_experiments.php'] as $file) {
        $migration = require __DIR__ . '/../database/migrations/' . $file;
        $migration->up($pdo);
    }

    $backtests = new BacktestResultService(new SQLiteBacktestResultRepository($pdo));
    $evaluations = new EvaluationService(new SQLiteEvaluationResultRepository($pdo));
    $experiments = new ExperimentService(new SQLiteExperimentRepository($pdo));

    return [
        'pdo' => $pdo,
        'backtests' => $backtests,
        'evaluations' => $evaluations,
        'experiments' => $experiments,
        'adapter' => new BacktestV2StorageAdapter(
            new BacktestResultMapper(),
            $backtests,
            $evaluations,
            true,
            $withExperimentAutoAttach ? new ExperimentAutoAttachService($experiments) : null
        ),
    ];
}

function strategyLinkEngineOutput(string $runId, ?string $strategyVersionId): array
{
    $summary = [
        'backtestRunId' => $runId,
        'id' => $runId,
        'strategyVersionName' => $strategyVersionId === null ? '' : 'Strategy V2 Linked',
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
        'finalBalance' => 12500,
        'netProfit' => 2500,
        'totalTrades' => 340,
        'winTrades' => 245,
        'lossTrades' => 95,
        'winRate' => 72.05,
        'averageRR' => 2.25,
        'profitFactor' => 2.5,
        'maxDrawdownPercent' => 7.2,
        'summaryNotes' => 'Strategy V2 backtest link smoke summary.',
    ];

    if ($strategyVersionId !== null) {
        $summary['strategyVersionId'] = $strategyVersionId;
    }

    return [
        ...$summary,
        'summary' => $summary,
        'trades' => [
            ['id' => 'tr_link_1', 'backtestRunId' => $runId, 'result' => 'WIN', 'rrResult' => 3.0],
            ['id' => 'tr_link_2', 'backtestRunId' => $runId, 'result' => 'LOSS', 'rrResult' => -1.0],
        ],
    ];
}

function strategyLinkVersion(string $id): array
{
    return [
        'id' => $id,
        'name' => 'Strategy V2 Linked',
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
