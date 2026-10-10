<?php

declare(strict_types=1);

interface BacktestRepositoryInterface extends AiTradeRepositoryInterface
{
    public function listRuns(int $limit = 50, array $filters = []): array;

    public function findRun(string $runId): ?array;

    public function saveRun(array $run): void;

    public function saveTrades(string $runId, array $trades): void;

    public function tradesForRun(string $runId, int $limit = 1000, array $filters = []): array;
}
