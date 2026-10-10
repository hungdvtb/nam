<?php

declare(strict_types=1);

interface ProviderInterface
{
    public function key(): string;

    public function displayName(): string;

    public function isEnabled(): bool;

    public function isImplemented(): bool;

    public function capabilities(): array;

    public function modelAliases(): array;

    public function resolveModel(?string $modelAlias = null): ?string;

    public function health(): array;

    public function complete(array $request): array;
}

