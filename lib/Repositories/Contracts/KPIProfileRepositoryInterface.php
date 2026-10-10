<?php

declare(strict_types=1);

interface KPIProfileRepositoryInterface extends AiTradeRepositoryInterface
{
    public function save(array $profile): array;

    public function find(string $id): ?array;

    public function list(int $limit = 100): array;

    public function default(): ?array;

    public function setDefault(string $id): array;

    public function delete(string $id): bool;
}
