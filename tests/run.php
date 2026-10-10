<?php

declare(strict_types=1);

require __DIR__ . '/../lib/JsonStore.php';
require __DIR__ . '/../lib/StrategyRuleParser.php';
require __DIR__ . '/../lib/MarketStructureDetector.php';
require __DIR__ . '/../lib/SupplyDemandZoneDetector.php';
require __DIR__ . '/../lib/M15EntryConfirmation.php';
require __DIR__ . '/../lib/StrategyVersionService.php';
require __DIR__ . '/../lib/BacktestEngine.php';
require __DIR__ . '/../lib/DataQualityChecker.php';
require __DIR__ . '/../lib/StrategyEvaluator.php';
require __DIR__ . '/../lib/AiUsageTracker.php';
require __DIR__ . '/../lib/TradeBrainAiService.php';
require __DIR__ . '/../lib/TradeBrainService.php';
require __DIR__ . '/../lib/TradeBrainLiveSupervisor.php';
require __DIR__ . '/../lib/ManualReviewService.php';
require __DIR__ . '/../lib/CalibrationReport.php';
require __DIR__ . '/../lib/CalibrationAdvisor.php';
require __DIR__ . '/../lib/CalibratedPresetService.php';
require __DIR__ . '/../lib/CalibrationQueueBuilder.php';

$tests = [
    'market structure and supply/demand detectors' => 'testPriceActionDetectors',
    'h4 zone lifecycle and evolution guards' => 'testZoneLifecycleAndEvolutionGuard',
    'price-action backtest limit/TP/SL/skip/no-lookahead' => 'testBacktestEngine',
    'backtest diagnostics, presets, and batch script' => 'testBacktestDiagnosticsAndBatch',
    'chart backtest evidence ui smoke' => 'testChartBacktestUiSmokeFile',
    'strategy version parent/status' => 'testStrategyVersionService',
    'strategy v2 api/ui/backtest link smoke' => 'testStrategyV2SmokeFiles',
    'sidebar workflow grouping smoke' => 'testSidebarGroupingSmokeFile',
    'ai orchestrator api/ui smoke' => 'testAIJobSmokeFiles',
    'auto research one-click smoke' => 'testAutoResearchSmokeFile',
    'research lab two-agent smoke' => 'testResearchLabSmokeFile',
    'price action training ui smoke' => 'testPaTrainingSmokeFile',
    'llm provider framework smoke' => 'testLLMProviderFrameworkSmokeFiles',
    'openai chat provider smoke' => 'testOpenAIChatProviderSmokeFiles',
    'openai local setup smoke' => 'testOpenAILocalSetupSmokeFiles',
    'ollama local provider smoke' => 'testOllamaProviderSmokeFiles',
    'prompt manager foundation smoke' => 'testPromptManagerSmokeFiles',
    'llm gateway core smoke' => 'testLLMGatewayCoreSmokeFiles',
    'strategy evaluator rules' => 'testStrategyEvaluator',
    'tradebrain mock json/memory' => 'testTradeBrain',
    'manual calibration review/report/advisor/preset/import-export' => 'testManualCalibrationLoop',
    'live supervisor fail closed' => 'testLiveSupervisor',
];

$passed = 0;
foreach ($tests as $name => $fn) {
    $fn();
    echo "[PASS] {$name}\n";
    $passed++;
}

echo "Passed {$passed} tests.\n";

