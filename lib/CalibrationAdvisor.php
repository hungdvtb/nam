<?php

declare(strict_types=1);

require_once __DIR__ . '/StrategyRuleParser.php';

final class CalibrationAdvisor
{
    public function recommend(array $report, array $rules): array
    {
        $rules = StrategyRuleParser::normalize($rules);
        $recommendations = [];

        $zone = is_array($report['zoneReviews'] ?? null) ? $report['zoneReviews'] : [];
        $candidate = is_array($report['candidateReviews'] ?? null) ? $report['candidateReviews'] : [];
        $trade = is_array($report['tradeReviews'] ?? null) ? $report['tradeReviews'] : [];

        if ((float)($zone['brokenWrongRate'] ?? 0) > 0.30) {
            $current = (float)($rules['h4Rules']['zoneLifecycle']['breakBufferAtr'] ?? 0.10);
            $recommendations[] = $this->recommendation(
                'zoneLifecycle',
                'h4Rules.zoneLifecycle.breakBufferAtr',
                $current,
                round(min(1.0, $current + 0.10), 2),
                'Nhieu zone bi label BROKEN_WRONG; can yeu cau pha zone ro hon.',
                (float)$zone['brokenWrongRate']
            );
        }

        if ((float)($zone['strengthTooLowRate'] ?? 0) > 0.30) {
            $current = (float)($rules['h4Rules']['minZoneStrength'] ?? 65);
            $recommendations[] = $this->recommendation(
                'zoneStrength',
                'h4Rules.minZoneStrength',
                $current,
                max(30, $current - 5),
                'Nhieu zone bi label STRENGTH_TOO_LOW; detector dang cham diem qua khat khe.',
                (float)$zone['strengthTooLowRate']
            );
        }

        if ((float)($zone['strengthTooHighRate'] ?? 0) > 0.30) {
            $current = (float)($rules['h4Rules']['minZoneStrength'] ?? 65);
            $recommendations[] = $this->recommendation(
                'zoneStrength',
                'h4Rules.minZoneStrength',
                $current,
                min(95, $current + 5),
                'Nhieu zone bi label STRENGTH_TOO_HIGH; detector dang cho qua zone yeu.',
                (float)$zone['strengthTooHighRate']
            );
        }

        if ((float)($candidate['noM15ConfirmWrongRate'] ?? 0) > 0.30) {
            $current = (bool)($rules['m15Rules']['requireStructureBreak'] ?? true);
            $recommendations[] = $this->recommendation(
                'm15Confirmation',
                'm15Rules.requireStructureBreak',
                $current,
                false,
                'Nhieu candidate bi label NO_M15_CONFIRM_WRONG; nen noi dieu kien structure break hoac reaction.',
                (float)$candidate['noM15ConfirmWrongRate']
            );
        }

        if ((float)($candidate['dirtySidewayWrongRate'] ?? 0) > 0.30) {
            $current = (bool)($rules['m15Rules']['avoidDirtySideway'] ?? true);
            $recommendations[] = $this->recommendation(
                'sidewayFilter',
                'm15Rules.avoidDirtySideway',
                $current,
                false,
                'Nhieu candidate bi label DIRTY_SIDEWAY_WRONG; filter sideway co the dang chan qua nhieu.',
                (float)$candidate['dirtySidewayWrongRate']
            );
        }

        if ((float)($candidate['badRrWrongRate'] ?? 0) > 0.30) {
            $current = (float)($rules['entryRules']['minDistanceToTPR'] ?? $rules['minRR'] ?? 3);
            $recommendations[] = $this->recommendation(
                'rrFilter',
                'entryRules.minDistanceToTPR',
                $current,
                max(1.5, $current - 0.5),
                'Nhieu candidate bi label BAD_RR_WRONG; RR filter co the qua chat voi setup nay.',
                (float)$candidate['badRrWrongRate']
            );
        }

        if ((float)($candidate['limitTooDeepRate'] ?? 0) > 0.30) {
            $current = (string)($rules['entryRules']['entryAt'] ?? 'zone_50_percent');
            $suggested = $current === 'zone_extreme' || $current === 'zone_extreme_or_50_percent'
                ? 'zone_50_percent'
                : 'zone_open';
            $recommendations[] = $this->recommendation(
                'entry',
                'entryRules.entryAt',
                $current,
                $suggested,
                'Nhieu label LIMIT_TOO_DEEP; limit dat qua sau trong zone va kho fill.',
                (float)$candidate['limitTooDeepRate']
            );
        }

        $entryWrongRate = max((float)($candidate['entryWrongRate'] ?? 0), (float)($trade['entryBadRate'] ?? 0));
        if ($entryWrongRate > 0.30) {
            $current = (string)($rules['entryRules']['entryAt'] ?? 'zone_50_percent');
            $recommendations[] = $this->recommendation(
                'entry',
                'entryRules.entryAt',
                $current,
                'zone_open',
                'Nhieu label ENTRY_WRONG/ENTRY_BAD; can test entry gan origin/open cua zone.',
                $entryWrongRate
            );
        }

        $slWrongRate = max((float)($candidate['slWrongRate'] ?? 0), (float)($trade['slBadRate'] ?? 0));
        if ($slWrongRate > 0.30) {
            $current = (float)($rules['entryRules']['stopBufferPoints'] ?? 2);
            $recommendations[] = $this->recommendation(
                'stopLoss',
                'entryRules.stopBufferPoints',
                $current,
                $current + 1,
                'Nhieu label SL_WRONG/SL_BAD; buffer stop co the qua mong.',
                $slWrongRate
            );
        }

        $tpWrongRate = max((float)($candidate['tpWrongRate'] ?? 0), (float)($trade['tpBadRate'] ?? 0));
        if ($tpWrongRate > 0.30) {
            $current = (string)($rules['entryRules']['takeProfitMode'] ?? 'rr_or_opposite_zone');
            $recommendations[] = $this->recommendation(
                'takeProfit',
                'entryRules.takeProfitMode',
                $current,
                'opposite_h4_zone_or_nearest_liquidity',
                'Nhieu label TP_WRONG/TP_BAD; nen test TP theo opposite H4 zone hoac nearest liquidity.',
                $tpWrongRate
            );
        }

        return [
            'generatedAt' => gmdate('c'),
            'recommendations' => $this->deduplicate($recommendations),
        ];
    }

    private function recommendation(string $area, string $field, mixed $currentValue, mixed $suggestedValue, string $reason, float $rate): array
    {
        return [
            'area' => $area,
            'field' => $field,
            'currentValue' => $currentValue,
            'suggestedValue' => $suggestedValue,
            'reason' => $reason,
            'confidence' => round(min(0.95, max(0.45, 0.45 + $rate)), 2),
            'requiresUserApproval' => true,
        ];
    }

    private function deduplicate(array $recommendations): array
    {
        $result = [];
        $seen = [];
        foreach ($recommendations as $recommendation) {
            $field = (string)$recommendation['field'];
            if (isset($seen[$field])) {
                continue;
            }
            $seen[$field] = true;
            $result[] = $recommendation;
        }
        return $result;
    }
}
