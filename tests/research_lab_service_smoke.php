<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/StrategyRuleParser.php';
require_once __DIR__ . '/../lib/MarketStructureDetector.php';
require_once __DIR__ . '/../lib/SupplyDemandZoneDetector.php';
require_once __DIR__ . '/../lib/M15EntryConfirmation.php';
require_once __DIR__ . '/../lib/StrategyVersionService.php';
require_once __DIR__ . '/../lib/BacktestEngine.php';
require_once __DIR__ . '/../lib/ResearchLabService.php';

assertContains((string)file_get_contents(__DIR__ . '/../api.php'), 'research_lab_run_cycle', 'api.php should expose AI Lab cycle endpoint');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="tab-research-lab"', 'index.php should expose AI Lab tab');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'researchLabAiProviderSelect', 'index.php should expose AI Lab provider select');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'h4_trend_zone_m15_pa', 'index.php should expose locked H4/M15 PA method');
assertContains((string)file_get_contents(__DIR__ . '/../index.php'), 'id="researchLabProgress"', 'index.php should expose AI Lab progress bar');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'runResearchLabLoop', 'app.js should bind AI Lab loop');
assertContains((string)file_get_contents(__DIR__ . '/../assets/app.js'), 'renderResearchLabProgress', 'app.js should render AI Lab progress');
assertContains((string)file_get_contents(__DIR__ . '/../assets/styles.css'), '.research-lab-flow', 'styles.css should style AI Lab flow');
assertContains((string)file_get_contents(__DIR__ . '/../assets/styles.css'), '.research-lab-progress', 'styles.css should style AI Lab progress');

$store = new JsonStore(researchLabTempDir('research_lab'), require __DIR__ . '/../config/defaults.php');
$store->writeJson('market_data/EURUSD_M15.json', researchLabM15());
$store->writeJson('market_data/EURUSD_H4.json', researchLabH4());

$versions = new StrategyVersionService($store);
$service = new ResearchLabService($store, $versions, new BacktestEngine($store));
$created = $service->create([
    'symbol' => 'EURUSD',
    'methodProfile' => 'h4_trend_zone_m15_pa',
    'aiProvider' => 'internal',
    'maxCycles' => 3,
    'kpi' => [
        'winRateMin' => 99,
        'averageRRMin' => 3,
        'profitFactorMin' => 10,
        'maxDrawdownMax' => 1,
        'totalTradesMin' => 99,
    ],
    'settings' => ['feeR' => 0, 'slippagePoints' => 0],
]);

assertTrue($created['ok'], 'AI Lab create should return ok');
$sessionId = $created['research_lab_session']['id'] ?? '';
assertTrue($sessionId !== '', 'AI Lab session should have id');
assertSame('h4_trend_zone_m15_pa', $created['research_lab_session']['methodProfile'], 'AI Lab should preserve locked H4/M15 PA method profile');
assertFalse((bool)$created['research_lab_session']['safety']['liveTrading'], 'AI Lab must not enable live trading');
assertFalse((bool)$created['research_lab_session']['safety']['mt5OrderExecution'], 'AI Lab must not send MT5 orders');

$cycleResult = $service->runCycle($sessionId);
assertTrue($cycleResult['ok'], 'AI Lab cycle should return ok');
$session = $cycleResult['research_lab_session'];
assertSame(1, count($session['cycles']), 'AI Lab should append one cycle');
$cycle = $session['cycles'][0];
assertTrue(is_array($cycle['researchAgent'] ?? null), 'cycle should include research agent');
assertTrue((bool)($cycle['researchAgent']['rulesJson']['m15Rules']['requirePriceActionPattern'] ?? false), 'AI Lab rules should require an M15 Price Action pattern');
assertTrue(is_array($cycle['backtestAgent']['summary'] ?? null), 'cycle should include backtest summary');
assertTrue(array_key_exists('trades', $cycle['backtestAgent']), 'cycle should include auditable trades');
assertTrue(is_array($cycle['reviewAgent'] ?? null), 'cycle should include review agent');

