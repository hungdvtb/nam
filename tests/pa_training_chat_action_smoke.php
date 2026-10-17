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

echo "pa_training_chat_action_smoke passed\n";
