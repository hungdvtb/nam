<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/StrategyVersionRepositoryInterface.php';
require_once __DIR__ . '/../../StrategyVersions/StrategyVersionStatus.php';
require_once __DIR__ . '/../../StrategyVersions/StrategyVersionRecord.php';

final class SQLiteStrategyVersionRepository implements StrategyVersionRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function storageName(): string
    {
        return 'sqlite_strategy_versions';
    }

    public function health(): array
    {
        $this->pdo->query('SELECT 1')->fetchColumn();

        return [
            'storage' => $this->storageName(),
            'table' => 'strategy_versions',
            'readable' => true,
        ];
    }

    public function create(array $version): array
    {
        $version = StrategyVersionRecord::normalize($version);
        StrategyVersionRecord::validate($version);

        if ($this->versionCodeExists($version['version_code'])) {
            throw new InvalidArgumentException('Strategy version code already exists: ' . $version['version_code']);
        }

        $this->insert($version);
        return $this->find((string)$version['id']) ?? $version;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (isset($filters['status'])) {
            $conditions[] = 'status = :status';
            $params[':status'] = StrategyVersionStatus::normalize((string)$filters['status']);
        }

        if (array_key_exists('parent_version_id', $filters)) {
            if ($filters['parent_version_id'] === null || $filters['parent_version_id'] === '') {
                $conditions[] = 'parent_version_id IS NULL';
            } else {
                $conditions[] = 'parent_version_id = :parent_version_id';
                $params[':parent_version_id'] = (string)$filters['parent_version_id'];
            }
        }

        if (isset($filters['created_by'])) {
            $conditions[] = 'created_by = :created_by';
            $params[':created_by'] = (string)$filters['created_by'];
        }

        if (array_key_exists('approved', $filters)) {
            $conditions[] = 'approved = :approved';
            $params[':approved'] = (bool)$filters['approved'] ? 1 : 0;
        }

        $sql = 'SELECT * FROM strategy_versions';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY created_at DESC, version_code DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn(array $row): array => $this->mapRow($row), $statement->fetchAll());
    }

    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM strategy_versions WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function findByVersionCode(string $versionCode): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM strategy_versions WHERE version_code = :version_code');
        $statement->execute([':version_code' => $versionCode]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function save(array $version): void
    {
        $version = StrategyVersionRecord::normalize($version);
        StrategyVersionRecord::validate($version);

        if ($this->versionCodeExists($version['version_code'], $version['id'])) {
            throw new InvalidArgumentException('Strategy version code already exists: ' . $version['version_code']);
        }

        if ($this->find((string)$version['id']) === null) {
            $this->insert($version);
            return;
        }

        $this->updateRow($version);
    }

    public function update(string $id, array $changes): array
    {
        $version = $this->find($id);
        if ($version === null) {
            throw new InvalidArgumentException('Strategy version not found: ' . $id);
        }

        unset($changes['id']);
        $updated = array_merge($version, $changes, ['updated_at' => $changes['updated_at'] ?? gmdate('c')]);
        $this->save($updated);

        return $this->find($id) ?? StrategyVersionRecord::normalize($updated);
    }

    public function latest(): ?array
    {
        return $this->list(1)[0] ?? null;
    }

    public function production(): ?array
    {
        return $this->list(1, ['status' => StrategyVersionStatus::PRODUCTION])[0] ?? null;
    }

    public function versionCodeExists(string $versionCode, ?string $excludeId = null): bool
    {
        if ($excludeId === null) {
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM strategy_versions WHERE version_code = :version_code');
            $statement->execute([':version_code' => $versionCode]);
        } else {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM strategy_versions WHERE version_code = :version_code AND id <> :id'
            );
            $statement->execute([
                ':version_code' => $versionCode,
                ':id' => $excludeId,
            ]);
        }

        return (int)$statement->fetchColumn() > 0;
    }

    public function appendEvent(string $versionId, string $eventType, array $payload): void
    {
        if ($this->find($versionId) === null) {
            throw new InvalidArgumentException('Strategy version not found: ' . $versionId);
        }

        $event = StrategyVersionRecord::historyEvent($versionId, $eventType, $payload);
        $statement = $this->pdo->prepare(
            'INSERT INTO storage_events (event_type, aggregate_type, aggregate_id, payload_json, created_at)
             VALUES (:event_type, :aggregate_type, :aggregate_id, :payload_json, :created_at)'
        );
        $statement->execute([
            ':event_type' => $eventType,
            ':aggregate_type' => 'strategy_version',
            ':aggregate_id' => $versionId,
            ':payload_json' => $this->encodeJson($event),
            ':created_at' => $event['created_at'],
        ]);
    }

    public function events(string $versionId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, event_type, payload_json, created_at
             FROM storage_events
             WHERE aggregate_type = :aggregate_type AND aggregate_id = :aggregate_id
             ORDER BY id ASC'
        );
        $statement->execute([
            ':aggregate_type' => 'strategy_version',
            ':aggregate_id' => $versionId,
        ]);

        $events = [];
        foreach ($statement->fetchAll() as $row) {
            $payload = json_decode((string)$row['payload_json'], true);
            $events[] = json_last_error() === JSON_ERROR_NONE && is_array($payload)
                ? $payload
                : [
                    'id' => (string)$row['id'],
                    'version_id' => $versionId,
                    'event_type' => (string)$row['event_type'],
                    'payload' => [],
                    'created_at' => (string)$row['created_at'],
                ];
        }

        return $events;
    }

    private function insert(array $version): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO strategy_versions (
                id, version_code, name, description, parent_version_id, status, created_by,
                created_at, updated_at, performance_json, rules_json, settings_json,
                win_rate, average_rr, profit_factor, max_drawdown, total_trades,
                backtest_start, backtest_end, approved, approved_at
            ) VALUES (
                :id, :version_code, :name, :description, :parent_version_id, :status, :created_by,
                :created_at, :updated_at, :performance_json, :rules_json, :settings_json,
                :win_rate, :average_rr, :profit_factor, :max_drawdown, :total_trades,
                :backtest_start, :backtest_end, :approved, :approved_at
            )'
        );
        $statement->execute($this->params($version));
    }

    private function updateRow(array $version): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE strategy_versions
             SET version_code = :version_code,
                 name = :name,
                 description = :description,
                 parent_version_id = :parent_version_id,
                 status = :status,
                 created_by = :created_by,
                 created_at = :created_at,
                 updated_at = :updated_at,
                 performance_json = :performance_json,
                 rules_json = :rules_json,
                 settings_json = :settings_json,
                 win_rate = :win_rate,
                 average_rr = :average_rr,
                 profit_factor = :profit_factor,
                 max_drawdown = :max_drawdown,
                 total_trades = :total_trades,
                 backtest_start = :backtest_start,
                 backtest_end = :backtest_end,
                 approved = :approved,
                 approved_at = :approved_at
             WHERE id = :id'
        );
        $statement->execute($this->params($version));
    }

    private function params(array $version): array
    {
        return [
            ':id' => $version['id'],
            ':version_code' => $version['version_code'],
            ':name' => $version['name'],
            ':description' => $version['description'],
            ':parent_version_id' => $version['parent_version_id'],
            ':status' => $version['status'],
            ':created_by' => $version['created_by'],
            ':created_at' => $version['created_at'],
            ':updated_at' => $version['updated_at'],
            ':performance_json' => $this->encodeJson($version['performance_json']),
            ':rules_json' => $this->encodeJson($version['rules_json']),
            ':settings_json' => $this->encodeJson($version['settings_json']),
            ':win_rate' => $version['win_rate'],
            ':average_rr' => $version['average_rr'],
            ':profit_factor' => $version['profit_factor'],
            ':max_drawdown' => $version['max_drawdown'],
            ':total_trades' => $version['total_trades'],
            ':backtest_start' => $version['backtest_start'],
            ':backtest_end' => $version['backtest_end'],
            ':approved' => $version['approved'] ? 1 : 0,
            ':approved_at' => $version['approved_at'],
        ];
    }

    private function mapRow(array $row): array
    {
        $record = StrategyVersionRecord::normalize([
            'id' => $row['id'],
            'version_code' => $row['version_code'],
            'name' => $row['name'],
            'description' => $row['description'],
            'parent_version_id' => $row['parent_version_id'],
            'status' => $row['status'],
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'performance_json' => $row['performance_json'],
            'rules_json' => $row['rules_json'],
            'settings_json' => $row['settings_json'],
            'win_rate' => $row['win_rate'],
            'average_rr' => $row['average_rr'],
            'profit_factor' => $row['profit_factor'],
            'max_drawdown' => $row['max_drawdown'],
            'total_trades' => $row['total_trades'],
            'backtest_start' => $row['backtest_start'],
            'backtest_end' => $row['backtest_end'],
            'approved' => (int)$row['approved'] === 1,
            'approved_at' => $row['approved_at'],
        ]);
        $record['history'] = $this->events((string)$record['id']);

        return $record;
    }

    private function encodeJson(mixed $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode strategy version JSON payload.');
        }

        return $json;
    }
}
