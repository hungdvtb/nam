<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/BacktestEngine.php';

function testV2BacktestAndEvaluationSaved(): void
{
    $pdo = sqliteMemory();
    applyMigration($pdo, '001_storage_foundation.php');
    applyMigration($pdo, '003_backtest_results.php');
    applyMigration($pdo, '005_evaluation_results.php');

    $backtestRepository = new SQLiteBacktestResultRepository($pdo);
    $evaluationRepository = new SQLiteEvaluationResultRepository($pdo);
    $adapter = new BacktestV2StorageAdapter(
        new BacktestResultMapper(),
        new BacktestResultService($backtestRepository),
        new EvaluationService($evaluationRepository)
    );

    $status = $adapter->storeAfterLegacyBacktest(
        sampleEngineOutput('bt_v2_adapter_pass'),
        sampleStrategyVersion(),
        ['symbol' => 'EURUSD', 'dataSplit' => 'train', 'rulesPreset' => 'balanced']
    );

    assertTrue($status['ok'], 'adapter should save V2 result successfully');
    assertTrue($status['backtest_saved'], 'adapter should report backtest saved');
    assertTrue($status['evaluation_saved'], 'adapter should report evaluation saved');

    $savedBacktest = $backtestRepository->find('bt_v2_adapter_pass');
    assertTrue($savedBacktest !== null, 'V2 backtest result should be saved');
    assertSame('strategy_v2_adapter', $savedBacktest['strategy_version_id'], 'strategy version should round-trip');
    assertSame(7.5, $savedBacktest['max_drawdown'], 'max drawdown should map to percent for KPI use');
    assertSame(320, $savedBacktest['total_trades'], 'total trades should round-trip');

    $savedEvaluation = $evaluationRepository->find('eval_bt_v2_adapter_pass');
    assertTrue($savedEvaluation !== null, 'evaluation result should be saved');
    assertSame(EvaluationStatus::PASSED, $savedEvaluation['status'], 'passing KPI result should be Passed');
    assertTrue($savedEvaluation['passed'], 'passing KPI result should set passed true');
    assertSame('bt_v2_adapter_pass', $savedEvaluation['backtest_result_id'], 'evaluation should reference saved backtest result');
}

function testV2FailureDoesNotBreakLegacyBacktestPersistence(): void
{
    $store = new JsonStore(tempDir('v2_failure'), testDefaults());
    $adapter = new BacktestV2StorageAdapter(
        new BacktestResultMapper(),
        new BacktestResultService(new ThrowingBacktestResultRepository()),
        new EvaluationService(new ThrowingEvaluationResultRepository())
    );
    $engine = new BacktestEngine($store, $adapter);

    $result = $engine->runFromPayloads(
        syntheticVersion(),
        syntheticPayload('WIN'),
        syntheticH4Payload('bullish'),
        ['dataSplit' => 'custom', 'persist' => true, 'feeR' => 0, 'slippagePoints' => 0, 'debugLevel' => 1]
    );

    $runs = $store->readJson('backtests/backtest_runs.json', []);
    assertSame(1, count($runs), 'legacy run should still be persisted when V2 storage fails');
    assertSame($result['summary']['backtestRunId'], $runs[0]['backtestRunId'], 'legacy run id should match engine output');
}

