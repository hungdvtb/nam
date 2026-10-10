<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/StrategyVersionService.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteStrategyVersionRepository.php';
require_once __DIR__ . '/../lib/StrategyVersions/StrategyVersionApiController.php';

$store = new JsonStore(strategyV2TempDir('strategy_v2_api'), require __DIR__ . '/../config/defaults.php');
$controller = new StrategyVersionApiController(
    new StrategyVersionService($store, new SQLiteStrategyVersionRepository(strategyV2SqliteMemory()))
);

$created = $controller->handle('strategy_v2_create', [
    'id' => 'strategy_v2_api_alpha',
    'version_code' => 'SV-API-ALPHA',
    'name' => 'API Alpha',
    'description' => 'Smoke test version',
    'rules_json' => ['symbols' => ['EURUSD'], 'riskPercent' => 1],
    'settings_json' => ['preset' => 'balanced'],
    'performance_json' => ['profitFactor' => 1.8, 'totalTrades' => 120],
]);
assertTrue($created['ok'], 'strategy_v2_create should return ok');
assertSame('strategy_v2_api_alpha', $created['strategy_version']['id'], 'created id should round-trip');
assertSame('Draft', $created['strategy_version']['status'], 'created status should normalize to Draft');

$found = $controller->handle('strategy_v2_find', ['id' => 'strategy_v2_api_alpha']);
assertSame('SV-API-ALPHA', $found['strategy_version']['version_code'], 'strategy_v2_find should return created version');

$updated = $controller->handle('strategy_v2_update', [
    'id' => 'strategy_v2_api_alpha',
    'status' => 'Testing',
    'settings_json' => ['preset' => 'strict'],
]);
assertSame('Testing', $updated['strategy_version']['status'], 'strategy_v2_update should update status');
assertSame('strict', $updated['strategy_version']['settings_json']['preset'], 'strategy_v2_update should update settings JSON');

$cloned = $controller->handle('strategy_v2_clone', [
    'sourceVersionId' => 'strategy_v2_api_alpha',
    'id' => 'strategy_v2_api_beta',
    'name' => 'API Beta',
]);
assertSame('strategy_v2_api_alpha', $cloned['strategy_version']['parent_version_id'], 'strategy_v2_clone should set parent id');
assertSame('Draft', $cloned['strategy_version']['status'], 'strategy_v2_clone should create a Draft version');

$approved = $controller->handle('strategy_v2_approve', ['id' => 'strategy_v2_api_alpha']);
assertSame('Passed', $approved['strategy_version']['status'], 'strategy_v2_approve should mark version Passed');
assertTrue($approved['strategy_version']['approved'], 'strategy_v2_approve should set approved flag');

$production = $controller->handle('strategy_v2_production', ['id' => 'strategy_v2_api_alpha']);
assertSame('Production', $production['strategy_version']['status'], 'strategy_v2_production with id should promote version');
assertSame('strategy_v2_api_alpha', $production['production_strategy_version']['id'], 'strategy_v2_production should return current production');

$currentProduction = $controller->handle('strategy_v2_production', []);
assertSame('strategy_v2_api_alpha', $currentProduction['strategy_version']['id'], 'strategy_v2_production without id should read current production');

$archived = $controller->handle('strategy_v2_archive', ['id' => 'strategy_v2_api_beta']);
assertSame('Archived', $archived['strategy_version']['status'], 'strategy_v2_archive should archive clone');

$listed = $controller->handle('strategy_v2_list', []);
assertSame(2, count($listed['strategy_versions']), 'strategy_v2_list should return both versions');

$latest = $controller->handle('strategy_v2_latest', []);
assertTrue(is_array($latest['strategy_version']), 'strategy_v2_latest should return a version payload');

echo 'Strategy V2 API smoke tests passed.' . PHP_EOL;

function strategyV2SqliteMemory(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    foreach (['001_storage_foundation.php', '002_strategy_versions.php'] as $file) {
        $migration = require __DIR__ . '/../database/migrations/' . $file;
        $migration->up($pdo);
    }

    return $pdo;
}

function strategyV2TempDir(string $prefix): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '_' . bin2hex(random_bytes(4));
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create temp dir: ' . $dir);
    }

    return $dir;
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}
