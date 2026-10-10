<?php

declare(strict_types=1);

final class ManualReviewService
{
    public const FILE = 'manual_reviews.json';
    private const LEGACY_ZONE_FILE = 'zone_manual_reviews.json';

    private const LABELS = [
        'zone' => [
            'ZONE_GOOD',
            'ZONE_BAD',
            'ZONE_TRADEABLE',
            'ZONE_NOT_TRADEABLE',
            'BROKEN_CORRECT',
            'BROKEN_WRONG',
            'STRENGTH_CORRECT',
            'STRENGTH_TOO_LOW',
            'STRENGTH_TOO_HIGH',
            'ORIGIN_CORRECT',
            'ORIGIN_WRONG',
        ],
        'candidate' => [
            'REJECT_CORRECT',
            'REJECT_WRONG',
            'SHOULD_HAVE_BEEN_TRADE',
            'NO_M15_CONFIRM_CORRECT',
            'NO_M15_CONFIRM_WRONG',
            'BAD_RR_CORRECT',
            'BAD_RR_WRONG',
            'DIRTY_SIDEWAY_CORRECT',
            'DIRTY_SIDEWAY_WRONG',
            'LIMIT_TOO_DEEP',
            'LIMIT_REASONABLE',
            'ENTRY_WRONG',
            'SL_WRONG',
            'TP_WRONG',
        ],
        'trade' => [
            'ENTRY_GOOD',
            'ENTRY_BAD',
            'SL_GOOD',
            'SL_BAD',
            'TP_GOOD',
            'TP_BAD',
            'SHOULD_NOT_TRADE',
            'GOOD_LOSS',
            'BAD_LOSS',
            'GOOD_WIN',
            'LUCKY_WIN',
        ],
    ];

    private const LEGACY_ZONE_LABELS = [
        'ZONE_DUNG' => 'ZONE_GOOD',
        'ZONE_SAI' => 'ZONE_BAD',
        'DANG_TRADE' => 'ZONE_TRADEABLE',
        'KHONG_DANG_TRADE' => 'ZONE_NOT_TRADEABLE',
        'BROKEN_SAI' => 'BROKEN_WRONG',
        'STRENGTH_SAI' => 'STRENGTH_TOO_LOW',
    ];

    public function __construct(private JsonStore $store)
    {
    }

    public function labels(): array
    {
        return self::LABELS;
    }

