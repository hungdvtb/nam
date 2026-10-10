<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/ExperimentRepositoryInterface.php';
require_once __DIR__ . '/../lib/Experiments/ExperimentStatus.php';
require_once __DIR__ . '/../lib/Experiments/ExperimentItemStatus.php';
require_once __DIR__ . '/../lib/Experiments/ExperimentRecord.php';
require_once __DIR__ . '/../lib/Experiments/ExperimentItemRecord.php';
require_once __DIR__ . '/../lib/Repositories/Json/JsonExperimentRepository.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteExperimentRepository.php';
require_once __DIR__ . '/../lib/ExperimentService.php';

testSQLiteExperimentRepository();
testJsonExperimentRepository();

echo 'Experiment repository smoke tests passed.' . PHP_EOL;

function testSQLiteExperimentRepository(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $foundation = require __DIR__ . '/../database/migrations/001_storage_foundation.php';
    $foundation->up($pdo);
    $migration = require __DIR__ . '/../database/migrations/004_experiments.php';
    $migration->up($pdo);

    exerciseExperimentService(new ExperimentService(new SQLiteExperimentRepository($pdo)), 'sqlite');
}

function testJsonExperimentRepository(): void
{
    $defaults = require __DIR__ . '/../config/defaults.php';
    $dir = sys_get_temp_dir() . '/ai_trade_experiments_' . bin2hex(random_bytes(4));
    $store = new JsonStore($dir, $defaults);
    $adapter = new JsonCompatibilityAdapter($store);

    assertTrue($adapter->hasDataset('experiments'), 'JSON adapter should expose experiments dataset');
    assertTrue($adapter->hasDataset('experiment_items'), 'JSON adapter should expose experiment_items dataset');

    exerciseExperimentService(new ExperimentService(new JsonExperimentRepository($adapter)), 'json');
}

function exerciseExperimentService(ExperimentService $service, string $label): void
{
    $experiment = $service->createExperiment([
        'id' => 'exp_' . $label . '_1',
        'name' => 'Experiment ' . $label,
        'description' => 'Smoke test experiment',
        'goal_json' => ['objective' => 'compare strategy versions'],
        'created_by' => 'smoke-test',
    ]);
    assertSame('Draft', $experiment['status'], $label . ': createExperiment should create Draft experiment');

    $updated = $service->updateExperiment($experiment['id'], ['notes' => 'updated notes']);
    assertSame('updated notes', $updated['notes'], $label . ': updateExperiment should update notes');

    $running = $service->startExperiment($experiment['id']);
    assertSame('Running', $running['status'], $label . ': startExperiment should set Running');
    assertTrue($running['started_at'] !== null, $label . ': startExperiment should set started_at');

    $paused = $service->pauseExperiment($experiment['id']);
    assertSame('Paused', $paused['status'], $label . ': pauseExperiment should set Paused');

    $itemOne = $service->addItem($experiment['id'], [
        'id' => 'item_' . $label . '_1',
        'strategy_version_id' => 'strategy_v_1',
        'backtest_result_id' => 'bt_result_1',
        'item_type' => 'candidate',
        'score' => 1.2,
        'metrics_json' => ['profitFactor' => 1.2],
    ]);
    $itemTwo = $service->addItem($experiment['id'], [
        'id' => 'item_' . $label . '_2',
        'strategy_version_id' => 'strategy_v_2',
        'backtest_result_id' => 'bt_result_2',
        'item_type' => 'candidate',
        'score' => 2.8,
        'metrics_json' => ['profitFactor' => 2.8],
    ]);

    $updatedItem = $service->updateItem($itemOne['id'], ['status' => 'Testing', 'score' => 1.8]);
    assertSame('Testing', $updatedItem['status'], $label . ': updateItem should update status');
    assertSame(1.8, $updatedItem['score'], $label . ': updateItem should update score');

    $items = $service->listItems($experiment['id']);
    assertSame(2, count($items), $label . ': listItems should return two items');

    $best = $service->getBestItem($experiment['id']);
    assertTrue($best !== null, $label . ': getBestItem should return an item');
    assertSame($itemTwo['id'], $best['id'], $label . ': getBestItem should pick highest score');

    $completed = $service->completeExperiment($experiment['id'], ['note' => 'done']);
    assertSame('Completed', $completed['status'], $label . ': completeExperiment should set Completed');
    assertSame('strategy_v_2', $completed['best_strategy_version_id'], $label . ': completeExperiment should store best strategy version');
    assertSame('bt_result_2', $completed['best_backtest_result_id'], $label . ': completeExperiment should store best backtest result');

    $found = $service->findExperiment($experiment['id']);
    assertTrue($found !== null, $label . ': findExperiment should find experiment');

    $listed = $service->listExperiments();
    assertSame(1, count($listed), $label . ': listExperiments should return one experiment');

    $archived = $service->archiveExperiment($experiment['id']);
    assertSame('Archived', $archived['status'], $label . ': archiveExperiment should set Archived');
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
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
