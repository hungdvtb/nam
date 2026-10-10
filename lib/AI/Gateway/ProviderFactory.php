<?php

declare(strict_types=1);

require_once __DIR__ . '/ProviderInterface.php';
require_once __DIR__ . '/../Providers/MockProvider.php';
require_once __DIR__ . '/../Providers/OpenAIChatProvider.php';
require_once __DIR__ . '/../Providers/OllamaProvider.php';
require_once __DIR__ . '/../Providers/PlaceholderProvider.php';

final class ProviderFactory
{
    public const SUPPORTED_PROVIDERS = [
        'mock',
        'openai_chat',
        'openai_codex',
        'claude',
        'gemini',
        'ollama',
        'openrouter',
    ];

    public function __construct(private array $config = [])
    {
    }

    public static function fromConfigFile(?string $path = null): self
    {
        $path = $path ?: __DIR__ . '/../../../config/ai_providers.php';
        self::loadLocalEnv($path);
        $config = is_file($path) ? require $path : [];

        if (!is_array($config)) {
            $config = [];
        }
        $config = self::mergeLocalConfig($path, $config);

        return new self($config);
    }

    public function create(string $providerKey): ProviderInterface
    {
        $providerKey = $this->normalizeProviderKey($providerKey);
        $config = $this->providerConfig($providerKey);

        if ($providerKey === 'mock') {
            return new MockProvider($config);
        }

        if ($providerKey === 'openai_chat') {
            return new OpenAIChatProvider($config);
        }

        if ($providerKey === 'ollama') {
            return new OllamaProvider($config);
        }

        return new PlaceholderProvider($providerKey, $config);
    }

    public function createRegistry(): ProviderRegistry
    {
        require_once __DIR__ . '/ProviderRegistry.php';

        return new ProviderRegistry($this, array_keys($this->providerConfigs()));
    }

    public function supportedProviderKeys(): array
    {
        return self::SUPPORTED_PROVIDERS;
    }

    public function defaultProviderKey(): string
    {
        return $this->normalizeProviderKey((string)($this->config['default_provider'] ?? 'mock'));
    }

    public function routes(): array
    {
        return is_array($this->config['routes'] ?? null) ? $this->config['routes'] : [];
    }

    public function providerConfig(string $providerKey): array
    {
        $providerKey = $this->normalizeProviderKey($providerKey);

        return $this->providerConfigs()[$providerKey] ?? [
            'enabled' => false,
            'implemented' => false,
            'display_name' => $providerKey,
            'model_aliases' => [],
            'capabilities' => [],
        ];
    }

    private function providerConfigs(): array
    {
        $providers = is_array($this->config['providers'] ?? null) ? $this->config['providers'] : [];
        foreach (self::SUPPORTED_PROVIDERS as $key) {
            if (!isset($providers[$key]) || !is_array($providers[$key])) {
                $providers[$key] = [
                    'enabled' => $key === 'mock',
                    'implemented' => $key === 'mock',
                    'display_name' => $key,
                    'model_aliases' => [],
                    'capabilities' => [],
                ];
            }
        }

        return $providers;
    }

    private function normalizeProviderKey(string $providerKey): string
    {
        $providerKey = strtolower(trim($providerKey));
        if (!in_array($providerKey, self::SUPPORTED_PROVIDERS, true)) {
            throw new InvalidArgumentException('Unsupported LLM provider: ' . $providerKey);
        }

        return $providerKey;
    }

    private static function loadLocalEnv(string $configPath): void
    {
        $envPath = self::projectRoot($configPath) . DIRECTORY_SEPARATOR . '.env.local';
        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
                continue;
            }
            if (getenv($name) !== false || isset($_ENV[$name]) || isset($_SERVER[$name])) {
                continue;
            }

            $value = self::unquoteEnvValue($value);
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    private static function mergeLocalConfig(string $configPath, array $config): array
    {
        $localPath = dirname($configPath) . DIRECTORY_SEPARATOR . 'local.php';
        if (!is_file($localPath) || !is_readable($localPath)) {
            return $config;
        }

        $local = require $localPath;
        if (!is_array($local)) {
            return $config;
        }

        if (isset($local['ai_providers']) && is_array($local['ai_providers'])) {
            $local = $local['ai_providers'];
        }

        return self::mergeConfig($config, $local);
    }

    private static function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($value)) {
                $base[$key] = self::mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function projectRoot(string $configPath): string
    {
        $dir = dirname($configPath);

        return basename($dir) === 'config' ? dirname($dir) : $dir;
    }

    private static function unquoteEnvValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $quote = $value[0];
        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
            $value = substr($value, 1, -1);
        }

        return str_replace(['\n', '\r'], ["\n", "\r"], $value);
    }
}
