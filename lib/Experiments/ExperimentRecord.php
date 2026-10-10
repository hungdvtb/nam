<?php

declare(strict_types=1);

final class ExperimentRecord
{
    public static function normalize(array $experiment): array
    {
        $now = gmdate('c');

        $record = [
            'id' => trim((string)($experiment['id'] ?? '')),
            'name' => (string)($experiment['name'] ?? 'Research experiment'),
            'description' => (string)($experiment['description'] ?? ''),
            'goal_json' => self::arrayPayload($experiment['goal_json'] ?? $experiment['goalJson'] ?? $experiment['goal'] ?? []),
            'status' => ExperimentStatus::normalize((string)($experiment['status'] ?? ExperimentStatus::DRAFT)),
            'created_by' => (string)($experiment['created_by'] ?? $experiment['createdBy'] ?? 'user'),
            'created_at' => (string)($experiment['created_at'] ?? $experiment['createdAt'] ?? $now),
            'updated_at' => (string)($experiment['updated_at'] ?? $experiment['updatedAt'] ?? $experiment['created_at'] ?? $experiment['createdAt'] ?? $now),
            'started_at' => self::nullableString($experiment['started_at'] ?? $experiment['startedAt'] ?? null),
            'completed_at' => self::nullableString($experiment['completed_at'] ?? $experiment['completedAt'] ?? null),
            'best_strategy_version_id' => self::nullableString($experiment['best_strategy_version_id'] ?? $experiment['bestStrategyVersionId'] ?? null),
            'best_backtest_result_id' => self::nullableString($experiment['best_backtest_result_id'] ?? $experiment['bestBacktestResultId'] ?? null),
            'summary_json' => self::arrayPayload($experiment['summary_json'] ?? $experiment['summaryJson'] ?? $experiment['summary'] ?? []),
            'notes' => (string)($experiment['notes'] ?? ''),
        ];

        return self::withAliases($record);
    }

    public static function validate(array $experiment): void
    {
        foreach (['id', 'name', 'status', 'created_at', 'updated_at'] as $field) {
            if (trim((string)($experiment[$field] ?? '')) === '') {
                throw new InvalidArgumentException('Experiment is missing required field: ' . $field);
            }
        }

        ExperimentStatus::normalize((string)$experiment['status']);
    }

    private static function withAliases(array $record): array
    {
        $record['goalJson'] = $record['goal_json'];
        $record['createdBy'] = $record['created_by'];
        $record['createdAt'] = $record['created_at'];
        $record['updatedAt'] = $record['updated_at'];
        $record['startedAt'] = $record['started_at'];
        $record['completedAt'] = $record['completed_at'];
        $record['bestStrategyVersionId'] = $record['best_strategy_version_id'];
        $record['bestBacktestResultId'] = $record['best_backtest_result_id'];
        $record['summaryJson'] = $record['summary_json'];

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
}
