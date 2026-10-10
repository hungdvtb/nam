<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/AIJobService.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteAIJobRepository.php';
require_once __DIR__ . '/../lib/AIJobs/AIJobApiController.php';

$service = new AIJobService(new SQLiteAIJobRepository(sqliteMemoryWithAIJobs()));
$controller = new AIJobApiController($service);

$created = $controller->handle('ai_job_create', [
    'id' => 'ai_job_api_pending',
    'job_type' => 'ImproveStrategy',
    'strategy_version_id' => 'strategy_v2_ai_job',
    'experiment_id' => 'exp_ai_job',
    'created_by' => 'api-smoke',
    'notes' => 'manual fake job',
    'input_json' => ['goal' => 'test fake AI orchestration'],
]);
assertTrue($created['ok'], 'ai_job_create should return ok');
assertSame('Pending', $created['ai_job']['status'], 'ai_job_create should create pending job');
assertSame('ImproveStrategy', $created['ai_job']['job_type'], 'ai_job_create should preserve job type');

$listed = $controller->handle('ai_job_list', ['status' => 'Pending']);
assertSame(1, count($listed['ai_jobs']), 'ai_job_list should filter by status');

$found = $controller->handle('ai_job_find', ['id' => 'ai_job_api_pending']);
assertSame('strategy_v2_ai_job', $found['ai_job']['strategy_version_id'], 'ai_job_find should return strategy link');

$cancelled = $controller->handle('ai_job_cancel', ['id' => 'ai_job_api_pending']);
assertSame('Cancelled', $cancelled['ai_job']['status'], 'ai_job_cancel should cancel pending job');
assertTrue($cancelled['ai_job']['completed_at'] !== null, 'ai_job_cancel should set completed_at');

$started = $service->createJob([
    'id' => 'ai_job_api_running',
    'job_type' => 'AnalyzeBacktest',
    'input_json' => ['backtest_result_id' => 'bt_ai_job'],
    'created_by' => 'service-smoke',
]);
$running = $service->startJob($started['id']);
assertSame('Running', $running['status'], 'startJob should set Running');
assertTrue($running['started_at'] !== null, 'startJob should set started_at');

$completed = $service->completeJob($running['id'], ['summary' => 'fake output only']);
assertSame('Completed', $completed['status'], 'completeJob should set Completed');
assertSame('fake output only', $completed['output_json']['summary'], 'completeJob should store output JSON');

$failed = $service->createJob([
    'id' => 'ai_job_api_failed',
    'job_type' => 'EvaluateExperiment',
    'created_by' => 'service-smoke',
]);
$failed = $service->failJob($failed['id'], 'manual failure');
assertSame('Failed', $failed['status'], 'failJob should set Failed');
assertSame('manual failure', $failed['error_message'], 'failJob should store error message');

$cancelCompletedFailed = false;
try {
    $service->cancelJob($completed['id']);
} catch (InvalidArgumentException $error) {
    $cancelCompletedFailed = str_contains($error->getMessage(), 'pending');
}
assertTrue($cancelCompletedFailed, 'cancelJob should only cancel pending jobs');

echo 'AI job API smoke tests passed.' . PHP_EOL;

function sqliteMemoryWithAIJobs(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    foreach (['001_storage_foundation.php', '014_ai_jobs.php'] as $file) {
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
