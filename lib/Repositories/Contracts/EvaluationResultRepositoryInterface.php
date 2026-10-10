<?php

declare(strict_types=1);

interface EvaluationResultRepositoryInterface extends AiTradeRepositoryInterface
{
    public function save(array $evaluation): array;

    public function find(string $id): ?array;

    public function list(int $limit = 100, array $filters = []): array;

    public function listByStrategyVersion(string $strategyVersionId, int $limit = 100): array;

    public function listByExperiment(string $experimentId, int $limit = 100): array;

    public function latest(array $filters = []): ?array;

    public function best(array $filters = []): ?array;
}
