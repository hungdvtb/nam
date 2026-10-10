<?php

declare(strict_types=1);

require_once __DIR__ . '/../Gateway/ProviderInterface.php';

final class PlaceholderProvider implements ProviderInterface
{
    public function __construct(private string $key, private array $config = [])
    {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function displayName(): string
    {
        return (string)($this->config['display_name'] ?? $this->key);
    }

    public function isEnabled(): bool
    {
        return (bool)($this->config['enabled'] ?? false);
    }

    public function isImplemented(): bool
    {
        return false;
    }

    public function capabilities(): array
    {
        return $this->config['capabilities'] ?? [];
    }

    public function modelAliases(): array
    {
        return $this->config['model_aliases'] ?? [];
    }

    public function resolveModel(?string $modelAlias = null): ?string
    {
        $aliases = $this->modelAliases();
        if ($modelAlias !== null && isset($aliases[$modelAlias])) {
            return (string)$aliases[$modelAlias];
        }

        return isset($this->config['default_model']) && $this->config['default_model'] !== null
            ? (string)$this->config['default_model']
            : null;
    }

    public function health(): array
    {
        return [
            'ok' => false,
            'provider' => $this->key(),
            'implemented' => false,
            'network' => false,
            'status' => $this->isEnabled() ? 'placeholder_not_implemented' : 'disabled_placeholder',
        ];
    }

    public function complete(array $request): array
    {
        return [
            'ok' => false,
            'provider' => $this->key(),
            'provider_response_id' => null,
            'model_alias' => null,
            'model_name' => $this->resolveModel(),
            'finish_reason' => 'error',
            'text' => '',
            'parsed' => null,
            'usage' => [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cached_tokens' => 0,
                'reasoning_tokens' => 0,
                'estimated' => true,
            ],
            'cost' => [
                'amount' => 0.0,
                'currency' => 'USD',
                'estimated' => false,
            ],
            'error' => [
                'category' => 'configuration_error',
                'message' => 'Provider placeholder is not implemented: ' . $this->key(),
            ],
            'metadata' => [
                'deterministic' => true,
                'network' => false,
                'placeholder' => true,
            ],
            'raw' => [
                'placeholder' => true,
            ],
        ];
    }
}

