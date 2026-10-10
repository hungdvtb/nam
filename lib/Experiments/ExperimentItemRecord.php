<?php

declare(strict_types=1);

final class ExperimentItemRecord
{
    public static function normalize(array $item): array
    {
        $now = gmdate('c');

        $record = [
            'id' => trim((string)($item['id'] ?? '')),
            'experiment_id' => trim((string)($item['experiment_id'] ?? $item['experimentId'] ?? '')),
            'strategy_version_id' => self::nullableString($item['strategy_version_id'] ?? $item['strategyVersionId'] ?? null),
            'backtest_result_id' => self::nullableString($item['backtest_result_id'] ?? $item['backtestResultId'] ?? null),
            'item_type' => (string)($item['item_type'] ?? $item['itemType'] ?? self::defaultItemType($item)),
            'status' => ExperimentItemStatus::normalize((string)($item['status'] ?? ExperimentItemStatus::PENDING)),
            'score' => self::nullableFloat($item['score'] ?? null),
            'metrics_json' => self::arrayPayload($item['metrics_json'] ?? $item['metricsJson'] ?? $item['metrics'] ?? []),
            'created_at' => (string)($item['created_at'] ?? $item['createdAt'] ?? $now),
            'notes' => (string)($item['notes'] ?? ''),
        ];

        return self::withAliases($record);
    }

    public static function validate(array $item): void
    {
        foreach (['id', 'experiment_id', 'item_type', 'status', 'created_at'] as $field) {
            if (trim((string)($item[$field] ?? '')) === '') {
                throw new InvalidArgumentException('Experiment item is missing required field: ' . $field);
            }
        }

        ExperimentItemStatus::normalize((string)$item['status']);
    }

    private static function withAliases(array $record): array
    {
        $record['experimentId'] = $record['experiment_id'];
        $record['strategyVersionId'] = $record['strategy_version_id'];
        $record['backtestResultId'] = $record['backtest_result_id'];
        $record['itemType'] = $record['item_type'];
        $record['metricsJson'] = $record['metrics_json'];
        $record['createdAt'] = $record['created_at'];

        return $record;
    }

    private static function defaultItemType(array $item): string
    {
        if (($item['backtest_result_id'] ?? $item['backtestResultId'] ?? null) !== null) {
            return 'backtest_result';
        }

        if (($item['strategy_version_id'] ?? $item['strategyVersionId'] ?? null) !== null) {
            return 'strategy_version';
        }

        return 'research_item';
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

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float)$value : null;
    }
}
