<?php

declare(strict_types=1);

interface TradeBrainAiService
{
    public function proposeStrategyUpgrade(array $input): array;
}

final class MockTradeBrainAiService implements TradeBrainAiService
{
    public function proposeStrategyUpgrade(array $input): array
    {
        $rules = is_array($input['rulesJson'] ?? null) ? $input['rulesJson'] : [];
        $train = is_array($input['trainSummary'] ?? null) ? $input['trainSummary'] : [];
        $trades = array_values(array_filter($input['trainTrades'] ?? [], 'is_array'));
        $memoryLessons = array_values(array_filter($input['memoryLessons'] ?? [], 'is_array'));

        $diagnosis = [];
        $lessons = [];
        $changes = [];
        $lossTags = $this->tagCounts($trades, 'LOSS');
        $skippedTags = $this->tagCounts($trades, 'SKIPPED');
        $profitFactor = (float)($train['profitFactor'] ?? 0);
        $drawdown = (float)($train['maxDrawdownPercent'] ?? 0);
        $lossStreak = (int)($train['longestLossStreak'] ?? 0);
        $winRate = (float)($train['winRate'] ?? 0);

        if ($profitFactor < 1.1) {
            $diagnosis[] = 'Profit factor train yếu, cần tăng chất lượng setup trước khi validation.';
            $changes[] = [
                'field' => 'h4Rules.detectTrend',
                'oldValue' => (bool)($rules['h4Rules']['detectTrend'] ?? false),
                'newValue' => true,
                'reason' => 'Ép setup thuận trend H4 đóng nến để giảm lệnh nhiễu.',
            ];
            $lessons[] = [
                'title' => 'Ưu tiên setup thuận trend H4',
                'content' => 'Nhóm lệnh train có profit factor yếu, phiên bản kế tiếp nên kiểm tra filter H4 trend trên validation.',
                'confidence' => 74,
            ];
        }

        if ($drawdown > 8 || $lossStreak >= 3 || ($lossTags['SIDEWAY'] ?? 0) > 0 || ($lossTags['DIRTY_SIDEWAY'] ?? 0) > 0 || ($skippedTags['DIRTY_SIDEWAY'] ?? 0) > 0) {
            $diagnosis[] = 'Drawdown hoặc chuỗi thua cao; cần giảm setup sideway/yếu.';
            $oldStrength = (float)($rules['h4Rules']['minZoneStrength'] ?? $rules['zoneStrengthMin'] ?? 65);
            $changes[] = [
                'field' => 'h4Rules.minZoneStrength',
                'oldValue' => $oldStrength,
                'newValue' => min(90, $oldStrength + 5),
                'reason' => 'Tăng ngưỡng strength để loại bớt vùng H4 yếu.',
            ];
            $changes[] = [
                'field' => 'm15Rules.avoidDirtySideway',
                'oldValue' => (bool)($rules['m15Rules']['avoidDirtySideway'] ?? false),
                'newValue' => true,
                'reason' => 'Sideway thường tạo cụm thua trong proxy EMA/structure.',
            ];
            $lessons[] = [
                'title' => 'Giảm lệnh trong vùng sideway',
                'content' => 'Khi drawdown hoặc loss streak tăng, nên kiểm tra filter sideway trước khi chấp nhận version mới.',
                'confidence' => 78,
            ];
        }

        if ($winRate < 38 && (float)($rules['minRR'] ?? $rules['rrMin'] ?? 3) > 2.5) {
            $diagnosis[] = 'Winrate thấp; thử giảm nhẹ RR để kiểm tra tính thực tế của exit.';
            $oldRr = (float)($rules['minRR'] ?? $rules['rrMin'] ?? 3);
            $newRr = max(2.5, $oldRr - 0.25);
            $changes[] = [
                'field' => 'minRR',
                'oldValue' => $oldRr,
                'newValue' => $newRr,
                'reason' => 'Giảm rất nhẹ RR, không tăng risk, sau đó bắt buộc validation/final.',
            ];
            $changes[] = [
                'field' => 'entryRules.minDistanceToTPR',
                'oldValue' => (float)($rules['entryRules']['minDistanceToTPR'] ?? $oldRr),
                'newValue' => $newRr,
                'reason' => 'Keep target distance aligned with minRR.',
            ];
            $changes[] = [
                'field' => 'rrMax',
                'oldValue' => (float)($rules['rrMax'] ?? $oldRr),
                'newValue' => max($newRr, min((float)($rules['rrMax'] ?? $oldRr), $newRr)),
                'reason' => 'Giữ rrMax đồng bộ với rrMin trong engine fixed-RR hiện tại.',
            ];
        }

        if (($skippedTags['HIGH_SPREAD'] ?? 0) > 3) {
            $diagnosis[] = 'Có nhiều setup bị loại do spread, không nên nới spread trong bản AI tự nâng cấp.';
            $lessons[] = [
                'title' => 'Không nới spread để tăng số lệnh',
                'content' => 'Spread cao làm méo kết quả backtest; version AI không được tăng maxSpread chỉ để có thêm lệnh.',
                'confidence' => 82,
            ];
        }

        if ($memoryLessons) {
            $diagnosis[] = 'Đã đọc memory lessons liên quan trước khi đề xuất thay đổi.';
        }

        if (!$changes) {
            $diagnosis[] = 'Train chưa cho thấy nguyên nhân rõ; chỉ thử một thay đổi bảo thủ.';
            $changes[] = [
                'field' => 'h4Rules.minZoneStrength',
                'oldValue' => (float)($rules['h4Rules']['minZoneStrength'] ?? $rules['zoneStrengthMin'] ?? 70),
                'newValue' => min(90, (float)($rules['h4Rules']['minZoneStrength'] ?? $rules['zoneStrengthMin'] ?? 70) + 5),
                'reason' => 'Kiểm tra bản chỉ nhận context H4 mạnh hơn, không tăng risk.',
            ];
            $lessons[] = [
                'title' => 'Thay đổi nhỏ khi tín hiệu chưa rõ',
                'content' => 'Nếu train không chỉ ra vấn đề nổi bật, version mới chỉ nên thay đổi một filter bảo thủ rồi validation.',
                'confidence' => 70,
            ];
        }

        return [
            'diagnosis' => array_values(array_unique($diagnosis)),
            'lessons' => $this->uniqueLessons($lessons),
            'proposedChanges' => $this->uniqueChanges($changes),
            'newVersionName' => 'v' . gmdate('YmdHis') . ' - TradeBrain filter upgrade',
            'expectedImpact' => 'Giảm lệnh nhiễu và kiểm tra lại drawdown trên validation/final.',
            'riskWarning' => 'Có thể giảm số lượng lệnh; không tăng riskPercent và không dùng kết quả train để tự approve.',
        ];
    }

    private function tagCounts(array $trades, string $result): array
    {
        $counts = [];
        foreach ($trades as $trade) {
            if (($trade['result'] ?? '') !== $result) {
                continue;
            }
            foreach (($trade['tags'] ?? []) as $tag) {
                $tag = (string)$tag;
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }
        arsort($counts);
        return $counts;
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

    private function uniqueLessons(array $lessons): array
    {
        $seen = [];
        $result = [];
        foreach ($lessons as $lesson) {
            $key = sha1((string)($lesson['title'] ?? '') . "\n" . (string)($lesson['content'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $lesson;
        }
        return $result;
    }
}
