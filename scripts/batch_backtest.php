<?php

declare(strict_types=1);

require __DIR__ . '/../lib/JsonStore.php';
require __DIR__ . '/../lib/StrategyVersionService.php';
require __DIR__ . '/../lib/BacktestEngine.php';

$defaults = require __DIR__ . '/../config/defaults.php';
$store = new JsonStore(__DIR__ . '/../data', $defaults);
$versions = new StrategyVersionService($store);
$engine = new BacktestEngine($store, createBacktestV2StorageAdapter($defaults, dirname(__DIR__)));

$split = strtolower((string)($argv[1] ?? 'train'));
if (!in_array($split, ['train', 'validation', 'final'], true)) {
    fwrite(STDERR, "Usage: php scripts/batch_backtest.php train|validation|final [strict|balanced|loose-debug|current|all] [--preset=NAME] [--limit=N] [--persist]\n");
    exit(1);
}

$presetArg = 'all';
$limit = (int)(getenv('BATCH_BACKTEST_LIMIT') ?: 0);
$persist = false;
$symbolFilter = '';
for ($i = 2; $i < count($argv); $i++) {
    $arg = strtolower((string)$argv[$i]);
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int)substr($arg, 8));
        continue;
    }
    if (str_starts_with($arg, '--symbol=')) {
        $symbolFilter = strtoupper(substr((string)$argv[$i], 9));
        continue;
    }
    if (str_starts_with($arg, '--preset=')) {
        $presetArg = strtolower(substr((string)$argv[$i], 9));
        continue;
    }
    if ($arg === '--persist') {
        $persist = true;
        continue;
    }
    if (in_array($arg, array_merge(['current', 'all'], StrategyRuleParser::presetNames()), true)) {
        $presetArg = $arg;
    }
}

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

$symbols = discoverSymbols(__DIR__ . '/../data/market_data');
if ($symbolFilter !== '') {
    $symbols = array_values(array_filter($symbols, fn($symbol) => $symbol === $symbolFilter));
}
if ($limit > 0) {
    $symbols = array_slice($symbols, 0, $limit);
}

$presets = $presetArg === 'all' ? StrategyRuleParser::presetNames() : [$presetArg];
$rows = [];
foreach ($symbols as $symbol) {
    foreach ($presets as $preset) {
        try {
            $result = $engine->run($version, [
                'symbol' => $symbol,
                'dataSplit' => $split,
                'initialBalance' => 10000,
                'feeR' => 0.02,
                'slippagePoints' => 2,
                'rulesPreset' => $preset,
                'debugLevel' => 1,
                'persist' => $persist,
            ]);
            $summary = $result['summary'] ?? $result;
            $diagnostics = is_array($summary['diagnostics'] ?? null) ? $summary['diagnostics'] : [];
            $skipped = is_array($diagnostics['skippedReasons'] ?? null) ? $diagnostics['skippedReasons'] : ($summary['breakdowns']['skippedReasons'] ?? []);
            $rows[] = [
                'symbol' => $symbol,
                'preset' => $preset,
                'split' => $split,
                'totalTrades' => (int)($summary['totalTrades'] ?? 0),
                'sampleStatus' => (string)($summary['sampleStatus'] ?? ''),
                'winRate' => (float)($summary['winRate'] ?? 0),
                'netProfitPercent' => (float)($summary['netProfitPercent'] ?? 0),
                'maxDrawdownPercent' => (float)($summary['maxDrawdownPercent'] ?? 0),
                'profitFactor' => (float)($summary['profitFactor'] ?? 0),
                'skippedSetups' => (int)($summary['skippedSetups'] ?? $diagnostics['setupsRejected'] ?? 0),
                'topSkippedReason' => topSkippedReason($skipped),
                'topSkippedReasons' => topSkippedReasons($skipped, 3),
                'h4ZonesDetected' => (int)($diagnostics['h4SupplyZones'] ?? 0) + (int)($diagnostics['h4DemandZones'] ?? 0),
                'zoneActive' => (int)($diagnostics['zoneActive'] ?? 0),
                'zoneFresh' => (int)($diagnostics['zoneFresh'] ?? 0),
                'zoneTouched' => (int)($diagnostics['zoneTouched'] ?? 0),
                'zoneWeakened' => (int)($diagnostics['zoneWeakened'] ?? 0),
                'zoneBroken' => (int)($diagnostics['zoneBroken'] ?? 0),
                'pendingCreated' => (int)($diagnostics['pendingLimitCreated'] ?? 0),
                'pendingFilled' => (int)($diagnostics['pendingLimitFilled'] ?? 0),
            ];
        } catch (Throwable $error) {
            $rows[] = [
                'symbol' => $symbol,
                'preset' => $preset,
                'split' => $split,
                'totalTrades' => 0,
                'sampleStatus' => 'ERROR',
                'winRate' => 0,
                'netProfitPercent' => 0,
                'maxDrawdownPercent' => 0,
                'profitFactor' => 0,
                'skippedSetups' => 0,
                'topSkippedReason' => 'ERROR: ' . $error->getMessage(),
                'topSkippedReasons' => 'ERROR',
                'h4ZonesDetected' => 0,
                'zoneActive' => 0,
                'zoneFresh' => 0,
                'zoneTouched' => 0,
                'zoneWeakened' => 0,
                'zoneBroken' => 0,
                'pendingCreated' => 0,
                'pendingFilled' => 0,
            ];
        }
    }
}

