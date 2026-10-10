<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StoragePaths.php';
require_once __DIR__ . '/../lib/Storage/SqliteConnection.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Storage/MigrationRunner.php';
require_once __DIR__ . '/../lib/Storage/SqliteInitializer.php';
require_once __DIR__ . '/../lib/AIJobService.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLiteAIJobRepository.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLitePromptTemplateRepository.php';
require_once __DIR__ . '/../lib/AI/Prompts/PromptManager.php';
require_once __DIR__ . '/../lib/AI/Gateway/LLMGateway.php';
require_once __DIR__ . '/../lib/AIJobs/AIJobWorker.php';

$projectRoot = dirname(__DIR__);
$options = workerOptions($argv);
$databasePath = $options['database'] ?? StoragePaths::sqliteDatabase($projectRoot);

try {
    (new SqliteInitializer(
        new SqliteConnection($databasePath),
        new MigrationRunner(StoragePaths::migrationsDir($projectRoot))
    ))->initialize();

    $pdo = (new SqliteConnection($databasePath))->connect();
    $worker = new AIJobWorker(
        new AIJobService(new SQLiteAIJobRepository($pdo)),
        createScriptLLMGateway($pdo, $projectRoot)
    );
    $limit = workerLimit($options);
    $results = [];

    for ($i = 0; $i < $limit; $i++) {
        $result = $worker->runOnce();
        $results[] = $result;
        if (!($result['processed'] ?? false)) {
            break;
        }
    }

    echo json_encode([
        'ok' => true,
        'database_path' => $databasePath,
        'processed' => count(array_filter($results, fn(array $result): bool => (bool)($result['processed'] ?? false))),
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'AI worker failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function workerOptions(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--all') {
            $options['all'] = true;
            continue;
        }
        if (str_starts_with($arg, '--limit=')) {
            $options['limit'] = (int)substr($arg, 8);
            continue;
        }
        if (str_starts_with($arg, '--database=')) {
            $options['database'] = substr($arg, 11);
            continue;
        }
        if (!str_starts_with($arg, '--') && !isset($options['database'])) {
            $options['database'] = $arg;
        }
    }

    return $options;
}

function workerLimit(array $options): int
{
    if (isset($options['limit'])) {
        return max(1, min(1000, (int)$options['limit']));
    }

    return ($options['all'] ?? false) ? 1000 : 1;
}

function createScriptLLMGateway(PDO $pdo, string $projectRoot): LLMGateway
{
    $migration = require StoragePaths::migrationsDir($projectRoot) . DIRECTORY_SEPARATOR . '015_prompt_templates.php';
    $migration->up($pdo);

    $prompts = new PromptManager(new SQLitePromptTemplateRepository($pdo));
    $prompts->seedDefaultTemplates();

    return new LLMGateway(
        $prompts,
        ProviderRouter::fromConfigFile($projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ai_providers.php'),
        new ResponseParser()
    );
}
