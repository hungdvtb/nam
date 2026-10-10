<?php

declare(strict_types=1);

require_once __DIR__ . '/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/Repositories/Contracts/BacktestResultRepositoryInterface.php';
require_once __DIR__ . '/Backtests/BacktestResultRecord.php';

final class BacktestResultService
{
    public function __construct(private BacktestResultRepositoryInterface $results)
    {
    }

    public function saveResult(array $result): array
    {
        if (trim((string)($result['id'] ?? '')) === '') {
            $result['id'] = $this->newId();
        }

        $normalized = BacktestResultRecord::normalize($result);
        BacktestResultRecord::validate($normalized);

        return $this->results->save($normalized);
    }

    public function findResult(string $id): ?array
    {
        return $this->results->find($id);
    }

    public function listResults(int $limit = 100, array $filters = []): array
    {
        return $this->results->list($limit, $filters);
    }

    public function listByStrategyVersion(string $strategyVersionId, int $limit = 100): array
    {
        return $this->results->listByStrategyVersion($strategyVersionId, $limit);
    }

    public function getLatestResult(?string $strategyVersionId = null): ?array
    {
        return $this->results->latest($strategyVersionId);
    }

    public function getBestResult(?string $strategyVersionId = null, string $metric = 'profit_factor'): ?array
    {
        $filters = $strategyVersionId === null ? [] : ['strategy_version_id' => $strategyVersionId];
        return $this->results->best($filters, $metric);
    }

    public function deleteResult(string $id): bool
    {
        return $this->results->delete($id);
    }

    private function newId(): string
    {
        return 'bt_result_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
}