printTable($rows);
printPresetComparison($rows);

function discoverSymbols(string $dir): array
{
    $m15 = [];
    $h4 = [];
    foreach (new DirectoryIterator($dir) as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $name = strtoupper($file->getFilename());
        if (preg_match('/^([A-Z0-9]+)_M15\.JSON$/', $name, $match)) {
            $m15[$match[1]] = true;
        }
        if (preg_match('/^([A-Z0-9]+)_H4\.JSON$/', $name, $match)) {
            $h4[$match[1]] = true;
        }
    }
    $symbols = array_values(array_intersect(array_keys($m15), array_keys($h4)));
    sort($symbols);
    return $symbols;
}

function topSkippedReason(array $skipped): string
{
    if (!$skipped) {
        return 'none';
    }
    arsort($skipped);
    $key = (string)array_key_first($skipped);
    return $key . ' (' . (int)$skipped[$key] . ')';
}

function topSkippedReasons(array $skipped, int $limit): string
{
    if (!$skipped) {
        return 'none';
    }
    arsort($skipped);
    $items = [];
    foreach (array_slice($skipped, 0, $limit, true) as $key => $value) {
        if ((int)$value > 0) {
            $items[] = (string)$key . ':' . (int)$value;
        }
    }
    return $items ? implode(',', $items) : 'none';
}

function printTable(array $rows): void
{
    $columns = [
        'symbol',
        'preset',
        'split',
        'totalTrades',
        'sampleStatus',
        'winRate',
        'netProfitPercent',
        'maxDrawdownPercent',
        'profitFactor',
        'skippedSetups',
        'topSkippedReason',
        'topSkippedReasons',
        'h4ZonesDetected',
        'zoneActive',
        'zoneFresh',
        'zoneTouched',
        'zoneWeakened',
        'zoneBroken',
        'pendingCreated',
        'pendingFilled',
    ];
    $widths = [];
    foreach ($columns as $column) {
        $widths[$column] = strlen($column);
    }
    foreach ($rows as $row) {
        foreach ($columns as $column) {
            $widths[$column] = max($widths[$column], strlen(formatCell($row[$column] ?? '')));
        }
    }
    echo implode(' | ', array_map(fn($column) => str_pad($column, $widths[$column]), $columns)) . PHP_EOL;
    echo implode('-+-', array_map(fn($column) => str_repeat('-', $widths[$column]), $columns)) . PHP_EOL;
    foreach ($rows as $row) {
        echo implode(' | ', array_map(fn($column) => str_pad(formatCell($row[$column] ?? ''), $widths[$column]), $columns)) . PHP_EOL;
    }
}

function printPresetComparison(array $rows): void
{
    $totals = [];
    foreach ($rows as $row) {
        $preset = (string)$row['preset'];
        $totals[$preset] ??= ['pendingCreated' => 0, 'pendingFilled' => 0, 'totalTrades' => 0, 'skippedSetups' => 0];
        $totals[$preset]['pendingCreated'] += (int)$row['pendingCreated'];
        $totals[$preset]['pendingFilled'] += (int)$row['pendingFilled'];
        $totals[$preset]['totalTrades'] += (int)$row['totalTrades'];
        $totals[$preset]['skippedSetups'] += (int)$row['skippedSetups'];
    }
    echo PHP_EOL . "Preset comparison:" . PHP_EOL;
    foreach ($totals as $preset => $total) {
        echo "- {$preset}: trades={$total['totalTrades']} pendingCreated={$total['pendingCreated']} pendingFilled={$total['pendingFilled']} skippedSetups={$total['skippedSetups']}" . PHP_EOL;
    }

    usort($rows, fn($a, $b) => ((int)$b['pendingCreated'] + (int)$b['skippedSetups']) <=> ((int)$a['pendingCreated'] + (int)$a['skippedSetups']));
    if ($rows) {
        $top = $rows[0];
        echo "Most setup activity: {$top['symbol']} {$top['preset']} pendingCreated={$top['pendingCreated']} skippedSetups={$top['skippedSetups']}" . PHP_EOL;
    }
}

function formatCell(mixed $value): string
{
    if (is_float($value)) {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
    return (string)$value;
}

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