function testPriceActionDetectors(): void
{
    $detector = new MarketStructureDetector();
    $structure = $detector->analyze(structureBars('bullish'), ['fractalPeriod' => 1]);
    assertTrue(count(array_filter($structure['swings'], fn($swing) => $swing['type'] === 'swingHigh')) > 0, 'expected swing high');
    assertTrue(count(array_filter($structure['swings'], fn($swing) => $swing['type'] === 'swingLow')) > 0, 'expected swing low');
    assertTrue(count(array_filter($structure['swings'], fn($swing) => $swing['structure'] === 'higherHigh')) > 0, 'expected higher high classification');
    assertTrue(count(array_filter($structure['events'], fn($event) => $event['direction'] === 'bullish')) > 0, 'expected bullish break of structure');

    $bearish = $detector->analyze(structureBars('bearish'), ['fractalPeriod' => 1]);
    assertTrue(count(array_filter($bearish['events'], fn($event) => $event['direction'] === 'bearish')) > 0, 'expected bearish break of structure');

    $zones = (new SupplyDemandZoneDetector())->detect(structureBars('bullish'), $structure, 'EURUSD', 'H4');
    assertTrue(count(array_filter($zones, fn($zone) => $zone['type'] === 'demand')) > 0, 'expected demand zone');
    $supplyZones = (new SupplyDemandZoneDetector())->detect(structureBars('bearish'), $bearish, 'EURUSD', 'H4');
    assertTrue(count(array_filter($supplyZones, fn($zone) => $zone['type'] === 'supply')) > 0, 'expected supply zone');

    $m15 = syntheticPayload('WIN');
    $entry = new M15EntryConfirmation();
    $bad = $entry->evaluate($m15['bars'], 330, [
        'type' => 'demand',
        'high' => 1.0960,
        'low' => 1.1000,
        'strengthScore' => 90,
    ], $detector->analyze($m15['bars'], ['fractalPeriod' => 1]), syntheticVersion()['rulesJson'], 'EURUSD');
    assertSame('BAD_RR', $bad['reasonCode'], 'invalid zone geometry should reject bad RR');
}

function testZoneLifecycleAndEvolutionGuard(): void
{
    $detector = new SupplyDemandZoneDetector();
    $config = [
        'zoneLifecycle' => [
            'requireCloseBeyondZoneToBreak' => true,
            'allowWickThrough' => true,
            'wickToleranceAtr' => 0,
            'breakBufferAtr' => 0,
            'maxTouchesBeforeWeak' => 2,
            'maxTouchesBeforeInvalid' => 4,
            'markTouchedButNotBroken' => true,
        ],
    ];

    $demand = lifecycleZone('demand', 1.1000, 1.0950);
    $demandWick = $detector->applyLifecycle($demand, [
        bar(1700000000, 1.1010, 1.1020, 1.1000, 1.1010),
        bar(1700014400, 1.1005, 1.1010, 1.0938, 1.0962),
    ], $config);
    assertFalse((bool)$demandWick['broken'], 'demand wick through with close back inside must not be broken');
    assertSame(1, (int)$demandWick['touchedCount'], 'demand wick touch should increment touchedCount');

    $supply = lifecycleZone('supply', 1.1050, 1.1000);
    $supplyWick = $detector->applyLifecycle($supply, [
        bar(1700000000, 1.0990, 1.1000, 1.0980, 1.0995),
        bar(1700014400, 1.1010, 1.1062, 1.1002, 1.1042),
    ], $config);
    assertFalse((bool)$supplyWick['broken'], 'supply wick through with close back inside must not be broken');
    assertSame(1, (int)$supplyWick['touchedCount'], 'supply wick touch should increment touchedCount');

    $demandCloseBreak = $detector->applyLifecycle($demand, [
        bar(1700000000, 1.1010, 1.1020, 1.1000, 1.1010),
        bar(1700014400, 1.1005, 1.1010, 1.0938, 1.0946),
    ], $config);
    assertTrue((bool)$demandCloseBreak['broken'], 'close below demand low must break demand zone');

    $supplyCloseBreak = $detector->applyLifecycle($supply, [
        bar(1700000000, 1.0990, 1.1000, 1.0980, 1.0995),
        bar(1700014400, 1.1010, 1.1062, 1.1002, 1.1056),
    ], $config);
    assertTrue((bool)$supplyCloseBreak['broken'], 'close above supply high must break supply zone');

    $futureBreak = $detector->applyLifecycle($demand, [
        bar(1700000000, 1.1010, 1.1020, 1.1000, 1.1010),
        bar(1700014400, 1.1005, 1.1010, 1.0940, 1.0960),
        bar(1700028800, 1.0960, 1.0970, 1.0920, 1.0940),
    ], $config);
    $beforeBreak = $detector->stateAt($futureBreak, 1);
    $afterBreak = $detector->stateAt($futureBreak, 2);
    assertFalse((bool)$beforeBreak['broken'], 'active zone state must not use future broken candle');
    assertTrue((bool)$beforeBreak['touched'], 'touch before future break should be visible');
    assertTrue((bool)$afterBreak['broken'], 'state after close beyond zone should be broken');

    $store = new JsonStore(tempDir('tradebrain_guard'), defaults());
    $store->writeJson('market_data/EURUSD_M15.json', syntheticPayload('WIN'));
    $store->writeJson('market_data/EURUSD_H4.json', syntheticH4Payload('bullish'));
    $versions = new StrategyVersionService($store);
    $version = $versions->createFromCurrentStrategy(defaults()['strategy'], defaults()['strategy_summary'], defaults()['accounts']);
    $tradeBrain = new TradeBrainService(
        $store,
        $versions,
        new BacktestEngine($store),
        new StrategyEvaluator($store),
        new MockTradeBrainAiService(),
        new AiUsageTracker($store)
    );
    $blocked = false;
    try {
        $tradeBrain->evolve((string)$version['id'], 'EURUSD', ['feeR' => 0, 'slippagePoints' => 0]);
    } catch (RuntimeException $error) {
        $blocked = str_contains($error->getMessage(), 'INSUFFICIENT_SAMPLE');
    }
    assertTrue($blocked, 'insufficient sample must block TradeBrain evolution before proposal');
}

