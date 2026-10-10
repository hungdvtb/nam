<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/AIJobService.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteAIJobRepository.php';
require_once __DIR__ . '/../lib/AIJobs/AIJobWorker.php';
require_once __DIR__ . '/../lib/AIJobs/AIJobApiController.php';

$service = new AIJobService(new SQLiteAIJobRepository(sqliteMemoryWithAIJobs()));
$controller = new AIJobApiController($service, new AIJobWorker($service));

assertTrue(is_file(__DIR__ . '/../scripts/run_ai_worker.php'), 'run_ai_worker.php should exist');

createAndRun($service, $controller, 'ai_job_worker_idea', 'GenerateStrategyIdea', [
    'research_goal' => 'Create a deterministic mock strategy idea.',
    'strategy_context' => [
        'symbol' => 'EURUSD',
        'timeframe' => 'H4/M15',
    ],
    'memory_lessons' => ['No real AI API.'],
], 'strategy.idea.generate');

createAndRun($service, $controller, 'ai_job_worker_improve', 'ImproveStrategy', [
    'current_rules' => [
        'riskPercent' => 1,
        'minRR' => 3,
    ],
    'backtest_summary' => [
        'profitFactor' => 1.2,
        'totalTrades' => 80,
    ],
    'known_weaknesses' => 'Weak-zone losses cluster in sideway sessions.',
], 'strategy.version.improve');

createAndRun($service, $controller, 'ai_job_worker_backtest', 'AnalyzeBacktest', [
    'backtest_summary' => [
        'profitFactor' => 1.4,
        'totalTrades' => 80,
    ],
    'diagnostics' => [
        'sampleStatus' => 'OK',
        'skippedReasons' => ['NO_H4_ZONE' => 4],
    ],
    'strategy_version_id' => 'strategy_v2_worker_smoke',
], 'backtest.summary');

createAndRun($service, $controller, 'ai_job_worker_experiment', 'EvaluateExperiment', [
    'experiment_summary' => 'Compare two deterministic candidates.',
    'candidates' => [
        ['strategy_version_id' => 'strategy_v2_a', 'profitFactor' => 1.4],
        ['strategy_version_id' => 'strategy_v2_b', 'profitFactor' => 1.2],
    ],
    'selection_criteria' => 'Prefer stable validation behavior.',
    'experiment_id' => 'exp_worker_smoke',
    'strategy_version_id' => 'strategy_v2_a',
], 'experiment.compare');

$service->createJob([
    'id' => 'ai_job_worker_failed',
    'job_type' => 'AnalyzeBacktest',
    'created_by' => 'worker-smoke',
    'input_json' => ['force_error' => true],
]);
$failed = $controller->handle('ai_worker_run_once', []);
assertTrue($failed['ok'], 'ai_worker_run_once should return ok for handled job failures');
assertTrue($failed['processed'], 'ai_worker_run_once should process the forced-failure job');
assertSame('Failed', $failed['ai_job']['status'], 'forced-failure job should be marked Failed');
assertTrue(trim((string)$failed['ai_job']['error_message']) !== '', 'failed job should store error message');
assertSame('Failed', $service->findJob('ai_job_worker_failed')['status'], 'failed status should persist');

$empty = $controller->handle('ai_worker_run_once', []);
assertFalse((bool)$empty['processed'], 'ai_worker_run_once should report no work when queue is empty');

echo 'AI worker smoke tests passed.' . PHP_EOL;

function createAndRun(AIJobService $service, AIJobApiController $controller, string $id, string $type, array $inputJson, string $promptTemplateKey): void
{
    $service->createJob([
        'id' => $id,
        'job_type' => $type,
        'created_by' => 'worker-smoke',
        'input_json' => $inputJson,
    ]);

    $result = $controller->handle('ai_worker_run_once', []);
    assertTrue($result['ok'], 'ai_worker_run_once should return ok');
    assertTrue($result['processed'], 'ai_worker_run_once should process a pending job');
    assertSame($id, $result['job_id'], 'ai_worker_run_once should return processed job id');
    assertSame('Completed', $result['ai_job']['status'], $type . ' should complete');
    assertSame('Completed', $service->findJob($id)['status'], $type . ' status should persist');
    assertTrue(is_array($result['ai_job']['output_json']) && $result['ai_job']['output_json'] !== [], $type . ' output_json should be filled');
    assertSame($promptTemplateKey, $result['ai_job']['output_json']['prompt_template_key'], $type . ' should record prompt template key');
    assertSame('mock', $result['ai_job']['output_json']['provider'], $type . ' should use mock provider');
    assertSame('mock-deterministic-v1', $result['ai_job']['output_json']['model'], $type . ' should record mock model');
    assertTrue(is_array($result['ai_job']['output_json']['usage']), $type . ' should record usage');
    assertTrue(is_array($result['ai_job']['output_json']['parsed_output']), $type . ' should record parsed output');
    assertTrue(is_array($result['ai_job']['output_json']['llm_response']), $type . ' should store llm_response');
    assertTrue((bool)$result['ai_job']['output_json']['llm_response']['ok'], $type . ' llm_response should be successful');
    assertSame($promptTemplateKey, $result['ai_job']['output_json']['llm_response']['prompt_template_key'], $type . ' llm_response should record prompt key');
}

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

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}
