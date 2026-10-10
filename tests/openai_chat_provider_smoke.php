<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/AI/Gateway/ProviderFactory.php';
require_once __DIR__ . '/../lib/AI/Gateway/ProviderRegistry.php';
require_once __DIR__ . '/../lib/AI/Gateway/ProviderRouter.php';
require_once __DIR__ . '/../lib/AI/Providers/OpenAIChatProvider.php';

$configPath = tempProviderConfig();
$factory = ProviderFactory::fromConfigFile($configPath);
$registry = $factory->createRegistry();

assertSame('mock', $factory->defaultProviderKey(), 'mock should remain the default provider');

$openai = $registry->get('openai_chat');
assertTrue($openai instanceof OpenAIChatProvider, 'openai_chat should use OpenAIChatProvider');
assertTrue($openai->isImplemented(), 'openai_chat provider should be implemented');
assertFalse($openai->isEnabled(), 'openai_chat provider should be disabled by default');
assertSame('disabled', $openai->health()['status'], 'disabled OpenAI provider should report disabled health');
assertFalse((bool)$openai->health()['ok'], 'disabled OpenAI provider health should not be ok');
assertSame('configuration_error', $openai->complete([])['error']['category'], 'disabled OpenAI provider should fail with configuration_error');

$blocked = false;
try {
    ProviderRouter::fromConfigFile($configPath)->route(['provider' => 'openai_chat']);
} catch (RuntimeException $error) {
    $blocked = str_contains($error->getMessage(), 'disabled');
}
assertTrue($blocked, 'router should block disabled OpenAI provider');

$missingKey = new OpenAIChatProvider([
    'enabled' => true,
    'default_model' => 'unit-test-model',
]);
assertSame('missing_api_key', $missingKey->health()['status'], 'enabled OpenAI without key should report missing_api_key');
assertSame('configuration_error', $missingKey->complete([])['error']['category'], 'OpenAI without key should return configuration_error');

$secret = 'sk-unit-test-secret';
$missingModel = new OpenAIChatProvider([
    'enabled' => true,
    'api_key' => $secret,
]);
assertSame('missing_model', $missingModel->health()['status'], 'enabled OpenAI without model should report missing_model');
assertNoSecret($secret, $missingModel->health(), 'health should not leak configured API key');

$configured = new OpenAIChatProvider([
    'enabled' => true,
    'api_key' => $secret,
    'default_model' => 'unit-test-model',
    'model_aliases' => [
        'analysis_default' => 'unit-test-model',
    ],
]);
assertSame('ready', $configured->health()['status'], 'configured OpenAI provider health should be ready without making a network call');
assertTrue((bool)$configured->health()['ok'], 'configured OpenAI provider health should be ok');
assertSame('unit-test-model', $configured->resolveModel('analysis_default'), 'OpenAI provider should resolve configured model alias');
assertTrue((bool)$configured->capabilities()['network'], 'OpenAI provider should declare network capability');
assertNoSecret($secret, $configured->health(), 'configured health should not leak API key');
assertNoSecret($secret, $configured->capabilities(), 'capabilities should not leak API key');
assertNoSecret($secret, $configured->modelAliases(), 'model aliases should not leak API key');

$index = file_get_contents(__DIR__ . '/../index.php');
$app = file_get_contents(__DIR__ . '/../assets/app.js');
$api = file_get_contents(__DIR__ . '/../api.php');
assertTrue(is_string($index) && !str_contains($index, $secret), 'index.php should not expose API key values');
assertTrue(is_string($app) && !str_contains($app, $secret), 'assets/app.js should not expose API key values');
assertTrue(is_string($api) && !str_contains($api, $secret), 'api.php should not expose API key values');

echo 'OpenAI Chat provider smoke tests passed.' . PHP_EOL;

function tempProviderConfig(): string
{
    $config = require __DIR__ . '/../config/ai_providers.php';
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_trade_openai_provider_' . bin2hex(random_bytes(3)) . '.php';
    file_put_contents($path, '<?php return ' . var_export($config, true) . ';');

    return $path;
}

function assertNoSecret(string $secret, array $payload, string $message): void
{
    assertFalse(str_contains(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '', $secret), $message);
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
