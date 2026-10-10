<?php

declare(strict_types=1);

final class CalibrationQueueBuilder
{
    private const REASON_TYPES = [
        'ZONE_ALREADY_BROKEN',
        'WEAK_H4_ZONE',
        'NO_M15_CONFIRMATION',
        'DIRTY_SIDEWAY',
        'BAD_RR',
        'LIMIT_NOT_FILLED',
    ];

    public function __construct(
        private JsonStore $store,
        private BacktestEngine $engine,
        private ManualReviewService $reviews,
    ) {
    }

    public function build(array $strategyVersion, array $params): array
    {
        $queueType = strtoupper(trim((string)($params['queueType'] ?? $params['type'] ?? 'ZONE_ALREADY_BROKEN')));
        $limit = max(1, min(500, (int)($params['limit'] ?? 50)));
        $symbol = strtoupper(trim((string)($params['symbol'] ?? ($strategyVersion['rulesJson']['symbols'][0] ?? 'EURUSD'))));
        $split = strtolower(trim((string)($params['dataSplit'] ?? $params['split'] ?? 'train')));
        $preset = strtolower(trim((string)($params['preset'] ?? $params['rulesPreset'] ?? 'loose-debug')));
        $debugLevel = max(2, min(3, (int)($params['debugLevel'] ?? 3)));

        $result = $this->engine->run($strategyVersion, [
            'symbol' => $symbol,
            'dataSplit' => $split,
            'initialBalance' => $params['initialBalance'] ?? 10000,
            'feeR' => $params['feeR'] ?? 0.02,
            'slippagePoints' => $params['slippagePoints'] ?? 2,
            'rulesPreset' => $preset,
            'debugLevel' => $debugLevel,
            'persist' => false,
        ]);

        $items = match ($queueType) {
            'TRADES_ENTERED', 'TRADES', 'ENTERED_TRADES' => $this->tradeItems($result, $limit),
            'ZONES_LIFECYCLE', 'ZONES' => $this->zoneItems($result, $limit),
            default => $this->candidateItems($result, in_array($queueType, self::REASON_TYPES, true) ? $queueType : 'ZONE_ALREADY_BROKEN', $limit),
        };

        return [
            'ok' => true,
            'queueType' => $queueType,
            'limit' => $limit,
            'items' => $this->attachReviewStatus($items),
            'total' => count($items),
            'backtest' => $result,
            'manualReviewLabels' => $this->reviews->labels(),
        ];
    }

    private function candidateItems(array $result, string $reason, int $limit): array
    {
        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : $result;
        $runId = (string)($summary['backtestRunId'] ?? $result['backtestRunId'] ?? '');
        $strategyVersionId = (string)($summary['strategyVersionId'] ?? '');
        $symbol = (string)($summary['symbol'] ?? $result['symbol'] ?? '');
        $zones = $this->zonesById($result);
        $items = [];

        $candidates = $result['debug']['setupCandidates'] ?? $result['setup_candidates'] ?? [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $candidateReason = strtoupper((string)($candidate['rejectReason'] ?? $candidate['zoneRejectCode'] ?? ''));
            if ($candidateReason !== $reason) {
                continue;
            }
            $items[] = $this->candidateItem($candidate, $zones, $runId, $strategyVersionId, $symbol, $reason);
            if (count($items) >= $limit) {
                return $items;
            }
        }

        if ($reason === 'LIMIT_NOT_FILLED') {
            foreach (($result['trades'] ?? []) as $trade) {
                if (!is_array($trade) || ($trade['result'] ?? '') !== 'SKIPPED' || strtoupper((string)($trade['reasonCode'] ?? '')) !== 'LIMIT_NOT_FILLED') {
                    continue;
                }
                $items[] = $this->skippedTradeAsCandidateItem($trade, $zones, $runId, $strategyVersionId, $symbol);
                if (count($items) >= $limit) {
                    break;
                }
            }
        }

        return $items;
    }

