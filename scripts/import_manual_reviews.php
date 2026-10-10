<?php

declare(strict_types=1);

require __DIR__ . '/../lib/JsonStore.php';
require __DIR__ . '/../lib/ManualReviewService.php';

$path = (string)($argv[1] ?? '');
$dataDir = __DIR__ . '/../data';
$replace = false;
foreach ($argv as $arg) {
    if ((string)$arg === '--replace') {
        $replace = true;
    }
    if (str_starts_with((string)$arg, '--data-dir=')) {
        $dataDir = substr((string)$arg, 11);
    }
}

if ($path === '' || str_starts_with($path, '--')) {
    fwrite(STDERR, "Usage: php scripts/import_manual_reviews.php path/to/file.json [--replace] [--data-dir=DIR]\n");
    exit(1);
}
if (!is_file($path)) {
    fwrite(STDERR, "Manual reviews file not found: {$path}\n");
    exit(1);
}

$content = file_get_contents($path);
$payload = $content === false ? null : json_decode($content, true);
if (!is_array($payload)) {
    fwrite(STDERR, "Manual reviews JSON is invalid.\n");
    exit(1);
}
if (isset($payload['format']) && $payload['format'] !== 'ai_trade_manual_reviews_v1') {
    fwrite(STDERR, "Manual reviews format is invalid.\n");
    exit(1);
}

$defaults = require __DIR__ . '/../config/defaults.php';
$store = new JsonStore($dataDir, $defaults);
$service = new ManualReviewService($store);
$result = $service->import($payload, $replace);

echo json_encode([
    'ok' => true,
    'imported' => $result['imported'],
    'total' => $result['total'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