function testBacktestEngine(): void
{
    $engine = new BacktestEngine();
    $version = syntheticVersion();

    $win = $engine->runFromPayloads($version, syntheticPayload('WIN'), syntheticH4Payload('bullish'), ['dataSplit' => 'custom', 'persist' => false, 'feeR' => 0, 'slippagePoints' => 0, 'debugLevel' => 2]);
    $winTrades = closedTrades($win['trades']);
    assertTrue(count($winTrades) >= 1, 'expected at least one closed trade');
    assertSame('WIN', $winTrades[0]['result'], 'first trade should hit TP');
    assertTrue(str_contains($winTrades[0]['reasonEnter'], 'H4 demand'), 'entry reason should reference H4 demand');
    assertTrue(isset($win['summary']['breakdowns']['winrateByTags']), 'summary should include tag breakdowns');
    assertTrue(count($win['debug']['h4Zones']) > 0, 'debug payload should include H4 zones');
    assertTrue(isset($win['summary']['diagnostics']), 'summary should include diagnostics');
    assertSame('EURUSD', $win['summary']['diagnostics']['symbol'], 'diagnostics should store symbol');
    assertTrue($win['summary']['diagnostics']['pendingLimitCreated'] >= 1, 'diagnostics should count pending created');
    assertTrue($win['summary']['diagnostics']['pendingLimitFilled'] >= 1, 'diagnostics should count pending filled');
    assertTrue(count($win['debug']['setupCandidates'] ?? []) >= 1, 'debugLevel 2 should save setup candidates');
    assertSame('INSUFFICIENT_SAMPLE', $win['summary']['sampleStatus'], 'small synthetic run should be insufficient sample');
    assertTrue($win['summary']['winRate'] > 0, 'winrate should be positive');

    $loss = $engine->runFromPayloads($version, syntheticPayload('LOSS'), syntheticH4Payload('bullish'), ['dataSplit' => 'custom', 'persist' => false, 'feeR' => 0, 'slippagePoints' => 0]);
    $lossTrades = closedTrades($loss['trades']);
    assertSame('LOSS', $lossTrades[0]['result'], 'first trade should hit SL');
    assertTrue($loss['summary']['maxDrawdownPercent'] > 0, 'loss run should have drawdown');

    $cancel = $engine->runFromPayloads($version, syntheticPayload('CANCEL'), syntheticH4Payload('bullish'), ['dataSplit' => 'custom', 'persist' => false, 'feeR' => 0, 'slippagePoints' => 0]);
    assertTrue(count(array_filter($cancel['trades'], fn($trade) => ($trade['reasonCode'] ?? '') === 'LIMIT_NOT_FILLED')) > 0, 'expected limit order canceled');
    assertTrue(($cancel['summary']['diagnostics']['skippedReasons']['LIMIT_NOT_FILLED'] ?? 0) > 0, 'diagnostics should count limit not filled');

    $weak = syntheticVersion(['h4Rules' => ['minZoneStrength' => 99]]);
    $weakRun = $engine->runFromPayloads($weak, syntheticPayload('WIN'), syntheticH4Payload('bullish'), ['dataSplit' => 'custom', 'persist' => false, 'feeR' => 0, 'slippagePoints' => 0]);
    assertTrue(count(array_filter($weakRun['trades'], fn($trade) => ($trade['reasonCode'] ?? '') === 'WEAK_H4_ZONE')) > 0, 'expected weak H4 zone rejected');

    $mutated = syntheticPayload('WIN');
    for ($i = 340; $i < count($mutated['bars']); $i++) {
        $mutated['bars'][$i]['high'] += 5;
        $mutated['bars'][$i]['low'] = max(0.01, $mutated['bars'][$i]['low'] - 5);
    }
    $again = $engine->runFromPayloads($version, $mutated, syntheticH4Payload('bullish'), ['dataSplit' => 'custom', 'persist' => false, 'feeR' => 0, 'slippagePoints' => 0]);
    assertSame($winTrades[0]['entryTime'], closedTrades($again['trades'])[0]['entryTime'], 'future candles must not change first entry time');

    $checker = new DataQualityChecker(new JsonStore(tempDir('dq'), defaults()));
    $quality = $checker->analyzePayload(syntheticPayload('WIN'), 'synthetic');
    assertSame('ok', $quality['status'], 'synthetic candles should pass quality');
    assertTrue(isset($quality['splits']['train'], $quality['splits']['validation'], $quality['splits']['final']), 'split ranges should exist');
}

