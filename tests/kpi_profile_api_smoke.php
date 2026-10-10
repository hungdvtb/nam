<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/KPIProfileRepositoryInterface.php';
require_once __DIR__ . '/../lib/KPIProfiles/KPIProfileRecord.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteKPIProfileRepository.php';
require_once __DIR__ . '/../lib/KPIProfileService.php';
require_once __DIR__ . '/../lib/KPIProfiles/KPIProfileApiController.php';

$service = new KPIProfileService(new SQLiteKPIProfileRepository(sqliteMemoryWithKPIProfiles()));
$controller = new KPIProfileApiController($service);

$created = $controller->handle('kpi_profile_create', [
    'id' => 'kpi_api_strict',
    'name' => 'KPI API Strict',
    'description' => 'Created by API smoke test',
    'is_default' => true,
    'win_rate_min' => 72,
    'average_rr_min' => 2.2,
    'profit_factor_min' => 2.1,
    'max_drawdown_max' => 9,
    'total_trades_min' => 320,
]);
assertTrue($created['ok'], 'kpi_profile_create should return ok');
assertSame('kpi_api_strict', $created['profile']['id'], 'kpi_profile_create should preserve id');
assertTrue($created['profile']['is_default'], 'kpi_profile_create should support default profile');

$listed = $controller->handle('kpi_profile_list', []);
assertSame(1, count($listed['profiles']), 'kpi_profile_list should return created profile');
assertSame('kpi_api_strict', $listed['default_profile']['id'], 'kpi_profile_list should include default profile');

$createdLoose = $controller->handle('kpi_profile_create', [
    'id' => 'kpi_api_loose',
    'name' => 'KPI API Loose',
    'win_rate_min' => 55,
    'average_rr_min' => 1.4,
    'profit_factor_min' => 1.3,
    'max_drawdown_max' => 18,
    'total_trades_min' => 120,
]);
assertSame('kpi_api_loose', $createdLoose['profile']['id'], 'second profile should be created');

$updated = $controller->handle('kpi_profile_update', [
    'id' => 'kpi_api_loose',
    'name' => 'KPI API Updated',
    'profit_factor_min' => 1.6,
]);
assertSame('KPI API Updated', $updated['profile']['name'], 'kpi_profile_update should update name');
assertSame(1.6, $updated['profile']['profit_factor_min'], 'kpi_profile_update should update KPI target');

$defaulted = $controller->handle('kpi_profile_set_default', ['id' => 'kpi_api_loose']);
assertTrue($defaulted['profile']['is_default'], 'kpi_profile_set_default should set default');
assertSame('kpi_api_loose', $defaulted['default_profile']['id'], 'kpi_profile_set_default should return default profile');

$deleted = $controller->handle('kpi_profile_delete', ['id' => 'kpi_api_strict']);
assertTrue($deleted['ok'], 'kpi_profile_delete should return ok');
assertSame(1, count($deleted['profiles']), 'kpi_profile_delete should remove profile');

echo 'KPI profile API smoke tests passed.' . PHP_EOL;

function sqliteMemoryWithKPIProfiles(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    foreach (['001_storage_foundation.php', '011_kpi_profiles.php'] as $file) {
        $migration = require __DIR__ . '/../database/migrations/' . $file;
        $migration->up($pdo);
    }

    return $pdo;
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
