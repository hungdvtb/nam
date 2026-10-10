<?php

declare(strict_types=1);

require_once __DIR__ . '/../BacktestResultService.php';
require_once __DIR__ . '/../EvaluationService.php';

final class BacktestV2ApiController
{
    public function __construct(
        private BacktestResultService $backtests,
        private EvaluationService $evaluations
    ) {
    }

    public function handle(string $action, array $payload): array
    {
        return match ($action) {
            'backtest_v2_list' => $this->listBacktests($payload),
            'backtest_v2_find' => $this->findBacktest($payload),
            'evaluation_list' => $this->listEvaluations($payload),
            'evaluation_find' => $this->findEvaluation($payload),
            'evaluation_latest' => $this->latestEvaluation($payload),
            'evaluation_best' => $this->bestEvaluation($payload),
            default => throw new InvalidArgumentException('Unsupported Backtest V2 API action: ' . $action),
        };
    }

    private function listBacktests(array $payload): array
    {
        return [
            'ok' => true,
            'backtest_results' => $this->backtests->listResults(
                $this->limit($payload),
                $this->filters($payload, ['strategy_version_id', 'symbol', 'timeframe', 'higher_timeframe', 'created_by'])
            ),
        ];
    }

    private function findBacktest(array $payload): array
    {
        $id = $this->id($payload, ['backtest_result_id', 'backtestResultId', 'id']);
        $result = $this->backtests->findResult($id);
        if ($result === null) {
            throw new InvalidArgumentException('Backtest V2 result not found: ' . $id);
        }

        return [
            'ok' => true,
            'backtest_result' => $result,
        ];
    }

    private function listEvaluations(array $payload): array
    {
        return [
            'ok' => true,
            'evaluations' => $this->evaluations->listEvaluations(
                $this->limit($payload),
                $this->evaluationFilters($payload)
            ),
        ];
    }

    private function findEvaluation(array $payload): array
    {
        $id = $this->id($payload, ['evaluation_id', 'evaluationId', 'id']);
        $evaluation = $this->evaluations->findEvaluation($id);
        if ($evaluation === null) {
            throw new InvalidArgumentException('Evaluation result not found: ' . $id);
        }

        return [
            'ok' => true,
            'evaluation' => $evaluation,
        ];
    }

    private function latestEvaluation(array $payload): array
    {
        $filters = $this->evaluationFilters($payload);
        $evaluation = $this->usesExtendedEvaluationFilters($filters)
            ? ($this->evaluations->listEvaluations(1, $filters)[0] ?? null)
            : $this->evaluations->getLatestEvaluation($filters['strategy_version_id'] ?? null, $filters['experiment_id'] ?? null);

        return [
            'ok' => true,
            'evaluation' => $evaluation,
        ];
    }

    private function bestEvaluation(array $payload): array
    {
        $filters = $this->evaluationFilters($payload);
        $evaluation = $this->usesExtendedEvaluationFilters($filters)
            ? $this->bestFromList($this->evaluations->listEvaluations($this->limit($payload, 500), $filters))
            : $this->evaluations->getBestEvaluation($filters['strategy_version_id'] ?? null, $filters['experiment_id'] ?? null);

        return [
            'ok' => true,
            'evaluation' => $evaluation,
        ];
    }

    private function bestFromList(array $evaluations): ?array
    {
        if ($evaluations === []) {
            return null;
        }

        usort($evaluations, function (array $a, array $b): int {
            $score = ((float)($b['score'] ?? 0)) <=> ((float)($a['score'] ?? 0));
            if ($score !== 0) {
                return $score;
            }

            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });

        return $evaluations[0];
    }

    private function evaluationFilters(array $payload): array
    {
        $filters = $this->filters($payload, [
            'strategy_version_id',
            'backtest_result_id',
            'experiment_id',
            'status',
            'created_by',
        ]);

        if (array_key_exists('passed', $payload)) {
            $filters['passed'] = $this->boolValue($payload['passed']);
        }

        return $filters;
    }

    private function filters(array $payload, array $fields): array
    {
        $filters = [];
        foreach ($fields as $field) {
            $value = $payload[$field] ?? $payload[$this->camel($field)] ?? null;
            if ($value !== null && trim((string)$value) !== '') {
                $filters[$field] = (string)$value;
            }
        }

        return $filters;
    }

    private function usesExtendedEvaluationFilters(array $filters): bool
    {
        return array_diff(array_keys($filters), ['strategy_version_id', 'experiment_id']) !== [];
    }

    private function id(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if ($value !== null && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        throw new InvalidArgumentException('Record id is required.');
    }

    private function limit(array $payload, int $default = 100): int
    {
        return max(1, min(500, (int)($payload['limit'] ?? $default)));
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'passed'], true);
    }

    private function camel(string $field): string
    {
        return preg_replace_callback('/_([a-z])/', fn(array $matches): string => strtoupper($matches[1]), $field) ?? $field;
    }
}