function testBacktestDiagnosticsAndBatch(): void
{
    $engine = new BacktestEngine();
    $version = syntheticVersion();
    $strict = $engine->runFromPayloads($version, syntheticPayload('WIN'), syntheticH4Payload('bullish'), ['dataSplit' => 'custom', 'persist' => false, 'feeR' => 0, 'slippagePoints' => 0, 'rulesPreset' => 'strict']);
    $balanced = $engine->runFromPayloads($version, syntheticPayload('WIN'), syntheticH4Payload('bullish'), ['dataSplit' => 'custom', 'persist' => false, 'feeR' => 0, 'slippagePoints' => 0, 'rulesPreset' => 'balanced']);
    $loose = $engine->runFromPayloads($version, syntheticPayload('WIN'), syntheticH4Payload('bullish'), ['dataSplit' => 'custom', 'persist' => false, 'feeR' => 0, 'slippagePoints' => 0, 'rulesPreset' => 'loose-debug']);

    $strictDiag = $strict['summary']['diagnostics'];
    $balancedDiag = $balanced['summary']['diagnostics'];
    $looseDiag = $loose['summary']['diagnostics'];
    assertTrue($strictDiag['pendingLimitCreated'] <= $balancedDiag['pendingLimitCreated'], 'strict preset should create <= balanced pending setups');
    assertTrue($looseDiag['pendingLimitCreated'] >= $strictDiag['pendingLimitCreated'], 'loose-debug should create >= strict pending setups');
    assertTrue(array_key_exists('NO_H4_ZONE', $strictDiag['skippedReasons']), 'skipped reason template should include NO_H4_ZONE');

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../scripts/batch_backtest.php') . ' train strict --limit=1 --no-persist';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'symbol') && str_contains($output, 'pendingCreated'), 'batch backtest should print table output');
}

function testStrategyVersionService(): void
{
    $store = new JsonStore(tempDir('versions'), defaults());
    $service = new StrategyVersionService($store);
    $v1 = $service->createFromCurrentStrategy(defaults()['strategy'], defaults()['strategy_summary'], defaults()['accounts']);
    $child = $service->createFromParent($v1['id'], 'v2 child', 'ai', 'test child', ['riskPercent' => 1, 'filters' => ['requireH4Trend' => true]]);

    assertSame($v1['id'], $child['parentVersionId'], 'child parentVersionId should match');
    assertSame('draft', $service->find($v1['id'])['status'], 'parent should remain unchanged');
    $updated = $service->updateStatus($child['id'], 'rejected', 'test reject');
    assertSame('rejected', $updated['status'], 'status transition should persist');
    assertTrue(count($service->diff($v1['id'], $child['id'])) > 0, 'diff should show rule changes');
}

