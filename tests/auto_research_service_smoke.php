<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/StrategyRuleParser.php';
require_once __DIR__ . '/../lib/MarketStructureDetector.php';
require_once __DIR__ . '/../lib/SupplyDemandZoneDetector.php';
require_once __DIR__ . '/../lib/M15EntryConfirmation.php';
require_once __DIR__ . '/../lib/StrategyVersionService.php';
require_once __DIR__ . '/../lib/BacktestEngine.php';
require_once __DIR__ . '/../lib/AutoResearchService.php';

assertContains((string)file_get_contents(__DIR__ . '/../api.php'), 'auto_research_run', 'api.php should expose auto_research_run action');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="runAutoResearchBtn"', 'index.php should expose one-click auto research button');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchModeSelect"', 'index.php should expose AI strategy discovery mode select');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchMethodSelect"', 'index.php should expose research method profile select');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'h4_trend_zone_m15_pa', 'index.php should expose locked H4/M15 PA method');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchTournamentToggle"', 'index.php should expose method tournament toggle');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchMethodChecklist"', 'index.php should expose multi-method tournament checklist');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchSymbolSelect" multiple', 'index.php should allow selecting multiple research symbols');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchTournamentBoard"', 'index.php should expose method tournament leaderboard');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchBrain"', 'index.php should expose Research Brain panel');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchTradeAudit"', 'index.php should expose research trade audit table');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="stopAutoResearchBtn"', 'index.php should expose stop auto research button');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchProgress"', 'index.php should expose auto research progress UI');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="autoResearchWorkflow"', 'index.php should expose the simplified auto research workflow guide');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'research-decision-panel', 'app.js should render the simplified research decision panel');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'workflow-details research-history-details', 'app.js should collapse recent research history by default');
assertContains((string)file_get_contents(__DIR__ . '/../assets/styles.css'), '.auto-research-workflow', 'styles.css should style the auto research workflow guide');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'runAutoResearch', 'app.js should bind auto research action');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'from_zero', 'app.js should send from-zero research mode');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'autoResearchSelectedSymbols', 'app.js should collect multiple research symbols');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'autoResearchMethodsForRun', 'app.js should collect multiple research methods');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'renderAutoResearchTournamentBoard', 'app.js should render method tournament leaderboard');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'autoResearchTournamentRankScore', 'app.js should score method tournament candidates');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'openAutoResearchTradeChart', 'app.js should let research trades open on chart');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'openAutoResearchRunBestChart', 'app.js should auto-open best research round on chart after each run');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'await openAutoResearchRunBestChart(data.research_run, true);', 'runAutoResearch should push each completed research result to chart immediately');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'summary.diagnostics = round.diagnostics', 'research chart runs should expose diagnostics to the chart sidebar');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'autoResearchTuningAdviceHtml', 'app.js should explain the active research optimization focus');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'renderAutoResearchBrain', 'app.js should render Research Brain learning summary');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'requestAutoResearchStop', 'app.js should support stopping auto research');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'autoResearchProgressSnapshot', 'app.js should compute auto research progress and ETA');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'signal: controller.signal', 'app.js should pass abort signal to auto research API calls');
assertContains((string)file_get_contents(__DIR__ . '/../assets/styles.css'), '.auto-research-progress', 'styles.css should style auto research progress UI');

$store = new JsonStore(autoResearchTempDir('auto_research'), require __DIR__ . '/../config/defaults.php');
$store->writeJson('market_data/EURUSD_M15.json', autoResearchSyntheticM15());
$store->writeJson('market_data/EURUSD_H4.json', autoResearchSyntheticH4());

$versions = new StrategyVersionService($store);
$service = new AutoResearchService($store, $versions, new BacktestEngine($store));
$result = $service->run([
    'symbol' => 'EURUSD',
    'maxRounds' => 1,
    'maxSeconds' => 30,
    'kpi' => [
        'winRateMin' => 99,
        'averageRRMin' => 3,
        'profitFactorMin' => 10,
        'maxDrawdownMax' => 1,
        'totalTradesMin' => 99,
    ],
    'settings' => [
        'feeR' => 0,
        'slippagePoints' => 0,
    ],
]);

assertTrue($result['ok'], 'auto research should return ok');
assertSame(1, $result['research_run']['roundsRun'], 'auto research should run one requested round');
assertSame('EURUSD', $result['research_run']['symbol'], 'auto research should preserve symbol');
assertSame('h4_trend_zone_m15_pa', $result['research_run']['methodProfile'], 'auto research default method should be locked H4/M15 PA');
assertTrue(is_array($result['research_run']['rounds'][0]['summary'] ?? null), 'round should include compact backtest summary');
assertTrue(array_key_exists('trades', $result['research_run']['rounds'][0]), 'round should include auditable trade list');
assertTrue(is_array($result['best_strategy_version'] ?? null), 'auto research should create one best research strategy version');
assertTrue(count($service->recentRuns()) === 1, 'recent runs should include persisted research run');
assertFalse((bool)$result['research_run']['safety']['liveTrading'], 'auto research must not enable live trading');
assertFalse((bool)$result['research_run']['safety']['mt5OrderExecution'], 'auto research must not send MT5 orders');