$cycleResult2 = $service->runCycle($sessionId);
assertTrue($cycleResult2['ok'], 'AI Lab second cycle should return ok');
$session2 = $cycleResult2['research_lab_session'];
assertSame(2, count($session2['cycles']), 'AI Lab should append a second cycle');
$cycle2 = $session2['cycles'][1];
assertSame('more_samples', $cycle2['researchAgent']['methodFamily'], 'cycle 2 should expand samples');
assertTrue((string)($cycle2['researchAgent']['rulesJson']['researchVariant'] ?? '') !== '', 'cycle 2 should store a concrete research variant');

$cycleResult3 = $service->runCycle($sessionId);
assertTrue($cycleResult3['ok'], 'AI Lab third cycle should return ok');
$session3 = $cycleResult3['research_lab_session'];
assertSame(3, count($session3['cycles']), 'AI Lab should append a third cycle');
$cycle3 = $session3['cycles'][2];
assertSame('quality', $cycle3['researchAgent']['methodFamily'], 'cycle 3 should switch to quality instead of repeating sample expansion');
assertTrue((string)($cycle3['researchAgent']['rulesJson']['researchVariant'] ?? '') !== (string)($cycle2['researchAgent']['rulesJson']['researchVariant'] ?? ''), 'AI Lab should not rerun the same research variant in consecutive cycles');
assertTrue(count($service->sessions()) === 1, 'AI Lab sessions should persist');

echo 'Research Lab service smoke tests passed.' . PHP_EOL;

function researchLabM15(): array
{
    $bars = [];
    $time = 1700000000;
    $close = 1.1150;
    for ($i = 0; $i < 430; $i++) {
        $open = $close;
        $close = 1.1150 - min($i, 260) * 0.00002 + sin($i / 9) * 0.00008;
        if ($i > 330) {
            $close = 1.1068 + sin($i / 5) * 0.0001;
        }
        $bars[] = researchLabBar($time + $i * 900, $open, max($open, $close) + 0.00018, min($open, $close) - 0.00018, $close);
    }
    return ['symbol' => 'EURUSD', 'timeframe' => 'M15', 'source' => 'synthetic', 'fetched_at' => gmdate('c'), 'bars' => $bars];
}

function researchLabH4(): array
{
    $time = 1700000000;
    $step = 14400;
    $bars = [
        researchLabBar($time + 0 * $step, 1.0980, 1.1010, 1.0960, 1.0990),
        researchLabBar($time + 1 * $step, 1.0990, 1.1050, 1.0980, 1.1040),
        researchLabBar($time + 2 * $step, 1.1040, 1.1045, 1.0950, 1.0960),
        researchLabBar($time + 3 * $step, 1.0960, 1.1000, 1.0940, 1.0950),
        researchLabBar($time + 4 * $step, 1.0950, 1.1100, 1.0945, 1.1080),
        researchLabBar($time + 5 * $step, 1.1080, 1.1120, 1.1040, 1.1100),
        researchLabBar($time + 6 * $step, 1.1100, 1.1110, 1.1060, 1.1070),
        researchLabBar($time + 7 * $step, 1.1070, 1.1110, 1.1065, 1.1090),
    ];
    return ['symbol' => 'EURUSD', 'timeframe' => 'H4', 'source' => 'synthetic', 'fetched_at' => gmdate('c'), 'bars' => $bars];
}

function researchLabBar(int $time, float $open, float $high, float $low, float $close): array
{
    return ['time' => $time, 'time_iso' => gmdate('c', $time), 'open' => $open, 'high' => $high, 'low' => $low, 'close' => $close, 'tick_volume' => 100, 'spread' => 0, 'real_volume' => 0];
}

function researchLabTempDir(string $prefix): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '_' . bin2hex(random_bytes(4));
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create temp dir: ' . $dir);
    }
    return $dir;
}

function assertContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
}