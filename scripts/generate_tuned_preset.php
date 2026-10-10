<?php

declare(strict_types=1);

require __DIR__ . '/../lib/JsonStore.php';
require __DIR__ . '/../lib/StrategyRuleParser.php';
require __DIR__ . '/../lib/StrategyVersionService.php';
require __DIR__ . '/../lib/ManualReviewService.php';
require __DIR__ . '/../lib/CalibrationReport.php';
require __DIR__ . '/../lib/CalibrationAdvisor.php';
require __DIR__ . '/../lib/CalibratedPresetService.php';

$basePreset = strtolower((string)($argv[1] ?? 'balanced'));
$dataDir = __DIR__ . '/../data';
$versionId = '';
foreach ($argv as $arg) {
    if (str_starts_with((string)$arg, '--base=')) {
        $basePreset = strtolower(substr((string)$arg, 7));
    }
    if (str_starts_with((string)$arg, '--strategy-version=')) {
        $versionId = substr((string)$arg, 19);
    }
    if (str_starts_with((string)$arg, '--data-dir=')) {
        $dataDir = substr((string)$arg, 11);
    }
}

$defaults = require __DIR__ . '/../config/defaults.php';
$store = new JsonStore($dataDir, $defaults);
$versions = new StrategyVersionService($store);
$list = $versions->list();
if (!$list) {
    $version = $versions->createFromCurrentStrategy(
        $store->readJson('strategy.json', []),
        $store->readJson('strategy_summary.json', []),
        $store->readJson('accounts.json', [])
    );
} elseif ($versionId !== '') {
    $version = $versions->find($versionId);
    if ($version === null) {
        fwrite(STDERR, "Strategy version not found: {$versionId}\n");
        exit(1);
    }
} else {
    $version = $list[count($list) - 1];
}

$reviews = new ManualReviewService($store);
$report = (new CalibrationReport())->build($reviews->all());
$baseRules = StrategyRuleParser::applyBacktestPreset(is_array($version['rulesJson'] ?? null) ? $version['rulesJson'] : [], $basePreset);
$advisor = (new CalibrationAdvisor())->recommend($report, $baseRules);
$preset = (new CalibratedPresetService($store))->create($version, $basePreset, $advisor['recommendations'] ?? []);

echo json_encode([
    'ok' => true,
    'preset' => $preset,
    'recommendations' => $advisor['recommendations'] ?? [],
    'note' => 'Preset is pending_user_approval and does not overwrite strict/balanced/loose-debug.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
