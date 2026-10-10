<?php

declare(strict_types=1);

require_once __DIR__ . '/ProviderFactory.php';

final class OllamaProviderStatus
{
    public static function fromConfigFile(?string $path = null): array
    {
        $factory = ProviderFactory::fromConfigFile($path);
        $provider = $factory->createRegistry()->get('ollama');
        $health = $provider->health();
        $modelConfigured = (bool)($health['model_configured'] ?? false);
        $endpointConfigured = (bool)($health['endpoint_configured'] ?? false);

        return [
            'ok' => true,
            'provider' => 'ollama',
            'default_provider' => $factory->defaultProviderKey(),
            'enabled' => $provider->isEnabled(),
            'enabled_status' => $provider->isEnabled() ? 'enabled' : 'disabled',
            'configured' => $modelConfigured && $endpointConfigured ? 'configured' : 'missing_config',
            'api_key_status' => 'not_required',
            'model' => $modelConfigured ? 'set' : 'missing_model',
            'model_status' => $modelConfigured ? 'set' : 'missing_model',
            'endpoint' => $endpointConfigured ? 'set' : 'missing_endpoint',
            'endpoint_status' => $endpointConfigured ? 'set' : 'missing_endpoint',
            'running_status' => self::runningStatus((string)($health['status'] ?? 'unknown')),
            'health_status' => (string)($health['status'] ?? 'unknown'),
            'implemented' => $provider->isImplemented(),
            'local' => true,
            'cloud' => false,
            'network' => false,
            'message' => is_scalar($health['message'] ?? null) ? (string)$health['message'] : null,
        ];
    }

    private static function runningStatus(string $healthStatus): string
    {
        return match ($healthStatus) {
            'ready' => 'running',
            'disabled' => 'disabled',
            'ollama_unreachable' => 'not_running',
            'model_not_found' => 'running_model_missing',
            'missing_model' => 'missing_model',
            'invalid_endpoint' => 'invalid_endpoint',
            default => 'unknown',
        };
    }
}
