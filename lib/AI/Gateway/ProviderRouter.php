<?php

declare(strict_types=1);

require_once __DIR__ . '/ProviderRegistry.php';

final class ProviderRouter
{
    public function __construct(
        private ProviderRegistry $registry,
        private array $routes = [],
        private string $defaultProvider = 'mock'
    ) {
    }

    public static function fromConfigFile(?string $path = null): self
    {
        $factory = ProviderFactory::fromConfigFile($path);

        return new self($factory->createRegistry(), $factory->routes(), $factory->defaultProviderKey());
    }

    public function route(array $request): ProviderInterface
    {
        $providerKey = $this->requestedProvider($request);
        if ($providerKey === null) {
            $taskType = $this->requestString($request, ['task_type', 'taskType']) ?: 'default';
            $route = $this->routes[$taskType] ?? $this->routes['default'] ?? [];
            $providerKey = is_array($route) && isset($route['provider']) ? (string)$route['provider'] : $this->defaultProvider;
        }

        $provider = $this->registry->get($providerKey);
        if (!$provider->isEnabled()) {
            throw new RuntimeException('LLM provider is disabled: ' . $provider->key());
        }

        if (!$provider->isImplemented()) {
            throw new RuntimeException('LLM provider is not implemented: ' . $provider->key());
        }

        return $provider;
    }

    public function routeInfo(array $request): array
    {
        $provider = $this->route($request);
        $taskType = $this->requestString($request, ['task_type', 'taskType']) ?: 'default';
        $route = $this->routes[$taskType] ?? $this->routes['default'] ?? [];
        $requestedAlias = $this->requestString($request, ['model_alias', 'modelAlias', 'preferred_model', 'preferredModel']);
        $modelAlias = $requestedAlias ?: (is_array($route) && isset($route['model_alias']) ? (string)$route['model_alias'] : 'default');

        return [
            'provider' => $provider->key(),
            'model_alias' => $modelAlias,
            'model_name' => $provider->resolveModel($modelAlias),
        ];
    }

    private function requestedProvider(array $request): ?string
    {
        return $this->requestString($request, ['provider', 'preferred_provider', 'preferredProvider']);
    }

    private function requestString(array $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($request[$key]) && is_scalar($request[$key])) {
                $value = strtolower(trim((string)$request[$key]));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}

