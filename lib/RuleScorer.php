<?php

final class RuleScorer
{
    public function score(array $case, array $strategy): array
    {
        $score = 50;
        $reasons = [];
        $blockers = [];

        if (!empty($case['news_blocked'])) {
            $blockers[] = 'Đang nằm trong vùng chặn tin đỏ';
        }

        if (!empty($case['weekend_blocked'])) {
            $blockers[] = 'Luật cuối tuần hoặc đóng lệnh thứ Sáu đang chặn risk mới';
        }

        $rr = (float)($case['rr'] ?? 0);
        $minRr = (float)($strategy['risk_reward']['min_rr'] ?? 3);
        if ($rr >= $minRr) {
            $score += 12;
            $reasons[] = 'RR đạt mức tối thiểu';
        } else {
            $score -= 25;
            $blockers[] = 'RR thấp hơn mức tối thiểu';
        }

        $zoneStrength = (float)($case['h4_zone_strength'] ?? 0);
        if ($zoneStrength >= 80) {
            $score += 18;
            $reasons[] = 'Vùng H4 mạnh';
        } elseif ($zoneStrength >= 65) {
            $score += 8;
            $reasons[] = 'Vùng H4 đủ điều kiện';
        } else {
            $score -= 15;
            $blockers[] = 'Vùng H4 yếu';
        }

        $trend = strtolower((string)($case['h4_trend'] ?? 'unclear'));
        $direction = strtolower((string)($case['direction'] ?? ''));
        $setup = (string)($case['setup_type'] ?? '');
        $allowSidewayStrongZone = (bool)($strategy['trend']['allow_h4_sideway_with_strong_zone'] ?? true);

        if ($trend === 'bullish' && $direction === 'buy') {
            $score += 14;
            $reasons[] = 'Hướng lệnh thuận xu hướng tăng H4';
        } elseif ($trend === 'bearish' && $direction === 'sell') {
            $score += 14;
            $reasons[] = 'Hướng lệnh thuận xu hướng giảm H4';
        } elseif ($trend === 'sideway' && $allowSidewayStrongZone && $zoneStrength >= 75) {
            $score += 6;
            $reasons[] = 'H4 sideway nhưng được phép vì vùng cản mạnh';
        } elseif ($setup !== 'h4_strong_zone_direct_limit') {
            $score -= 18;
            $blockers[] = 'Hướng lệnh không thuận xu hướng H4';
        }

        $m15 = strtolower((string)($case['m15_structure'] ?? ''));
        if (str_contains($m15, 'break') || str_contains($m15, 'qml')) {
            $score += 12;
            $reasons[] = 'Cấu trúc M15 xác nhận có lực tham gia';
        } elseif ($setup === 'h4_strong_zone_direct_limit') {
            $score += 2;
            $reasons[] = 'Mẫu lệnh vùng H4 mạnh có thể cân nhắc trước phản ứng M15';
        } else {
            $score -= 10;
            $blockers[] = 'Thiếu xác nhận cấu trúc M15';
        }

        $score = max(0, min(100, $score));
        $decision = count($blockers) === 0 && $score >= 70 ? 'enter' : 'skip';

        return [
            'decision' => $decision,
            'confidence' => $score,
            'reasons' => $reasons,
            'blockers' => $blockers,
            'model' => 'offline_rule_scorer_v1',
        ];
    }
}
