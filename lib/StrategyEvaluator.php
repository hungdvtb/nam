<?php

declare(strict_types=1);

final class StrategyEvaluator
{
    private const FILE = 'tradebrain/strategy_evaluations.json';

    public function __construct(private ?JsonStore $store = null)
    {
    }

    public function list(int $limit = 50): array
    {
        if ($this->store === null) {
            return [];
        }

        $items = $this->store->readJson(self::FILE, []);
        if (!is_array($items)) {
            return [];
        }
        usort($items, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        return array_slice(array_values(array_filter($items, 'is_array')), 0, $limit);
    }

    public function evaluate(array $parentVersion, array $newVersion, array $parentRuns, array $newRuns, bool $persist = true): array
    {
        $parentTrain = $this->summary($parentRuns['train'] ?? null);
        $parentValidation = $this->summary($parentRuns['validation'] ?? null);
        $parentFinal = $this->summary($parentRuns['final'] ?? null);
        $newTrain = $this->summary($newRuns['train'] ?? null);
        $newValidation = $this->summary($newRuns['validation'] ?? null);
        $newFinal = $this->summary($newRuns['final'] ?? null);

        $failReasons = [];
        $notes = [];
        $validationComparison = $this->compare($parentValidation, $newValidation);
        $finalComparison = $this->compare($parentFinal, $newFinal);

        if (($newValidation['totalTrades'] ?? 0) < 50) {
            $failReasons[] = 'Chưa đủ mẫu: dữ liệu xác thực cần ít nhất 50 lệnh';
        }
        if (($newTrain['sampleStatus'] ?? '') === 'INSUFFICIENT_SAMPLE') {
            $failReasons[] = 'Chưa đủ mẫu: dữ liệu huấn luyện bị đánh dấu chưa đủ mẫu';
        }
        if (($newValidation['sampleStatus'] ?? '') === 'INSUFFICIENT_SAMPLE') {
            $failReasons[] = 'Chưa đủ mẫu: dữ liệu xác thực bị đánh dấu chưa đủ mẫu';
        }

        $riskParent = (float)($parentVersion['rulesJson']['riskPercent'] ?? 0);
        $riskNew = (float)($newVersion['rulesJson']['riskPercent'] ?? 0);
        if ($riskNew > $riskParent + 0.00001) {
            $failReasons[] = 'Tỷ lệ rủi ro tăng khi chưa có người dùng phê duyệt';
        }

        $parentProfit = (float)$parentValidation['netProfitPercent'];
        $newProfit = (float)$newValidation['netProfitPercent'];
        $validationProfitBetter = $newProfit >= $parentProfit + max(0.01, abs($parentProfit) * 0.10);
        $profitNearlyEqual = abs($newProfit - $parentProfit) <= 2.0;
        $drawdownImproved = (float)$newValidation['maxDrawdownPercent'] <= (float)$parentValidation['maxDrawdownPercent'] * 0.80;
        if (!$validationProfitBetter && !($profitNearlyEqual && $drawdownImproved)) {
            $failReasons[] = 'Dữ liệu xác thực không cải thiện lợi nhuận ròng ít nhất 10% và cũng không giảm rõ rệt mức sụt giảm';
        }

        if ((float)$newValidation['maxDrawdownPercent'] > (float)$parentValidation['maxDrawdownPercent'] * 1.20) {
            $failReasons[] = 'Mức sụt giảm trên dữ liệu xác thực xấu hơn bản gốc trên 20%';
        }

        if ((float)$newValidation['profitFactor'] + 0.00001 < (float)$parentValidation['profitFactor']) {
            $failReasons[] = 'Hệ số lợi nhuận trên dữ liệu xác thực thấp hơn bản gốc';
        }

        if ((int)$newValidation['longestLossStreak'] > (int)$parentValidation['longestLossStreak'] + 2) {
            $failReasons[] = 'Chuỗi lệnh thua dài nhất trên dữ liệu xác thực xấu hơn bản gốc quá 2 lệnh';
        }

        if ((float)$newFinal['netProfitPercent'] < 0) {
            $failReasons[] = 'Lợi nhuận ròng trên dữ liệu kiểm tra cuối đang âm';
        }

        if ((float)$newTrain['netProfitPercent'] > (float)$parentTrain['netProfitPercent']
            && (float)$newValidation['netProfitPercent'] < (float)$parentValidation['netProfitPercent']) {
            $failReasons[] = 'Có thể bị khớp quá mức: tốt hơn trên dữ liệu huấn luyện nhưng xấu hơn trên dữ liệu xác thực';
        }

        $parentTrades = max(1, (int)$parentValidation['totalTrades']);
        $newTrades = (int)$newValidation['totalTrades'];
        if ($newTrades < max(50, (int)floor($parentTrades * 0.55))) {
            $failReasons[] = 'Số lệnh trên dữ liệu xác thực giảm quá nhiều; tỷ lệ thắng có thể chỉ đẹp hơn về hình thức';
        }

        if (!$failReasons) {
            $notes[] = 'Phiên bản mới đạt các quy tắc đánh giá xác định trên dữ liệu xác thực và dữ liệu kiểm tra cuối.';
        }

        $evaluation = [
            'id' => 'eval_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)),
            'parentStrategyVersionId' => (string)($parentVersion['id'] ?? ''),
            'newStrategyVersionId' => (string)($newVersion['id'] ?? ''),
            'trainComparison' => $this->compare($parentTrain, $newTrain),
            'validationComparison' => $validationComparison,
            'finalTestComparison' => $finalComparison,
            'scoreParent' => $this->score($parentValidation),
            'scoreNew' => $this->score($newValidation),
            'passed' => count($failReasons) === 0,
            'failReasons' => $failReasons,
            'evaluatorNotes' => implode(' ', $notes),
            'createdAt' => gmdate('c'),
        ];

        if ($persist) {
            $this->persist($evaluation);
        }

        return $evaluation;
    }

    public function score(array $summary): float
    {
        $profitFactor = (float)($summary['profitFactor'] ?? 0);
        if (!is_finite($profitFactor)) {
            $profitFactor = 10;
        }

        return round(
            (float)($summary['netProfitPercent'] ?? 0)
            - (float)($summary['maxDrawdownPercent'] ?? 0) * 2
            + $profitFactor * 10
            - (float)($summary['longestLossStreak'] ?? 0) * 2,
            2
        );
    }

    private function compare(array $parent, array $new): array
    {
        return [
            'parent' => $parent,
            'new' => $new,
            'delta' => [
                'netProfitPercent' => round((float)$new['netProfitPercent'] - (float)$parent['netProfitPercent'], 4),
                'maxDrawdownPercent' => round((float)$new['maxDrawdownPercent'] - (float)$parent['maxDrawdownPercent'], 4),
                'profitFactor' => round((float)$new['profitFactor'] - (float)$parent['profitFactor'], 4),
                'totalTrades' => (int)$new['totalTrades'] - (int)$parent['totalTrades'],
                'longestLossStreak' => (int)$new['longestLossStreak'] - (int)$parent['longestLossStreak'],
            ],
        ];
    }

    private function summary(mixed $run): array
    {
        $summary = is_array($run) && is_array($run['summary'] ?? null) ? $run['summary'] : (is_array($run) ? $run : []);
        return [
            'backtestRunId' => (string)($summary['backtestRunId'] ?? $summary['id'] ?? ''),
            'dataSplit' => (string)($summary['dataSplit'] ?? $summary['rangeType'] ?? ''),
            'totalTrades' => (int)($summary['totalTrades'] ?? 0),
            'winRate' => (float)($summary['winRate'] ?? 0),
            'netProfitPercent' => (float)($summary['netProfitPercent'] ?? 0),
            'maxDrawdownPercent' => (float)($summary['maxDrawdownPercent'] ?? 0),
            'profitFactor' => (float)($summary['profitFactor'] ?? 0),
            'averageRR' => (float)($summary['averageRR'] ?? 0),
            'longestLossStreak' => (int)($summary['longestLossStreak'] ?? 0),
            'finalBalance' => (float)($summary['finalBalance'] ?? 0),
            'sampleStatus' => (string)($summary['sampleStatus'] ?? $summary['result'] ?? ''),
        ];
    }

    private function persist(array $evaluation): void
    {
        if ($this->store === null) {
            return;
        }

        $items = $this->store->readJson(self::FILE, []);
        if (!is_array($items)) {
            $items = [];
        }
        array_unshift($items, $evaluation);
        $this->store->writeJson(self::FILE, array_slice($items, 0, 200));
    }
}
