<?php

declare(strict_types=1);

interface AIJobRepositoryInterface extends AiTradeRepositoryInterface
{
    public function create(array $job): array;

    public function update(string $id, array $changes): array;

    public function find(string $id): ?array;

    public function list(int $limit = 100, array $filters = []): array;
}
