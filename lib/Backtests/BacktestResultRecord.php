<?php

declare(strict_types=1);

final class BacktestResultRecord
{
    public static function normalize(array $result): array
    {
        $now = gmdate('c');
        $metrics = self::arrayPayload(
            $result['metrics_json']
            ?? $result['metricsJson']
            ?? $result['metrics']
            ?? $result['summary']
            ?? []
        );
        $settings = self::arrayPayload($result['settings_json'] ?? $result['settingsJson'] ?? $result['settings'] ?? []);
        $trades = self::arrayPayload($result['trades_json'] ?? $result['tradesJson'] ?? $result['trades'] ?? []);
        $id = trim((string)($result['id'] ?? $result['backtestRunId'] ?? ''));

        $record = [
            'id' => $id,
            'strategy_version_id' => self::nullableString($result['strategy_version_id'] ?? $result['strategyVersionId'] ?? null),
            'symbol' => (string)($result['symbol'] ?? $metrics['symbol'] ?? ''),
            'timeframe' => (string)($result['timeframe'] ?? $metrics['timeframe'] ?? ''),
            'higher_timeframe' => (string)($result['higher_timeframe'] ?? $result['higherTimeframe'] ?? $result['contextTimeframe'] ?? $metrics['contextTimeframe'] ?? ''),
            'start_date' => self::nullableString($result['start_date'] ?? $result['startDate'] ?? $metrics['startDate'] ?? $metrics['start'] ?? null),
            'end_date' => self::nullableString($result['end_date'] ?? $result['endDate'] ?? $metrics['endDate'] ?? $metrics['end'] ?? null),
            'initial_balance' => self::nullableFloat($result['initial_balance'] ?? $result['initialBalance'] ?? $metrics['initialBalance'] ?? null),
            'final_balance' => self::nullableFloat($result['final_balance'] ?? $result['finalBalance'] ?? $metrics['finalBalance'] ?? null),
            'net_profit' => self::nullableFloat($result['net_profit'] ?? $result['netProfit'] ?? $metrics['netProfit'] ?? $metrics['netProfitPercent'] ?? null),
            'win_rate' => self::nullableFloat($result['win_rate'] ?? $result['winRate'] ?? $metrics['winRate'] ?? null),
            'average_rr' => self::nullableFloat($result['average_rr'] ?? $result['averageRR'] ?? $metrics['averageRR'] ?? null),
            'profit_factor' => self::nullableFloat($result['profit_factor'] ?? $result['profitFactor'] ?? $metrics['profitFactor'] ?? null),
            'max_drawdown' => self::nullableFloat($result['max_drawdown'] ?? $result['maxDrawdown'] ?? $result['maxDrawdownPercent'] ?? $metrics['maxDrawdownPercent'] ?? $metrics['maxDrawdown'] ?? null),
            'total_trades' => self::nullableInt($result['total_trades'] ?? $result['totalTrades'] ?? $metrics['totalTrades'] ?? null),
            'winning_trades' => self::nullableInt($result['winning_trades'] ?? $result['winningTrades'] ?? $result['winTrades'] ?? $metrics['winTrades'] ?? $metrics['winningTrades'] ?? null),
            'losing_trades' => self::nullableInt($result['losing_trades'] ?? $result['losingTrades'] ?? $result['lossTrades'] ?? $metrics['lossTrades'] ?? $metrics['losingTrades'] ?? null),
            'settings_json' => $settings,
            'metrics_json' => $metrics,
            'trades_json' => $trades,
            'report_path' => self::nullableString($result['report_path'] ?? $result['reportPath'] ?? null),
            'created_at' => (string)($result['created_at'] ?? $result['createdAt'] ?? $now),
            'created_by' => (string)($result['created_by'] ?? $result['createdBy'] ?? 'system'),
            'notes' => (string)($result['notes'] ?? ''),
        ];

        return self::withAliases($record);
    }

    public static function validate(array $result): void
    {
        foreach (['id', 'created_at'] as $field) {
            if (trim((string)($result[$field] ?? '')) === '') {
                throw new InvalidArgumentException('Backtest result is missing required field: ' . $field);
            }
        }
    }

    private static function withAliases(array $record): array
    {
        $record['strategyVersionId'] = $record['strategy_version_id'];
        $record['higherTimeframe'] = $record['higher_timeframe'];
        $record['startDate'] = $record['start_date'];
        $record['endDate'] = $record['end_date'];
        $record['initialBalance'] = $record['initial_balance'];
        $record['finalBalance'] = $record['final_balance'];
        $record['netProfit'] = $record['net_profit'];
        $record['winRate'] = $record['win_rate'];
        $record['averageRR'] = $record['average_rr'];
        $record['profitFactor'] = $record['profit_factor'];
        $record['maxDrawdown'] = $record['max_drawdown'];
        $record['totalTrades'] = $record['total_trades'];
        $record['winningTrades'] = $record['winning_trades'];
        $record['losingTrades'] = $record['losing_trades'];
        $record['settingsJson'] = $record['settings_json'];
        $record['metricsJson'] = $record['metrics_json'];
        $record['tradesJson'] = $record['trades_json'];
        $record['reportPath'] = $record['report_path'];
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
}
