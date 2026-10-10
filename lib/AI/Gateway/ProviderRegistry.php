<?php

declare(strict_types=1);

require_once __DIR__ . '/ProviderInterface.php';
require_once __DIR__ . '/ProviderFactory.php';

final class ProviderRegistry
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    public function __construct(ProviderFactory $factory, array $providerKeys)
    {
        foreach ($providerKeys as $providerKey) {
            $provider = $factory->create((string)$providerKey);
            $this->providers[$provider->key()] = $provider;
        }
    }

    public static function fromConfigFile(?string $path = null): self
    {
        return ProviderFactory::fromConfigFile($path)->createRegistry();
    }

    public function has(string $providerKey): bool
    {
        return isset($this->providers[strtolower(trim($providerKey))]);
    }

    public function get(string $providerKey): ProviderInterface
    {
        $providerKey = strtolower(trim($providerKey));
        if (!$this->has($providerKey)) {
            throw new InvalidArgumentException('Provider is not registered: ' . $providerKey);
        }

        return $this->providers[$providerKey];
    }

    public function keys(): array
    {
        return array_keys($this->providers);
    }

    public function all(): array
    {
        return $this->providers;
    }

    public function enabled(): array
    {
        return array_values(array_filter($this->providers, fn(ProviderInterface $provider) => $provider->isEnabled()));
    }

    public function implemented(): array
    {
        return array_values(array_filter($this->providers, fn(ProviderInterface $provider) => $provider->isImplemented()));
    }

    public function health(): array
    {
        $health = [];
        foreach ($this->providers as $key => $provider) {
            $health[$key] = $provider->health();
        }

        return $health;
    }
}

