<?php

declare(strict_types=1);

final class AIJobRecord
{
    public static function normalize(array $job): array
    {
        $now = gmdate('c');
        $id = trim((string)($job['id'] ?? ''));
        $createdAt = (string)($job['created_at'] ?? $job['createdAt'] ?? $now);

        $record = [
            'id' => $id,
            'job_type' => AIJobType::normalize((string)($job['job_type'] ?? $job['jobType'] ?? AIJobType::GENERATE_STRATEGY_IDEA)),
            'status' => AIJobStatus::normalize((string)($job['status'] ?? AIJobStatus::PENDING)),
            'input_json' => self::arrayPayload($job['input_json'] ?? $job['inputJson'] ?? $job['input'] ?? []),
            'output_json' => self::arrayPayload($job['output_json'] ?? $job['outputJson'] ?? $job['output'] ?? []),
            'error_message' => self::nullableString($job['error_message'] ?? $job['errorMessage'] ?? null),
            'strategy_version_id' => self::nullableString($job['strategy_version_id'] ?? $job['strategyVersionId'] ?? null),
            'experiment_id' => self::nullableString($job['experiment_id'] ?? $job['experimentId'] ?? null),
            'created_at' => $createdAt,
            'started_at' => self::nullableString($job['started_at'] ?? $job['startedAt'] ?? null),
            'completed_at' => self::nullableString($job['completed_at'] ?? $job['completedAt'] ?? null),
            'created_by' => (string)($job['created_by'] ?? $job['createdBy'] ?? 'user'),
            'notes' => (string)($job['notes'] ?? ''),
        ];

        return self::withAliases($record);
    }

    public static function validate(array $job): void
    {
        foreach (['id', 'job_type', 'status', 'created_at', 'created_by'] as $field) {
            if (trim((string)($job[$field] ?? '')) === '') {
                throw new InvalidArgumentException('AI job is missing required field: ' . $field);
            }
        }

        AIJobType::normalize((string)$job['job_type']);
        AIJobStatus::normalize((string)$job['status']);
    }

    private static function withAliases(array $record): array
    {
        $record['jobType'] = $record['job_type'];
        $record['inputJson'] = $record['input_json'];
        $record['outputJson'] = $record['output_json'];
        $record['errorMessage'] = $record['error_message'];
        $record['strategyVersionId'] = $record['strategy_version_id'];
        $record['experimentId'] = $record['experiment_id'];
        $record['createdAt'] = $record['created_at'];
        $record['startedAt'] = $record['started_at'];
        $record['completedAt'] = $record['completed_at'];
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
}
