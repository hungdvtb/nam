<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/JsonStore.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/PromptTemplateRepositoryInterface.php';
require_once __DIR__ . '/../lib/AI/Prompts/PromptTemplateRecord.php';
require_once __DIR__ . '/../lib/AI/Prompts/PromptRenderer.php';
require_once __DIR__ . '/../lib/AI/Prompts/PromptManager.php';
require_once __DIR__ . '/../lib/AI/Prompts/PromptTemplateApiController.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLitePromptTemplateRepository.php';
require_once __DIR__ . '/../lib/Repositories/Json/JsonPromptTemplateRepository.php';

$sqliteManager = new PromptManager(new SQLitePromptTemplateRepository(sqliteMemoryWithPromptTemplates()));
$sqliteManager->seedDefaultTemplates();

$defaultActive = $sqliteManager->findActiveTemplate('strategy.idea.generate');
assertTrue($defaultActive !== null, 'seeded strategy.idea.generate template should be active');
assertSame('Active', $defaultActive['status'], 'seeded active template should have Active status');

$created = $sqliteManager->createTemplate([
    'template_key' => 'test.prompt.render',
    'version' => 1,
    'name' => 'Render Test',
    'description' => 'Smoke test template',
    'owner_module' => 'Tests',
    'status' => 'Approved',
    'system_prompt' => 'System {{name}}',
    'developer_prompt' => 'Developer {{optional_note}}',
    'user_prompt_template' => 'Hello {{name}}, review {{subject}}.',
    'required_variables_json' => ['name', 'subject'],
    'optional_variables_json' => ['optional_note'],
    'output_format' => 'plain_text',
    'parser_mode' => 'plain_text',
    'safety_level' => 'test',
]);
assertSame('test.prompt.render', $created['template_key'], 'createTemplate should store template_key');

$rendered = (new PromptRenderer())->render($created, [
    'name' => 'AI Trade',
    'subject' => 'prompt manager',
    'optional_note' => 'no provider call',
]);
assertSame(3, count($rendered['messages']), 'renderer should return system, developer, and user messages');
assertTrue(str_contains($rendered['messages'][2]['content'], 'prompt manager'), 'renderer should replace required variables');

$missingFailed = false;
try {
    (new PromptRenderer())->render($created, ['name' => 'AI Trade']);
} catch (InvalidArgumentException $error) {
    $missingFailed = str_contains($error->getMessage(), 'subject');
}
assertTrue($missingFailed, 'missing required variable should fail');

$activated = $sqliteManager->activateTemplate($created['id']);
assertSame('Active', $activated['status'], 'activateTemplate should set Active status');
$active = $sqliteManager->findActiveTemplate('test.prompt.render');
assertSame($created['id'], $active['id'], 'active lookup should return activated template');

$managerRendered = $sqliteManager->render('test.prompt.render', [
    'name' => 'Manager',
    'subject' => 'active lookup',
]);
assertTrue(str_contains($managerRendered['messages'][2]['content'], 'active lookup'), 'PromptManager should render active template');

$controller = new PromptTemplateApiController($sqliteManager);
$apiCreated = $controller->handle('prompt_template_create', [
    'template_key' => 'api.prompt.test',
    'version' => 1,
    'name' => 'API Prompt Test',
    'owner_module' => 'Tests',
    'status' => 'Draft',
    'system_prompt' => 'System',
    'developer_prompt' => 'Developer',
    'user_prompt_template' => 'API {{value}}',
    'required_variables_json' => ['value'],
    'output_format' => 'plain_text',
    'parser_mode' => 'plain_text',
    'safety_level' => 'test',
]);
assertTrue($apiCreated['ok'], 'prompt_template_create should return ok');
assertSame('api.prompt.test', $apiCreated['template']['template_key'], 'prompt_template_create should store key');

$apiUpdated = $controller->handle('prompt_template_update', [
    'id' => $apiCreated['template']['id'],
    'name' => 'API Prompt Updated',
    'status' => 'Approved',
]);
assertSame('API Prompt Updated', $apiUpdated['template']['name'], 'prompt_template_update should update name');

$apiActivated = $controller->handle('prompt_template_activate', ['id' => $apiCreated['template']['id']]);
assertSame('Active', $apiActivated['template']['status'], 'prompt_template_activate should activate template');

$apiFound = $controller->handle('prompt_template_find', ['template_key' => 'api.prompt.test']);
assertSame($apiCreated['template']['id'], $apiFound['template']['id'], 'prompt_template_find should find active template by key');

$apiArchived = $controller->handle('prompt_template_archive', ['id' => $apiCreated['template']['id']]);
assertSame('Archived', $apiArchived['template']['status'], 'prompt_template_archive should archive template');

$jsonStore = new JsonStore(tempDir('prompt_json'), defaults());
$jsonManager = new PromptManager(new JsonPromptTemplateRepository($jsonStore));
$jsonManager->seedDefaultTemplates();
$jsonActive = $jsonManager->findActiveTemplate('backtest.summary');
assertTrue($jsonActive !== null, 'JSON repository should seed default active templates');
$jsonRendered = $jsonManager->render('backtest.summary', [
    'backtest_summary' => ['profitFactor' => 1.6],
    'diagnostics' => ['sampleStatus' => 'OK'],
]);
assertTrue(str_contains($jsonRendered['messages'][2]['content'], 'profitFactor'), 'JSON repository active template should render variables');

echo 'Prompt Manager smoke tests passed.' . PHP_EOL;

function sqliteMemoryWithPromptTemplates(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    foreach (['001_storage_foundation.php', '015_prompt_templates.php'] as $file) {
        $migration = require __DIR__ . '/../database/migrations/' . $file;
        $migration->up($pdo);
    }

    return $pdo;
}

function defaults(): array
{
    return [
        'accounts' => [],
        'strategy' => [],
        'strategy_summary' => [],
        'candidate_cases' => [],
    ];
}

function tempDir(string $name): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_trade_tests_' . $name . '_' . bin2hex(random_bytes(3));
    mkdir($dir, 0775, true);
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