    private function candidateItem(array $candidate, array $zones, string $runId, string $strategyVersionId, string $symbol, string $reason): array
    {
        $zoneId = (string)($candidate['zoneId'] ?? $candidate['h4ZoneId'] ?? '');
        $zone = $zones[$zoneId] ?? [];
        $time = (string)($candidate['time'] ?? '');
        return [
            'id' => 'q_' . (string)($candidate['id'] ?? substr(sha1($reason . $zoneId . $time), 0, 14)),
            'reviewType' => 'candidate',
            'targetId' => (string)($candidate['id'] ?? substr(sha1($reason . $zoneId . $time), 0, 14)),
            'symbol' => (string)($candidate['symbol'] ?? $symbol),
            'timeframe' => 'M15',
            'time' => $time,
            'reasonCode' => $reason,
            'title' => $reason . ' ' . $time,
            'subtitle' => (string)($candidate['reason'] ?? $candidate['zoneRejectReason'] ?? ''),
            'relatedBacktestRunId' => $runId,
            'relatedStrategyVersionId' => $strategyVersionId,
            'snapshotData' => [
                'reasonCode' => $reason,
                'candidate' => $candidate,
                'zone' => $zone,
                'nearbyM15Candles' => is_array($candidate['nearbyM15Candles'] ?? null) ? $candidate['nearbyM15Candles'] : $this->nearbyCandles((string)($candidate['symbol'] ?? $symbol), 'M15', (int)($candidate['timestamp'] ?? strtotime($time) ?: 0), 12),
                'nearbyH4Candles' => $this->nearbyCandles((string)($candidate['symbol'] ?? $symbol), 'H4', (int)($candidate['timestamp'] ?? strtotime($time) ?: 0), 6),
            ],
        ];
    }

    private function skippedTradeAsCandidateItem(array $trade, array $zones, string $runId, string $strategyVersionId, string $symbol): array
    {
        $zoneId = (string)($trade['zoneId'] ?? '');
        $zone = $zones[$zoneId] ?? [];
        $timestamp = (int)(strtotime((string)($trade['entryTime'] ?? '')) ?: 0);
        return [
            'id' => 'q_' . (string)($trade['id'] ?? substr(sha1(json_encode($trade)), 0, 14)),
            'reviewType' => 'candidate',
            'targetId' => (string)($trade['id'] ?? ''),
            'symbol' => (string)($trade['symbol'] ?? $symbol),
            'timeframe' => 'M15',
            'time' => (string)($trade['entryTime'] ?? ''),
            'reasonCode' => 'LIMIT_NOT_FILLED',
            'title' => 'LIMIT_NOT_FILLED ' . (string)($trade['entryTime'] ?? ''),
            'subtitle' => (string)($trade['reasonSkipped'] ?? ''),
            'relatedBacktestRunId' => $runId,
            'relatedStrategyVersionId' => $strategyVersionId,
            'snapshotData' => [
                'reasonCode' => 'LIMIT_NOT_FILLED',
                'trade' => $trade,
                'zone' => $zone,
                'nearbyM15Candles' => $this->nearbyCandles((string)($trade['symbol'] ?? $symbol), 'M15', $timestamp, 12),
                'nearbyH4Candles' => $this->nearbyCandles((string)($trade['symbol'] ?? $symbol), 'H4', $timestamp, 6),
            ],
        ];
    }

