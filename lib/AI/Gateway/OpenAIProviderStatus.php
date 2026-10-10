<?php

declare(strict_types=1);

require_once __DIR__ . '/ProviderFactory.php';

final class OpenAIProviderStatus
{
    public static function fromConfigFile(?string $path = null): array
    {
        $factory = ProviderFactory::fromConfigFile($path);
        $provider = $factory->createRegistry()->get('openai_chat');
        $health = $provider->health();
        $apiKeyConfigured = (bool)($health['api_key_configured'] ?? false);
        $modelConfigured = (bool)($health['model_configured'] ?? false);

        return [
            'ok' => true,
            'provider' => 'openai_chat',
            'default_provider' => $factory->defaultProviderKey(),
            'enabled' => $provider->isEnabled(),
            'enabled_status' => $provider->isEnabled() ? 'enabled' : 'disabled',
            'configured' => $apiKeyConfigured ? 'configured' : 'missing_api_key',
            'api_key_status' => $apiKeyConfigured ? 'configured' : 'missing_api_key',
            'model' => $modelConfigured ? 'set' : 'missing_model',
            'model_status' => $modelConfigured ? 'set' : 'missing_model',
            'health_status' => (string)($health['status'] ?? 'unknown'),
            'implemented' => $provider->isImplemented(),
            'network' => (bool)($health['network'] ?? true),
        ];
    }
}
