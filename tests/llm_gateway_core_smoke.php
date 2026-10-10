<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Contracts/PromptTemplateRepositoryInterface.php';
require_once __DIR__ . '/../lib/Repositories/Sqlite/SQLitePromptTemplateRepository.php';
require_once __DIR__ . '/../lib/AI/Prompts/PromptTemplateRecord.php';
require_once __DIR__ . '/../lib/AI/Prompts/PromptRenderer.php';
require_once __DIR__ . '/../lib/AI/Prompts/PromptManager.php';
require_once __DIR__ . '/../lib/AI/Gateway/LLMGateway.php';
require_once __DIR__ . '/../lib/AI/Gateway/LLMGatewayApiController.php';

$configPath = tempProviderConfig([]);
$manager = new PromptManager(new SQLitePromptTemplateRepository(sqliteMemoryWithPromptTemplates()));
$manager->seedDefaultTemplates();
$gateway = new LLMGateway($manager, ProviderRouter::fromConfigFile($configPath), new ResponseParser());

$response = $gateway->call([
    'task_type' => 'strategy_proposal',
    'agent_role' => 'StrategyAI',
    'prompt_template_key' => 'strategy.idea.generate',
    'variables' => [
        'research_goal' => 'Create a deterministic mock strategy idea.',
        'strategy_context' => [
            'symbol' => 'EURUSD',
            'timeframe' => 'H4/M15',
        ],
    ],
    'output_format' => 'json_object',
])->toArray();

assertTrue($response['ok'], 'render + mock provider call should succeed');
assertSame('mock', $response['provider'], 'gateway should route to mock provider');
assertSame('strategy.idea.generate', $response['prompt']['template_key'], 'response should include prompt template metadata');
assertTrue(count($response['prompt']['messages']) >= 1, 'response should include rendered prompt messages');
assertTrue($response['usage']['input_tokens'] > 0, 'gateway should return usage record');
assertSame('valid', $response['validation_status'], 'mock JSON response should parse as valid');

$decoded = json_decode($response['text'], true);
assertTrue(is_array($decoded), 'json response text should decode');
assertSame($decoded, $response['parsed'], 'parsed JSON should match response text');
assertTrue((bool)$response['parsed']['proposal_only'], 'mock structured output should remain proposal-only');

$missing = $gateway->call([
    'task_type' => 'strategy_proposal',
    'agent_role' => 'StrategyAI',
    'prompt_template_key' => 'strategy.idea.generate',
    'variables' => [
        'research_goal' => 'Missing strategy context should fail.',
    ],
    'output_format' => 'json_object',
])->toArray();
assertFalse($missing['ok'], 'missing required variable should fail');
assertSame('invalid_request', $missing['error']['category'], 'missing variable failure should be normalized');
assertTrue(str_contains($missing['error']['message'], 'strategy_context'), 'missing variable error should name the variable');

$providerErrorConfig = tempProviderConfig(['force_error' => [
    'category' => 'provider_error',
    'message' => 'Forced mock provider error for gateway test.',
]]);
$errorGateway = new LLMGateway($manager, ProviderRouter::fromConfigFile($providerErrorConfig), new ResponseParser());
$providerError = $errorGateway->call([
    'task_type' => 'backtest_summary',
    'agent_role' => 'AnalysisAI',
    'prompt_template_key' => 'backtest.summary',
    'variables' => [
        'backtest_summary' => ['profitFactor' => 1.4],
        'diagnostics' => ['sampleStatus' => 'OK'],
    ],
    'output_format' => 'markdown_report',
])->toArray();
assertFalse($providerError['ok'], 'provider error should return normalized failure');
assertSame('provider_error', $providerError['error']['category'], 'provider error category should be preserved');
assertSame('mock', $providerError['provider'], 'provider failure should still report provider identity');

$api = (new LLMGatewayApiController($gateway))->handle('llm_gateway_test_call', [
    'prompt_template_key' => 'backtest.summary',
    'task_type' => 'backtest_summary',
    'agent_role' => 'AnalysisAI',
    'output_format' => 'markdown_report',
    'variables' => [
        'backtest_summary' => ['profitFactor' => 1.6, 'totalTrades' => 80],
        'diagnostics' => ['sampleStatus' => 'OK'],
    ],
]);
assertTrue($api['ok'], 'llm_gateway_test_call API wrapper should return ok envelope');
assertTrue($api['llm_response']['ok'], 'llm_gateway_test_call should include successful normalized response');
assertSame('mock', $api['llm_response']['provider'], 'llm_gateway_test_call should use mock provider');

$openaiUnavailable = (new LLMGatewayApiController($gateway))->handle('llm_gateway_test_call', [
    'prompt_template_key' => 'backtest.summary',
    'task_type' => 'backtest_summary',
    'agent_role' => 'AnalysisAI',
    'output_format' => 'markdown_report',
    'preferred_provider' => 'openai_chat',
    'variables' => [
        'backtest_summary' => ['profitFactor' => 1.6, 'totalTrades' => 80],
        'diagnostics' => ['sampleStatus' => 'OK'],
    ],
]);
assertTrue($openaiUnavailable['ok'], 'llm_gateway_test_call should keep API envelope ok for unavailable OpenAI');
assertFalse((bool)$openaiUnavailable['llm_response']['ok'], 'unconfigured OpenAI test call should return normalized failed response');
assertSame('configuration_error', $openaiUnavailable['llm_response']['error']['category'], 'unconfigured OpenAI should return configuration_error');
assertTrue(str_contains($openaiUnavailable['llm_response']['error']['message'], 'OPENAI_API_KEY'), 'unconfigured OpenAI error should explain server setup');

$ollamaUnavailable = (new LLMGatewayApiController($gateway))->handle('llm_gateway_test_call', [
    'prompt_template_key' => 'backtest.summary',
    'task_type' => 'backtest_summary',
    'agent_role' => 'AnalysisAI',
    'output_format' => 'markdown_report',
    'preferred_provider' => 'ollama',
    'variables' => [
        'backtest_summary' => ['profitFactor' => 1.6, 'totalTrades' => 80],
        'diagnostics' => ['sampleStatus' => 'OK'],
    ],
]);
assertTrue($ollamaUnavailable['ok'], 'llm_gateway_test_call should keep API envelope ok for unavailable Ollama');
assertFalse((bool)$ollamaUnavailable['llm_response']['ok'], 'disabled Ollama test call should return normalized failed response');
assertSame('configuration_error', $ollamaUnavailable['llm_response']['error']['category'], 'disabled Ollama should return configuration_error');
assertTrue(str_contains($ollamaUnavailable['llm_response']['error']['message'], 'ollama serve'), 'disabled Ollama error should explain local setup');

echo 'LLM Gateway core smoke tests passed.' . PHP_EOL;

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

function tempProviderConfig(array $mockOverrides): string
{
    $config = require __DIR__ . '/../config/ai_providers.php';
    $config['providers']['mock'] = array_merge($config['providers']['mock'], $mockOverrides);
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_trade_llm_gateway_config_' . bin2hex(random_bytes(3)) . '.php';
    file_put_contents($path, '<?php return ' . var_export($config, true) . ';');

    return $path;
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
