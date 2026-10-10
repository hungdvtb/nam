<?php

declare(strict_types=1);

require __DIR__ . '/../lib/JsonStore.php';
require __DIR__ . '/../lib/ManualReviewService.php';

$dataDir = __DIR__ . '/../data';
$stdout = false;
foreach ($argv as $arg) {
    if ((string)$arg === '--stdout') {
        $stdout = true;
    }
    if (str_starts_with((string)$arg, '--data-dir=')) {
        $dataDir = substr((string)$arg, 11);
    }
}

$defaults = require __DIR__ . '/../config/defaults.php';
$store = new JsonStore($dataDir, $defaults);
$service = new ManualReviewService($store);
$snapshot = $service->exportSnapshot();

if ($stdout) {
    echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

$exportsDir = rtrim($dataDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'exports';
if (!is_dir($exportsDir)) {
    mkdir($exportsDir, 0775, true);
}
$file = $exportsDir . DIRECTORY_SEPARATOR . 'manual_reviews_' . gmdate('Ymd_His') . '.json';
file_put_contents($file, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);

echo json_encode([
    'ok' => true,
    'reviews' => count($snapshot['reviews'] ?? []),
    'file' => realpath($file) ?: $file,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
