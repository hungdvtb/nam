<?php

declare(strict_types=1);

interface BacktestResultRepositoryInterface extends AiTradeRepositoryInterface
{
    public function save(array $result): array;

    public function find(string $id): ?array;

    public function list(int $limit = 100, array $filters = []): array;

    public function listByStrategyVersion(string $strategyVersionId, int $limit = 100): array;

    public function latest(?string $strategyVersionId = null): ?array;

    public function best(array $filters = [], string $metric = 'profit_factor'): ?array;

    public function delete(string $id): bool;
}
