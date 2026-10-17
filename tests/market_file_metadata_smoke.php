<?php

declare(strict_types=1);

define('AI_TRADE_API_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/api.php';

$path = tempnam(sys_get_temp_dir(), 'market_file_metadata_');
if ($path === false) throw new RuntimeException('Could not create a temporary market file.');

try {
    $payload = [
        'symbol' => 'AUDJPY',
        'timeframe' => 'H4',
        'fetched_at' => '2026-10-17T00:00:00+00:00',
        'bars' => [
            ['time' => 1, 'note' => 'braces } and brackets ] inside a string'],
            ['time' => 2, 'nested' => ['marker' => '{not a bar}']],
            ['time' => 3],
        ],
    ];
    file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $result = marketFileMetadata($path, [
        'file' => 'AUDJPY_H4.json',
        'symbol' => '',
        'timeframe' => '',
        'bars' => 0,
        'fetched_at' => '',
    ]);

    if ($result['symbol'] !== 'AUDJPY' || $result['timeframe'] !== 'H4') {
        throw new RuntimeException('Market file identity metadata was not read.');
    }
    if ($result['bars'] !== 3) throw new RuntimeException('Expected 3 bars, got ' . $result['bars'] . '.');
    if ($result['fetched_at'] !== $payload['fetched_at']) throw new RuntimeException('Fetch timestamp was not read.');

    $fallbackHandle = fopen('php://temp', 'w+b');
    fwrite($fallbackHandle, '[{"open":1,"nested":{"marker":"{not a bar}"}},{"open":2}]');
    rewind($fallbackHandle);
    if (countMarketFileBars($fallbackHandle) !== 2) throw new RuntimeException('Fallback scanner did not count nested bar objects.');
    fclose($fallbackHandle);
} finally {
    @unlink($path);
}

echo "market_file_metadata_smoke passed\n";
