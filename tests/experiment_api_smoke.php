<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteExperimentRepository.php';
require_once __DIR__ . '/../lib/Experiments/ExperimentApiController.php';

$controller = new ExperimentApiController(new ExperimentService(new SQLiteExperimentRepository(sqliteMemoryWithExperiments())));
$experimentId = 'exp_api_smoke_' . bin2hex(random_bytes(3));

$created = $controller->handle('experiment_create', [
    'id' => $experimentId,
    'name' => 'API smoke experiment',
    'description' => 'Created from API smoke test',
    'goal' => ['objective' => 'validate experiment API'],
    'created_by' => 'api-smoke',
]);
assertTrue($created['ok'], 'experiment_create should return ok');
assertSame($experimentId, $created['experiment']['id'], 'experiment_create should use provided id');
assertSame(ExperimentStatus::DRAFT, $created['experiment']['status'], 'experiment_create should create Draft experiment');

$listed = $controller->handle('experiment_list', ['status' => ExperimentStatus::DRAFT]);
assertSame(1, count($listed['experiments']), 'experiment_list should filter by status');

$found = $controller->handle('experiment_find', ['experimentId' => $experimentId]);
assertSame($experimentId, $found['experiment']['id'], 'experiment_find should return experiment');

$updated = $controller->handle('experiment_update', [
    'experiment_id' => $experimentId,
    'notes' => 'updated through API smoke',
    'goal_json' => ['objective' => 'updated objective'],
]);
assertSame('updated through API smoke', $updated['experiment']['notes'], 'experiment_update should update notes');
assertSame('updated objective', $updated['experiment']['goal_json']['objective'], 'experiment_update should update goal_json');

$started = $controller->handle('experiment_start', ['id' => $experimentId]);
assertSame(ExperimentStatus::RUNNING, $started['experiment']['status'], 'experiment_start should set Running');
assertTrue($started['experiment']['started_at'] !== null, 'experiment_start should set started_at');

$item = $controller->handle('experiment_add_item', [
    'experiment_id' => $experimentId,
    'id' => 'exp_api_item_1',
    'strategy_version_id' => 'strategy_api_v1',
    'backtest_result_id' => 'bt_api_1',
    'item_type' => 'BacktestResult',
    'status' => ExperimentItemStatus::PASSED,
    'score' => 88.5,
    'metrics_json' => ['profitFactor' => 2.7],
]);
assertTrue($item['ok'], 'experiment_add_item should return ok');
assertSame('exp_api_item_1', $item['item']['id'], 'experiment_add_item should use provided item id');

$items = $controller->handle('experiment_list_items', ['experiment_id' => $experimentId, 'item_type' => 'BacktestResult']);
assertSame(1, count($items['items']), 'experiment_list_items should return matching item');

$best = $controller->handle('experiment_best_item', ['experiment_id' => $experimentId]);
assertSame('exp_api_item_1', $best['item']['id'], 'experiment_best_item should return highest score item');

$paused = $controller->handle('experiment_pause', ['id' => $experimentId]);
assertSame(ExperimentStatus::PAUSED, $paused['experiment']['status'], 'experiment_pause should set Paused');

$controller->handle('experiment_start', ['id' => $experimentId]);
$completed = $controller->handle('experiment_complete', ['id' => $experimentId, 'summary' => ['done' => true]]);
assertSame(ExperimentStatus::COMPLETED, $completed['experiment']['status'], 'experiment_complete should set Completed');
assertSame('strategy_api_v1', $completed['experiment']['best_strategy_version_id'], 'experiment_complete should set best strategy version');
assertSame('bt_api_1', $completed['experiment']['best_backtest_result_id'], 'experiment_complete should set best backtest result');

$archived = $controller->handle('experiment_archive', ['id' => $experimentId]);
assertSame(ExperimentStatus::ARCHIVED, $archived['experiment']['status'], 'experiment_archive should set Archived');

$validationFailed = false;
try {
    $controller->handle('experiment_create', ['description' => 'missing name']);
} catch (InvalidArgumentException $error) {
    $validationFailed = str_contains($error->getMessage(), 'name is required');
}
assertTrue($validationFailed, 'experiment_create should validate required name');

echo 'Experiment API smoke tests passed.' . PHP_EOL;

function sqliteMemoryWithExperiments(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    foreach (['001_storage_foundation.php', '004_experiments.php'] as $file) {
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
