<?php

declare(strict_types=1);

require __DIR__ . '/../lib/JsonStore.php';
require __DIR__ . '/../lib/StrategyVersionService.php';
require __DIR__ . '/../lib/BacktestEngine.php';

$defaults = require __DIR__ . '/../config/defaults.php';
$store = new JsonStore(__DIR__ . '/../data', $defaults);
$versions = new StrategyVersionService($store);
$engine = new BacktestEngine($store, createBacktestV2StorageAdapter($defaults, dirname(__DIR__)));

$list = $versions->list();
if (!$list) {
    $version = $versions->createFromCurrentStrategy(
        $store->readJson('strategy.json', []),
        $store->readJson('strategy_summary.json', []),
        $store->readJson('accounts.json', [])
    );
} else {
    $version = $list[count($list) - 1];
}

$rules = $version['rulesJson'] ?? [];
$symbol = strtoupper((string)($argv[1] ?? $rules['symbols'][0] ?? 'EURUSD'));
$split = strtolower((string)($argv[2] ?? 'train'));
$preset = 'current';
$debugLevel = 2;
for ($i = 3; $i < count($argv); $i++) {
    $arg = (string)$argv[$i];
    $lower = strtolower($arg);
    if (str_starts_with($lower, '--preset=')) {
        $preset = strtolower(substr($arg, 9));
        continue;
    }
    if (str_starts_with($lower, '--debug=')) {
        $debugLevel = max(0, min(3, (int)substr($arg, 8)));
        continue;
    }
    if (in_array($lower, array_merge(['current'], StrategyRuleParser::presetNames()), true)) {
        $preset = $lower;
        continue;
    }
    if (is_numeric($arg)) {
        $debugLevel = max(0, min(3, (int)$arg));
    }
}

$result = $engine->run($version, [
    'symbol' => $symbol,
    'dataSplit' => $split,
    'initialBalance' => 10000,
    'feeR' => 0.02,
    'slippagePoints' => 2,
    'rulesPreset' => $preset,
    'debugLevel' => $debugLevel,
    'persist' => true,
]);
$summary = $result['summary'] ?? $result;
$versions->attachBacktestSummary((string)$version['id'], $summary);

echo json_encode([
    'ok' => true,
    'strategyVersionId' => $version['id'],
    'strategyVersionName' => $version['name'],
    'symbol' => $symbol,
    'dataSplit' => $split,
    'rulesPreset' => $summary['rulesPreset'] ?? $preset,
    'debugLevel' => $summary['debugLevel'] ?? $debugLevel,
    'backtestRunId' => $summary['backtestRunId'] ?? $summary['id'] ?? '',
    'totalTrades' => $summary['totalTrades'] ?? 0,
    'winRate' => $summary['winRate'] ?? 0,
    'netProfitPercent' => $summary['netProfitPercent'] ?? 0,
    'maxDrawdownPercent' => $summary['maxDrawdownPercent'] ?? 0,
    'profitFactor' => $summary['profitFactor'] ?? 0,
    'sampleStatus' => $summary['sampleStatus'] ?? '',
    'diagnostics' => $summary['diagnostics'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function createBacktestV2StorageAdapter(array $config, string $projectRoot): ?BacktestV2StorageAdapter
{
    $enabled = $config['enable_v2_backtest_storage'] ?? true;
    if (is_string($enabled)) {
        $enabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    }

    if (!$enabled) {
        return null;
    }

    $experimentAutoAttachEnabled = $config['enable_experiment_auto_attach'] ?? true;
    if (is_string($experimentAutoAttachEnabled)) {
        $experimentAutoAttachEnabled = filter_var($experimentAutoAttachEnabled, FILTER_VALIDATE_BOOLEAN);
    }

    try {
        return BacktestV2StorageAdapter::sqliteDefault($projectRoot, true, (bool)$experimentAutoAttachEnabled);
    } catch (Throwable $error) {
        error_log('AI Trade V2 backtest storage disabled: ' . $error->getMessage());
        return null;
    }
}
