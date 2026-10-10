<?php

declare(strict_types=1);

require __DIR__ . '/../lib/JsonStore.php';
require __DIR__ . '/../lib/StrategyVersionService.php';
require __DIR__ . '/../lib/BacktestEngine.php';

$defaults = require __DIR__ . '/../config/defaults.php';
$store = new JsonStore(__DIR__ . '/../data', $defaults);
$versions = new StrategyVersionService($store);
$engine = new BacktestEngine($store);

$symbol = strtoupper((string)($argv[1] ?? ''));
$split = strtolower((string)($argv[2] ?? 'train'));
$reason = strtoupper((string)($argv[3] ?? ''));
$limit = max(1, (int)($argv[4] ?? 50));
$preset = 'balanced';
foreach ($argv as $arg) {
    if (str_starts_with((string)$arg, '--preset=')) {
        $preset = strtolower(substr((string)$arg, 9));
    }
}

if ($symbol === '' || $reason === '' || !in_array($split, ['train', 'validation', 'final', 'custom'], true)) {
    fwrite(STDERR, "Usage: php scripts/export_debug_samples.php SYMBOL train|validation|final REASON 50 [--preset=balanced]\n");
    exit(1);
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

$result = $engine->run($version, [
    'symbol' => $symbol,
    'dataSplit' => $split,
    'initialBalance' => 10000,
    'feeR' => 0.02,
    'slippagePoints' => 2,
    'rulesPreset' => $preset,
    'debugLevel' => 3,
    'persist' => false,
]);

$m15Payload = $store->readJson('market_data/' . $symbol . '_M15.json', []);
$h4Payload = $store->readJson('market_data/' . $symbol . '_H4.json', []);
$m15Bars = normalizeBars(is_array($m15Payload['bars'] ?? null) ? $m15Payload['bars'] : []);
$h4Bars = normalizeBars(is_array($h4Payload['bars'] ?? null) ? $h4Payload['bars'] : []);
$zones = [];
foreach (($result['debug']['h4Zones'] ?? []) as $zone) {
    if (is_array($zone) && (string)($zone['id'] ?? '') !== '') {
        $zones[(string)$zone['id']] = $zone;
    }
}

$candidates = $result['debug']['setupCandidates'] ?? $result['setup_candidates'] ?? [];
$matches = [];
foreach ($candidates as $candidate) {
    if (!is_array($candidate)) {
        continue;
    }
    $candidateReason = strtoupper((string)($candidate['rejectReason'] ?? $candidate['zoneRejectCode'] ?? ''));
    if ($candidateReason !== $reason) {
        continue;
    }
    $zoneId = (string)($candidate['zoneId'] ?? $candidate['h4ZoneId'] ?? '');
    $zone = $zones[$zoneId] ?? [];
    $timestamp = (int)($candidate['timestamp'] ?? strtotime((string)($candidate['time'] ?? '')) ?: 0);
    $matches[] = [
        'candidateTime' => (string)($candidate['time'] ?? ''),
        'reason' => $candidateReason,
        'zoneId' => $zoneId,
        'zoneType' => (string)($candidate['zoneType'] ?? ($zone['type'] ?? '')),
        'zoneHigh' => $candidate['zoneHigh'] ?? ($zone['high'] ?? null),
        'zoneLow' => $candidate['zoneLow'] ?? ($zone['low'] ?? null),
        'createdAt' => (string)($candidate['createdAt'] ?? ($zone['createdAt'] ?? '')),
        'originCandleTime' => (string)($candidate['originCandleTime'] ?? ($zone['originCandleTime'] ?? '')),
        'brokenStatusAtCandidate' => (bool)($candidate['zoneStateAtCandidate']['broken'] ?? false),
        'freshAtCandidate' => (bool)($candidate['zoneStateAtCandidate']['fresh'] ?? false),
        'touchedCountAtCandidate' => (int)($candidate['zoneStateAtCandidate']['touchedCount'] ?? 0),
        'lifecycleStatusAtCandidate' => (string)($candidate['zoneStateAtCandidate']['lifecycleStatus'] ?? ''),
        'brokenTime' => $candidate['brokenTime'] ?? ($zone['brokenTime'] ?? null),
        'brokenByCandle' => $candidate['brokenByCandle'] ?? ($zone['brokenByCandle'] ?? null),
        'brokenReason' => (string)($candidate['brokenReason'] ?? ($zone['brokenReason'] ?? '')),
        'strengthScore' => $candidate['strengthScore'] ?? ($candidate['zoneStrength'] ?? ($zone['strengthScore'] ?? null)),
        'strengthBreakdown' => $candidate['strengthBreakdown'] ?? ($zone['strengthBreakdown'] ?? null),
        'proposedEntry' => $candidate['proposedEntry'] ?? null,
        'proposedSL' => $candidate['proposedSL'] ?? null,
        'proposedTP' => $candidate['proposedTP'] ?? null,
        'rr' => $candidate['rr'] ?? null,
        'm15Confirmation' => [
            'priceReturnedToZone' => (bool)($candidate['priceReturnedToZone'] ?? false),
            'm15Reaction' => (bool)($candidate['m15Reaction'] ?? false),
            'm15StructureBreak' => (bool)($candidate['m15StructureBreak'] ?? false),
            'qmlDetected' => (bool)($candidate['qmlDetected'] ?? false),
        ],
        'nearbyM15Candles' => nearbyCandles($m15Bars, $timestamp, 12),
        'nearbyH4Candles' => nearbyCandles($h4Bars, $timestamp, 6),
    ];
    if (count($matches) >= $limit) {
        break;
    }
}

$safeReason = preg_replace('/[^A-Z0-9_\-]/', '_', $reason);
$stamp = gmdate('Ymd_His');
$base = __DIR__ . '/../data/exports/debug_samples_' . $symbol . '_' . $split . '_' . $safeReason . '_' . $stamp;
$jsonFile = $base . '.json';
$csvFile = $base . '.csv';

$payload = [
    'format' => 'ai_trade_debug_samples_v1',
    'exportedAt' => gmdate('c'),
    'symbol' => $symbol,
    'split' => $split,
    'preset' => $preset,
    'reason' => $reason,
    'requestedLimit' => $limit,
    'matched' => count($matches),
    'summary' => $result['summary'] ?? [],
    'samples' => $matches,
];
file_put_contents($jsonFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);

$handle = fopen($csvFile, 'wb');
if ($handle === false) {
    throw new RuntimeException('Cannot write CSV export.');
}
writeCsvRow($handle, [
    'candidateTime',
    'reason',
    'zoneId',
    'zoneType',
    'zoneLow',
    'zoneHigh',
    'createdAt',
    'brokenStatusAtCandidate',
    'brokenTime',
    'brokenReason',
    'strengthScore',
    'strengthBreakdown',
    'proposedEntry',
    'proposedSL',
    'proposedTP',
    'rr',
]);
foreach ($matches as $sample) {
    writeCsvRow($handle, [
        $sample['candidateTime'],
        $sample['reason'],
        $sample['zoneId'],
        $sample['zoneType'],
        $sample['zoneLow'],
        $sample['zoneHigh'],
        $sample['createdAt'],
        $sample['brokenStatusAtCandidate'] ? 'true' : 'false',
        $sample['brokenTime'],
        $sample['brokenReason'],
        $sample['strengthScore'],
        json_encode($sample['strengthBreakdown'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $sample['proposedEntry'],
        $sample['proposedSL'],
        $sample['proposedTP'],
        $sample['rr'],
    ]);
}
fclose($handle);

echo json_encode([
    'ok' => true,
    'matched' => count($matches),
    'json' => realpath($jsonFile) ?: $jsonFile,
    'csv' => realpath($csvFile) ?: $csvFile,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function normalizeBars(array $bars): array
{
    $normalized = [];
    foreach ($bars as $index => $bar) {
        if (!is_array($bar)) {
            continue;
        }
        $bar['_source_index'] = $index;
        $normalized[] = $bar;
    }
    usort($normalized, fn($a, $b) => barTime($a) <=> barTime($b));
    return $normalized;
}

function writeCsvRow($handle, array $fields): void
{
    fputcsv($handle, $fields, ',', '"', '');
}

function nearbyCandles(array $bars, int $timestamp, int $radius): array
{
    if (!$bars || $timestamp <= 0) {
        return [];
    }
    $best = 0;
    $bestDistance = PHP_INT_MAX;
    foreach ($bars as $index => $bar) {
        $distance = abs(barTime($bar) - $timestamp);
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $best = $index;
        }
    }
    $slice = array_slice($bars, max(0, $best - $radius), $radius * 2 + 1);
    return array_map(fn($bar) => [
        'time' => (string)($bar['time_iso'] ?? gmdate('c', barTime($bar))),
        'open' => (float)($bar['open'] ?? 0),
        'high' => (float)($bar['high'] ?? 0),
        'low' => (float)($bar['low'] ?? 0),
        'close' => (float)($bar['close'] ?? 0),
    ], $slice);
}

function barTime(array $bar): int
{
    if (isset($bar['time']) && is_numeric($bar['time'])) {
        return (int)$bar['time'];
    }
    $time = strtotime((string)($bar['time_iso'] ?? ''));
    return $time === false ? 0 : $time;
}