    public function all(): array
    {
        $reviews = $this->store->readJson(self::FILE, []);
        if (!is_array($reviews)) {
            $reviews = [];
        }

        $normalized = [];
        foreach ($reviews as $review) {
            if (is_array($review)) {
                $normalized[] = $this->normalizeRecord($review, false);
            }
        }

        $seen = [];
        foreach ($normalized as $review) {
            $seen[(string)($review['id'] ?? '')] = true;
        }

        foreach ($this->legacyZoneReviews() as $legacy) {
            if (!isset($seen[$legacy['id']])) {
                $normalized[] = $legacy;
                $seen[$legacy['id']] = true;
            }
        }

        usort($normalized, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        return array_values($normalized);
    }

    public function byType(string $type): array
    {
        $type = $this->normalizeType($type);
        return array_values(array_filter($this->all(), fn($review) => ($review['reviewType'] ?? '') === $type));
    }

    public function latestForTarget(string $targetId, string $type = ''): ?array
    {
        foreach ($this->all() as $review) {
            if ((string)($review['targetId'] ?? '') !== $targetId) {
                continue;
            }
            if ($type !== '' && (string)($review['reviewType'] ?? '') !== $this->normalizeType($type)) {
                continue;
            }
            return $review;
        }
        return null;
    }

    public function save(array $payload): array
    {
        $record = $this->normalizeRecord($payload, true);
        $reviews = $this->store->readJson(self::FILE, []);
        if (!is_array($reviews)) {
            $reviews = [];
        }

        $reviews = array_values(array_filter(
            $reviews,
            fn($item) => is_array($item) && (string)($item['id'] ?? '') !== $record['id']
        ));
        array_unshift($reviews, $record);
        $this->store->writeJson(self::FILE, array_slice($reviews, 0, 20000));
        return $record;
    }

    public function import(array $payload, bool $replace = false): array
    {
        $incoming = $payload['reviews'] ?? $payload;
        if (!is_array($incoming)) {
            throw new InvalidArgumentException('Danh sách đánh giá thủ công không đúng định dạng.');
        }

        $existing = $replace ? [] : $this->store->readJson(self::FILE, []);
        if (!is_array($existing)) {
            $existing = [];
        }
        $byId = [];
        foreach ($existing as $review) {
            if (is_array($review)) {
                $normalized = $this->normalizeRecord($review, false);
                $byId[$normalized['id']] = $normalized;
            }
        }

        $imported = 0;
        foreach ($incoming as $review) {
            if (!is_array($review)) {
                continue;
            }
            $normalized = $this->normalizeRecord($review, true);
            if (!isset($byId[$normalized['id']])) {
                $imported++;
            }
            $byId[$normalized['id']] = $normalized;
        }

        $reviews = array_values($byId);
        usort($reviews, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        $this->store->writeJson(self::FILE, $reviews);

        return [
            'imported' => $imported,
            'total' => count($reviews),
            'reviews' => $reviews,
        ];
    }

    public function exportSnapshot(): array
    {
        return [
            'format' => 'ai_trade_manual_reviews_v1',
            'exportedAt' => gmdate('c'),
            'reviews' => $this->all(),
        ];
    }

    public function zoneReviewsForLegacyUi(): array
    {
        return array_values(array_map(function (array $review): array {
            return [
                ...$review,
                'zoneId' => (string)($review['targetId'] ?? ''),
            ];
        }, $this->byType('zone')));
    }

    public function calibrationCounts(): array
    {
        $counts = [
            'total' => 0,
            'zone' => 0,
            'candidate' => 0,
            'trade' => 0,
        ];
        foreach ($this->all() as $review) {
            $type = (string)($review['reviewType'] ?? '');
            $counts['total']++;
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }
        return $counts;
    }

    private function normalizeRecord(array $payload, bool $validate): array
    {
        $type = $this->normalizeType((string)($payload['reviewType'] ?? $payload['type'] ?? 'zone'));
        $label = strtoupper(trim((string)($payload['label'] ?? '')));
        $label = self::LEGACY_ZONE_LABELS[$label] ?? $label;
        if ($validate && !in_array($label, self::LABELS[$type], true)) {
            throw new InvalidArgumentException('Nhãn đánh giá thủ công không hợp lệ cho loại ' . $type . '.');
        }

        $targetId = trim((string)($payload['targetId'] ?? $payload['zoneId'] ?? $payload['candidateId'] ?? $payload['tradeId'] ?? ''));
        if ($validate && $targetId === '') {
            throw new InvalidArgumentException('Thiếu mã đối tượng cần đánh giá thủ công.');
        }

        return [
            'id' => (string)($payload['id'] ?? $this->newId($type)),
            'reviewType' => $type,
            'targetId' => $targetId,
            'symbol' => strtoupper(trim((string)($payload['symbol'] ?? ''))),
            'timeframe' => strtoupper(trim((string)($payload['timeframe'] ?? ''))),
            'label' => $label,
            'note' => trim((string)($payload['note'] ?? $payload['your_note'] ?? '')),
            'reviewer' => trim((string)($payload['reviewer'] ?? 'user')) ?: 'user',
            'createdAt' => (string)($payload['createdAt'] ?? $payload['stored_at'] ?? gmdate('c')),
            'relatedBacktestRunId' => (string)($payload['relatedBacktestRunId'] ?? $payload['backtestRunId'] ?? ''),
            'relatedStrategyVersionId' => (string)($payload['relatedStrategyVersionId'] ?? $payload['strategyVersionId'] ?? ''),
            'snapshotData' => is_array($payload['snapshotData'] ?? null) ? $payload['snapshotData'] : [],
        ];
    }

    private function legacyZoneReviews(): array
    {
        $legacy = $this->store->readJson(self::LEGACY_ZONE_FILE, []);
        if (!is_array($legacy)) {
            return [];
        }

        $reviews = [];
        foreach ($legacy as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = strtoupper((string)($item['label'] ?? ''));
            $reviews[] = $this->normalizeRecord([
                'id' => (string)($item['id'] ?? 'legacy_zrev_' . substr(sha1(json_encode($item)), 0, 12)),
                'reviewType' => 'zone',
                'targetId' => (string)($item['zoneId'] ?? ''),
                'symbol' => (string)($item['symbol'] ?? ''),
                'timeframe' => (string)($item['timeframe'] ?? 'H4'),
                'label' => self::LEGACY_ZONE_LABELS[$label] ?? $label,
                'note' => (string)($item['note'] ?? ''),
                'reviewer' => 'user',
                'createdAt' => (string)($item['createdAt'] ?? gmdate('c')),
                'relatedBacktestRunId' => (string)($item['relatedBacktestRunId'] ?? ''),
                'relatedStrategyVersionId' => (string)($item['relatedStrategyVersionId'] ?? ''),
                'snapshotData' => is_array($item['snapshotData'] ?? null) ? $item['snapshotData'] : [],
            ], false);
        }
        return $reviews;
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['zone', 'candidate', 'trade'], true)) {
            throw new InvalidArgumentException('Loại đánh giá phải là vùng giá, mẫu ứng viên hoặc lệnh giao dịch.');
        }
        return $type;
    }

    private function newId(string $type): string
    {
        return match ($type) {
            'candidate' => 'crev_',
            'trade' => 'trev_',
            default => 'zrev_',
        } . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
}
