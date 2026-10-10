<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/EvaluationResultRepositoryInterface.php';
require_once __DIR__ . '/../../Evaluations/EvaluationStatus.php';
require_once __DIR__ . '/../../Evaluations/EvaluationResultRecord.php';

final class SQLiteEvaluationResultRepository implements EvaluationResultRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function storageName(): string
    {
        return 'sqlite_evaluation_results';
    }

    public function health(): array
    {
        $this->pdo->query('SELECT 1')->fetchColumn();

        return [
            'storage' => $this->storageName(),
            'table' => 'evaluation_results',
            'readable' => true,
        ];
    }

    public function save(array $evaluation): array
    {
        $evaluation = EvaluationResultRecord::normalize($evaluation);
        EvaluationResultRecord::validate($evaluation);

        if ($this->find((string)$evaluation['id']) === null) {
            $this->insert($evaluation);
        } else {
            $this->update($evaluation);
        }

        return $this->find((string)$evaluation['id']) ?? $evaluation;
    }

    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM evaluation_results WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        [$where, $params] = $this->filters($filters);
        $sql = 'SELECT * FROM evaluation_results' . $where . ' ORDER BY created_at DESC, id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn(array $row): array => $this->mapRow($row), $statement->fetchAll());
    }

    public function listByStrategyVersion(string $strategyVersionId, int $limit = 100): array
    {
        return $this->list($limit, ['strategy_version_id' => $strategyVersionId]);
    }

    public function listByExperiment(string $experimentId, int $limit = 100): array
    {
        return $this->list($limit, ['experiment_id' => $experimentId]);
    }

    public function latest(array $filters = []): ?array
    {
        return $this->list(1, $filters)[0] ?? null;
    }

    public function best(array $filters = []): ?array
    {
        [$where, $params] = $this->filters($filters);
        $sql = 'SELECT * FROM evaluation_results' . $where . ' ORDER BY score DESC, created_at DESC, id DESC LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    private function insert(array $evaluation): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO evaluation_results (
                id, strategy_version_id, backtest_result_id, experiment_id, status,
                score, passed, kpi_json, metrics_json, reasons_json,
                recommendations_json, created_at, created_by, notes
            ) VALUES (
                :id, :strategy_version_id, :backtest_result_id, :experiment_id, :status,
                :score, :passed, :kpi_json, :metrics_json, :reasons_json,
                :recommendations_json, :created_at, :created_by, :notes
            )'
        );
        $statement->execute($this->params($evaluation));
    }

    private function update(array $evaluation): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE evaluation_results
             SET strategy_version_id = :strategy_version_id,
                 backtest_result_id = :backtest_result_id,
                 experiment_id = :experiment_id,
                 status = :status,
                 score = :score,
                 passed = :passed,
                 kpi_json = :kpi_json,
                 metrics_json = :metrics_json,
                 reasons_json = :reasons_json,
                 recommendations_json = :recommendations_json,
                 created_at = :created_at,
                 created_by = :created_by,
                 notes = :notes
             WHERE id = :id'
        );
        $statement->execute($this->params($evaluation));
    }

    private function params(array $evaluation): array
    {
        return [
            ':id' => $evaluation['id'],
            ':strategy_version_id' => $evaluation['strategy_version_id'],
            ':backtest_result_id' => $evaluation['backtest_result_id'],
            ':experiment_id' => $evaluation['experiment_id'],
            ':status' => $evaluation['status'],
            ':score' => $evaluation['score'],
            ':passed' => $evaluation['passed'] ? 1 : 0,
            ':kpi_json' => $this->encodeJson($evaluation['kpi_json']),
            ':metrics_json' => $this->encodeJson($evaluation['metrics_json']),
            ':reasons_json' => $this->encodeJson($evaluation['reasons_json']),
            ':recommendations_json' => $this->encodeJson($evaluation['recommendations_json']),
            ':created_at' => $evaluation['created_at'],
            ':created_by' => $evaluation['created_by'],
            ':notes' => $evaluation['notes'],
        ];
    }

    private function filters(array $filters): array
    {
        $conditions = [];
        $params = [];
        foreach (['strategy_version_id', 'backtest_result_id', 'experiment_id', 'status', 'created_by'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = $field . ' = :' . $field;
                $params[':' . $field] = (string)$filters[$field];
            }
        }

        if (array_key_exists('passed', $filters)) {
            $conditions[] = 'passed = :passed';
            $params[':passed'] = (bool)$filters['passed'] ? 1 : 0;
        }

        return [
            $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions),
            $params,
        ];
    }

    private function mapRow(array $row): array
    {
        return EvaluationResultRecord::normalize([
            'id' => $row['id'],
            'strategy_version_id' => $row['strategy_version_id'],
            'backtest_result_id' => $row['backtest_result_id'],
            'experiment_id' => $row['experiment_id'],
            'status' => $row['status'],
            'score' => $row['score'],
            'passed' => (int)$row['passed'] === 1,
            'kpi_json' => $row['kpi_json'],
            'metrics_json' => $row['metrics_json'],
            'reasons_json' => $row['reasons_json'],
            'recommendations_json' => $row['recommendations_json'],
            'created_at' => $row['created_at'],
            'created_by' => $row['created_by'],
            'notes' => $row['notes'],
        ]);
    }

    private function encodeJson(mixed $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode evaluation JSON payload.');
        }

        return $json;
    }
}