function testChartBacktestUiSmokeFile(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/chart_backtest_ui_contract_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'chart_backtest_ui_contract_smoke.php should pass. Output: ' . (string)$output);
}

function testStrategyV2SmokeFiles(): void
{
    foreach (['strategy_v2_api_smoke.php', 'strategy_v2_ui_contract_smoke.php', 'strategy_v2_backtest_link_smoke.php'] as $file) {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $file) . ' 2>&1';
        $output = shell_exec($cmd);
        assertTrue(is_string($output) && str_contains($output, 'passed'), $file . ' should pass. Output: ' . (string)$output);
    }
}

function testAIJobSmokeFiles(): void
{
    foreach (['ai_job_api_smoke.php', 'ai_job_ui_contract_smoke.php', 'ai_worker_smoke.php', 'ai_job_strategy_draft_smoke.php', 'research_mock_run_once_smoke.php'] as $file) {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $file) . ' 2>&1';
        $output = shell_exec($cmd);
        assertTrue(is_string($output) && str_contains($output, 'passed'), $file . ' should pass. Output: ' . (string)$output);
    }
}

function testSidebarGroupingSmokeFile(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/sidebar_grouping_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'sidebar_grouping_smoke.php should pass. Output: ' . (string)$output);
}

function testAutoResearchSmokeFile(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/auto_research_service_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'auto_research_service_smoke.php should pass. Output: ' . (string)$output);
}
function testResearchLabSmokeFile(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/research_lab_service_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'research_lab_service_smoke.php should pass. Output: ' . (string)$output);
}

function testPaTrainingSmokeFile(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/pa_training_ui_contract_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'pa_training_ui_contract_smoke.php should pass. Output: ' . (string)$output);
}
function testLLMProviderFrameworkSmokeFiles(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/llm_provider_framework_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'llm_provider_framework_smoke.php should pass. Output: ' . (string)$output);
}

function testOpenAIChatProviderSmokeFiles(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/openai_chat_provider_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'openai_chat_provider_smoke.php should pass. Output: ' . (string)$output);
}

function testOpenAILocalSetupSmokeFiles(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/openai_local_setup_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'openai_local_setup_smoke.php should pass. Output: ' . (string)$output);
}

function testOllamaProviderSmokeFiles(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ollama_provider_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'ollama_provider_smoke.php should pass. Output: ' . (string)$output);
}

function testPromptManagerSmokeFiles(): void
{
    foreach (['prompt_manager_smoke.php', 'prompt_template_ui_contract_smoke.php'] as $file) {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $file) . ' 2>&1';
        $output = shell_exec($cmd);
        assertTrue(is_string($output) && str_contains($output, 'passed'), $file . ' should pass. Output: ' . (string)$output);
    }
}

function testLLMGatewayCoreSmokeFiles(): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/llm_gateway_core_smoke.php') . ' 2>&1';
    $output = shell_exec($cmd);
    assertTrue(is_string($output) && str_contains($output, 'passed'), 'llm_gateway_core_smoke.php should pass. Output: ' . (string)$output);
}

function testStrategyEvaluator(): void
{
    $evaluator = new StrategyEvaluator();
    $parent = syntheticVersion(['riskPercent' => 1]);
    $better = syntheticVersion(['riskPercent' => 1]);
    $riskier = syntheticVersion(['riskPercent' => 2]);

    $parentRuns = runs(10, 5, 1.5, 100, 3, 2);
    $goodRuns = runs(12, 4, 1.8, 100, 3, 2);
    assertTrue($evaluator->evaluate($parent, $better, $parentRuns, $goodRuns, false)['passed'], 'better validation/final should pass');

    $trainOnly = runs(20, 4, 2.0, 100, 3, 2);
    $badValidation = runs(5, 4, 1.8, 100, 3, 2);
    assertFalse($evaluator->evaluate($parent, $better, $parentRuns, ['train' => $trainOnly['train'], 'validation' => $badValidation['validation'], 'final' => $goodRuns['final']], false)['passed'], 'train-only improvement should fail validation');

    assertFalse($evaluator->evaluate($parent, $riskier, $parentRuns, $goodRuns, false)['passed'], 'risk increase should fail');
    $tooFew = runs(12, 4, 1.8, 20, 3, 2);
    assertFalse($evaluator->evaluate($parent, $better, $parentRuns, $tooFew, false)['passed'], 'too few trades should fail');
    $negativeFinal = $goodRuns;
    $negativeFinal['final'] = summaryRun(-1, 4, 1.5, 100, 3);
    assertFalse($evaluator->evaluate($parent, $better, $parentRuns, $negativeFinal, false)['passed'], 'negative final should fail');
}

