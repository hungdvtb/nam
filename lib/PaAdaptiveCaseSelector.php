<?php

declare(strict_types=1);

final class PaAdaptiveCaseSelector
{
    private const WINDOW_SIZE = 40;
    private const POOL_SIZE = 1000;

    public static function select(array $cases, array $memory, int $limit, array $excludedIds = [], string $preferredId = ''): array
    {
        usort($cases, static fn(array $a, array $b): int => self::caseTime($a) <=> self::caseTime($b));
        $cases = array_slice($cases, -self::POOL_SIZE);
        $caseById = [];
        foreach ($cases as $case) $caseById[(string)($case['id'] ?? '')] = $case;

        $reviewsByTarget = [];
        foreach ($memory['setup_reviews'] ?? [] as $review) {
            if (!is_array($review) || !empty($review['deletedAt'])) continue;
            $targetId = (string)($review['targetId'] ?? '');
            if ($targetId === '' || isset($reviewsByTarget[$targetId])) continue;
            $reviewsByTarget[$targetId] = $review;
        }

        $lastReviewedTime = 0;
        $symbol = (string)($cases[0]['symbol'] ?? '');
        foreach ($reviewsByTarget as $id => $review) {
            if ((string)($review['symbol'] ?? '') !== $symbol) continue;
            $snapshot = is_array($review['snapshot'] ?? null) ? $review['snapshot'] : [];
            $reviewCase = $caseById[$id] ?? $snapshot['originalTrade'] ?? $snapshot['trade'] ?? null;
            if (!is_array($reviewCase) || empty($reviewCase['isTeachingCase'])) continue;
            $lastReviewedTime = max($lastReviewedTime, self::caseTime($reviewCase));
        }

        $excluded = array_fill_keys(array_map('strval', $excludedIds), true);
        $available = array_values(array_filter($cases, static function (array $case) use ($reviewsByTarget, $excluded, $lastReviewedTime): bool {
            $id = (string)($case['id'] ?? '');
            return $id !== '' && !isset($reviewsByTarget[$id]) && !isset($excluded[$id]) && self::caseTime($case) > $lastReviewedTime;
        }));
        $window = array_slice($available, 0, max(self::WINDOW_SIZE, min(100, $limit * 3)));
        $ranked = [];
        foreach ($window as $case) {
            $ranked[] = self::scoreCase($case, $reviewsByTarget);
        }
        if (array_filter($ranked, static fn(array $case): bool => ($case['learning']['taughtCount'] ?? 0) > 0)) {
            usort($ranked, static fn(array $a, array $b): int => (($b['learning']['score'] ?? 0) <=> ($a['learning']['score'] ?? 0)) ?: (self::caseTime($a) <=> self::caseTime($b)));
        }
        if ($preferredId !== '' && isset($caseById[$preferredId]) && !isset($excluded[$preferredId])) {
            $preferred = self::scoreCase($caseById[$preferredId], $reviewsByTarget);
            if (isset($reviewsByTarget[$preferredId])) {
                $review = $reviewsByTarget[$preferredId];
                $preferred['review'] = $review;
                $preferred['savedReviewId'] = $review['id'] ?? '';
                $preferred['savedReviewUpdatedAt'] = $review['updatedAt'] ?? $review['createdAt'] ?? '';
            }
            $ranked = array_values(array_filter($ranked, static fn(array $case): bool => ($case['id'] ?? '') !== $preferredId));
            array_unshift($ranked, $preferred);
        }

        return [
            'cases' => array_slice($ranked, 0, max(1, $limit)),
            'available_count' => count($available),
            'reviewed_count' => count($reviewsByTarget),
            'window_count' => count($window),
        ];
    }

    public static function features(array $case): array
    {
        $zone = is_array($case['zone'] ?? null) ? $case['zone'] : [];
        $low = (float)($case['zoneLow'] ?? $zone['low'] ?? 0);
        $high = (float)($case['zoneHigh'] ?? $zone['high'] ?? 0);
        $entry = (float)($case['entryPrice'] ?? 0);
        $stop = (float)($case['initialStop'] ?? $case['stopLoss'] ?? 0);
        $width = max(0.00000001, $high - $low);
        $origin = (int)($case['zoneOriginIndex'] ?? $zone['originIndex'] ?? 0);
        $touch = (int)($zone['firstTouchIndex'] ?? $case['anchorIndex'] ?? $origin);
        return [
            'symbol' => (string)($case['symbol'] ?? ''),
            'direction' => (string)($case['direction'] ?? ''),
            'zoneStrength' => max(0.0, min(100.0, (float)($zone['strengthScore'] ?? 60))),
            'zoneAgeBars' => max(0, min(200, $touch - $origin)),
            'zoneWidthPct' => $entry > 0 ? min(5.0, 100.0 * $width / $entry) : 0.0,
            'entryPosition' => max(-2.0, min(3.0, ($entry - $low) / $width)),
            'riskZoneRatio' => $stop > 0 ? min(10.0, abs($entry - $stop) / $width) : 0.0,
            'rr' => max(0.0, min(10.0, (float)($case['rr'] ?? 0))),
            'zoneTouchCount' => max(1, min(10, (int)($case['zoneTouchCount'] ?? 1))),
            'signalBodyFraction' => max(0.0, min(1.0, (float)($case['signalBodyFraction'] ?? 0.5))),
        ];
    }

