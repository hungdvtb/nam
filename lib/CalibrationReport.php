<?php

declare(strict_types=1);

final class CalibrationReport
{
    public function build(array $reviews): array
    {
        $byType = [
            'zone' => [],
            'candidate' => [],
            'trade' => [],
        ];
        $labelCounts = [];
        foreach ($reviews as $review) {
            if (!is_array($review)) {
                continue;
            }
            $type = (string)($review['reviewType'] ?? '');
            if (!isset($byType[$type])) {
                continue;
            }
            $byType[$type][] = $review;
            $label = (string)($review['label'] ?? 'UNKNOWN');
            $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
        }
        arsort($labelCounts);

        return [
            'generatedAt' => gmdate('c'),
            'manualReviewsTotal' => count($byType['zone']) + count($byType['candidate']) + count($byType['trade']),
            'reviewTypeCounts' => [
                'zone' => count($byType['zone']),
                'candidate' => count($byType['candidate']),
                'trade' => count($byType['trade']),
            ],
            'labelCounts' => $labelCounts,
            'topDetectorErrors' => $this->topDetectorErrors($reviews),
            'zoneReviews' => $this->zoneReport($byType['zone']),
            'candidateReviews' => $this->candidateReport($byType['candidate']),
            'tradeReviews' => $this->tradeReport($byType['trade']),
        ];
    }

    private function zoneReport(array $reviews): array
    {
        $counts = $this->counts($reviews);
        $total = count($reviews);
        return [
            'total' => $total,
            'byLabel' => $counts,
            'brokenWrongRate' => $this->pairRate($counts, 'BROKEN_WRONG', 'BROKEN_CORRECT', $total),
            'strengthTooLowRate' => $this->multiRate($counts, ['STRENGTH_TOO_LOW'], ['STRENGTH_CORRECT', 'STRENGTH_TOO_HIGH'], $total),
            'strengthTooHighRate' => $this->multiRate($counts, ['STRENGTH_TOO_HIGH'], ['STRENGTH_CORRECT', 'STRENGTH_TOO_LOW'], $total),
            'originWrongRate' => $this->pairRate($counts, 'ORIGIN_WRONG', 'ORIGIN_CORRECT', $total),
            'zoneBadRate' => $this->pairRate($counts, 'ZONE_BAD', 'ZONE_GOOD', $total),
            'notTradeableRate' => $this->pairRate($counts, 'ZONE_NOT_TRADEABLE', 'ZONE_TRADEABLE', $total),
        ];
    }

    private function candidateReport(array $reviews): array
    {
        $counts = $this->counts($reviews);
        $total = count($reviews);
        return [
            'total' => $total,
            'byLabel' => $counts,
            'rejectWrongRate' => $this->multiRate($counts, ['REJECT_WRONG', 'SHOULD_HAVE_BEEN_TRADE'], ['REJECT_CORRECT'], $total),
            'noM15ConfirmWrongRate' => $this->pairRate($counts, 'NO_M15_CONFIRM_WRONG', 'NO_M15_CONFIRM_CORRECT', $total),
            'badRrWrongRate' => $this->pairRate($counts, 'BAD_RR_WRONG', 'BAD_RR_CORRECT', $total),
            'dirtySidewayWrongRate' => $this->pairRate($counts, 'DIRTY_SIDEWAY_WRONG', 'DIRTY_SIDEWAY_CORRECT', $total),
            'limitTooDeepRate' => $this->pairRate($counts, 'LIMIT_TOO_DEEP', 'LIMIT_REASONABLE', $total),
            'entryWrongRate' => $this->singleRate($counts, 'ENTRY_WRONG', $total),
            'slWrongRate' => $this->singleRate($counts, 'SL_WRONG', $total),
            'tpWrongRate' => $this->singleRate($counts, 'TP_WRONG', $total),
        ];
    }

    private function tradeReport(array $reviews): array
    {
        $counts = $this->counts($reviews);
        $total = count($reviews);
        return [
            'total' => $total,
            'byLabel' => $counts,
            'entryBadRate' => $this->pairRate($counts, 'ENTRY_BAD', 'ENTRY_GOOD', $total),
            'slBadRate' => $this->pairRate($counts, 'SL_BAD', 'SL_GOOD', $total),
            'tpBadRate' => $this->pairRate($counts, 'TP_BAD', 'TP_GOOD', $total),
            'shouldNotTradeRate' => $this->singleRate($counts, 'SHOULD_NOT_TRADE', $total),
            'badLossRate' => $this->pairRate($counts, 'BAD_LOSS', 'GOOD_LOSS', $total),
            'luckyWinRate' => $this->pairRate($counts, 'LUCKY_WIN', 'GOOD_WIN', $total),
        ];
    }

