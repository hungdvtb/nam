<?php

declare(strict_types=1);

define('AI_TRADE_API_LIBRARY_ONLY', true);
require_once dirname(__DIR__) . '/api.php';

$assertAction = static function (string $message, string $symbol, string $timeframe = ''): void {
    $actions = paTrainingChatActions($message);
    foreach ($actions as $action) {
        if (($action['type'] ?? '') === 'open_symbol_chart'
            && ($action['symbol'] ?? '') === $symbol
            && ($action['timeframe'] ?? '') === $timeframe) {
            return;
        }
    }
    throw new RuntimeException("Expected open_symbol_chart {$symbol} {$timeframe} for: {$message}");
};

$assertAction('mở biểu đồ âu', 'AUDUSD');
$assertAction('Mở chart GU H4', 'GBPUSD', 'H4');
$assertAction('xem biểu đồ EURUSD M15', 'EURUSD', 'M15');

$drawActions = paTrainingChatActions('vẽ cho t vùng cung 0.69812-0.69911', ['symbol' => 'AUDUSD', 'timeframe' => 'H4']);
$drawAction = array_values(array_filter($drawActions, static fn(array $action): bool => ($action['type'] ?? '') === 'draw_chart_annotation'))[0] ?? null;
if (!is_array($drawAction)
    || ($drawAction['drawing']['type'] ?? '') !== 'rect'
    || ($drawAction['drawing']['priceRange'] ?? []) !== [0.69812, 0.69911]
    || ($drawAction['drawing']['zoneType'] ?? '') !== 'supply') {
    throw new RuntimeException('Expected an executable supply-zone drawing action from explicit price bounds');
}

if (!paTrainingIsChartDrawingRequest('vẽ đường xu hướng')) {
    throw new RuntimeException('Expected natural chart-drawing requests to be recognized');
}
$normalized = paTrainingNormalizeAiChartActions([[
    'type' => 'draw_chart_annotation',
    'drawing' => ['type' => 'trendline', 'points' => [['barIndex' => 12, 'price' => 1.1], ['barIndex' => 80, 'price' => 1.2]], 'color' => '#2563eb'],
]], ['symbol' => 'EURUSD', 'timeframe' => 'H4'], ['barCount' => 100]);
if (count($normalized) !== 1 || ($normalized[0]['drawing']['points'][1]['barIndex'] ?? null) !== 80) {
    throw new RuntimeException('Expected valid AI chart points to be preserved');
}
$outOfRange = paTrainingNormalizeAiChartActions([[
    'type' => 'draw_chart_annotation',
    'drawing' => ['type' => 'trendline', 'points' => [['barIndex' => 12, 'price' => 1.1], ['barIndex' => 180, 'price' => 1.2]]],
]], ['symbol' => 'EURUSD', 'timeframe' => 'H4'], ['barCount' => 100]);
if ($outOfRange !== []) {
    throw new RuntimeException('AI drawing points outside loaded candles must be rejected instead of shifted');
}

echo "pa_training_chat_action_smoke passed\n";