function testTradeBrain(): void
{
    $store = new JsonStore(tempDir('tradebrain'), defaults());
    $versions = new StrategyVersionService($store);
    $version = $versions->createFromCurrentStrategy(defaults()['strategy'], defaults()['strategy_summary'], defaults()['accounts']);
    $ai = new MockTradeBrainAiService();
    $proposal = $ai->proposeStrategyUpgrade([
        'rulesJson' => $version['rulesJson'],
        'trainSummary' => ['profitFactor' => 0.8, 'maxDrawdownPercent' => 9, 'longestLossStreak' => 4, 'winRate' => 35],
        'trainTrades' => [['result' => 'LOSS', 'tags' => ['SIDEWAY']]],
        'memoryLessons' => [],
    ]);

    assertTrue(isset($proposal['diagnosis'], $proposal['lessons'], $proposal['proposedChanges']), 'mock AI output should be valid JSON shape');
    $updatedRules = $versions->applyChanges($version['rulesJson'], $proposal['proposedChanges']);
    assertTrue(($updatedRules['h4Rules']['detectTrend'] ?? false) === true, 'proposedChanges should keep H4 trend detection enabled');

    $tradeBrain = new TradeBrainService(
        $store,
        $versions,
        new BacktestEngine($store),
        new StrategyEvaluator($store),
        $ai,
        new AiUsageTracker($store)
    );
    $lesson = [
        'type' => 'backtest_lesson',
        'title' => 'Avoid weak context',
        'content' => 'Weak context creates clustered losses.',
        'confidence' => 80,
        'relatedStrategyVersionId' => $version['id'],
    ];
    $one = $tradeBrain->addMemory($lesson);
    $two = $tradeBrain->addMemory($lesson);
    assertSame($one['id'], $two['id'], 'duplicate lesson should not be stored twice');
}

