<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/ExperimentRepositoryInterface.php';
require_once __DIR__ . '/../../Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/../../Experiments/ExperimentStatus.php';
require_once __DIR__ . '/../../Experiments/ExperimentItemStatus.php';
require_once __DIR__ . '/../../Experiments/ExperimentRecord.php';
require_once __DIR__ . '/../../Experiments/ExperimentItemRecord.php';

final class JsonExperimentRepository implements ExperimentRepositoryInterface
{
    public function __construct(private JsonCompatibilityAdapter $adapter)
    {
    }

    public function storageName(): string
    {
        return 'legacy_json_experiments';
    }

    public function health(): array
    {
        return [
            'storage' => $this->storageName(),
            'datasets' => ['experiments', 'experiment_items'],
            'readable' => true,
        ];
    }

    public function createExperiment(array $experiment): array
    {
        $experiment = ExperimentRecord::normalize($experiment);
        ExperimentRecord::validate($experiment);

        if ($this->findExperiment((string)$experiment['id']) !== null) {
            throw new InvalidArgumentException('Experiment already exists: ' . $experiment['id']);
        }

        $experiments = $this->storedExperiments();
        array_unshift($experiments, $experiment);
        $this->writeExperiments($experiments);

        return $this->findExperiment((string)$experiment['id']) ?? $experiment;
    }

    public function updateExperiment(string $id, array $changes): array
    {
        $experiments = $this->storedExperiments();
        foreach ($experiments as $index => $experiment) {
            if ((string)($experiment['id'] ?? '') !== $id) {
                continue;
            }

            unset($changes['id']);
            $updated = ExperimentRecord::normalize(array_merge($experiment, $changes));
            ExperimentRecord::validate($updated);
            $experiments[$index] = $updated;
            $this->writeExperiments($experiments);

            return $this->findExperiment($id) ?? $updated;
        }

        throw new InvalidArgumentException('Experiment not found: ' . $id);
    }

    public function findExperiment(string $id): ?array
    {
        foreach ($this->listExperiments(0) as $experiment) {
            if ((string)$experiment['id'] === $id) {
                return $experiment;
            }
        }

        return null;
    }

    public function listExperiments(int $limit = 100, array $filters = []): array
    {
        $experiments = array_map(
            fn(array $experiment): array => ExperimentRecord::normalize($experiment),
            $this->storedExperiments()
        );
        $experiments = array_values(array_filter($experiments, fn(array $experiment): bool => $this->matchesExperimentFilters($experiment, $filters)));
        usort($experiments, fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));

        return $limit > 0 ? array_slice($experiments, 0, $limit) : $experiments;
    }

    public function addItem(array $item): array
    {
        $item = ExperimentItemRecord::normalize($item);
        ExperimentItemRecord::validate($item);

        if ($this->findExperiment((string)$item['experiment_id']) === null) {
            throw new InvalidArgumentException('Experiment not found: ' . $item['experiment_id']);
        }
        if ($this->findItem((string)$item['id']) !== null) {
            throw new InvalidArgumentException('Experiment item already exists: ' . $item['id']);
        }

        $items = $this->storedItems();
        array_unshift($items, $item);
        $this->writeItems($items);

        return $this->findItem((string)$item['id']) ?? $item;
    }

    public function updateItem(string $id, array $changes): array
    {
        $items = $this->storedItems();
        foreach ($items as $index => $item) {
            if ((string)($item['id'] ?? '') !== $id) {
                continue;
            }

            unset($changes['id']);
            $updated = ExperimentItemRecord::normalize(array_merge($item, $changes));
            ExperimentItemRecord::validate($updated);
            $items[$index] = $updated;
            $this->writeItems($items);

            return $this->findItem($id) ?? $updated;
        }

        throw new InvalidArgumentException('Experiment item not found: ' . $id);
    }

    public function findItem(string $id): ?array
    {
        foreach ($this->storedItems() as $item) {
            $normalized = ExperimentItemRecord::normalize($item);
            if ((string)$normalized['id'] === $id) {
                return $normalized;
            }
        }

        return null;
    }

    public function listItems(string $experimentId, int $limit = 100, array $filters = []): array
    {
        $items = array_map(
            fn(array $item): array => ExperimentItemRecord::normalize($item),
            $this->storedItems()
        );
        $items = array_values(array_filter($items, function (array $item) use ($experimentId, $filters): bool {
            if ((string)$item['experiment_id'] !== $experimentId) {
                return false;
            }

            return $this->matchesItemFilters($item, $filters);
        }));
        usort($items, fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));

        return $limit > 0 ? array_slice($items, 0, $limit) : $items;
    }

    public function bestItem(string $experimentId): ?array
    {
        $items = array_values(array_filter(
            $this->listItems($experimentId, 0),
            fn(array $item): bool => $item['score'] !== null
        ));
        usort($items, fn(array $a, array $b): int => (float)$b['score'] <=> (float)$a['score']);

        return $items[0] ?? null;
    }

    private function storedExperiments(): array
    {
        $experiments = $this->adapter->read('experiments', []);
        return is_array($experiments) ? array_values(array_filter($experiments, 'is_array')) : [];
    }

    private function writeExperiments(array $experiments): void
    {
        $this->adapter->write('experiments', array_values(array_map(
            fn(array $experiment): array => ExperimentRecord::normalize($experiment),
            $experiments
        )));
    }

    private function storedItems(): array
    {
        $items = $this->adapter->read('experiment_items', []);
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    private function writeItems(array $items): void
    {
        $this->adapter->write('experiment_items', array_values(array_map(
            fn(array $item): array => ExperimentItemRecord::normalize($item),
            $items
        )));
    }

    private function matchesExperimentFilters(array $experiment, array $filters): bool
    {
        foreach (['status', 'created_by'] as $field) {
            if (isset($filters[$field]) && (string)$experiment[$field] !== (string)$filters[$field]) {
                return false;
            }
        }

        return true;
    }

    private function matchesItemFilters(array $item, array $filters): bool
    {
        foreach (['status', 'item_type', 'strategy_version_id', 'backtest_result_id'] as $field) {
            if (isset($filters[$field]) && (string)($item[$field] ?? '') !== (string)$filters[$field]) {
                return false;
            }
        }

        return true;
    }
}
