<?php

declare(strict_types=1);

require_once __DIR__ . '/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/Repositories/Contracts/ExperimentRepositoryInterface.php';
require_once __DIR__ . '/Experiments/ExperimentStatus.php';
require_once __DIR__ . '/Experiments/ExperimentItemStatus.php';
require_once __DIR__ . '/Experiments/ExperimentRecord.php';
require_once __DIR__ . '/Experiments/ExperimentItemRecord.php';

final class ExperimentService
{
    public function __construct(private ExperimentRepositoryInterface $experiments)
    {
    }

    public function createExperiment(array $payload = []): array
    {
        $now = gmdate('c');
        $experiment = ExperimentRecord::normalize(array_merge([
            'id' => $this->newExperimentId(),
            'status' => ExperimentStatus::DRAFT,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => 'user',
        ], $payload));
        ExperimentRecord::validate($experiment);

        return $this->experiments->createExperiment($experiment);
    }

    public function updateExperiment(string $id, array $changes): array
    {
        unset($changes['id']);
        if (isset($changes['status'])) {
            $changes['status'] = ExperimentStatus::normalize((string)$changes['status']);
        }
        $changes['updated_at'] = gmdate('c');

        return $this->experiments->updateExperiment($id, $changes);
    }

    public function startExperiment(string $id): array
    {
        $current = $this->mustFindExperiment($id);
        $now = gmdate('c');

        return $this->experiments->updateExperiment($id, [
            'status' => ExperimentStatus::RUNNING,
            'started_at' => $current['started_at'] ?? $now,
            'updated_at' => $now,
        ]);
    }

    public function pauseExperiment(string $id): array
    {
        return $this->experiments->updateExperiment($id, [
            'status' => ExperimentStatus::PAUSED,
            'updated_at' => gmdate('c'),
        ]);
    }

    public function completeExperiment(string $id, array $summary = []): array
    {
        $best = $this->experiments->bestItem($id);
        $now = gmdate('c');

        return $this->experiments->updateExperiment($id, [
            'status' => ExperimentStatus::COMPLETED,
            'completed_at' => $now,
            'updated_at' => $now,
            'best_strategy_version_id' => $best['strategy_version_id'] ?? null,
            'best_backtest_result_id' => $best['backtest_result_id'] ?? null,
            'summary_json' => $summary,
        ]);
    }

    public function archiveExperiment(string $id): array
    {
        return $this->experiments->updateExperiment($id, [
            'status' => ExperimentStatus::ARCHIVED,
            'updated_at' => gmdate('c'),
        ]);
    }

    public function addItem(string $experimentId, array $payload = []): array
    {
        $this->mustFindExperiment($experimentId);
        $item = ExperimentItemRecord::normalize(array_merge([
            'id' => $this->newItemId(),
            'experiment_id' => $experimentId,
            'status' => ExperimentItemStatus::PENDING,
            'created_at' => gmdate('c'),
        ], $payload));
        ExperimentItemRecord::validate($item);

        return $this->experiments->addItem($item);
    }

    public function updateItem(string $id, array $changes): array
    {
        unset($changes['id']);
        if (isset($changes['status'])) {
            $changes['status'] = ExperimentItemStatus::normalize((string)$changes['status']);
        }

        return $this->experiments->updateItem($id, $changes);
    }

    public function listExperiments(int $limit = 100, array $filters = []): array
    {
        return $this->experiments->listExperiments($limit, $filters);
    }

    public function findExperiment(string $id): ?array
    {
        return $this->experiments->findExperiment($id);
    }

    public function listItems(string $experimentId, int $limit = 100, array $filters = []): array
    {
        return $this->experiments->listItems($experimentId, $limit, $filters);
    }

    public function getBestItem(string $experimentId): ?array
    {
        return $this->experiments->bestItem($experimentId);
    }

    private function mustFindExperiment(string $id): array
    {
        $experiment = $this->experiments->findExperiment($id);
        if ($experiment === null) {
            throw new InvalidArgumentException('Experiment not found: ' . $id);
        }

        return $experiment;
    }

    private function newExperimentId(): string
    {
        return 'exp_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }

    private function newItemId(): string
    {
        return 'exp_item_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
}
