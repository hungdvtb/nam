<?php

final class RiskCalculator
{
    public function preview(array $account, float $equity, ?float $dayStartBalance = null, ?float $dayStartEquity = null): array
    {
        $initial = (float)($account['initial_balance'] ?? 0);
        $riskPct = max(0.0, (float)($account['risk_per_trade_pct'] ?? 0));
        $dailyStopPct = max(0.0, (float)($account['daily_stop_pct'] ?? 0));
        $maxLossPct = max(0.0, (float)($account['max_loss_pct'] ?? 0));
        $capPct = $account['risk_base_cap_pct'] ?? null;
        $riskModel = (string)($account['risk_model'] ?? 'equity_percent');
        $survivalReservePct = max(0.0, min(90.0, (float)($account['survival_reserve_pct'] ?? 20)));
        $dailyReservePct = max(0.0, min(90.0, (float)($account['daily_reserve_pct'] ?? 20)));

        if ($riskModel === 'prop_buffer_capped') {
            $absoluteFloor = $initial * (1 - $maxLossPct / 100);
            $currentBuffer = max(0.0, $equity - $absoluteFloor);
            $cap = $capPct === null ? $initial * ($maxLossPct / 100) : $initial * ((float)$capPct / 100);
            $maxLossAmount = max(0.0, $initial * ($maxLossPct / 100));
            $survivalReserve = $maxLossAmount * ($survivalReservePct / 100);
            $tradableBuffer = max(0.0, $currentBuffer - $survivalReserve);
            $riskBase = min($tradableBuffer, max(0.0, $cap));
            $bufferRatio = $cap > 0 ? max(0.0, min(1.0, $currentBuffer / $cap)) : 0.0;
        } else {
            $absoluteFloor = null;
            $currentBuffer = max(0.0, $equity);
            $cap = null;
            $survivalReserve = 0.0;
            $tradableBuffer = $currentBuffer;
            $riskBase = $equity;
            $bufferRatio = $initial > 0 ? max(0.0, min(1.0, $equity / $initial)) : 1.0;
        }

        $riskMultiplier = $this->survivalMultiplier($bufferRatio, $tradableBuffer);
        $rawRiskPerTrade = max(0.0, $riskBase * $riskPct / 100);
        $riskPerTrade = $rawRiskPerTrade * $riskMultiplier;
        $effectiveRiskPct = $riskPct * $riskMultiplier;

        $dayRef = max($dayStartBalance ?? $equity, $dayStartEquity ?? $equity);
        $botDailyFloor = $dayRef * (1 - $dailyStopPct / 100);
        $dailyBuffer = max(0.0, $equity - $botDailyFloor);
        $dailyReserve = $dailyBuffer * ($dailyReservePct / 100);
        $dailyTradableBuffer = max(0.0, $dailyBuffer - $dailyReserve);
        $riskAllowedNow = max(0.0, min($riskPerTrade, $dailyTradableBuffer, $tradableBuffer));
        $riskStatus = $this->riskStatus($riskAllowedNow, $riskMultiplier, $tradableBuffer, $dailyTradableBuffer);

        return [
            'initial_balance' => $initial,
            'equity' => $equity,
            'absolute_floor' => $absoluteFloor,
            'current_buffer' => $currentBuffer,
            'risk_base_cap' => $cap,
            'risk_base' => $riskBase,
            'survival_reserve' => $survivalReserve,
            'survival_reserve_pct' => $survivalReservePct,
            'tradable_buffer' => $tradableBuffer,
            'buffer_ratio' => $bufferRatio,
            'raw_risk_per_trade' => $rawRiskPerTrade,
            'risk_multiplier' => $riskMultiplier,
            'effective_risk_pct' => $effectiveRiskPct,
            'risk_per_trade' => $riskPerTrade,
            'day_start_reference' => $dayRef,
            'bot_daily_floor' => $botDailyFloor,
            'daily_buffer' => $dailyBuffer,
            'daily_reserve' => $dailyReserve,
            'daily_reserve_pct' => $dailyReservePct,
            'daily_tradable_buffer' => $dailyTradableBuffer,
            'risk_allowed_now' => $riskAllowedNow,
            'risk_status' => $riskStatus,
        ];
    }

    private function survivalMultiplier(float $bufferRatio, float $tradableBuffer): float
    {
        if ($tradableBuffer <= 0 || $bufferRatio <= 0.2) return 0.0;
        if ($bufferRatio <= 0.35) return 0.25;
        if ($bufferRatio <= 0.55) return 0.5;
        if ($bufferRatio <= 0.75) return 0.75;
        return 1.0;
    }

    private function riskStatus(float $riskAllowedNow, float $riskMultiplier, float $tradableBuffer, float $dailyTradableBuffer): string
    {
        if ($riskAllowedNow <= 0 || $riskMultiplier <= 0 || $tradableBuffer <= 0 || $dailyTradableBuffer <= 0) {
            return 'HALTED';
        }
        if ($riskMultiplier < 1) {
            return 'REDUCED';
        }
        return 'NORMAL';
    }
}