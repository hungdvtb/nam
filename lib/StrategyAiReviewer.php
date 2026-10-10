<?php

declare(strict_types=1);

interface StrategyAiReviewer
{
    public function review(array $input): array;
}

final class MockStrategyAiReviewer implements StrategyAiReviewer
{
    public function review(array $input): array
    {
        $rules = is_array($input['rulesJson'] ?? null) ? $input['rulesJson'] : [];
        $train = is_array($input['trainMetrics'] ?? null) ? $input['trainMetrics'] : [];
        $validation = is_array($input['validationMetrics'] ?? null) ? $input['validationMetrics'] : [];
        $changes = [];
        $diagnosis = [];

        $profitFactor = (float)($train['profitFactor'] ?? 0);
        $drawdown = (float)($train['maxDrawdownPercent'] ?? 0);
        $lossStreak = (int)($train['longestLossStreak'] ?? 0);
        $rejected = (int)($train['rejectedSetupCount'] ?? 0);
        $winRate = (float)($train['winRate'] ?? 0);

        if ($profitFactor < 1.1) {
            $diagnosis[] = 'Profit factor on train is weak, so the strategy needs stricter entry quality.';
            $changes[] = [
                'field' => 'filters.requireH4Trend',
                'oldValue' => (bool)($rules['filters']['requireH4Trend'] ?? false),
                'newValue' => true,
                'reason' => 'Losing groups often improve first by forcing alignment with the closed H4 trend.',
            ];
        }

        if ($drawdown > 8 || $lossStreak >= 3) {
            $diagnosis[] = 'Drawdown or loss streak is high relative to the current return.';
            $old = (float)($rules['zoneStrengthMin'] ?? 65);
            $changes[] = [
                'field' => 'zoneStrengthMin',
                'oldValue' => $old,
                'newValue' => min(90, $old + 5),
                'reason' => 'Raise the H4 zone-strength proxy so weaker context trades are filtered out.',
            ];
            $changes[] = [
                'field' => 'filters.avoidSideway',
                'oldValue' => (bool)($rules['filters']['avoidSideway'] ?? false),
                'newValue' => true,
                'reason' => 'Sideway conditions tend to create clustered losses in an EMA/structure proxy.',
            ];
        }

        if ($winRate < 40 && ($rules['rrMin'] ?? 3) > 2) {
            $diagnosis[] = 'Win rate is low, so validation should check whether a slightly lower RR produces a smoother curve.';
            $old = (float)($rules['rrMin'] ?? 3);
            $changes[] = [
                'field' => 'rrMin',
                'oldValue' => $old,
                'newValue' => max(2, $old - 0.25),
                'reason' => 'Lower RR a little to test whether exits become more realistic without destroying reward/risk.',
            ];
            $changes[] = [
                'field' => 'rrMax',
                'oldValue' => (float)($rules['rrMax'] ?? $old),
                'newValue' => max(2, min((float)($rules['rrMax'] ?? $old), $old - 0.25)),
                'reason' => 'Keep rrMax aligned with rrMin for this v1 fixed-RR engine.',
            ];
        }

        if ($rejected > (int)($train['totalTrades'] ?? 0) * 2 && ($rules['filters']['requireM15StructureBreak'] ?? true)) {
            $diagnosis[] = 'Many setups are rejected before entry, so the current filters may be too strict for available data.';
        }

        if (!$changes) {
            $diagnosis[] = 'Train metrics do not show a clear reason to change rules aggressively.';
            $changes[] = [
                'field' => 'filters.requireStrongH4Zone',
                'oldValue' => (bool)($rules['filters']['requireStrongH4Zone'] ?? false),
                'newValue' => true,
                'reason' => 'Test a conservative version that only accepts stronger H4 context.',
            ];
        }

        $validationScore = $validation['score'] ?? null;
        if ($validationScore !== null) {
            $diagnosis[] = 'Validation metrics are included for comparison only; the proposed changes are based on train metrics.';
        }

        return [
            'diagnosis' => array_values(array_unique($diagnosis)),
            'proposedChanges' => $this->uniqueChanges($changes),
            'newVersionName' => 'v' . gmdate('YmdHis') . ' - AI filter adjustment',
            'expectedImpact' => 'Reduce noisy entries and drawdown before checking validation.',
            'riskWarning' => 'This is a mock reviewer. It can miss fast reversal trades and must be validated before approval.',
            'reviewer' => 'mock_strategy_ai_reviewer_v1',
        ];
    }

    private function uniqueChanges(array $changes): array
    {
        $seen = [];
        $result = [];
        foreach ($changes as $change) {
            $field = (string)($change['field'] ?? '');
            if ($field === '' || isset($seen[$field])) {
                continue;
            }
            $seen[$field] = true;
            $result[] = $change;
        }
        return $result;
    }
}
