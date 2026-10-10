<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/AIJobService.php';
require_once __DIR__ . '/../lib/StrategyVersionService.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteAIJobRepository.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteStrategyVersionRepository.php';
require_once __DIR__ . '/../lib/AIJobs/AIJobApiController.php';
require_once __DIR__ . '/../lib/AIJobs/AIJobStrategyDraftService.php';

$pdo = sqliteMemoryWithAIJobsAndStrategies();
$store = new JsonStore(strategyDraftTempDir('ai_job_strategy_draft'), require __DIR__ . '/../config/defaults.php');
$jobs = new AIJobService(new SQLiteAIJobRepository($pdo));
$versions = new StrategyVersionService($store, new SQLiteStrategyVersionRepository($pdo));
$controller = new AIJobApiController(
    $jobs,
    null,
    new AIJobStrategyDraftService($jobs, $versions)
);

$existing = $versions->createVersion([
    'id' => 'strategy_v2_existing_keep',
    'version_code' => 'SV-EXISTING-KEEP',
    'name' => 'Existing keep',
    'description' => 'Must not be overwritten',
    'rules_json' => ['symbols' => ['GBPUSD'], 'riskPercent' => 1],
]);

$ideaJob = $jobs->createJob([
    'id' => 'ai_job_draft_idea',
    'job_type' => 'GenerateStrategyIdea',
    'created_by' => 'draft-smoke',
]);
$jobs->completeJob($ideaJob['id'], [
    'mock_strategy_idea' => 'Follow clean H4 demand with M15 confirmation.',
    'rules_json' => ['symbols' => ['EURUSD'], 'riskPercent' => 1, 'minRR' => 3],
    'explanation' => 'Generated draft explanation.',
]);
$ideaDraft = $controller->handle('ai_job_create_strategy_draft', ['id' => $ideaJob['id']]);
assertTrue($ideaDraft['ok'], 'GenerateStrategyIdea draft API should return ok');
assertTrue($ideaDraft['created'], 'GenerateStrategyIdea should create a new draft');
assertSame('Draft', $ideaDraft['strategy_version']['status'], 'GenerateStrategyIdea draft should be Draft');
assertSame('ai', $ideaDraft['strategy_version']['created_by'], 'GenerateStrategyIdea draft should be created by ai');
assertSame(['EURUSD'], $ideaDraft['strategy_version']['rules_json']['symbols'], 'GenerateStrategyIdea rules should come from AI output');
assertTrue(str_contains($ideaDraft['strategy_version']['description'], 'Generated draft explanation'), 'GenerateStrategyIdea explanation should fill description');

$ideaJobAfter = $jobs->findJob($ideaJob['id']);
assertSame($ideaDraft['strategy_version']['id'], $ideaJobAfter['output_json']['strategy_version_id'], 'AI job output should link created strategy version id');

$again = $controller->handle('ai_job_create_strategy_draft', ['id' => $ideaJob['id']]);
assertFalse((bool)$again['created'], 'Repeated conversion should return existing linked strategy version');
assertSame($ideaDraft['strategy_version']['id'], $again['strategy_version']['id'], 'Repeated conversion should not create another draft');

$improveJob = $jobs->createJob([
    'id' => 'ai_job_draft_improve',
    'job_type' => 'ImproveStrategy',
    'created_by' => 'draft-smoke',
]);
$jobs->completeJob($improveJob['id'], [
    'mock_improvement_suggestion' => 'Tighten weak-zone filter.',
    'changed_rules_json' => [
        'symbols' => ['XAUUSD'],
        'h4Rules' => ['minZoneStrength' => 82],
        'm15Rules' => ['avoidDirtySideway' => true],
    ],
    'reason' => 'Improvement draft reason.',
]);
$improveDraft = $controller->handle('ai_job_create_strategy_draft', ['id' => $improveJob['id']]);
assertTrue($improveDraft['created'], 'ImproveStrategy should create a new draft');
assertSame('Draft', $improveDraft['strategy_version']['status'], 'ImproveStrategy draft should be Draft');
assertSame(['XAUUSD'], $improveDraft['strategy_version']['rules_json']['symbols'], 'ImproveStrategy changed rules should fill draft rules');
assertTrue((float)$improveDraft['strategy_version']['rules_json']['h4Rules']['minZoneStrength'] === 82.0, 'ImproveStrategy changed rule should be normalized and saved');
assertTrue(str_contains($improveDraft['strategy_version']['description'], 'Improvement draft reason'), 'ImproveStrategy reason should fill description');

$pendingJob = $jobs->createJob([
    'id' => 'ai_job_draft_pending',
    'job_type' => 'GenerateStrategyIdea',
    'created_by' => 'draft-smoke',
]);
$nonCompletedFailed = false;
try {
    $controller->handle('ai_job_create_strategy_draft', ['id' => $pendingJob['id']]);
} catch (InvalidArgumentException $error) {
    $nonCompletedFailed = str_contains($error->getMessage(), 'completed');
}
assertTrue($nonCompletedFailed, 'Non-completed job should not create a draft');

$invalidJob = $jobs->createJob([
    'id' => 'ai_job_draft_invalid',
    'job_type' => 'GenerateStrategyIdea',
    'created_by' => 'draft-smoke',
]);
$jobs->completeJob($invalidJob['id'], [
    'explanation' => 'No rules here.',
]);
$invalidOutputFailed = false;
try {
    $controller->handle('ai_job_create_strategy_draft', ['id' => $invalidJob['id']]);
} catch (InvalidArgumentException $error) {
    $invalidOutputFailed = str_contains($error->getMessage(), 'rules');
}
assertTrue($invalidOutputFailed, 'Invalid output should fail safely');

$existingAfter = $versions->findVersion($existing['id']);
assertSame('Existing keep', $existingAfter['name'], 'Existing strategy version should not be overwritten');
assertSame('Must not be overwritten', $existingAfter['description'], 'Existing strategy description should remain unchanged');
assertTrue(count($versions->listVersions()) >= 3, 'Draft conversion should add versions without replacing existing ones');

echo 'AI job strategy draft smoke tests passed.' . PHP_EOL;

function sqliteMemoryWithAIJobsAndStrategies(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    foreach (['001_storage_foundation.php', '002_strategy_versions.php', '014_ai_jobs.php'] as $file) {
        $migration = require __DIR__ . '/../database/migrations/' . $file;
        $migration->up($pdo);
    }

    return $pdo;
}

function strategyDraftTempDir(string $prefix): string
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
