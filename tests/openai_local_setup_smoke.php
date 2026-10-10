<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/AI/Gateway/OpenAIProviderStatus.php';
require_once __DIR__ . '/../lib/AI/Gateway/ProviderFactory.php';

$envExample = file_get_contents(__DIR__ . '/../.env.example');
assertContains('OPENAI_API_KEY=', $envExample, '.env.example should include OPENAI_API_KEY');
assertContains('OPENAI_MODEL_DEFAULT=', $envExample, '.env.example should include OPENAI_MODEL_DEFAULT');

$gitignore = file_get_contents(__DIR__ . '/../.gitignore');
assertContains('.env.local', $gitignore, '.gitignore should ignore .env.local');
assertContains('config/local.php', $gitignore, '.gitignore should ignore config/local.php');

$docs = file_get_contents(__DIR__ . '/../docs/OpenAI_Local_Setup.md');
assertContains('openai_provider_status', $docs, 'OpenAI local setup docs should mention diagnostic action');
assertContains('config/local.php', $docs, 'OpenAI local setup docs should mention local config override');
assertContains('.env.local', $docs, 'OpenAI local setup docs should mention .env.local');

$api = file_get_contents(__DIR__ . '/../api.php');
assertContains('openai_provider_status', $api, 'api.php should expose openai_provider_status action');

$root = localSetupTempDir();
mkdir($root . DIRECTORY_SEPARATOR . 'config', 0775, true);
$secret = 'sk-local-setup-test-secret';
$keyEnv = 'AI_TRADE_TEST_OPENAI_KEY_' . strtoupper(bin2hex(random_bytes(3)));
$modelEnv = 'AI_TRADE_TEST_OPENAI_MODEL_' . strtoupper(bin2hex(random_bytes(3)));

$base = require __DIR__ . '/../config/ai_providers.php';
$base['providers']['openai_chat']['api_key_env'] = $keyEnv;
$base['providers']['openai_chat']['default_model_env'] = $modelEnv;
file_put_contents($root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ai_providers.php', '<?php return ' . var_export($base, true) . ';');
file_put_contents($root . DIRECTORY_SEPARATOR . '.env.local', $keyEnv . '=' . $secret . PHP_EOL . $modelEnv . '=unit-test-local-model' . PHP_EOL);
file_put_contents($root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'local.php', "<?php\nreturn ['ai_providers' => ['providers' => ['openai_chat' => ['enabled' => true]]]];\n");

$configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ai_providers.php';
$factory = ProviderFactory::fromConfigFile($configPath);
$provider = $factory->createRegistry()->get('openai_chat');
$health = $provider->health();
assertTrue($provider->isEnabled(), 'config/local.php should enable openai_chat locally');
assertSame('ready', $health['status'], '.env.local and config/local.php should make OpenAI provider ready');
assertTrue((bool)$health['api_key_configured'], 'health should report API key configured');
assertTrue((bool)$health['model_configured'], 'health should report model configured');
assertNoSecret($secret, $health, 'provider health must not expose API key');

$status = OpenAIProviderStatus::fromConfigFile($configPath);
assertSame('enabled', $status['enabled_status'], 'diagnostic should report enabled');
assertSame('configured', $status['configured'], 'diagnostic should report configured API key');
assertSame('set', $status['model'], 'diagnostic should report model set');
assertNoSecret($secret, $status, 'diagnostic status must not expose API key');
assertFalse(str_contains(json_encode($status, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '', 'unit-test-local-model'), 'diagnostic status should not expose model value');

echo 'OpenAI local setup smoke tests passed.' . PHP_EOL;

function localSetupTempDir(): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_trade_openai_local_' . bin2hex(random_bytes(4));
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create temp dir: ' . $dir);
    }

    return $dir;
}

function assertContains(string $needle, string|false $haystack, string $message): void
{
    if (!is_string($haystack) || !str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
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
