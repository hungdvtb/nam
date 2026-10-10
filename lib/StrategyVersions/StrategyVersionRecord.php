<?php

declare(strict_types=1);

final class StrategyVersionRecord
{
    public static function normalize(array $version): array
    {
        $now = gmdate('c');
        $id = trim((string)($version['id'] ?? ''));
        $versionCode = trim((string)($version['version_code'] ?? $version['versionCode'] ?? $version['code'] ?? $id));
        $createdAt = (string)($version['created_at'] ?? $version['createdAt'] ?? $now);
        $updatedAt = (string)($version['updated_at'] ?? $version['updatedAt'] ?? $createdAt);
        $status = StrategyVersionStatus::normalize((string)($version['status'] ?? StrategyVersionStatus::DRAFT));
        $performance = self::arrayPayload($version['performance_json'] ?? $version['performanceJson'] ?? $version['backtestSummary'] ?? []);
        $rules = self::arrayPayload($version['rules_json'] ?? $version['rulesJson'] ?? []);
        $settings = self::arrayPayload($version['settings_json'] ?? $version['settingsJson'] ?? []);
        $approved = self::boolPayload($version['approved'] ?? ($status === StrategyVersionStatus::PASSED || $status === StrategyVersionStatus::PRODUCTION));
        $approvedAt = $version['approved_at'] ?? $version['approvedAt'] ?? null;

        $record = [
            'id' => $id,
            'version_code' => $versionCode,
            'name' => (string)($version['name'] ?? 'Strategy version'),
            'description' => (string)($version['description'] ?? $version['notes'] ?? ''),
            'parent_version_id' => self::nullableString($version['parent_version_id'] ?? $version['parentVersionId'] ?? null),
            'status' => $status,
            'created_by' => (string)($version['created_by'] ?? $version['createdBy'] ?? 'user'),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'performance_json' => $performance,
            'rules_json' => $rules,
            'settings_json' => $settings,
            'win_rate' => self::nullableFloat($version['win_rate'] ?? $version['winRate'] ?? $performance['winRate'] ?? null),
            'average_rr' => self::nullableFloat($version['average_rr'] ?? $version['averageRR'] ?? $performance['averageRR'] ?? null),
            'profit_factor' => self::nullableFloat($version['profit_factor'] ?? $version['profitFactor'] ?? $performance['profitFactor'] ?? null),
            'max_drawdown' => self::nullableFloat($version['max_drawdown'] ?? $version['maxDrawdown'] ?? $version['maxDrawdownPercent'] ?? $performance['maxDrawdownPercent'] ?? null),
            'total_trades' => self::nullableInt($version['total_trades'] ?? $version['totalTrades'] ?? $performance['totalTrades'] ?? null),
            'backtest_start' => self::nullableString($version['backtest_start'] ?? $version['backtestStart'] ?? $performance['start'] ?? null),
            'backtest_end' => self::nullableString($version['backtest_end'] ?? $version['backtestEnd'] ?? $performance['end'] ?? null),
            'approved' => $approved,
            'approved_at' => self::nullableString($approvedAt),
            'history' => is_array($version['history'] ?? null) ? array_values($version['history']) : [],
        ];

        return self::withLegacyAliases($record, $version);
    }

    public static function validate(array $version): void
    {
        foreach (['id', 'version_code', 'status', 'created_at', 'updated_at'] as $field) {
            if (trim((string)($version[$field] ?? '')) === '') {
                throw new InvalidArgumentException('Strategy version is missing required field: ' . $field);
            }
        }

        StrategyVersionStatus::normalize((string)$version['status']);
    }

    public static function historyEvent(string $versionId, string $eventType, array $payload = []): array
    {
        return [
            'id' => 'evt_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)),
            'version_id' => $versionId,
            'event_type' => $eventType,
            'payload' => $payload,
            'created_at' => gmdate('c'),
        ];
    }

    private static function withLegacyAliases(array $record, array $original): array
    {
        $record['versionCode'] = $record['version_code'];
        $record['parentVersionId'] = $record['parent_version_id'];
        $record['createdBy'] = $record['created_by'];
        $record['createdAt'] = $record['created_at'];
        $record['updatedAt'] = $record['updated_at'];
        $record['rulesJson'] = $record['rules_json'];
        $record['settingsJson'] = $record['settings_json'];
        $record['performanceJson'] = $record['performance_json'];
        $record['backtestSummary'] = is_array($original['backtestSummary'] ?? null)
            ? $original['backtestSummary']
            : ($record['performance_json'] !== [] ? $record['performance_json'] : null);
        $record['validationSummary'] = is_array($original['validationSummary'] ?? null) ? $original['validationSummary'] : null;
        $record['finalTestSummary'] = is_array($original['finalTestSummary'] ?? null) ? $original['finalTestSummary'] : null;
        $record['notes'] = $record['description'];
        $record['approvalNotes'] = (string)($original['approvalNotes'] ?? '');

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

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float)$value : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
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