    private static function scoreCase(array $case, array $reviews): array
    {
        $features = self::features($case);
        $time = self::caseTime($case);
        $sum = 0.0;
        $weight = 0.0;
        $taught = 0;
        $closest = null;
        $closestSimilarity = 0.0;
        // Only pre-entry setup fields affect similarity; later SL/TP outcomes never enter the score.
        foreach ($reviews as $review) {
            $decision = (string)($review['decision'] ?? '');
            $label = match ($decision) {
                'good_case', 'approved' => 1.0,
                'bad_case', 'rejected' => -1.0,
                'weak_case', 'edited' => -0.6,
                default => 0.0,
            };
            if ($label === 0.0) continue;
            $snapshot = is_array($review['snapshot'] ?? null) ? $review['snapshot'] : [];
            $original = is_array($snapshot['originalTrade'] ?? null) ? $snapshot['originalTrade'] : ($snapshot['trade'] ?? null);
            if (!is_array($original)) continue;
            $reviewTime = self::caseTime($original);
            if ($reviewTime <= 0 || $reviewTime >= $time) continue;
            $original['zone'] = $snapshot['zone'] ?? $original['zone'] ?? [];
            $focus = self::noteFocus((string)($review['note'] ?? ''));
            $similarity = self::similarity($features, self::features($original), $focus);
            $influence = $similarity ** 3;
            $sum += $label * $influence;
            $weight += $influence;
            $taught++;
            if ($similarity > $closestSimilarity) {
                $closest = $review;
                $closestSimilarity = $similarity;
            }
            if ($decision === 'weak_case' && !empty($snapshot['trade']['corrected'])) {
                $corrected = $snapshot['trade'];
                $corrected['zone'] = $snapshot['zone'] ?? [];
                $correctedInfluence = self::similarity($features, self::features($corrected), $focus) ** 3 * 0.5;
                $sum += $correctedInfluence;
                $weight += $correctedInfluence;
            }
        }
        $learned = $weight > 0 ? $sum / ($weight + 1.5) * 100.0 : 0.0;
        $base = ($features['zoneStrength'] - 60.0) * 0.08;
        $score = round($learned + $base, 2);
        $reason = $taught === 0
            ? 'Ca đầu theo thứ tự thời gian; chưa có phản hồi trước ca này để học.'
            : sprintf('Xếp hạng từ %d ca đã dạy trước thời điểm này. %s', $taught,
                $sum >= 0 ? 'Setup gần các ca được chọn hơn các ca bị bỏ.' : 'Đây là ca ít trùng lỗi đã bị bỏ nhất trong nhóm đang xét.');
        if ($closest && $closestSimilarity >= 0.55) {
            $reason .= ' Ca giống nhất: ' . (string)($closest['label'] ?? $closest['decision'] ?? '') . '.';
        }
        $case['learning'] = ['score' => $score, 'taughtCount' => $taught, 'reason' => $reason];
        return $case;
    }

    private static function similarity(array $a, array $b, array $focus = []): float
    {
        $parts = [
            'direction' => [($a['direction'] === $b['direction']) ? 1.0 : 0.1, 2.0],
            'symbol' => [($a['symbol'] === $b['symbol']) ? 1.0 : 0.65, 0.5],
            'zoneStrength' => [self::near($a['zoneStrength'], $b['zoneStrength'], 35), 1.0],
            'zoneAgeBars' => [self::near($a['zoneAgeBars'], $b['zoneAgeBars'], 36), 0.7],
            'zoneWidthPct' => [self::near($a['zoneWidthPct'], $b['zoneWidthPct'], 0.4), 0.7],
            'entryPosition' => [self::near($a['entryPosition'], $b['entryPosition'], 0.6), 1.0],
            'riskZoneRatio' => [self::near($a['riskZoneRatio'], $b['riskZoneRatio'], 1.5), 1.0],
            'rr' => [self::near($a['rr'], $b['rr'], 2.0), 0.4],
            'zoneTouchCount' => [self::near($a['zoneTouchCount'], $b['zoneTouchCount'], 2.0), 1.0],
            'signalBodyFraction' => [self::near($a['signalBodyFraction'], $b['signalBodyFraction'], 0.3), 1.0],
        ];
        $sum = 0.0;
        $total = 0.0;
        foreach ($parts as $key => [$value, $partWeight]) {
            if (in_array($key, $focus, true)) $partWeight *= 3;
            $sum += $value * $partWeight;
            $total += $partWeight;
        }
        return $total > 0 ? $sum / $total : 0.0;
    }

    private static function noteFocus(string $note): array
    {
        $patterns = [
            'zoneTouchCount' => '/chạm|touch/iu',
            'signalBodyFraction' => '/xác nhận|nến|candle/iu',
            'entryPosition' => '/entry|vào sớm|vào muộn/iu',
            'riskZoneRatio' => '/stop|\bsl\b|rủi ro/iu',
            'rr' => '/\brr\b|\btp\b/iu',
            'zoneStrength' => '/vùng yếu|vùng mạnh|zone/iu',
        ];
        return array_keys(array_filter($patterns, static fn(string $pattern): bool => preg_match($pattern, $note) === 1));
    }

    private static function near(float $a, float $b, float $scale): float
    {
        return exp(-abs($a - $b) / $scale);
    }

    private static function caseTime(array $case): int
    {
        $value = $case['entryTime'] ?? $case['anchorTime'] ?? null;
        if (is_numeric($value)) return (int)$value;
        $parsed = strtotime((string)$value);
        return $parsed === false ? 0 : $parsed;
    }
}