function testManualCalibrationLoop(): void
{
    $dir = tempDir('calibration');
    $store = new JsonStore($dir, defaults());
    $service = new ManualReviewService($store);

    $zone = $service->save([
        'reviewType' => 'zone',
        'targetId' => 'zone_1',
        'symbol' => 'EURUSD',
        'timeframe' => 'H4',
        'label' => 'BROKEN_WRONG',
        'note' => 'close was not decisive',
        'relatedBacktestRunId' => 'bt_1',
        'relatedStrategyVersionId' => 'sv_1',
        'snapshotData' => ['reasonCode' => 'ZONE_ALREADY_BROKEN'],
    ]);
    $candidate = $service->save([
        'reviewType' => 'candidate',
        'targetId' => 'cand_1',
        'symbol' => 'EURUSD',
        'timeframe' => 'M15',
        'label' => 'NO_M15_CONFIRM_WRONG',
        'snapshotData' => ['reasonCode' => 'NO_M15_CONFIRMATION'],
    ]);
    $trade = $service->save([
        'reviewType' => 'trade',
        'targetId' => 'tr_1',
        'symbol' => 'EURUSD',
        'timeframe' => 'M15',
        'label' => 'TP_BAD',
        'snapshotData' => ['trade' => ['reasonCode' => 'TP']],
    ]);

    assertSame('zone', $zone['reviewType'], 'zone review should be saved');
    assertSame('candidate', $candidate['reviewType'], 'candidate review should be saved');
    assertSame('trade', $trade['reviewType'], 'trade review should be saved');

    $report = (new CalibrationReport())->build($service->all());
    assertSame(3, (int)$report['manualReviewsTotal'], 'report should count all manual reviews');
    assertSame(1.0, (float)$report['zoneReviews']['brokenWrongRate'], 'broken wrong rate should be calculated');
    assertSame(1.0, (float)$report['candidateReviews']['noM15ConfirmWrongRate'], 'no M15 wrong rate should be calculated');
    assertSame(1.0, (float)$report['tradeReviews']['tpBadRate'], 'TP bad rate should be calculated');

    $advisor = (new CalibrationAdvisor())->recommend($report, syntheticVersion()['rulesJson']);
    $fields = array_map(fn($item) => $item['field'], $advisor['recommendations']);
    assertTrue(in_array('h4Rules.zoneLifecycle.breakBufferAtr', $fields, true), 'advisor should recommend break buffer');
    assertTrue(in_array('m15Rules.requireStructureBreak', $fields, true), 'advisor should recommend M15 confirmation tuning');
    assertTrue(in_array('entryRules.takeProfitMode', $fields, true), 'advisor should recommend TP tuning');

    $versions = new StrategyVersionService($store);
    $version = $versions->createFromCurrentStrategy(defaults()['strategy'], defaults()['strategy_summary'], defaults()['accounts']);
    $presetService = new CalibratedPresetService($store);
    $preset = $presetService->create($version, 'balanced', $advisor['recommendations']);
    assertSame('balanced-calibrated-v1', $preset['name'], 'first tuned preset should be v1');
    assertSame('pending_user_approval', $preset['status'], 'tuned preset must require approval');
    assertTrue(count($preset['diff']) > 0, 'tuned preset should store diff');
    assertTrue(in_array('balanced', StrategyRuleParser::builtinPresetNames(), true), 'balanced preset must remain built-in');

    $export = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../scripts/export_manual_reviews.php') . ' --stdout --data-dir=' . escapeshellarg($dir));
    assertTrue(is_string($export) && str_contains($export, 'ai_trade_manual_reviews_v1'), 'export_manual_reviews should print snapshot');
    $exportPath = $dir . DIRECTORY_SEPARATOR . 'manual_reviews_export.json';
    file_put_contents($exportPath, $export);
    $importDir = tempDir('calibration_import');
    $import = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../scripts/import_manual_reviews.php') . ' ' . escapeshellarg($exportPath) . ' --data-dir=' . escapeshellarg($importDir));
    assertTrue(is_string($import) && str_contains($import, '"ok": true'), 'import_manual_reviews should import snapshot');
    $imported = new ManualReviewService(new JsonStore($importDir, defaults()));
    assertSame(3, count($imported->all()), 'imported manual reviews should be reusable');

    $guardStore = new JsonStore(tempDir('calibration_guard'), defaults());
    $guardStore->writeJson('market_data/EURUSD_M15.json', syntheticPayload('WIN'));
    $guardStore->writeJson('market_data/EURUSD_H4.json', syntheticH4Payload('bullish'));
    $guardVersions = new StrategyVersionService($guardStore);
    $guardVersion = $guardVersions->createFromCurrentStrategy(defaults()['strategy'], defaults()['strategy_summary'], defaults()['accounts']);
    $tradeBrain = new TradeBrainService(
        $guardStore,
        $guardVersions,
        new BacktestEngine($guardStore),
        new StrategyEvaluator($guardStore),
        new MockTradeBrainAiService(),
        new AiUsageTracker($guardStore)
    );
    $blocked = false;
    try {
        $tradeBrain->evolve((string)$guardVersion['id'], 'EURUSD', ['feeR' => 0, 'slippagePoints' => 0]);
    } catch (RuntimeException $error) {
        $blocked = str_contains($error->getMessage(), 'manual calibration') && str_contains($error->getMessage(), 'manualReviewsTotal < 30');
    }
    assertTrue($blocked, 'TradeBrain must be blocked before enough manual calibration reviews');
}

