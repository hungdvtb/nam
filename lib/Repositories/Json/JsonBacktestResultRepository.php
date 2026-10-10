<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/BacktestResultRepositoryInterface.php';
require_once __DIR__ . '/../../Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/../../Backtests/BacktestResultRecord.php';

final class JsonBacktestResultRepository implements BacktestResultRepositoryInterface
{
    public function __construct(private JsonCompatibilityAdapter $adapter)
    {
    }

    public function storageName(): string
    {
        return 'legacy_json_backtest_results';
    }

    public function health(): array
    {
        return [
            'storage' => $this->storageName(),
            'dataset' => 'backtest_results',
            'readable' => true,
        ];
    }

    public function save(array $result): array
    {
        $result = BacktestResultRecord::normalize($result);
        BacktestResultRecord::validate($result);

        $results = $this->storedResults();
        $found = false;
        foreach ($results as $index => $existing) {
            if ((string)($existing['id'] ?? '') === (string)$result['id']) {
                $results[$index] = $result;
                $found = true;
                break;
            }
        }

        if (!$found) {
            array_unshift($results, $result);
        }

        $this->writeResults($results);
        return $this->find((string)$result['id']) ?? $result;
    }

    public function find(string $id): ?array
    {
        foreach ($this->list(0) as $result) {
            if ((string)$result['id'] === $id) {
                return $result;
            }
        }

        return null;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        $results = array_map(
            fn(array $result): array => BacktestResultRecord::normalize($result),
            $this->storedResults()
        );

        $results = array_values(array_filter($results, fn(array $result): bool => $this->matchesFilters($result, $filters)));
        usort($results, fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));

        return $limit > 0 ? array_slice($results, 0, $limit) : $results;
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

        $results = $this->list(0, $filters);
        usort($results, function (array $a, array $b) use ($metric): int {
            $left = $a[$metric] ?? null;
            $right = $b[$metric] ?? null;
            if ($left === null && $right === null) {
                return 0;
            }
            if ($left === null) {
                return 1;
            }
            if ($right === null) {
                return -1;
            }

            if ($metric === 'max_drawdown') {
                return (float)$left <=> (float)$right;
            }

            return (float)$right <=> (float)$left;
        });

        return $results[0] ?? null;
    }

    public function delete(string $id): bool
    {
        $results = $this->storedResults();
        $kept = array_values(array_filter($results, fn(array $result): bool => (string)($result['id'] ?? '') !== $id));
        if (count($kept) === count($results)) {
            return false;
        }

        $this->writeResults($kept);
        return true;
    }

    private function storedResults(): array
    {
        $results = $this->adapter->read('backtest_results', []);
        return is_array($results) ? array_values(array_filter($results, 'is_array')) : [];
    }

    private function writeResults(array $results): void
    {
        $normalized = array_map(
            fn(array $result): array => BacktestResultRecord::normalize($result),
            $results
        );
        $this->adapter->write('backtest_results', array_values($normalized));
    }

    private function matchesFilters(array $result, array $filters): bool
    {
        foreach (['strategy_version_id', 'symbol', 'timeframe', 'higher_timeframe', 'created_by'] as $field) {
            if (isset($filters[$field]) && (string)($result[$field] ?? '') !== (string)$filters[$field]) {
                return false;
            }
        }

        return true;
    }
}
