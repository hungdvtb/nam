<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$app = file_get_contents($root . '/assets/app.js') ?: '';
$index = file_get_contents($root . '/index.php') ?: '';
$styles = file_get_contents($root . '/assets/styles.css') ?: '';

assertContains('id="chartSideTradeList"', $index, 'chart sidebar should include backtest trade list');
assertContains('id="chartSideDiagnostics"', $index, 'chart sidebar should include backtest diagnostics');
assertContains('function openBacktestResultChart(backtest)', $app, 'run_backtest should open chart result helper');
assertContains('await openBacktestResultChart(data.backtest);', $app, 'runServerBacktest should open chart after render');
assertContains('function focusBacktestRunTradeRange(run)', $app, 'chart should focus the backtest trade range');
assertContains('function chartIndexForBarsTime(bars, value, fallback = 0)', $app, 'server trades should map to current chart timeframe by time');
assertContains('Nến: #${escapeHtml(number(candleNumber))}', $app, 'chart tooltip should show candle sequence number within loaded candles');
assertContains('id="chartJumpStartBtn"', $index, 'chart toolbar should include jump-to-start button');
assertContains('id="chartJumpEndBtn"', $index, 'chart toolbar should include jump-to-end button');
assertContains('id="chartFitHorizontalBtn"', $index, 'chart toolbar should include horizontal fit button');
assertContains('id="chartFitVerticalBtn"', $index, 'chart toolbar should include vertical fit button');
assertContains('function jumpChartToBoundary', $app, 'chart should jump quickly to first or latest candles');
assertContains('function fitChartHorizontally', $app, 'chart should fit only the horizontal candle range');
assertContains('function fitChartVertically', $app, 'chart should fit only the vertical price scale');
assertContains('function chartFutureOffsetLimit', $app, 'chart should allow panning into empty future space after the latest candle');
assertContains('barsFirstIndex', $app, 'chart rendering should keep actual candle indexes separate from virtual blank-space indexes');
assertContains('function drawAggregatedCandles', $app, 'chart should use aggregated candle rendering when zoomed far out');
assertContains('function chartSeriesSampleStep', $app, 'chart indicators should sample points when zoomed far out');
assertContains('.map((run) => serverRunToChartRun(run, data))', $app, 'server backtest runs should be normalized with active chart data');
assertContains("$('.drawing-list-head:not(.backtest-side-head) strong')", $app, 'price-action UI should not rename the backtest sidebar heading');
assertContains('.chart-side-trade', $styles, 'chart sidebar trades should have dedicated styling');
assertContains('.chart-diagnostics-grid', $styles, 'chart diagnostics should have dedicated styling');

echo "chart_backtest_ui_contract_smoke passed\n";

function assertContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . "\nMissing: " . $needle . "\n");
        exit(1);
    }
}
