<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/AIJobRepositoryInterface.php';
require_once __DIR__ . '/../../AIJobs/AIJobStatus.php';
require_once __DIR__ . '/../../AIJobs/AIJobType.php';
require_once __DIR__ . '/../../AIJobs/AIJobRecord.php';

final class SQLiteAIJobRepository implements AIJobRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function storageName(): string
    {
        return 'sqlite_ai_jobs';
    }

    public function health(): array
    {
        $this->pdo->query('SELECT 1')->fetchColumn();

        return [
            'storage' => $this->storageName(),
            'table' => 'ai_jobs',
            'readable' => true,
        ];
    }

    public function create(array $job): array
    {
        $job = AIJobRecord::normalize($job);
        AIJobRecord::validate($job);

        $statement = $this->pdo->prepare(
            'INSERT INTO ai_jobs (
                id, job_type, status, input_json, output_json, error_message,
                strategy_version_id, experiment_id, created_at, started_at,
                completed_at, created_by, notes
            ) VALUES (
                :id, :job_type, :status, :input_json, :output_json, :error_message,
                :strategy_version_id, :experiment_id, :created_at, :started_at,
                :completed_at, :created_by, :notes
            )'
        );
        $statement->execute($this->params($job));

        return $this->find((string)$job['id']) ?? $job;
    }

    public function update(string $id, array $changes): array
    {
        $job = $this->find($id);
        if ($job === null) {
            throw new InvalidArgumentException('AI job not found: ' . $id);
        }

        unset($changes['id']);
        $updated = AIJobRecord::normalize(array_merge($job, $changes));
        AIJobRecord::validate($updated);

        $statement = $this->pdo->prepare(
            'UPDATE ai_jobs
             SET job_type = :job_type,
                 status = :status,
                 input_json = :input_json,
                 output_json = :output_json,
                 error_message = :error_message,
                 strategy_version_id = :strategy_version_id,
                 experiment_id = :experiment_id,
                 created_at = :created_at,
                 started_at = :started_at,
                 completed_at = :completed_at,
                 created_by = :created_by,
                 notes = :notes
             WHERE id = :id'
        );
        $statement->execute($this->params($updated));

        return $this->find($id) ?? $updated;
    }

    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ai_jobs WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        $conditions = [];
        $params = [];

        foreach (['status', 'job_type', 'strategy_version_id', 'experiment_id', 'created_by'] as $field) {
            if (!isset($filters[$field]) || trim((string)$filters[$field]) === '') {
                continue;
            }

            $conditions[] = $field . ' = :' . $field;
            $params[':' . $field] = match ($field) {
                'status' => AIJobStatus::normalize((string)$filters[$field]),
                'job_type' => AIJobType::normalize((string)$filters[$field]),
                default => (string)$filters[$field],
            };
        }

        $sql = 'SELECT * FROM ai_jobs';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn(array $row): array => $this->mapRow($row), $statement->fetchAll());
    }

    private function params(array $job): array
    {
        return [
            ':id' => $job['id'],
            ':job_type' => $job['job_type'],
            ':status' => $job['status'],
            ':input_json' => $this->encodeJson($job['input_json']),
            ':output_json' => $this->encodeJson($job['output_json']),
            ':error_message' => $job['error_message'],
            ':strategy_version_id' => $job['strategy_version_id'],
            ':experiment_id' => $job['experiment_id'],
            ':created_at' => $job['created_at'],
            ':started_at' => $job['started_at'],
            ':completed_at' => $job['completed_at'],
            ':created_by' => $job['created_by'],
            ':notes' => $job['notes'],
        ];
    }

    private function mapRow(array $row): array
    {
        return AIJobRecord::normalize([
            'id' => $row['id'],
            'job_type' => $row['job_type'],
            'status' => $row['status'],
            'input_json' => $row['input_json'],
            'output_json' => $row['output_json'],
            'error_message' => $row['error_message'],
            'strategy_version_id' => $row['strategy_version_id'],
            'experiment_id' => $row['experiment_id'],
            'created_at' => $row['created_at'],
            'started_at' => $row['started_at'],
            'completed_at' => $row['completed_at'],
            'created_by' => $row['created_by'],
            'notes' => $row['notes'],
        ]);
    }

    private function encodeJson(mixed $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode AI job JSON payload.');
        }

        return $json;
    }
}