function testLiveSupervisor(): void
{
    $supervisor = new TradeBrainLiveSupervisor();
    $decision = $supervisor->reviewProposal([]);
    assertSame('REJECT', $decision['decision'], 'invalid live proposal must reject');
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
    if ($outcome === 'CANCEL') {
        $bars[] = bar($time + 331 * 900, 1.1030, 1.1060, 1.1018, 1.1052);
        $bars[] = bar($time + 332 * 900, 1.1052, 1.1080, 1.1045, 1.1070);
        $bars[] = bar($time + 333 * 900, 1.1070, 1.1090, 1.1060, 1.1080);
    } else {
        $bars[] = bar($time + 331 * 900, 1.1010, 1.1020, 1.0962, 1.0986);
    }
    if ($outcome === 'LOSS') {
        $bars[] = bar($time + 332 * 900, 1.0986, 1.1000, 1.0930, 1.0940);
    } elseif ($outcome === 'WIN') {
        $bars[] = bar($time + 332 * 900, 1.0986, 1.1075, 1.0962, 1.1068);
    }
    for ($i = count($bars); $i < 400; $i++) {
        $base = $outcome === 'LOSS' ? 1.0940 : ($outcome === 'CANCEL' ? 1.1080 : 1.1068);
        $bars[] = bar($time + $i * 900, $base, $base + 0.00018, $base - 0.00018, $base);
    }

    return [
        'symbol' => 'EURUSD',
        'timeframe' => 'M15',
        'source' => 'synthetic',
        'fetched_at' => gmdate('c'),
        'bars' => $bars,
    ];
}

function lifecycleZone(string $type, float $high, float $low): array
{
    return [
        'id' => 'zone_test_' . $type,
        'symbol' => 'EURUSD',
        'timeframe' => 'H4',
        'type' => $type,
        'createdAt' => gmdate('c', 1700000000),
        'createdIndex' => 0,
        'high' => $high,
        'low' => $low,
        'originCandleTime' => gmdate('c', 1700000000),
        'baseStrengthBreakdown' => [
            'bosScore' => 63,
            'impulseScore' => 12,
            'freshnessScore' => 0,
            'touchPenalty' => 0,
            'brokenPenalty' => 0,
            'trendContextScore' => 5,
            'finalScore' => 80,
        ],
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

function closedTrades(array $trades): array
{
    return array_values(array_filter($trades, fn($trade) => ($trade['result'] ?? '') !== 'SKIPPED'));
}

function runs(float $validationProfit, float $drawdown, float $profitFactor, int $trades, int $lossStreak, float $finalProfit): array
{
    return [
        'train' => summaryRun($validationProfit + 1, $drawdown, $profitFactor, $trades, $lossStreak),
        'validation' => summaryRun($validationProfit, $drawdown, $profitFactor, $trades, $lossStreak),
        'final' => summaryRun($finalProfit, $drawdown, $profitFactor, $trades, $lossStreak),
    ];
}

function summaryRun(float $profit, float $drawdown, float $profitFactor, int $trades, int $lossStreak): array
{
    return [
        'summary' => [
            'backtestRunId' => 'bt_test',
            'dataSplit' => 'test',
            'totalTrades' => $trades,
            'winRate' => 50,
            'netProfitPercent' => $profit,
            'maxDrawdownPercent' => $drawdown,
            'profitFactor' => $profitFactor,
            'averageRR' => 0.2,
            'longestLossStreak' => $lossStreak,
            'finalBalance' => 10000 + $profit,
        ],
    ];
}

function defaults(): array
{
    return [
        'accounts' => [['risk_per_trade_pct' => 1, 'max_open_trades' => 1]],
        'strategy' => [
            'name' => 'Test strategy',
            'symbols' => ['EURUSD'],
            'timeframes' => ['entry' => 'M15', 'bias' => 'H4'],
            'entries' => [['id' => 'test_setup', 'enabled' => true]],
            'trend' => ['min_confidence' => 65],
            'risk_reward' => ['min_rr' => 3, 'default_rr' => 3],
            'management' => ['partial_close' => false, 'be_trigger_r_min' => 1.8],
            'filters' => ['news_filter_enabled' => false],
        ],
        'strategy_summary' => ['title' => 'Test', 'sections' => []],
        'candidate_cases' => [],
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

