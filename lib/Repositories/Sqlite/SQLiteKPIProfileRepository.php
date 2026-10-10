<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/KPIProfileRepositoryInterface.php';
require_once __DIR__ . '/../../KPIProfiles/KPIProfileRecord.php';

final class SQLiteKPIProfileRepository implements KPIProfileRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function storageName(): string
    {
        return 'sqlite_kpi_profiles';
    }

    public function health(): array
    {
        $this->pdo->query('SELECT 1')->fetchColumn();

        return [
            'storage' => $this->storageName(),
            'table' => 'kpi_profiles',
            'readable' => true,
        ];
    }

    public function save(array $profile): array
    {
        $profile = KPIProfileRecord::normalize($profile);
        KPIProfileRecord::validate($profile);

        if ($this->find((string)$profile['id']) === null) {
            $this->insert($profile);
        } else {
            $this->update($profile);
        }

        return $this->find((string)$profile['id']) ?? $profile;
    }

    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kpi_profiles WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function list(int $limit = 100): array
    {
        $sql = 'SELECT * FROM kpi_profiles ORDER BY is_default DESC, updated_at DESC, id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $statement = $this->pdo->query($sql);

        return array_map(fn(array $row): array => $this->mapRow($row), $statement->fetchAll());
    }

    public function default(): ?array
    {
        $statement = $this->pdo->query('SELECT * FROM kpi_profiles WHERE is_default = 1 ORDER BY updated_at DESC, id DESC LIMIT 1');
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function setDefault(string $id): array
    {
        $profile = $this->find($id);
        if ($profile === null) {
            throw new InvalidArgumentException('KPI profile not found: ' . $id);
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('UPDATE kpi_profiles SET is_default = 0');
            $statement = $this->pdo->prepare('UPDATE kpi_profiles SET is_default = 1, updated_at = :updated_at WHERE id = :id');
            $statement->execute([
                ':id' => $id,
                ':updated_at' => gmdate('c'),
            ]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }

        return $this->find($id) ?? $profile;
    }

    public function delete(string $id): bool
    {
        $wasDefault = (bool)($this->find($id)['is_default'] ?? false);
        $statement = $this->pdo->prepare('DELETE FROM kpi_profiles WHERE id = :id');
        $statement->execute([':id' => $id]);
        $deleted = $statement->rowCount() > 0;

        if ($deleted && $wasDefault && $this->default() === null) {
            $next = $this->list(1)[0] ?? null;
            if ($next !== null) {
                $this->setDefault((string)$next['id']);
            }
        }

        return $deleted;
    }

    private function insert(array $profile): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO kpi_profiles (
                id, name, description, is_default, win_rate_min, average_rr_min,
                profit_factor_min, max_drawdown_max, total_trades_min,
                created_at, updated_at, notes
            ) VALUES (
                :id, :name, :description, :is_default, :win_rate_min, :average_rr_min,
                :profit_factor_min, :max_drawdown_max, :total_trades_min,
                :created_at, :updated_at, :notes
            )'
        );
        $statement->execute($this->params($profile));
    }

    private function update(array $profile): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE kpi_profiles
             SET name = :name,
                 description = :description,
                 is_default = :is_default,
                 win_rate_min = :win_rate_min,
                 average_rr_min = :average_rr_min,
                 profit_factor_min = :profit_factor_min,
                 max_drawdown_max = :max_drawdown_max,
                 total_trades_min = :total_trades_min,
                 created_at = :created_at,
                 updated_at = :updated_at,
                 notes = :notes
             WHERE id = :id'
        );
        $statement->execute($this->params($profile));
    }

    private function params(array $profile): array
    {
        return [
            ':id' => $profile['id'],
            ':name' => $profile['name'],
            ':description' => $profile['description'],
            ':is_default' => $profile['is_default'] ? 1 : 0,
            ':win_rate_min' => $profile['win_rate_min'],
            ':average_rr_min' => $profile['average_rr_min'],
            ':profit_factor_min' => $profile['profit_factor_min'],
            ':max_drawdown_max' => $profile['max_drawdown_max'],
            ':total_trades_min' => $profile['total_trades_min'],
            ':created_at' => $profile['created_at'],
            ':updated_at' => $profile['updated_at'],
            ':notes' => $profile['notes'],
        ];
    }

    private function mapRow(array $row): array
    {
        return KPIProfileRecord::normalize([
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'is_default' => (int)$row['is_default'] === 1,
            'win_rate_min' => $row['win_rate_min'],
            'average_rr_min' => $row['average_rr_min'],
            'profit_factor_min' => $row['profit_factor_min'],
            'max_drawdown_max' => $row['max_drawdown_max'],
            'total_trades_min' => $row['total_trades_min'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'notes' => $row['notes'],
        ]);
    }
}
