<?php

declare(strict_types=1);

interface StrategyVersionRepositoryInterface extends AiTradeRepositoryInterface
{
    public function create(array $version): array;

    public function list(int $limit = 100, array $filters = []): array;

    public function find(string $id): ?array;

    public function findByVersionCode(string $versionCode): ?array;

    public function save(array $version): void;

    public function update(string $id, array $changes): array;

    public function latest(): ?array;

    public function production(): ?array;

    public function versionCodeExists(string $versionCode, ?string $excludeId = null): bool;

    public function appendEvent(string $versionId, string $eventType, array $payload): void;

    public function events(string $versionId): array;
}
