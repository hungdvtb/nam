<?php

declare(strict_types=1);

final class TradeBrainLiveSupervisor
{
    public function reviewProposal(array $tradeProposal): array
    {
        $validation = $this->validateProposal($tradeProposal);
        if (!$validation['ok']) {
            return $this->reject('Invalid TradeProposal: ' . implode('; ', $validation['errors']), 0, ['No MT5 order execution is allowed in this module.']);
        }

        return [
            'decision' => 'WAIT',
            'confidence' => 0,
            'reason' => 'Live Trade Supervisor is a simulation stub only. No live trading or MT5 order execution is enabled.',
            'warnings' => [
                'Fail-closed behavior: if AI is unavailable, times out, returns bad JSON, or confidence is low, do not enter the trade.',
                'Risk Manager and MT5 Order Executor are future interfaces, not active execution paths.',
            ],
            'suggestedEntry' => null,
            'suggestedStopLoss' => null,
            'suggestedTakeProfit' => null,
        ];
    }

    public function futureFlow(): array
    {
        return [
            'TradeProposal received',
            'TradeBrain reads proposal, memory, and similar backtest stats',
            'TradeBrain returns APPROVE / REJECT / WAIT / MODIFY JSON',
            'Risk Manager performs final deterministic checks',
            'Future MT5 Order Executor may place an order only after separate human-approved implementation',
        ];
    }

    private function validateProposal(array $proposal): array
    {
        $required = [
            'symbol',
            'direction',
            'entryType',
            'entryPrice',
            'stopLoss',
            'takeProfit',
            'rr',
            'spread',
            'strategyVersionId',
            'setupSummary',
            'contextSummary',
            'similarBacktestStats',
            'currentRiskState',
        ];
        $errors = [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $proposal)) {
                $errors[] = 'missing ' . $field;
            }
        }

        $direction = strtoupper((string)($proposal['direction'] ?? ''));
        if ($direction !== '' && !in_array($direction, ['BUY', 'SELL'], true)) {
            $errors[] = 'direction must be BUY or SELL';
        }

        return ['ok' => count($errors) === 0, 'errors' => $errors];
    }

    private function reject(string $reason, float $confidence, array $warnings): array
    {
        return [
            'decision' => 'REJECT',
            'confidence' => $confidence,
            'reason' => $reason,
            'warnings' => $warnings,
            'suggestedEntry' => null,
            'suggestedStopLoss' => null,
            'suggestedTakeProfit' => null,
        ];
    }
}
