<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/BacktestResultRepositoryInterface.php';
require_once __DIR__ . '/../../Backtests/BacktestResultRecord.php';

final class SQLiteBacktestResultRepository implements BacktestResultRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function storageName(): string
    {
        return 'sqlite_backtest_results';
    }

    public function health(): array
    {
        $this->pdo->query('SELECT 1')->fetchColumn();

        return [
            'storage' => $this->storageName(),
            'table' => 'backtest_results',
            'readable' => true,
        ];
    }

    public function save(array $result): array
    {
        $result = BacktestResultRecord::normalize($result);
        BacktestResultRecord::validate($result);

        if ($this->find((string)$result['id']) === null) {
            $this->insert($result);
        } else {
            $this->update($result);
        }

        return $this->find((string)$result['id']) ?? $result;
    }

    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM backtest_results WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        $conditions = [];
        $params = [];

        foreach (['strategy_version_id', 'symbol', 'timeframe', 'higher_timeframe', 'created_by'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = $field . ' = :' . $field;
                $params[':' . $field] = (string)$filters[$field];
            }
        }

        $sql = 'SELECT * FROM backtest_results';
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

    public function listByStrategyVersion(string $strategyVersionId, int $limit = 100): array
    {
        return $this->list($limit, ['strategy_version_id' => $strategyVersionId]);
    }

    public function latest(?string $strategyVersionId = null): ?array
    {
        $filters = $strategyVersionId === null ? [] : ['strategy_version_id' => $strategyVersionId];
        return $this->list(1, $filters)[0] ?? null;
    }

    public function best(array $filters = [], string $metric = 'profit_factor'): ?array
    {
        $allowed = ['profit_factor', 'net_profit', 'win_rate', 'average_rr', 'max_drawdown'];
        if (!in_array($metric, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported best-result metric: ' . $metric);
        }

        $conditions = [];
        $params = [];
        foreach (['strategy_version_id', 'symbol', 'timeframe', 'higher_timeframe', 'created_by'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = $field . ' = :' . $field;
                $params[':' . $field] = (string)$filters[$field];
            }
        }
        $conditions[] = $metric . ' IS NOT NULL';

        $direction = $metric === 'max_drawdown' ? 'ASC' : 'DESC';
        $sql = 'SELECT * FROM backtest_results WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY ' . $metric . ' ' . $direction . ', created_at DESC LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function delete(string $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM backtest_results WHERE id = :id');
        $statement->execute([':id' => $id]);

        return $statement->rowCount() > 0;
    }

    private function insert(array $result): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO backtest_results (
                id, strategy_version_id, symbol, timeframe, higher_timeframe,
                start_date, end_date, initial_balance, final_balance, net_profit,
                win_rate, average_rr, profit_factor, max_drawdown, total_trades,
                winning_trades, losing_trades, settings_json, metrics_json,
                trades_json, report_path, created_at, created_by, notes
            ) VALUES (
                :id, :strategy_version_id, :symbol, :timeframe, :higher_timeframe,
                :start_date, :end_date, :initial_balance, :final_balance, :net_profit,
                :win_rate, :average_rr, :profit_factor, :max_drawdown, :total_trades,
                :winning_trades, :losing_trades, :settings_json, :metrics_json,
                :trades_json, :report_path, :created_at, :created_by, :notes
            )'
        );
        $statement->execute($this->params($result));
    }

    private function update(array $result): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE backtest_results
             SET strategy_version_id = :strategy_version_id,
                 symbol = :symbol,
                 timeframe = :timeframe,
                 higher_timeframe = :higher_timeframe,
                 start_date = :start_date,
                 end_date = :end_date,
                 initial_balance = :initial_balance,
                 final_balance = :final_balance,
                 net_profit = :net_profit,
                 win_rate = :win_rate,
                 average_rr = :average_rr,
                 profit_factor = :profit_factor,
                 max_drawdown = :max_drawdown,
                 total_trades = :total_trades,
                 winning_trades = :winning_trades,
                 losing_trades = :losing_trades,
                 settings_json = :settings_json,
                 metrics_json = :metrics_json,
                 trades_json = :trades_json,
                 report_path = :report_path,
                 created_at = :created_at,
                 created_by = :created_by,
                 notes = :notes
             WHERE id = :id'
        );
        $statement->execute($this->params($result));
    }

    private function params(array $result): array
    {
        return [
            ':id' => $result['id'],
            ':strategy_version_id' => $result['strategy_version_id'],
            ':symbol' => $result['symbol'],
            ':timeframe' => $result['timeframe'],
            ':higher_timeframe' => $result['higher_timeframe'],
            ':start_date' => $result['start_date'],
            ':end_date' => $result['end_date'],
            ':initial_balance' => $result['initial_balance'],
            ':final_balance' => $result['final_balance'],
            ':net_profit' => $result['net_profit'],
            ':win_rate' => $result['win_rate'],
            ':average_rr' => $result['average_rr'],
            ':profit_factor' => $result['profit_factor'],
            ':max_drawdown' => $result['max_drawdown'],
            ':total_trades' => $result['total_trades'],
            ':winning_trades' => $result['winning_trades'],
            ':losing_trades' => $result['losing_trades'],
            ':settings_json' => $this->encodeJson($result['settings_json']),
            ':metrics_json' => $this->encodeJson($result['metrics_json']),
            ':trades_json' => $this->encodeJson($result['trades_json']),
            ':report_path' => $result['report_path'],
            ':created_at' => $result['created_at'],
            ':created_by' => $result['created_by'],
            ':notes' => $result['notes'],
        ];
    }

    private function mapRow(array $row): array
    {
        return BacktestResultRecord::normalize([
            'id' => $row['id'],
            'strategy_version_id' => $row['strategy_version_id'],
            'symbol' => $row['symbol'],
            'timeframe' => $row['timeframe'],
            'higher_timeframe' => $row['higher_timeframe'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'initial_balance' => $row['initial_balance'],
            'final_balance' => $row['final_balance'],
            'net_profit' => $row['net_profit'],
            'win_rate' => $row['win_rate'],
            'average_rr' => $row['average_rr'],
            'profit_factor' => $row['profit_factor'],
            'max_drawdown' => $row['max_drawdown'],
            'total_trades' => $row['total_trades'],
            'winning_trades' => $row['winning_trades'],
            'losing_trades' => $row['losing_trades'],
            'settings_json' => $row['settings_json'],
            'metrics_json' => $row['metrics_json'],
            'trades_json' => $row['trades_json'],
            'report_path' => $row['report_path'],
            'created_at' => $row['created_at'],
            'created_by' => $row['created_by'],
            'notes' => $row['notes'],
        ]);
    }

    private function encodeJson(mixed $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode backtest result JSON payload.');
        }

        return $json;
    }
}