    private function tradeItems(array $result, int $limit): array
    {
        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : $result;
        $runId = (string)($summary['backtestRunId'] ?? $result['backtestRunId'] ?? '');
        $strategyVersionId = (string)($summary['strategyVersionId'] ?? '');
        $zones = $this->zonesById($result);
        $items = [];
        foreach (($result['trades'] ?? []) as $trade) {
            if (!is_array($trade) || ($trade['result'] ?? '') === 'SKIPPED') {
                continue;
            }
            $zoneId = (string)($trade['zoneId'] ?? '');
            $timestamp = (int)(strtotime((string)($trade['entryTime'] ?? '')) ?: 0);
            $items[] = [
                'id' => 'q_' . (string)($trade['id'] ?? substr(sha1(json_encode($trade)), 0, 14)),
                'reviewType' => 'trade',
                'targetId' => (string)($trade['id'] ?? ''),
                'symbol' => (string)($trade['symbol'] ?? ''),
                'timeframe' => 'M15',
                'time' => (string)($trade['entryTime'] ?? ''),
                'reasonCode' => (string)($trade['result'] ?? ''),
                'title' => (string)($trade['result'] ?? '') . ' ' . (string)($trade['entryTime'] ?? ''),
                'subtitle' => (string)($trade['reasonEnter'] ?? ''),
                'relatedBacktestRunId' => $runId,
                'relatedStrategyVersionId' => $strategyVersionId,
                'snapshotData' => [
                    'trade' => $trade,
                    'zone' => $zones[$zoneId] ?? [],
                    'nearbyM15Candles' => $this->nearbyCandles((string)($trade['symbol'] ?? ''), 'M15', $timestamp, 16),
                    'nearbyH4Candles' => $this->nearbyCandles((string)($trade['symbol'] ?? ''), 'H4', $timestamp, 6),
                ],
            ];
            if (count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    private function zoneItems(array $result, int $limit): array
    {
        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : $result;
        $runId = (string)($summary['backtestRunId'] ?? $result['backtestRunId'] ?? '');
        $strategyVersionId = (string)($summary['strategyVersionId'] ?? '');
        $items = [];
        foreach (($result['debug']['h4Zones'] ?? []) as $zone) {
            if (!is_array($zone)) {
                continue;
            }
            $status = (string)($zone['lifecycleStatus'] ?? ($zone['broken'] ? 'broken' : ($zone['fresh'] ? 'fresh' : 'active')));
            if (!in_array($status, ['active', 'fresh', 'touched', 'weakened', 'broken', 'invalidated_by_touches'], true)) {
                continue;
            }
            $timestamp = (int)($zone['originTimestamp'] ?? strtotime((string)($zone['originCandleTime'] ?? $zone['createdAt'] ?? '')) ?: 0);
            $id = (string)($zone['id'] ?? $zone['zoneId'] ?? '');
            $items[] = [
                'id' => 'q_' . $id,
                'reviewType' => 'zone',
                'targetId' => $id,
                'symbol' => (string)($zone['symbol'] ?? $summary['symbol'] ?? ''),
                'timeframe' => (string)($zone['timeframe'] ?? 'H4'),
                'time' => (string)($zone['originCandleTime'] ?? $zone['createdAt'] ?? ''),
                'reasonCode' => strtoupper($status),
                'title' => strtoupper($status) . ' ' . $id,
                'subtitle' => (string)($zone['reason'] ?? ''),
                'relatedBacktestRunId' => $runId,
                'relatedStrategyVersionId' => $strategyVersionId,
                'snapshotData' => [
                    'zone' => $zone,
                    'reasonCode' => strtoupper($status),
                    'nearbyH4Candles' => $this->nearbyCandles((string)($zone['symbol'] ?? $summary['symbol'] ?? ''), 'H4', $timestamp, 8),
                ],
            ];
            if (count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    private function attachReviewStatus(array $items): array
    {
        foreach ($items as &$item) {
            $review = $this->reviews->latestForTarget((string)($item['targetId'] ?? ''), (string)($item['reviewType'] ?? ''));
            $item['latestReview'] = $review;
            $item['reviewed'] = $review !== null;
        }
        unset($item);
        return $items;
    }

    private function zonesById(array $result): array
    {
        $zones = [];
        foreach (($result['debug']['h4Zones'] ?? []) as $zone) {
            if (is_array($zone)) {
                $id = (string)($zone['id'] ?? $zone['zoneId'] ?? '');
                if ($id !== '') {
                    $zones[$id] = $zone;
                }
            }
        }
        return $zones;
    }

    private function nearbyCandles(string $symbol, string $timeframe, int $timestamp, int $radius): array
    {
        $symbol = strtoupper(trim($symbol));
        $timeframe = strtoupper(trim($timeframe));
        if ($symbol === '' || $timeframe === '' || $timestamp <= 0) {
            return [];
        }
        $payload = $this->store->readJson('market_data/' . $symbol . '_' . $timeframe . '.json', []);
        $bars = is_array($payload['bars'] ?? null) ? $payload['bars'] : [];
        if (!$bars) {
            return [];
        }
        usort($bars, fn($a, $b) => $this->barTime($a) <=> $this->barTime($b));
        $best = 0;
        $bestDistance = PHP_INT_MAX;
        foreach ($bars as $index => $bar) {
            if (!is_array($bar)) {
                continue;
            }
            $distance = abs($this->barTime($bar) - $timestamp);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $index;
            }
        }
        $slice = array_slice($bars, max(0, $best - $radius), $radius * 2 + 1);
        return array_map(fn($bar) => [
            'time' => (string)($bar['time_iso'] ?? gmdate('c', $this->barTime($bar))),
            'open' => (float)($bar['open'] ?? 0),
            'high' => (float)($bar['high'] ?? 0),
            'low' => (float)($bar['low'] ?? 0),
            'close' => (float)($bar['close'] ?? 0),
        ], array_filter($slice, 'is_array'));
    }

    private function barTime(array $bar): int
    {
        if (isset($bar['time']) && is_numeric($bar['time'])) {
            return (int)$bar['time'];
        }
        $time = strtotime((string)($bar['time_iso'] ?? ''));
        return $time === false ? 0 : $time;
    }
}