function sqliteMemory(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function applyMigration(PDO $pdo, string $file): void
{
    $migration = require __DIR__ . '/../database/migrations/' . $file;
    $migration->up($pdo);
}

function sampleEngineOutput(string $runId): array
{
    $summary = [
        'backtestRunId' => $runId,
        'id' => $runId,
        'strategyVersionId' => 'strategy_v2_adapter',
        'strategyVersionName' => 'Adapter Strategy',
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
        'finalBalance' => 12400,
        'netProfit' => 2400,
        'totalTrades' => 320,
        'winTrades' => 230,
        'lossTrades' => 90,
        'winRate' => 71.875,
        'averageRR' => 2.15,
        'profitFactor' => 2.35,
        'maxDrawdown' => 750,
        'maxDrawdownPercent' => 7.5,
        'summaryNotes' => 'Adapter smoke test summary.',
    ];

    return [
        ...$summary,
        'summary' => $summary,
        'trades' => [
            ['id' => 'tr_1', 'backtestRunId' => $runId, 'result' => 'WIN', 'rrResult' => 3.1],
            ['id' => 'tr_2', 'backtestRunId' => $runId, 'result' => 'LOSS', 'rrResult' => -1.0],
        ],
    ];
}

function sampleStrategyVersion(): array
{
    return [
        'id' => 'strategy_v2_adapter',
        'name' => 'Adapter Strategy',
        'rulesJson' => [
            'symbols' => ['EURUSD'],
            'entryTimeframe' => 'M15',
            'contextTimeframe' => 'H4',
        ],
    ];
}

function syntheticVersion(array $ruleOverrides = []): array
{
    $rules = array_replace_recursive([
        'symbols' => ['EURUSD'],
        'contextTimeframe' => 'H4',
        'entryTimeframe' => 'M15',
        'entryMode' => 'LIMIT',
        'directionAllowed' => 'BUY_SELL',
        'minRR' => 3,
        'maxRiskPercent' => 3,
        'riskPercent' => 1,
        'maxOpenTrades' => 1,
        'setupTypes' => ['synthetic_h4_zone_m15_bos'],
        'aiScoreMin' => 0,
        'rrMax' => 3,
        'h4Rules' => [
            'detectTrend' => true,
            'trendMethod' => 'market_structure',
            'detectSupplyDemandZones' => true,
            'minZoneStrength' => 50,
            'allowCounterTrendOnlyIfZoneStrong' => true,
            'counterTrendZoneStrengthMin' => 90,
            'fractalPeriod' => 1,
        ],
        'm15Rules' => [
            'requireStructureBreak' => true,
            'requireQmlOrFakeBreak' => false,
            'requireReactionAtZone' => true,
            'avoidDirtySideway' => false,
            'fractalPeriod' => 1,
        ],
        'entryRules' => [
            'entryType' => 'LIMIT',
            'entryAt' => 'zone_extreme_or_50_percent',
            'stopLossMode' => 'behind_zone_or_swing',
            'takeProfitMode' => 'rr_or_opposite_zone',
            'minDistanceToTPR' => 3,
            'stopBufferPoints' => 2,
        ],
        'managementRules' => [
            'moveSlToBEAfterR' => 0,
            'partialCloseEnabled' => false,
            'cancelLimitAfterCandles' => 3,
        ],
        'filters' => [
            'avoidNewsWindow' => false,
            'avoidWeekend' => false,
            'maxSpread' => null,
        ],
        'exitRules' => [
            'stopLossMode' => 'behind_zone_or_swing',
            'takeProfitMode' => 'fixed_rr',
            'partialCloseRule' => 'none',
            'emergencyExitRule' => 'time_stop',
        ],
    ], $ruleOverrides);

    return [
        'id' => 'synthetic_v',
        'name' => 'Synthetic',
        'parentVersionId' => null,
        'status' => 'draft',
        'createdBy' => 'user',
        'createdAt' => gmdate('c'),
        'sourceStrategyText' => '',
        'rulesJson' => StrategyRuleParser::normalize($rules),
        'notes' => '',
    ];
}

function syntheticPayload(string $outcome): array
{
    $bars = [];
    $time = 1700000000;
    $close = 1.1150;
    for ($i = 0; $i < 320; $i++) {
        $open = $close;
        $close = 1.1150 - min($i, 250) * 0.00002 + sin($i / 9) * 0.00008;
        $bars[] = bar($time + $i * 900, $open, max($open, $close) + 0.00018, min($open, $close) - 0.00018, $close);
    }

    for ($i = 320; $i < 330; $i++) {
        $open = $close;
        $close = 1.1020 - ($i - 320) * 0.00045;
        $bars[] = bar($time + $i * 900, $open, max($open, $close) + 0.00015, min($open, $close) - 0.00015, $close);
    }

    $bars[] = bar($time + 330 * 900, 1.0982, 1.1032, 1.0952, 1.1024);
    if ($outcome === 'WIN') {
        $bars[] = bar($time + 331 * 900, 1.1010, 1.1020, 1.0962, 1.0986);
        $bars[] = bar($time + 332 * 900, 1.0986, 1.1075, 1.0962, 1.1068);
    }
    for ($i = count($bars); $i < 400; $i++) {
        $bars[] = bar($time + $i * 900, 1.1068, 1.1070, 1.1066, 1.1068);
    }

    return [
        'symbol' => 'EURUSD',
        'timeframe' => 'M15',
        'source' => 'synthetic',
        'fetched_at' => gmdate('c'),
        'bars' => $bars,
    ];
}

function syntheticH4Payload(string $shape): array
{
    return [
        'symbol' => 'EURUSD',
        'timeframe' => 'H4',
        'source' => 'synthetic',
        'fetched_at' => gmdate('c'),
        'bars' => structureBars($shape),
    ];
}

function structureBars(string $shape): array
{
    $time = 1700000000;
    $step = 14400;
    if ($shape === 'bearish') {
        return [
            bar($time + 0 * $step, 1.1200, 1.1220, 1.1180, 1.1210),
            bar($time + 1 * $step, 1.1210, 1.1240, 1.1190, 1.1230),
            bar($time + 2 * $step, 1.1230, 1.1235, 1.1150, 1.1160),
            bar($time + 3 * $step, 1.1160, 1.1210, 1.1155, 1.1200),
            bar($time + 4 * $step, 1.1200, 1.1205, 1.1080, 1.1090),
            bar($time + 5 * $step, 1.1090, 1.1120, 1.1070, 1.1085),
            bar($time + 6 * $step, 1.1085, 1.1100, 1.1060, 1.1075),
        ];
    }

    return [
        bar($time + 0 * $step, 1.0980, 1.1010, 1.0960, 1.0990),
        bar($time + 1 * $step, 1.0990, 1.1050, 1.0980, 1.1040),
        bar($time + 2 * $step, 1.1040, 1.1045, 1.0950, 1.0960),
        bar($time + 3 * $step, 1.0960, 1.1000, 1.0940, 1.0950),
        bar($time + 4 * $step, 1.0950, 1.1100, 1.0945, 1.1080),
        bar($time + 5 * $step, 1.1080, 1.1120, 1.1040, 1.1100),
        bar($time + 6 * $step, 1.1100, 1.1110, 1.1060, 1.1070),
        bar($time + 7 * $step, 1.1070, 1.1110, 1.1065, 1.1090),
    ];
}

function bar(int $time, float $open, float $high, float $low, float $close): array
{
    return [
        'time' => $time,
        'time_iso' => gmdate('c', $time),
        'open' => $open,
        'high' => $high,
        'low' => $low,
        'close' => $close,
        'tick_volume' => 100,
        'spread' => 0,
        'real_volume' => 0,
    ];
}

function testDefaults(): array
{
    return [
        'accounts' => [['risk_per_trade_pct' => 1, 'max_open_trades' => 1]],
        'strategy' => ['name' => 'Test strategy'],
        'strategy_summary' => ['title' => 'Test', 'sections' => []],
        'candidate_cases' => [],
        'enable_v2_backtest_storage' => true,
    ];
}

function tempDir(string $name): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_trade_tests_' . $name . '_' . bin2hex(random_bytes(3));
    mkdir($dir, 0775, true);
    return $dir;
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

final class ThrowingBacktestResultRepository implements BacktestResultRepositoryInterface
{
    public function storageName(): string
    {
        return 'throwing_backtest_results';
    }

    public function health(): array
    {
        return ['readable' => false];
    }

    public function save(array $result): array
    {
        throw new RuntimeException('forced V2 backtest storage failure');
    }

    public function find(string $id): ?array
    {
        return null;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        return [];
    }

    public function listByStrategyVersion(string $strategyVersionId, int $limit = 100): array
    {
        return [];
    }

    public function latest(?string $strategyVersionId = null): ?array
    {
        return null;
    }

    public function best(array $filters = [], string $metric = 'profit_factor'): ?array
    {
        return null;
    }

    public function delete(string $id): bool
    {
        return false;
    }
}

final class ThrowingEvaluationResultRepository implements EvaluationResultRepositoryInterface
{
    public function storageName(): string
    {
        return 'throwing_evaluation_results';
    }

    public function health(): array
    {
        return ['readable' => false];
    }

    public function save(array $evaluation): array
    {
        throw new RuntimeException('forced V2 evaluation storage failure');
    }

    public function find(string $id): ?array
    {
        return null;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        return [];
    }

    public function listByStrategyVersion(string $strategyVersionId, int $limit = 100): array
    {
        return [];
    }

    public function listByExperiment(string $experimentId, int $limit = 100): array
    {
        return [];
    }

    public function latest(array $filters = []): ?array
    {
        return null;
    }

    public function best(array $filters = []): ?array
    {
        return null;
    }
}

testV2BacktestAndEvaluationSaved();
testV2FailureDoesNotBreakLegacyBacktestPersistence();

echo 'Backtest V2 storage adapter smoke tests passed.' . PHP_EOL;
