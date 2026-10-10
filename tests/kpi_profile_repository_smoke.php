<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/KPIProfileRepositoryInterface.php';
require_once __DIR__ . '/../lib/KPIProfiles/KPIProfileRecord.php';
require_once __DIR__ . '/../lib/Repositories/Json/JsonKPIProfileRepository.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteKPIProfileRepository.php';
require_once __DIR__ . '/../lib/KPIProfileService.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/EvaluationResultRepositoryInterface.php';
require_once __DIR__ . '/../lib/Evaluations/EvaluationStatus.php';
require_once __DIR__ . '/../lib/Evaluations/EvaluationResultRecord.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteEvaluationResultRepository.php';
require_once __DIR__ . '/../lib/EvaluationService.php';

testSQLiteKPIProfileRepository();
testJsonKPIProfileRepository();
testEvaluationServiceUsesDefaultProfile();

echo 'KPI profile repository smoke tests passed.' . PHP_EOL;

function testSQLiteKPIProfileRepository(): void
{
    $pdo = sqliteMemoryWithKPIProfiles();
    exerciseKPIProfileService(new KPIProfileService(new SQLiteKPIProfileRepository($pdo)), 'sqlite');
}

function testJsonKPIProfileRepository(): void
{
    $defaults = require __DIR__ . '/../config/defaults.php';
    $dir = sys_get_temp_dir() . '/ai_trade_kpi_profiles_' . bin2hex(random_bytes(4));
    $store = new JsonStore($dir, $defaults);
    $adapter = new JsonCompatibilityAdapter($store);

    assertTrue($adapter->hasDataset('kpi_profiles'), 'JSON adapter should expose kpi_profiles dataset');
    exerciseKPIProfileService(new KPIProfileService(new JsonKPIProfileRepository($adapter)), 'json');
}

function exerciseKPIProfileService(KPIProfileService $service, string $label): void
{
    $profile = $service->createProfile(sampleProfile('kpi_' . $label . '_strict', true));
    assertSame('kpi_' . $label . '_strict', $profile['id'], $label . ': createProfile should preserve id');
    assertTrue($profile['is_default'], $label . ': first profile should be default when requested');

    $loose = $service->createProfile(sampleProfile('kpi_' . $label . '_loose', false, [
        'win_rate_min' => 55,
        'average_rr_min' => 1.4,
        'profit_factor_min' => 1.3,
        'max_drawdown_max' => 18,
        'total_trades_min' => 120,
    ]));
    assertTrue(!$loose['is_default'], $label . ': second profile should not be default initially');

    $updated = $service->updateProfile($loose['id'], [
        'name' => 'KPI linh hoạt',
        'win_rate_min' => 58,
        'notes' => 'Updated by smoke test',
    ]);
    assertSame('KPI linh hoạt', $updated['name'], $label . ': updateProfile should update name');
    assertSame(58.0, $updated['win_rate_min'], $label . ': updateProfile should update KPI target');

    $default = $service->setDefaultProfile($loose['id']);
    assertTrue($default['is_default'], $label . ': setDefaultProfile should mark selected profile default');
    assertSame($loose['id'], $service->getDefaultProfile()['id'], $label . ': getDefaultProfile should return selected default');
    assertTrue(!$service->findProfile($profile['id'])['is_default'], $label . ': setDefaultProfile should clear previous default');

    assertSame(2, count($service->listProfiles()), $label . ': listProfiles should return two profiles');
    assertTrue($service->deleteProfile($profile['id']), $label . ': deleteProfile should delete non-default profile');
    assertSame(1, count($service->listProfiles()), $label . ': deleteProfile should remove profile from list');
}

function testEvaluationServiceUsesDefaultProfile(): void
{
    $pdo = sqliteMemoryWithKPIProfiles();
    $profileService = new KPIProfileService(new SQLiteKPIProfileRepository($pdo));
    $profileService->createProfile(sampleProfile('kpi_eval_default', true, [
        'win_rate_min' => 50,
        'average_rr_min' => 1.2,
        'profit_factor_min' => 1.2,
        'max_drawdown_max' => 20,
        'total_trades_min' => 100,
    ]));

    $evaluationService = new EvaluationService(
        new SQLiteEvaluationResultRepository($pdo),
        $profileService
    );
    $evaluation = $evaluationService->evaluateBacktestResult(sampleBacktestResult());
    assertSame(50.0, $evaluation['kpi_json']['win_rate_min'], 'EvaluationService should use default KPI profile win rate');
    assertTrue($evaluation['passed'], 'Evaluation should pass with relaxed default KPI profile');

    $fallbackEvaluation = (new EvaluationService(new SQLiteEvaluationResultRepository($pdo)))
        ->evaluateBacktestResult(sampleBacktestResult());
    assertSame(70.0, $fallbackEvaluation['kpi_json']['win_rate_min'], 'EvaluationService should fallback to hardcoded defaults without profile service');
    assertTrue(!$fallbackEvaluation['passed'], 'Fallback hardcoded KPI should still apply when no profile service is configured');
}

function sqliteMemoryWithKPIProfiles(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    foreach (['001_storage_foundation.php', '005_evaluation_results.php', '011_kpi_profiles.php'] as $file) {
        $migration = require __DIR__ . '/../database/migrations/' . $file;
        $migration->up($pdo);
    }

    return $pdo;
}

function sampleProfile(string $id, bool $isDefault, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'name' => 'KPI tiêu chuẩn',
        'description' => 'Smoke test KPI profile',
        'is_default' => $isDefault,
        'win_rate_min' => 70,
        'average_rr_min' => 2,
        'profit_factor_min' => 2,
        'max_drawdown_max' => 10,
        'total_trades_min' => 300,
        'notes' => 'Smoke test profile',
    ], $overrides);
}

function sampleBacktestResult(): array
{
    return [
        'id' => 'bt_kpi_profile_eval',
        'strategy_version_id' => 'strategy_kpi_profile',
        'win_rate' => 55,
        'average_rr' => 1.5,
        'profit_factor' => 1.5,
        'max_drawdown' => 12,
        'total_trades' => 150,
    ];
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
