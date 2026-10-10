<?php

declare(strict_types=1);

interface ExperimentRepositoryInterface extends AiTradeRepositoryInterface
{
    public function createExperiment(array $experiment): array;

    public function updateExperiment(string $id, array $changes): array;

    public function findExperiment(string $id): ?array;

    public function listExperiments(int $limit = 100, array $filters = []): array;

    public function addItem(array $item): array;

    public function updateItem(string $id, array $changes): array;

    public function findItem(string $id): ?array;

    public function listItems(string $experimentId, int $limit = 100, array $filters = []): array;

    public function bestItem(string $experimentId): ?array;
}
