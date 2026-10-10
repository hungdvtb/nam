<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/AI/Gateway/OllamaProviderStatus.php';
require_once __DIR__ . '/../lib/AI/Gateway/ProviderFactory.php';
require_once __DIR__ . '/../lib/AI/Gateway/ProviderRegistry.php';
require_once __DIR__ . '/../lib/AI/Gateway/ProviderRouter.php';
require_once __DIR__ . '/../lib/AI/Providers/OllamaProvider.php';

$configPath = tempProviderConfig();
$factory = ProviderFactory::fromConfigFile($configPath);
$registry = $factory->createRegistry();

assertSame('mock', $factory->defaultProviderKey(), 'mock should remain the default provider');

$ollama = $registry->get('ollama');
assertTrue($ollama instanceof OllamaProvider, 'ollama should use OllamaProvider');
assertTrue($ollama->isImplemented(), 'ollama provider should be implemented');
assertFalse($ollama->isEnabled(), 'ollama provider should be disabled by default');
assertSame('disabled', $ollama->health()['status'], 'disabled Ollama provider should report disabled health');
assertFalse((bool)$ollama->health()['ok'], 'disabled Ollama provider health should not be ok');
assertSame('configuration_error', $ollama->complete([])['error']['category'], 'disabled Ollama provider should fail with configuration_error');
assertTrue((bool)$ollama->capabilities()['local'], 'Ollama should declare local capability');
assertFalse((bool)$ollama->capabilities()['cloud'], 'Ollama must not be a cloud provider');
assertFalse((bool)$ollama->capabilities()['network'], 'Ollama should not be marked as cloud/network provider');
assertFalse((bool)$ollama->capabilities()['api_key_required'], 'Ollama should not require an API key');
assertSame('llama3.1:8b', $ollama->resolveModel('default'), 'Ollama should default to llama3.1:8b');

$blocked = false;
try {
    ProviderRouter::fromConfigFile($configPath)->route(['provider' => 'ollama']);
} catch (RuntimeException $error) {
    $blocked = str_contains($error->getMessage(), 'disabled');
}
assertTrue($blocked, 'router should block disabled Ollama provider');

$configured = new OllamaProvider([
    'enabled' => true,
    'endpoint' => 'http://localhost:11434',
    'default_model' => 'unit-test-model',
], fakeOllamaTransport());
$health = $configured->health();
assertSame('ready', $health['status'], 'configured Ollama provider should be ready with a successful local health probe');
assertTrue((bool)$health['ok'], 'configured Ollama provider health should be ok');
assertSame('unit-test-model', $configured->resolveModel('default'), 'Ollama provider should resolve configured model');

$completion = $configured->complete([
    'response_format' => 'json_object',
    'messages' => [
        ['role' => 'system', 'content' => 'Return JSON only.'],
        ['role' => 'user', 'content' => 'Say hello from local Ollama.'],
    ],
]);
assertTrue($completion['ok'], 'Ollama completion should succeed with fake local transport');
assertSame('ollama', $completion['provider'], 'Ollama completion provider should be ollama');
assertSame('unit-test-model', $completion['model_name'], 'Ollama completion should record model');
assertTrue($completion['usage']['input_tokens'] > 0, 'Ollama completion should track input tokens');
assertTrue($completion['usage']['output_tokens'] > 0, 'Ollama completion should track output tokens');
assertFalse((bool)$completion['metadata']['cloud'], 'Ollama completion must record no cloud usage');
assertFalse((bool)$completion['metadata']['network'], 'Ollama completion must record no cloud/network usage');
assertTrue(is_array(json_decode($completion['text'], true)), 'Ollama JSON text should decode');

$down = new OllamaProvider([
    'enabled' => true,
    'endpoint' => 'http://localhost:11434',
    'default_model' => 'unit-test-model',
], fn(): array => [
    'ok' => false,
    'category' => 'network_error',
    'message' => 'Connection refused',
]);
$downHealth = $down->health();
assertSame('ollama_unreachable', $downHealth['status'], 'unreachable Ollama should report ollama_unreachable');
assertTrue(str_contains($downHealth['message'], 'ollama serve'), 'unreachable health should explain how to start Ollama');
$downResponse = $down->complete([
    'messages' => [
        ['role' => 'user', 'content' => 'Hello'],
    ],
]);
assertFalse((bool)$downResponse['ok'], 'unreachable Ollama completion should fail');
assertSame('network_error', $downResponse['error']['category'], 'unreachable Ollama should return network_error');
assertTrue(str_contains($downResponse['error']['message'], 'Ollama is not reachable'), 'unreachable Ollama error should be clear');

$remote = new OllamaProvider([
    'enabled' => true,
    'endpoint' => 'https://example.com',
    'default_model' => 'unit-test-model',
], fakeOllamaTransport());
assertSame('invalid_endpoint', $remote->health()['status'], 'Ollama should reject non-local endpoints');
assertSame('configuration_error', $remote->complete([])['error']['category'], 'non-local Ollama endpoint should fail before transport');

$status = OllamaProviderStatus::fromConfigFile($configPath);
assertSame('disabled', $status['enabled_status'], 'diagnostic should report Ollama disabled by default');
assertSame('set', $status['model'], 'diagnostic should report default model set');
assertSame('not_required', $status['api_key_status'], 'diagnostic should report no API key required');
assertSame('disabled', $status['running_status'], 'disabled diagnostic should not perform local network health checks');

$index = file_get_contents(__DIR__ . '/../index.php');
$app = file_get_contents(__DIR__ . '/../assets/app.js');
$api = file_get_contents(__DIR__ . '/../api.php');
$docs = file_get_contents(__DIR__ . '/../docs/Ollama_Local_Setup.md');
assertContains('Ollama Local', $index, 'Prompt AI provider selector should include Ollama Local');
assertContains('ollama', $app, 'App should allow Ollama as a Prompt AI provider');
assertContains('ollama_provider_status', $api, 'api.php should expose ollama_provider_status action');
assertContains('ollama_provider_status', $docs, 'Ollama docs should mention diagnostic action');

echo 'Ollama provider smoke tests passed.' . PHP_EOL;

function fakeOllamaTransport(): callable
{
    return function (string $method, string $url, ?array $payload, int $timeoutSeconds): array {
        if ($method === 'GET' && str_ends_with($url, '/api/tags')) {
            return [
                'ok' => true,
                'data' => [
                    'models' => [
                        ['name' => 'unit-test-model'],
                    ],
                ],
            ];
        }

        if ($method === 'POST' && str_ends_with($url, '/api/chat')) {
            return [
                'ok' => true,
                'data' => [
                    'model' => (string)($payload['model'] ?? 'unit-test-model'),
                    'created_at' => '2026-07-02T00:00:00Z',
                    'message' => [
                        'role' => 'assistant',
                        'content' => '{"ok":true,"provider":"ollama","message":"local test"}',
                    ],
                    'done' => true,
                    'done_reason' => 'stop',
                    'prompt_eval_count' => 14,
                    'eval_count' => 9,
                    'total_duration' => 1000,
                    'load_duration' => 100,
                ],
            ];
        }

        return [
            'ok' => false,
            'category' => 'provider_error',
            'message' => 'Unexpected fake Ollama request.',
        ];
    };
}

function tempProviderConfig(): string
{
    $config = require __DIR__ . '/../config/ai_providers.php';
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_trade_ollama_provider_' . bin2hex(random_bytes(3)) . '.php';
    file_put_contents($path, '<?php return ' . var_export($config, true) . ';');

    return $path;
}

function assertContains(string $needle, string|false $haystack, string $message): void
{
    if (!is_string($haystack) || !str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
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
