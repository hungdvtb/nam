<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/AIJobService.php';
require_once __DIR__ . '/../lib/ExperimentService.php';
require_once __DIR__ . '/../lib/StrategyVersionService.php';
require_once __DIR__ . '/../lib/ResearchMockRunOnceService.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteAIJobRepository.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteExperimentRepository.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteStrategyVersionRepository.php';

assertTrue(str_contains((string)file_get_contents(__DIR__ . '/../api.php'), 'research_mock_run_once'), 'api.php should expose research_mock_run_once action');

$emptyContext = researchMockContext('research_mock_empty');
$emptyResult = $emptyContext['service']->runOnce();

assertTrue($emptyResult['ok'], 'research_mock_run_once should return ok');
assertTrue($emptyResult['experiment_created'], 'research mock should create experiment if none running');
assertSame(ExperimentStatus::RUNNING, $emptyResult['experiment']['status'], 'created experiment should be Running');
assertSame(AIJobType::GENERATE_STRATEGY_IDEA, $emptyResult['ai_job']['job_type'], 'research mock should create GenerateStrategyIdea job');
assertSame(AIJobStatus::COMPLETED, $emptyResult['ai_job']['status'], 'research mock should complete AI job');
assertSame($emptyResult['experiment']['id'], $emptyResult['ai_job']['experiment_id'], 'AI job should link to experiment');
assertSame($emptyResult['strategy_version']['id'], $emptyResult['ai_job']['output_json']['strategy_version_id'], 'AI job output should link strategy draft');
assertSame(StrategyVersionStatus::DRAFT, $emptyResult['strategy_version']['status'], 'strategy version should be Draft');
assertSame('ai', $emptyResult['strategy_version']['created_by'], 'strategy version should be created by ai');
assertSame($emptyResult['ai_job']['id'], $emptyResult['strategy_version']['settings_json']['source_ai_job_id'], 'strategy draft should link source AI job');
assertSame($emptyResult['experiment']['id'], $emptyResult['strategy_version']['settings_json']['source_experiment_id'], 'strategy draft should link source experiment');

$emptyItems = $emptyContext['experiments']->listItems($emptyResult['experiment']['id']);
assertSame(1, count($emptyItems), 'research mock should create one linked experiment item');
assertSame('StrategyV2Draft', $emptyItems[0]['item_type'], 'experiment item should mark Strategy V2 draft type');
assertSame(ExperimentItemStatus::PENDING, $emptyItems[0]['status'], 'experiment item should stay Pending until manual testing');
assertSame($emptyResult['strategy_version']['id'], $emptyItems[0]['strategy_version_id'], 'experiment item should link strategy version');
assertSame($emptyResult['ai_job']['id'], $emptyItems[0]['metrics_json']['ai_job_id'], 'experiment item metrics should link AI job');
assertFalse((bool)$emptyResult['safety']['real_ai_api'], 'research mock must not call a real AI API');
assertFalse((bool)$emptyResult['safety']['optimizer'], 'research mock must not run optimizer');
assertFalse((bool)$emptyResult['safety']['auto_trading'], 'research mock must not trigger auto trading');
assertFalse((bool)$emptyResult['safety']['backtest_calculation_changed'], 'research mock must not change backtest calculations');

$runningContext = researchMockContext('research_mock_running');
$existing = $runningContext['experiments']->createExperiment([
    'id' => 'exp_research_existing_running',
    'name' => 'Existing running research',
    'goal_json' => ['objective' => 'reuse me'],
    'created_by' => 'smoke',
]);
$existing = $runningContext['experiments']->startExperiment($existing['id']);
$oldPending = $runningContext['jobs']->createJob([
    'id' => 'ai_job_old_pending_kept',
    'job_type' => AIJobType::ANALYZE_BACKTEST,
    'created_by' => 'smoke',
]);

$runningResult = $runningContext['service']->runOnce();
assertFalse((bool)$runningResult['experiment_created'], 'research mock should use existing Running experiment');
assertSame($existing['id'], $runningResult['experiment']['id'], 'research mock should reuse the Running experiment id');
assertSame(AIJobStatus::PENDING, $runningContext['jobs']->findJob($oldPending['id'])['status'], 'targeted research worker should not consume older pending jobs');
assertSame(AIJobStatus::COMPLETED, $runningContext['jobs']->findJob($runningResult['ai_job']['id'])['status'], 'research job should complete');
assertSame(1, count($runningContext['experiments']->listExperiments(0)), 'research mock should not create another experiment when one is running');

$runningItems = $runningContext['experiments']->listItems($existing['id']);
assertSame(1, count($runningItems), 'existing running experiment should receive one research item');
assertSame($runningResult['strategy_version']['id'], $runningItems[0]['strategy_version_id'], 'existing experiment item should link created strategy draft');
assertSame($runningResult['ai_job']['id'], $runningItems[0]['metrics_json']['ai_job_id'], 'existing experiment item metrics should link created AI job');

echo 'Research mock run-once smoke tests passed.' . PHP_EOL;

function researchMockContext(string $prefix): array
{
    $pdo = sqliteMemoryWithResearchMock();
    $store = new JsonStore(researchMockTempDir($prefix), require __DIR__ . '/../config/defaults.php');
    $jobs = new AIJobService(new SQLiteAIJobRepository($pdo));
    $experiments = new ExperimentService(new SQLiteExperimentRepository($pdo));
    $versions = new StrategyVersionService($store, new SQLiteStrategyVersionRepository($pdo));

    return [
        'jobs' => $jobs,
        'experiments' => $experiments,
        'versions' => $versions,
        'service' => new ResearchMockRunOnceService(
            $experiments,
            $jobs,
            new AIJobWorker($jobs),
            new AIJobStrategyDraftService($jobs, $versions),
            $versions
        ),
    ];
}

function sqliteMemoryWithResearchMock(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    foreach (['001_storage_foundation.php', '002_strategy_versions.php', '004_experiments.php', '014_ai_jobs.php'] as $file) {
        $migration = require __DIR__ . '/../database/migrations/' . $file;
        $migration->up($pdo);
    }

    return $pdo;
}

function researchMockTempDir(string $prefix): string
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
