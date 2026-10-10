<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/ExperimentRepositoryInterface.php';
require_once __DIR__ . '/../../Experiments/ExperimentStatus.php';
require_once __DIR__ . '/../../Experiments/ExperimentItemStatus.php';
require_once __DIR__ . '/../../Experiments/ExperimentRecord.php';
require_once __DIR__ . '/../../Experiments/ExperimentItemRecord.php';

final class SQLiteExperimentRepository implements ExperimentRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function storageName(): string
    {
        return 'sqlite_experiments';
    }

    public function health(): array
    {
        $this->pdo->query('SELECT 1')->fetchColumn();

        return [
            'storage' => $this->storageName(),
            'tables' => ['experiments', 'experiment_items'],
            'readable' => true,
        ];
    }

    public function createExperiment(array $experiment): array
    {
        $experiment = ExperimentRecord::normalize($experiment);
        ExperimentRecord::validate($experiment);

        $statement = $this->pdo->prepare(
            'INSERT INTO experiments (
                id, name, description, goal_json, status, created_by, created_at, updated_at,
                started_at, completed_at, best_strategy_version_id, best_backtest_result_id,
                summary_json, notes
            ) VALUES (
                :id, :name, :description, :goal_json, :status, :created_by, :created_at, :updated_at,
                :started_at, :completed_at, :best_strategy_version_id, :best_backtest_result_id,
                :summary_json, :notes
            )'
        );
        $statement->execute($this->experimentParams($experiment));

        return $this->findExperiment((string)$experiment['id']) ?? $experiment;
    }

    public function updateExperiment(string $id, array $changes): array
    {
        $experiment = $this->findExperiment($id);
        if ($experiment === null) {
            throw new InvalidArgumentException('Experiment not found: ' . $id);
        }

        unset($changes['id']);
        $updated = ExperimentRecord::normalize(array_merge($experiment, $changes));
        ExperimentRecord::validate($updated);

        $statement = $this->pdo->prepare(
            'UPDATE experiments
             SET name = :name,
                 description = :description,
                 goal_json = :goal_json,
                 status = :status,
                 created_by = :created_by,
                 created_at = :created_at,
                 updated_at = :updated_at,
                 started_at = :started_at,
                 completed_at = :completed_at,
                 best_strategy_version_id = :best_strategy_version_id,
                 best_backtest_result_id = :best_backtest_result_id,
                 summary_json = :summary_json,
                 notes = :notes
             WHERE id = :id'
        );
        $statement->execute($this->experimentParams($updated));

        return $this->findExperiment($id) ?? $updated;
    }

    public function findExperiment(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM experiments WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapExperimentRow($row) : null;
    }

    public function listExperiments(int $limit = 100, array $filters = []): array
    {
        $conditions = [];
        $params = [];
        foreach (['status', 'created_by'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = $field . ' = :' . $field;
                $params[':' . $field] = (string)$filters[$field];
            }
        }

        $sql = 'SELECT * FROM experiments';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn(array $row): array => $this->mapExperimentRow($row), $statement->fetchAll());
    }

    public function addItem(array $item): array
    {
        $item = ExperimentItemRecord::normalize($item);
        ExperimentItemRecord::validate($item);

        $statement = $this->pdo->prepare(
            'INSERT INTO experiment_items (
                id, experiment_id, strategy_version_id, backtest_result_id, item_type,
                status, score, metrics_json, created_at, notes
            ) VALUES (
                :id, :experiment_id, :strategy_version_id, :backtest_result_id, :item_type,
                :status, :score, :metrics_json, :created_at, :notes
            )'
        );
        $statement->execute($this->itemParams($item));

        return $this->findItem((string)$item['id']) ?? $item;
    }

    public function updateItem(string $id, array $changes): array
    {
        $item = $this->findItem($id);
        if ($item === null) {
            throw new InvalidArgumentException('Experiment item not found: ' . $id);
        }

        unset($changes['id']);
        $updated = ExperimentItemRecord::normalize(array_merge($item, $changes));
        ExperimentItemRecord::validate($updated);

        $statement = $this->pdo->prepare(
            'UPDATE experiment_items
             SET experiment_id = :experiment_id,
                 strategy_version_id = :strategy_version_id,
                 backtest_result_id = :backtest_result_id,
                 item_type = :item_type,
                 status = :status,
                 score = :score,
                 metrics_json = :metrics_json,
                 created_at = :created_at,
                 notes = :notes
             WHERE id = :id'
        );
        $statement->execute($this->itemParams($updated));

        return $this->findItem($id) ?? $updated;
    }

    public function findItem(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM experiment_items WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapItemRow($row) : null;
    }

    public function listItems(string $experimentId, int $limit = 100, array $filters = []): array
    {
        $conditions = ['experiment_id = :experiment_id'];
        $params = [':experiment_id' => $experimentId];

        foreach (['status', 'item_type', 'strategy_version_id', 'backtest_result_id'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = $field . ' = :' . $field;
                $params[':' . $field] = (string)$filters[$field];
            }
        }

        $sql = 'SELECT * FROM experiment_items WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY created_at DESC, id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn(array $row): array => $this->mapItemRow($row), $statement->fetchAll());
    }

    public function bestItem(string $experimentId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM experiment_items
             WHERE experiment_id = :experiment_id AND score IS NOT NULL
             ORDER BY score DESC, created_at DESC
             LIMIT 1'
        );
        $statement->execute([':experiment_id' => $experimentId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapItemRow($row) : null;
    }

    private function experimentParams(array $experiment): array
    {
        return [
            ':id' => $experiment['id'],
            ':name' => $experiment['name'],
            ':description' => $experiment['description'],
            ':goal_json' => $this->encodeJson($experiment['goal_json']),
            ':status' => $experiment['status'],
            ':created_by' => $experiment['created_by'],
            ':created_at' => $experiment['created_at'],
            ':updated_at' => $experiment['updated_at'],
            ':started_at' => $experiment['started_at'],
            ':completed_at' => $experiment['completed_at'],
            ':best_strategy_version_id' => $experiment['best_strategy_version_id'],
            ':best_backtest_result_id' => $experiment['best_backtest_result_id'],
            ':summary_json' => $this->encodeJson($experiment['summary_json']),
            ':notes' => $experiment['notes'],
        ];
    }

    private function itemParams(array $item): array
    {
        return [
            ':id' => $item['id'],
            ':experiment_id' => $item['experiment_id'],
            ':strategy_version_id' => $item['strategy_version_id'],
            ':backtest_result_id' => $item['backtest_result_id'],
            ':item_type' => $item['item_type'],
            ':status' => $item['status'],
            ':score' => $item['score'],
            ':metrics_json' => $this->encodeJson($item['metrics_json']),
            ':created_at' => $item['created_at'],
            ':notes' => $item['notes'],
        ];
    }

    private function mapExperimentRow(array $row): array
    {
        return ExperimentRecord::normalize([
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'goal_json' => $row['goal_json'],
            'status' => $row['status'],
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'started_at' => $row['started_at'],
            'completed_at' => $row['completed_at'],
            'best_strategy_version_id' => $row['best_strategy_version_id'],
            'best_backtest_result_id' => $row['best_backtest_result_id'],
            'summary_json' => $row['summary_json'],
            'notes' => $row['notes'],
        ]);
    }

    private function mapItemRow(array $row): array
    {
        return ExperimentItemRecord::normalize([
            'id' => $row['id'],
            'experiment_id' => $row['experiment_id'],
            'strategy_version_id' => $row['strategy_version_id'],
            'backtest_result_id' => $row['backtest_result_id'],
            'item_type' => $row['item_type'],
            'status' => $row['status'],
            'score' => $row['score'],
            'metrics_json' => $row['metrics_json'],
            'created_at' => $row['created_at'],
            'notes' => $row['notes'],
        ]);
    }

    private function encodeJson(mixed $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode experiment JSON payload.');
        }

        return $json;
    }
}
