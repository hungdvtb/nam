<?php
declare(strict_types=1);

/** Edits teaching records only; never submits or modifies a broker order. */
final class PaReviewService
{
    public static function correctedTrade(array $trade, array $changes): array
    {
        $fields = ['zoneLow', 'zoneHigh', 'entryPrice', 'initialStop', 'targetPrice', 'targetPrice2'];
        foreach ($fields as $field) {
            $value = array_key_exists($field, $changes) ? $changes[$field] : ($trade[$field] ?? null);
            if ($field === 'targetPrice2' && ($value === null || $value === '')) {
                $trade[$field] = null;
                continue;
            }
            if (!is_numeric($value) || !is_finite((float)$value) || (float)$value <= 0) {
                throw new InvalidArgumentException('Giá vùng, entry, SL và TP phải là số dương hợp lệ.');
            }
            $trade[$field] = (float)$value;
        }
        if ($trade['zoneLow'] >= $trade['zoneHigh']) throw new InvalidArgumentException('Đáy vùng phải thấp hơn đỉnh vùng.');
        $long = ($trade['direction'] ?? 'long') !== 'short';
        $entry = $trade['entryPrice'];
        if ($long ? $trade['initialStop'] >= $entry || $trade['targetPrice'] <= $entry
                  : $trade['initialStop'] <= $entry || $trade['targetPrice'] >= $entry) {
            throw new InvalidArgumentException($long ? 'Lệnh mua cần SL < Entry < TP1.' : 'Lệnh bán cần TP1 < Entry < SL.');
        }
        if ($trade['targetPrice2'] !== null && ($long ? $trade['targetPrice2'] <= $trade['targetPrice'] : $trade['targetPrice2'] >= $trade['targetPrice'])) {
            throw new InvalidArgumentException('TP2 phải xa entry hơn TP1 theo hướng lệnh.');
        }
        $trade['rr'] = abs($trade['targetPrice'] - $entry) / abs($entry - $trade['initialStop']);
        $trade['riskR'] = abs($entry - $trade['initialStop']);
        $trade['zone'] = array_merge($trade['zone'] ?? [], ['low' => $trade['zoneLow'], 'high' => $trade['zoneHigh']]);
        $trade['corrected'] = true;
        // Historical outcome belongs to the original proposal; do not reuse it after an edit.
        foreach (['netR', 'pnlUsd', 'exitPrice', 'exitTime', 'exitIndex', 'exitType', 'mfeR', 'maeR', 'holdingBars'] as $field) $trade[$field] = null;
        $trade['resultStatus'] = 'not_recalculated';
        return $trade;
    }

    public static function mutate(array $memory, string $action, array $payload): array
    {
        $id = (string)($payload['id'] ?? '');
        $index = null;
        foreach ($memory['setup_reviews'] ?? [] as $i => $review) if (($review['id'] ?? '') === $id) { $index = $i; break; }
        if ($id === '' || $index === null) throw new InvalidArgumentException('Không tìm thấy ca đã lưu.');
        $review = $memory['setup_reviews'][$index];
        if (isset($payload['expectedUpdatedAt']) && $payload['expectedUpdatedAt'] !== ($review['updatedAt'] ?? $review['createdAt'] ?? '')) {
            throw new RuntimeException('Ca đã thay đổi ở cửa sổ khác. Hãy tải lại danh sách trước khi sửa.');
        }
        $now = (new DateTimeImmutable())->format('Y-m-d\TH:i:s.uP');
        if ($action === 'delete') {
            $review['deletedAt'] = $now;
        } elseif ($action === 'restore') {
            unset($review['deletedAt']);
        } elseif ($action === 'update') {
            if (!empty($review['deletedAt'])) throw new InvalidArgumentException('Khôi phục ca trước khi chỉnh sửa.');
            $snapshot = $review['snapshot'] ?? [];
            // Older snapshots could have been compacted under summary.
            if (!isset($snapshot['trade']) && isset($snapshot['summary']['trade'])) $snapshot = $snapshot['summary'];
            if (!is_array($snapshot['trade'] ?? null)) throw new InvalidArgumentException('Ca cũ không có dữ liệu lệnh để sửa.');
            $snapshot['originalTrade'] ??= $snapshot['trade'];
            $snapshot['trade'] = self::correctedTrade($snapshot['trade'], $payload['changes'] ?? []);
            $snapshot['zone'] = array_merge($snapshot['zone'] ?? [], ['low' => $snapshot['trade']['zoneLow'], 'high' => $snapshot['trade']['zoneHigh']]);
            $review['note'] = trim((string)($payload['note'] ?? $review['note'] ?? ''));
            if (strlen($review['note']) > 12000) throw new InvalidArgumentException('Ghi chú quá dài.');
            $snapshot['trade']['correctionNote'] = $review['note'];
            $review['decision'] = 'adjusted_case';
            $review['label'] = 'Đã điều chỉnh setup';
            $snapshot['session'] = array_merge($snapshot['session'] ?? [], ['decision' => $review['decision'], 'label' => $review['label'], 'finalNote' => $review['note']]);
            $review['snapshot'] = $snapshot;
        } else {
            throw new InvalidArgumentException('Thao tác ca không hợp lệ.');
        }
        $review['updatedAt'] = $now;
        $memory['setup_reviews'][$index] = $review;
        foreach ($memory['lessons'] ?? [] as $i => $lesson) {
            if (($lesson['sourceReviewId'] ?? '') !== $id) continue;
            if ($action === 'delete') $memory['lessons'][$i]['deletedAt'] = $now;
            if ($action === 'restore') unset($memory['lessons'][$i]['deletedAt']);
            if ($action === 'update') {
                $memory['lessons'][$i]['content'] = $review['note'];
                $memory['lessons'][$i]['title'] = $review['label'];
                $memory['lessons'][$i]['tags'] = ['feedback', 'adjusted_case'];
            }
        }
        return $memory;
    }
}
