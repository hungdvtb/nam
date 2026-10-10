<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/AI/Gateway/ProviderFactory.php';
require_once __DIR__ . '/../lib/AI/Gateway/ProviderRegistry.php';
require_once __DIR__ . '/../lib/AI/Gateway/ProviderRouter.php';

$configPath = tempProviderConfig();
$factory = ProviderFactory::fromConfigFile($configPath);
$registry = $factory->createRegistry();

$expectedProviders = ['mock', 'openai_chat', 'openai_codex', 'claude', 'gemini', 'ollama', 'openrouter'];
assertSame($expectedProviders, $factory->supportedProviderKeys(), 'factory should advertise all configured provider keys');
assertSame($expectedProviders, $registry->keys(), 'registry should contain every supported provider');

$mock = $registry->get('mock');
assertTrue($mock instanceof MockProvider, 'mock provider should use MockProvider adapter');
assertTrue($mock->isEnabled(), 'mock provider should be enabled');
assertTrue($mock->isImplemented(), 'mock provider should be implemented');
assertSame('ready', $mock->health()['status'], 'mock health should be ready');
assertFalse((bool)$mock->capabilities()['network'], 'mock provider must not require network');

foreach (array_slice($expectedProviders, 1) as $providerKey) {
    if (in_array($providerKey, ['openai_chat', 'ollama'], true)) {
        continue;
    }
    $provider = $registry->get($providerKey);
    assertTrue($provider instanceof PlaceholderProvider, $providerKey . ' should be a placeholder adapter');
    assertFalse($provider->isImplemented(), $providerKey . ' should not be implemented yet');
    assertFalse($provider->health()['ok'], $providerKey . ' health should be unavailable');
    assertSame('configuration_error', $provider->complete([])['error']['category'], $providerKey . ' placeholder should return configuration_error');
}

$openai = $registry->get('openai_chat');
assertTrue($openai instanceof OpenAIChatProvider, 'openai_chat should use OpenAIChatProvider adapter');
assertFalse($openai->isEnabled(), 'openai_chat should be disabled by default');
assertTrue($openai->isImplemented(), 'openai_chat should be implemented behind the gateway');
assertSame('disabled', $openai->health()['status'], 'openai_chat disabled health should be explicit');
assertSame('configuration_error', $openai->complete([])['error']['category'], 'disabled openai_chat should return configuration_error');

$ollama = $registry->get('ollama');
assertTrue($ollama instanceof OllamaProvider, 'ollama should use OllamaProvider adapter');
assertFalse($ollama->isEnabled(), 'ollama should be disabled by default');
assertTrue($ollama->isImplemented(), 'ollama should be implemented behind the gateway');
assertSame('disabled', $ollama->health()['status'], 'ollama disabled health should be explicit');
assertSame('configuration_error', $ollama->complete([])['error']['category'], 'disabled ollama should return configuration_error');
assertFalse((bool)$ollama->capabilities()['api_key_required'], 'ollama should not require an API key');

$request = [
    'task_type' => 'backtest_summary',
    'agent_role' => 'AnalysisAI',
    'response_format' => 'plain_text',
    'messages' => [
        ['role' => 'user', 'content' => 'Summarize EURUSD validation run.'],
    ],
    'prompt_variables' => [
        'strategy_version_id' => 'sv_mock_001',
        'backtest_run_id' => 'bt_mock_001',
    ],
];

$first = $mock->complete($request);
$second = $mock->complete($request);
assertSame($first, $second, 'mock response must be deterministic for identical requests');
assertTrue($first['ok'], 'mock completion should succeed');
assertSame('mock', $first['provider'], 'mock response provider should be mock');
assertSame('mock-deterministic-v1', $first['model_name'], 'mock should resolve default deterministic model');
assertSame('stop', $first['finish_reason'], 'mock finish reason should be stop');
assertTrue(str_contains($first['text'], 'Mock provider deterministic response'), 'mock text should identify deterministic response');
assertTrue($first['usage']['input_tokens'] > 0, 'mock response should estimate input tokens');
assertTrue($first['usage']['output_tokens'] > 0, 'mock response should estimate output tokens');
assertSame(0.0, $first['cost']['amount'], 'mock cost should be zero');
assertFalse((bool)$first['metadata']['network'], 'mock response must record no network usage');

$json = $mock->complete([
    'task_type' => 'strategy_proposal',
    'agent_role' => 'StrategyAI',
    'response_format' => 'json_object',
    'model_alias' => 'structured_default',
    'messages' => [
        ['role' => 'user', 'content' => 'Create a proposal-only strategy idea.'],
    ],
]);
$decoded = json_decode($json['text'], true);
assertTrue(is_array($decoded), 'json_object text should decode');
assertSame($json['parsed'], $decoded, 'json_object text should match parsed payload');
assertTrue((bool)$json['parsed']['proposal_only'], 'mock structured output should remain proposal-only');

$router = ProviderRouter::fromConfigFile($configPath);
$routeInfo = $router->routeInfo(['task_type' => 'risk_review']);
assertSame('mock', $routeInfo['provider'], 'router should use mock for configured risk_review route');
assertSame('risk_review', $routeInfo['model_alias'], 'router should use configured model alias');
assertSame('mock-deterministic-v1', $routeInfo['model_name'], 'router should resolve mock model alias');

$explicit = $router->route(['provider' => 'mock']);
assertSame('mock', $explicit->key(), 'router should honor explicit mock provider');

$blocked = false;
try {
    $router->route(['provider' => 'openai_chat']);
} catch (RuntimeException $error) {
    $blocked = str_contains($error->getMessage(), 'disabled') || str_contains($error->getMessage(), 'not implemented');
}
assertTrue($blocked, 'router must not route to placeholder cloud providers');

$ollamaBlocked = false;
try {
    $router->route(['provider' => 'ollama']);
} catch (RuntimeException $error) {
    $ollamaBlocked = str_contains($error->getMessage(), 'disabled');
}
assertTrue($ollamaBlocked, 'router must not route to disabled Ollama provider');

echo 'LLM provider framework smoke tests passed.' . PHP_EOL;

function tempProviderConfig(): string
{
    $config = require __DIR__ . '/../config/ai_providers.php';
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_trade_provider_framework_' . bin2hex(random_bytes(3)) . '.php';
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
