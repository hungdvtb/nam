<?php

declare(strict_types=1);

final class EvaluationResultRecord
{
    public static function normalize(array $evaluation): array
    {
        $now = gmdate('c');
        $kpi = self::arrayPayload($evaluation['kpi_json'] ?? $evaluation['kpiJson'] ?? $evaluation['kpi'] ?? []);
        $metrics = self::arrayPayload($evaluation['metrics_json'] ?? $evaluation['metricsJson'] ?? $evaluation['metrics'] ?? []);
        $reasons = self::arrayPayload($evaluation['reasons_json'] ?? $evaluation['reasonsJson'] ?? $evaluation['reasons'] ?? []);
        $recommendations = self::arrayPayload($evaluation['recommendations_json'] ?? $evaluation['recommendationsJson'] ?? $evaluation['recommendations'] ?? []);

        $record = [
            'id' => trim((string)($evaluation['id'] ?? '')),
            'strategy_version_id' => self::nullableString($evaluation['strategy_version_id'] ?? $evaluation['strategyVersionId'] ?? null),
            'backtest_result_id' => self::nullableString($evaluation['backtest_result_id'] ?? $evaluation['backtestResultId'] ?? null),
            'experiment_id' => self::nullableString($evaluation['experiment_id'] ?? $evaluation['experimentId'] ?? null),
            'status' => EvaluationStatus::normalize((string)($evaluation['status'] ?? EvaluationStatus::FAILED)),
            'score' => self::floatPayload($evaluation['score'] ?? 0),
            'passed' => self::boolPayload($evaluation['passed'] ?? false),
            'kpi_json' => $kpi,
            'metrics_json' => $metrics,
            'reasons_json' => $reasons,
            'recommendations_json' => $recommendations,
            'created_at' => (string)($evaluation['created_at'] ?? $evaluation['createdAt'] ?? $now),
            'created_by' => (string)($evaluation['created_by'] ?? $evaluation['createdBy'] ?? 'system'),
            'notes' => (string)($evaluation['notes'] ?? ''),
        ];

        return self::withAliases($record);
    }

    public static function validate(array $evaluation): void
    {
        foreach (['id', 'status', 'created_at'] as $field) {
            if (trim((string)($evaluation[$field] ?? '')) === '') {
                throw new InvalidArgumentException('Evaluation result is missing required field: ' . $field);
            }
        }

        EvaluationStatus::normalize((string)$evaluation['status']);
    }

    private static function withAliases(array $record): array
    {
        $record['strategyVersionId'] = $record['strategy_version_id'];
        $record['backtestResultId'] = $record['backtest_result_id'];
        $record['experimentId'] = $record['experiment_id'];
        $record['kpiJson'] = $record['kpi_json'];
        $record['metricsJson'] = $record['metrics_json'];
        $record['reasonsJson'] = $record['reasons_json'];
        $record['recommendationsJson'] = $record['recommendations_json'];
        $record['createdAt'] = $record['created_at'];
        $record['createdBy'] = $record['created_by'];

        return $record;
    }

    private static function arrayPayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private static function floatPayload(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return is_numeric($value) ? (float)$value : 0.0;
    }

    private static function boolPayload(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes'], true);
        }

        return false;
    }
}