$fromZero = $service->run([
    'mode' => 'from_zero',
    'symbol' => 'EURUSD',
    'methodProfile' => 'pa_sd_h4_m15',
    'maxRounds' => 4,
    'maxSeconds' => 30,
    'kpi' => [
        'winRateMin' => 99,
        'averageRRMin' => 3,
        'profitFactorMin' => 10,
        'maxDrawdownMax' => 1,
        'totalTradesMin' => 99,
    ],
    'settings' => [
        'feeR' => 0,
        'slippagePoints' => 0,
    ],
]);

assertTrue($fromZero['ok'], 'from-zero strategy discovery should return ok');
assertSame('from_zero', $fromZero['research_run']['mode'], 'from-zero run should preserve discovery mode');
assertSame('pa_sd_h4_m15', $fromZero['research_run']['methodProfile'], 'from-zero run should preserve method profile');
assertSame('', $fromZero['research_run']['baseStrategyVersionId'], 'from-zero run should not require a base strategy version');
assertTrue(array_key_exists('parentVersionId', $fromZero['best_strategy_version']) && $fromZero['best_strategy_version']['parentVersionId'] === null, 'from-zero best version should be saved as a root strategy version');
assertTrue(($fromZero['research_run']['rounds'][0]['strategyFamily'] ?? '') !== '', 'from-zero round should expose a strategy family label');
assertTrue(str_starts_with((string)($fromZero['research_run']['rounds'][3]['strategyFamily'] ?? ''), 'adaptive_fast_'), 'from-zero discovery should switch to adaptive fast search after seed rounds');
assertTrue(($fromZero['research_run']['rounds'][3]['optimizationFocus'] ?? '') !== '', 'adaptive round should explain what it is optimizing');
assertTrue(array_key_exists('parentRound', $fromZero['research_run']['rounds'][3]), 'adaptive round should expose lineage parent round');

echo 'Auto research service smoke tests passed.' . PHP_EOL;

function autoResearchSyntheticM15(): array
{
    $bars = [];
    $time = 1700000000;
    $close = 1.1150;
    for ($i = 0; $i < 340; $i++) {
        $open = $close;
        $close = 1.1150 - min($i, 250) * 0.00002 + sin($i / 9) * 0.00008;
        $bars[] = autoResearchBar($time + $i * 900, $open, max($open, $close) + 0.00018, min($open, $close) - 0.00018, $close);
    }
    for ($i = 340; $i < 410; $i++) {
        $base = 1.1068;
        $bars[] = autoResearchBar($time + $i * 900, $base, $base + 0.00018, $base - 0.00018, $base);
    }

    return [
        'symbol' => 'EURUSD',
        'timeframe' => 'M15',
        'source' => 'synthetic',
        'fetched_at' => gmdate('c'),
        'bars' => $bars,
    ];
}

function autoResearchSyntheticH4(): array
{
    $time = 1700000000;
    $step = 14400;
    $bars = [
        autoResearchBar($time + 0 * $step, 1.0980, 1.1010, 1.0960, 1.0990),
        autoResearchBar($time + 1 * $step, 1.0990, 1.1050, 1.0980, 1.1040),
        autoResearchBar($time + 2 * $step, 1.1040, 1.1045, 1.0950, 1.0960),
        autoResearchBar($time + 3 * $step, 1.0960, 1.1000, 1.0940, 1.0950),
        autoResearchBar($time + 4 * $step, 1.0950, 1.1100, 1.0945, 1.1080),
        autoResearchBar($time + 5 * $step, 1.1080, 1.1120, 1.1040, 1.1100),
        autoResearchBar($time + 6 * $step, 1.1100, 1.1110, 1.1060, 1.1070),
        autoResearchBar($time + 7 * $step, 1.1070, 1.1110, 1.1065, 1.1090),
    ];

    return [
        'symbol' => 'EURUSD',
        'timeframe' => 'H4',
        'source' => 'synthetic',
        'fetched_at' => gmdate('c'),
        'bars' => $bars,
    ];
}

function autoResearchBar(int $time, float $open, float $high, float $low, float $close): array
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

function autoResearchTempDir(string $prefix): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '_' . bin2hex(random_bytes(4));
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create temp dir: ' . $dir);
    }
    return $dir;
}

function assertContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

