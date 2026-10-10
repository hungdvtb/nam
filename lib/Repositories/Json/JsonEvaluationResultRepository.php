<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/EvaluationResultRepositoryInterface.php';
require_once __DIR__ . '/../../Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/../../Evaluations/EvaluationStatus.php';
require_once __DIR__ . '/../../Evaluations/EvaluationResultRecord.php';

final class JsonEvaluationResultRepository implements EvaluationResultRepositoryInterface
{
    public function __construct(private JsonCompatibilityAdapter $adapter)
    {
    }

    public function storageName(): string
    {
        return 'legacy_json_evaluation_results';
    }

    public function health(): array
    {
        return [
            'storage' => $this->storageName(),
            'dataset' => 'evaluation_results',
            'readable' => true,
        ];
    }

    public function save(array $evaluation): array
    {
        $evaluation = EvaluationResultRecord::normalize($evaluation);
        EvaluationResultRecord::validate($evaluation);

        $evaluations = $this->storedEvaluations();
        $found = false;
        foreach ($evaluations as $index => $existing) {
            if ((string)($existing['id'] ?? '') === (string)$evaluation['id']) {
                $evaluations[$index] = $evaluation;
                $found = true;
                break;
            }
        }

        if (!$found) {
            array_unshift($evaluations, $evaluation);
        }

        $this->writeEvaluations($evaluations);
        return $this->find((string)$evaluation['id']) ?? $evaluation;
    }

    public function find(string $id): ?array
    {
        foreach ($this->list(0) as $evaluation) {
            if ((string)$evaluation['id'] === $id) {
                return $evaluation;
            }
        }

        return null;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        $evaluations = array_map(
            fn(array $evaluation): array => EvaluationResultRecord::normalize($evaluation),
            $this->storedEvaluations()
        );
        $evaluations = array_values(array_filter($evaluations, fn(array $evaluation): bool => $this->matchesFilters($evaluation, $filters)));
        usort($evaluations, fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));

        return $limit > 0 ? array_slice($evaluations, 0, $limit) : $evaluations;
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
        $evaluations = $this->list(0, $filters);
        usort($evaluations, function (array $a, array $b): int {
            $score = (float)$b['score'] <=> (float)$a['score'];
            if ($score !== 0) {
                return $score;
            }

            return strcmp((string)$b['created_at'], (string)$a['created_at']);
        });

        return $evaluations[0] ?? null;
    }

    private function storedEvaluations(): array
    {
        $evaluations = $this->adapter->read('evaluation_results', []);
        return is_array($evaluations) ? array_values(array_filter($evaluations, 'is_array')) : [];
    }

    private function writeEvaluations(array $evaluations): void
    {
        $this->adapter->write('evaluation_results', array_values(array_map(
            fn(array $evaluation): array => EvaluationResultRecord::normalize($evaluation),
            $evaluations
        )));
    }

    private function matchesFilters(array $evaluation, array $filters): bool
    {
        foreach (['strategy_version_id', 'backtest_result_id', 'experiment_id', 'status', 'created_by'] as $field) {
            if (isset($filters[$field]) && (string)($evaluation[$field] ?? '') !== (string)$filters[$field]) {
                return false;
            }
        }

        if (array_key_exists('passed', $filters) && (bool)$evaluation['passed'] !== (bool)$filters['passed']) {
            return false;
        }

        return true;
    }
}
