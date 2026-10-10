<?php

declare(strict_types=1);

$index = file_get_contents(__DIR__ . '/../index.php');
$app = file_get_contents(__DIR__ . '/../assets/app.js');

assertContains($index, 'data-tab="strategy-versions"', 'strategy version manager nav tab should exist');
assertContains($index, 'id="tab-strategy-versions"', 'strategy version manager panel should exist');
assertContains($index, 'id="strategyV2List"', 'strategy V2 list container should exist');
assertContains($index, 'id="strategyV2Detail"', 'strategy V2 detail container should exist');
assertContains($index, 'id="strategyV2RulesJson"', 'strategy V2 rules textarea should exist');
assertContains($index, 'id="runStrategyV2BacktestBtn"', 'strategy V2 run backtest button should exist');
assertContains($index, 'id="approveStrategyV2Btn"', 'strategy V2 approve button should exist');
assertContains($index, 'id="promoteStrategyV2Btn"', 'strategy V2 production button should exist');
assertContains($index, 'id="archiveStrategyV2Btn"', 'strategy V2 archive button should exist');

foreach ([
    'strategy_v2_list',
    'strategy_v2_create',
    'strategy_v2_clone',
    'strategy_v2_update',
    'strategy_v2_approve',
    'strategy_v2_production',
    'strategy_v2_archive',
] as $action) {
    assertContains($app, $action, $action . ' should be called by the frontend');
}

assertContains($app, 'runStrategyV2Backtest', 'App should expose Strategy V2 backtest action');
assertContains($app, "strategyVersionSource: 'v2'", 'Strategy V2 backtest action should mark V2 source');
assertContains($app, 'strategyVersionReferenceRow', 'Backtest V2 detail should render strategy version reference');
assertContains($app, 'strategyVersions: []', 'legacy strategyVersions state should remain present');
assertContains($app, 'strategyV2Versions: []', 'strategy V2 state should be separate from legacy state');
assertContains($index, 'id="strategyVersionList"', 'legacy strategy version list should remain present');
assertContains($app, "api('strategy_versions'", 'legacy strategy version API usage should remain present');

echo 'Strategy V2 UI contract smoke tests passed.' . PHP_EOL;

function assertContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}
