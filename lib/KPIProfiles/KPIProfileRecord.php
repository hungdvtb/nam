<?php

declare(strict_types=1);

final class KPIProfileRecord
{
    public static function normalize(array $profile): array
    {
        $now = gmdate('c');
        $record = [
            'id' => trim((string)($profile['id'] ?? '')),
            'name' => trim((string)($profile['name'] ?? '')),
            'description' => (string)($profile['description'] ?? ''),
            'is_default' => self::boolPayload($profile['is_default'] ?? $profile['isDefault'] ?? false),
            'win_rate_min' => self::floatPayload($profile['win_rate_min'] ?? $profile['winRateMin'] ?? 70),
            'average_rr_min' => self::floatPayload($profile['average_rr_min'] ?? $profile['averageRrMin'] ?? $profile['averageRRMin'] ?? 2),
            'profit_factor_min' => self::floatPayload($profile['profit_factor_min'] ?? $profile['profitFactorMin'] ?? 2),
            'max_drawdown_max' => self::floatPayload($profile['max_drawdown_max'] ?? $profile['maxDrawdownMax'] ?? 10),
            'total_trades_min' => self::intPayload($profile['total_trades_min'] ?? $profile['totalTradesMin'] ?? 300),
            'created_at' => (string)($profile['created_at'] ?? $profile['createdAt'] ?? $now),
            'updated_at' => (string)($profile['updated_at'] ?? $profile['updatedAt'] ?? $now),
            'notes' => (string)($profile['notes'] ?? ''),
        ];

        return self::withAliases($record);
    }

    public static function validate(array $profile): void
    {
        foreach (['id', 'name', 'created_at', 'updated_at'] as $field) {
            if (trim((string)($profile[$field] ?? '')) === '') {
                throw new InvalidArgumentException('KPI profile is missing required field: ' . $field);
            }
        }

        if ((float)$profile['win_rate_min'] < 0 || (float)$profile['win_rate_min'] > 100) {
            throw new InvalidArgumentException('KPI profile win_rate_min must be between 0 and 100.');
        }

        foreach (['average_rr_min', 'profit_factor_min', 'max_drawdown_max'] as $field) {
            if ((float)$profile[$field] < 0) {
                throw new InvalidArgumentException('KPI profile field must be non-negative: ' . $field);
            }
        }

        if ((int)$profile['total_trades_min'] < 0) {
            throw new InvalidArgumentException('KPI profile total_trades_min must be non-negative.');
        }
    }

    public static function toKpiTargets(array $profile): array
    {
        $profile = self::normalize($profile);

        return [
            'win_rate_min' => (float)$profile['win_rate_min'],
            'average_rr_min' => (float)$profile['average_rr_min'],
            'profit_factor_min' => (float)$profile['profit_factor_min'],
            'max_drawdown_max' => (float)$profile['max_drawdown_max'],
            'total_trades_min' => (int)$profile['total_trades_min'],
        ];
    }

    private static function withAliases(array $record): array
    {
        $record['isDefault'] = $record['is_default'];
        $record['winRateMin'] = $record['win_rate_min'];
        $record['averageRRMin'] = $record['average_rr_min'];
        $record['profitFactorMin'] = $record['profit_factor_min'];
        $record['maxDrawdownMax'] = $record['max_drawdown_max'];
        $record['totalTradesMin'] = $record['total_trades_min'];
        $record['createdAt'] = $record['created_at'];
        $record['updatedAt'] = $record['updated_at'];

        return $record;
    }

    private static function boolPayload(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'default'], true);
    }

    private static function floatPayload(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return is_numeric($value) ? (float)$value : 0.0;
    }

    private static function intPayload(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return is_numeric($value) ? (int)$value : 0;
    }
}
