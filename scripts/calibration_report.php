<?php

declare(strict_types=1);

require __DIR__ . '/../lib/JsonStore.php';
require __DIR__ . '/../lib/ManualReviewService.php';
require __DIR__ . '/../lib/CalibrationReport.php';

$dataDir = __DIR__ . '/../data';
foreach ($argv as $arg) {
    if (str_starts_with((string)$arg, '--data-dir=')) {
        $dataDir = substr((string)$arg, 11);
    }
}

$defaults = require __DIR__ . '/../config/defaults.php';
$store = new JsonStore($dataDir, $defaults);
$reviews = new ManualReviewService($store);
$report = (new CalibrationReport())->build($reviews->all());

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
