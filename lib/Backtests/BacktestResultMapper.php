<?php

declare(strict_types=1);

final class BacktestResultMapper
{
    public function map(array $engineOutput, array $strategyVersion = [], array $input = []): array
    {
        $summary = is_array($engineOutput['summary'] ?? null) ? $engineOutput['summary'] : $engineOutput;
        $trades = is_array($engineOutput['trades'] ?? null)
            ? array_values(array_filter($engineOutput['trades'], 'is_array'))
            : [];

        $id = $this->stringValue($summary, ['backtestRunId', 'id']);
        if ($id === '') {
            $id = $this->fallbackId($summary, $strategyVersion, $input);
        }

        $settings = is_array($summary['settings'] ?? null) ? $summary['settings'] : [];
        $rules = is_array($strategyVersion['rulesJson'] ?? null) ? $strategyVersion['rulesJson'] : [];

        return [
            'id' => $id,
            'strategy_version_id' => $this->firstString([
                $summary['strategyVersionId'] ?? null,
                $summary['strategy_version_id'] ?? null,
                $strategyVersion['id'] ?? null,
            ]),
            'symbol' => $this->firstString([
                $summary['symbol'] ?? null,
                $input['symbol'] ?? null,
                $rules['symbols'][0] ?? null,
            ]),
            'timeframe' => $this->firstString([
                $summary['timeframe'] ?? null,
                $input['timeframe'] ?? null,
                $rules['entryTimeframe'] ?? null,
                'M15',
            ]),
            'higher_timeframe' => $this->firstString([
                $summary['contextTimeframe'] ?? null,
                $summary['higherTimeframe'] ?? null,
                $input['contextTimeframe'] ?? null,
                $rules['contextTimeframe'] ?? null,
                'H4',
            ]),
            'start_date' => $this->nullableFirstString([
                $summary['startDate'] ?? null,
                $summary['start_date'] ?? null,
            ]),
            'end_date' => $this->nullableFirstString([
                $summary['endDate'] ?? null,
                $summary['end_date'] ?? null,
            ]),
            'initial_balance' => $this->floatValue($summary, ['initialBalance', 'initial_balance']),
            'final_balance' => $this->floatValue($summary, ['finalBalance', 'final_balance']),
            'net_profit' => $this->floatValue($summary, ['netProfit', 'net_profit']),
            'win_rate' => $this->floatValue($summary, ['winRate', 'win_rate']),
            'average_rr' => $this->floatValue($summary, ['averageRR', 'average_rr']),
            'profit_factor' => $this->floatValue($summary, ['profitFactor', 'profit_factor']),
            'max_drawdown' => $this->floatValue($summary, ['maxDrawdownPercent', 'max_drawdown_percent', 'max_drawdown', 'maxDrawdown']),
            'total_trades' => $this->intValue($summary, ['totalTrades', 'total_trades']),
            'winning_trades' => $this->intValue($summary, ['winTrades', 'winningTrades', 'winning_trades']),
            'losing_trades' => $this->intValue($summary, ['lossTrades', 'losingTrades', 'losing_trades']),
            'settings_json' => [
                'input' => $this->jsonSafe($input),
                'engine_settings' => $this->jsonSafe($settings),
                'data_split' => $summary['dataSplit'] ?? $summary['rangeType'] ?? $input['dataSplit'] ?? null,
                'rules_preset' => $summary['rulesPreset'] ?? $input['rulesPreset'] ?? $input['preset'] ?? null,
                'debug_level' => $summary['debugLevel'] ?? $input['debugLevel'] ?? null,
            ],
            'metrics_json' => $this->jsonSafe($summary),
            'trades_json' => $this->jsonSafe($trades),
            'report_path' => $this->nullableFirstString([
                $summary['reportPath'] ?? null,
                $summary['report_path'] ?? null,
            ]),
            'created_at' => $this->firstString([
                $summary['createdAt'] ?? null,
                $summary['created_at'] ?? null,
                gmdate('c'),
            ]),
            'created_by' => 'backtest-v2-adapter',
            'notes' => (string)($summary['summaryNotes'] ?? 'V2 copy of legacy BacktestEngine output.'),
        ];
    }

    private function stringValue(array $source, array $keys): string
    {
        return $this->firstString(array_map(fn(string $key): mixed => $source[$key] ?? null, $keys));
    }

    private function firstString(array $values): string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function nullableFirstString(array $values): ?string
    {
        $value = $this->firstString($values);
        return $value === '' ? null : $value;
    }

    private function floatValue(array $source, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_numeric($source[$key])) {
                return (float)$source[$key];
            }
        }

        return null;
    }

    private function intValue(array $source, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_numeric($source[$key])) {
                return (int)$source[$key];
            }
        }

        return null;
    }

    private function jsonSafe(mixed $payload): mixed
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return [];
        }

        $decoded = json_decode($json, true);
        return $decoded ?? [];
    }

    private function fallbackId(array $summary, array $strategyVersion, array $input): string
    {
        $seed = json_encode([
            'summary' => $summary,
            'strategy_version_id' => $strategyVersion['id'] ?? '',
            'input' => $input,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'bt_' . substr(sha1((string)$seed), 0, 16);
    }
}
