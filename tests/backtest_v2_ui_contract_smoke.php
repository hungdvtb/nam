<?php

declare(strict_types=1);

$index = file_get_contents(__DIR__ . '/../index.php');
$app = file_get_contents(__DIR__ . '/../assets/app.js');

assertContains('data-tab="backtest-v2"', $index, 'Backtest V2 navigation tab should exist');
assertContains('Kết quả kiểm thử V2', $index, 'Backtest V2 UI should use Vietnamese label');
assertContains('id="tab-backtest-v2"', $index, 'Backtest V2 tab panel should exist');
assertContains('id="backtestV2ResultList"', $index, 'Backtest V2 result list container should exist');
assertContains('id="backtestV2Detail"', $index, 'Backtest V2 detail container should exist');
assertContains('id="backtestV2Evaluation"', $index, 'Backtest V2 evaluation container should exist');
assertContains('id="backtestV2Reasons"', $index, 'Backtest V2 reasons container should exist');
assertContains('id="backtestV2Recommendations"', $index, 'Backtest V2 recommendations container should exist');

foreach ([
    'backtest_v2_list',
    'backtest_v2_find',
    'evaluation_list',
    'evaluation_find',
    'evaluation_latest',
    'evaluation_best',
] as $action) {
    assertContains($action, $app, "App should call {$action}");
}

assertContains('renderBacktestV2Results', $app, 'App should render Backtest V2 UI');
assertContains('selectedBacktestV2Id', $app, 'App state should track the selected V2 backtest result');

echo 'Backtest V2 UI contract smoke tests passed.' . PHP_EOL;

function assertContains(string $needle, string|false $haystack, string $message): void
{
    if (!is_string($haystack) || !str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}