    private function topDetectorErrors(array $reviews): array
    {
        $errorLabels = [
            'BROKEN_WRONG',
            'STRENGTH_TOO_LOW',
            'STRENGTH_TOO_HIGH',
            'ORIGIN_WRONG',
            'ZONE_BAD',
            'ZONE_NOT_TRADEABLE',
            'REJECT_WRONG',
            'SHOULD_HAVE_BEEN_TRADE',
            'NO_M15_CONFIRM_WRONG',
            'BAD_RR_WRONG',
            'DIRTY_SIDEWAY_WRONG',
            'LIMIT_TOO_DEEP',
            'ENTRY_WRONG',
            'SL_WRONG',
            'TP_WRONG',
            'ENTRY_BAD',
            'SL_BAD',
            'TP_BAD',
            'SHOULD_NOT_TRADE',
            'BAD_LOSS',
            'LUCKY_WIN',
        ];

        $counts = [];
        foreach ($reviews as $review) {
            if (!is_array($review)) {
                continue;
            }
            $label = (string)($review['label'] ?? '');
            if (!in_array($label, $errorLabels, true)) {
                continue;
            }
            $snapshot = is_array($review['snapshotData'] ?? null) ? $review['snapshotData'] : [];
            $reason = (string)($snapshot['reasonCode']
                ?? $snapshot['rejectReason']
                ?? $snapshot['candidate']['rejectReason']
                ?? $snapshot['candidate']['zoneRejectCode']
                ?? $snapshot['trade']['reasonCode']
                ?? $label);
            $area = $this->areaForLabel($label);
            $key = $area . ':' . ($reason !== '' ? $reason : $label);
            $counts[$key] ??= [
                'area' => $area,
                'reason' => $reason !== '' ? $reason : $label,
                'count' => 0,
                'labels' => [],
            ];
            $counts[$key]['count']++;
            $counts[$key]['labels'][$label] = ($counts[$key]['labels'][$label] ?? 0) + 1;
        }

        usort($counts, fn($a, $b) => (int)$b['count'] <=> (int)$a['count']);
        return array_slice(array_values($counts), 0, 10);
    }

    private function areaForLabel(string $label): string
    {
        return match ($label) {
            'BROKEN_WRONG' => 'zoneLifecycle',
            'STRENGTH_TOO_LOW', 'STRENGTH_TOO_HIGH' => 'zoneStrength',
            'ORIGIN_WRONG' => 'zoneOrigin',
            'NO_M15_CONFIRM_WRONG' => 'm15Confirmation',
            'BAD_RR_WRONG' => 'rrFilter',
            'DIRTY_SIDEWAY_WRONG' => 'sidewayFilter',
            'LIMIT_TOO_DEEP', 'ENTRY_WRONG', 'ENTRY_BAD' => 'entry',
            'SL_WRONG', 'SL_BAD', 'BAD_LOSS' => 'stopLoss',
            'TP_WRONG', 'TP_BAD', 'LUCKY_WIN' => 'takeProfit',
            default => 'detector',
        };
    }

    private function counts(array $reviews): array
    {
        $counts = [];
        foreach ($reviews as $review) {
            if (!is_array($review)) {
                continue;
            }
            $label = (string)($review['label'] ?? 'UNKNOWN');
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }
        arsort($counts);
        return $counts;
    }

    private function pairRate(array $counts, string $bad, string $good, int $fallbackTotal): float
    {
        return $this->multiRate($counts, [$bad], [$good], $fallbackTotal);
    }

    private function singleRate(array $counts, string $label, int $total): float
    {
        return $total > 0 ? round((int)($counts[$label] ?? 0) / $total, 4) : 0.0;
    }

    private function multiRate(array $counts, array $badLabels, array $goodLabels, int $fallbackTotal): float
    {
        $bad = 0;
        foreach ($badLabels as $label) {
            $bad += (int)($counts[$label] ?? 0);
        }
        $good = 0;
        foreach ($goodLabels as $label) {
            $good += (int)($counts[$label] ?? 0);
        }
        $denominator = $bad + $good;
        if ($denominator <= 0) {
            $denominator = $fallbackTotal;
        }
        return $denominator > 0 ? round($bad / $denominator, 4) : 0.0;
    }
}
