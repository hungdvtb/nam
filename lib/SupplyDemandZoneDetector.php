<?php

declare(strict_types=1);

final class SupplyDemandZoneDetector
{
    private const DEFAULT_LIFECYCLE = [
        'requireCloseBeyondZoneToBreak' => true,
        'allowWickThrough' => true,
        'wickToleranceAtr' => 0.25,
        'breakBufferAtr' => 0.10,
        'maxTouchesBeforeWeak' => 2,
        'maxTouchesBeforeInvalid' => 4,
        'markTouchedButNotBroken' => true,
    ];

    public function detect(array $bars, array $structure, string $symbol, string $timeframe, array $config = []): array
    {
        $medianRange = $this->medianRange($bars);
        $lifecycle = $this->lifecycleConfig($config);
        $zones = [];

        foreach (($structure['events'] ?? []) as $event) {
            if (!is_array($event) || !in_array(($event['type'] ?? ''), ['breakOfStructure', 'changeOfCharacter'], true)) {
                continue;
            }
            if (($config['requireBosForZone'] ?? true) && ($event['type'] ?? '') !== 'breakOfStructure') {
                continue;
            }

            $eventIndex = (int)($event['index'] ?? -1);
            if ($eventIndex < 3 || !isset($bars[$eventIndex])) {
                continue;
            }

            $direction = (string)($event['direction'] ?? '');
            if ($direction !== 'bullish' && $direction !== 'bearish') {
                continue;
            }

            $originIndex = $this->findOriginIndex($bars, $eventIndex, $direction);
            if ($originIndex < 0) {
                continue;
            }

            $origin = $bars[$originIndex];
            $type = $direction === 'bullish' ? 'demand' : 'supply';
            $high = (float)$origin['high'];
            $low = (float)$origin['low'];
            if ($high <= $low) {
                continue;
            }

            $impulseStrength = $this->impulseStrength($bars, $originIndex, $eventIndex, $direction, $medianRange);
            if ($impulseStrength < (float)($config['minImpulseSize'] ?? 0)) {
                continue;
            }

            $baseBreakdown = $this->baseStrengthBreakdown($event, $impulseStrength, $bars, $originIndex, $eventIndex);
            $baseScore = $baseBreakdown['finalScore'];
            $zone = [
                'id' => $this->zoneId($symbol, $timeframe, $type, $originIndex, $eventIndex),
                'zoneId' => $this->zoneId($symbol, $timeframe, $type, $originIndex, $eventIndex),
                'symbol' => strtoupper($symbol),
                'timeframe' => strtoupper($timeframe),
                'type' => $type,
                'createdAt' => $this->barIso($bars[$eventIndex]),
                'createdTimestamp' => $this->barTime($bars[$eventIndex]),
                'createdIndex' => $eventIndex,
                'createdByBos' => ($event['type'] ?? '') === 'breakOfStructure',
                'high' => $high,
                'low' => $low,
                'originOpen' => (float)$origin['open'],
                'originHigh' => (float)$origin['high'],
                'originLow' => (float)$origin['low'],
                'originClose' => (float)$origin['close'],
                'originCandleTime' => $this->barIso($origin),
                'originTimestamp' => $this->barTime($origin),
                'originIndex' => $originIndex,
                'originCandle' => $this->candleSnapshot($origin, $originIndex),
                'impulseCandles' => $this->candleSnapshots(array_slice($bars, $originIndex + 1, max(0, $eventIndex - $originIndex)), $originIndex + 1),
                'impulseStrength' => round($impulseStrength, 4),
                'createdByBosCandle' => $this->candleSnapshot($bars[$eventIndex], $eventIndex),
                'bosCandle' => $this->candleSnapshot($bars[$eventIndex], $eventIndex),
                'structureEvent' => $event,
                'baseStrengthScore' => $baseScore,
                'baseStrengthBreakdown' => $baseBreakdown,
                'strengthBreakdown' => $baseBreakdown,
                'fresh' => true,
                'touched' => false,
                'weakened' => false,
                'invalidatedByTouches' => false,
                'active' => true,
                'touchedCount' => 0,
                'touches' => [],
                'touchedIndexes' => [],
                'firstTouchTime' => null,
                'broken' => false,
                'brokenIndex' => null,
                'brokenTime' => null,
                'brokenByCandle' => null,
                'brokenReason' => '',
                'strengthScore' => $baseScore,
                'lifecycleStatus' => 'fresh',
                'lifecycleConfig' => $lifecycle,
                'trendAtCreation' => $direction,
                'reason' => $this->reason($type, $event, $impulseStrength),
            ];

            $zones[] = $this->applyLifecycle($zone, $bars, $lifecycle, $medianRange);
        }

        usort($zones, fn($a, $b) => (int)$a['createdIndex'] <=> (int)$b['createdIndex']);
        return $zones;
    }

    public function applyLifecycle(array $zone, array $bars, array $config = [], ?float $medianRange = null): array
    {
        $lifecycle = $this->lifecycleConfig($config ?: ($zone['lifecycleConfig'] ?? []));
        $medianRange = max(0.0000001, $medianRange ?? $this->medianRange($bars));
        $zone['lifecycleConfig'] = $lifecycle;
        $zone['touches'] = [];
        $zone['touchedIndexes'] = [];
        $zone['firstTouchTime'] = null;
        $zone['broken'] = false;
        $zone['brokenIndex'] = null;
        $zone['brokenTime'] = null;
        $zone['brokenByCandle'] = null;
        $zone['brokenReason'] = '';

        $inside = false;
        $start = max(0, (int)($zone['createdIndex'] ?? -1) + 1);
        for ($i = $start; $i < count($bars); $i++) {
            $bar = $bars[$i];
            $overlap = $this->overlaps($zone, $bar);
            if ($overlap && !$inside && (bool)$lifecycle['markTouchedButNotBroken']) {
                $touch = [
                    'index' => $i,
                    'time' => $this->barIso($bar),
                    'timestamp' => $this->barTime($bar),
                    'candle' => $this->candleSnapshot($bar, $i),
                ];
                $zone['touches'][] = $touch;
                $zone['touchedIndexes'][] = $i;
                if ($zone['firstTouchTime'] === null) {
                    $zone['firstTouchTime'] = $touch['time'];
                }
            }
            $inside = $overlap;

            $break = $this->breakDecision($zone, $bar, $medianRange, $lifecycle);
            if ($break['broken']) {
                $zone['broken'] = true;
                $zone['brokenIndex'] = $i;
                $zone['brokenTime'] = $this->barIso($bar);
                $zone['brokenByCandle'] = $this->candleSnapshot($bar, $i);
                $zone['brokenReason'] = $break['reason'];
                break;
            }
        }

        return $this->stateAt($zone, count($bars) - 1);
    }

    public function stateAt(array $zone, int $barIndex): array
    {
        $barIndex = max(-1, $barIndex);
        $touches = $this->touchesAt($zone, $barIndex);
        $brokenIndex = $zone['brokenIndex'] ?? null;
        $broken = $brokenIndex !== null && (int)$brokenIndex <= $barIndex;
        $lifecycle = $this->lifecycleConfig($zone['lifecycleConfig'] ?? []);
        $touchCount = count($touches);
        $fresh = $touchCount === 0 && !$broken;
        $weakened = !$broken && $touchCount > (int)$lifecycle['maxTouchesBeforeWeak'];
        $invalidatedByTouches = !$broken
            && (int)$lifecycle['maxTouchesBeforeInvalid'] > 0
            && $touchCount >= (int)$lifecycle['maxTouchesBeforeInvalid'];
        $breakdown = $this->strengthBreakdownAtState($zone, $fresh, $touchCount, $broken);
        $status = 'fresh';
        if ($broken) {
            $status = 'broken';
        } elseif ($invalidatedByTouches) {
            $status = 'invalidated_by_touches';
        } elseif ($weakened) {
            $status = 'weakened';
        } elseif ($touchCount > 0) {
            $status = 'touched';
        }

        $firstTouch = $touches[0] ?? null;
        return [
            ...$zone,
            'fresh' => $fresh,
            'touched' => $touchCount > 0,
            'weakened' => $weakened,
            'invalidatedByTouches' => $invalidatedByTouches,
            'active' => (int)($zone['createdIndex'] ?? PHP_INT_MAX) <= $barIndex && !$broken && !$invalidatedByTouches,
            'touchedCount' => $touchCount,
            'touches' => $touches,
            'touchedIndexes' => array_values(array_map(fn($touch) => (int)$touch['index'], $touches)),
            'firstTouchTime' => $firstTouch['time'] ?? null,
            'broken' => $broken,
            'brokenTime' => $broken ? ($zone['brokenTime'] ?? null) : null,
            'brokenByCandle' => $broken ? ($zone['brokenByCandle'] ?? null) : null,
            'brokenReason' => $broken ? (string)($zone['brokenReason'] ?? '') : '',
            'strengthScore' => $breakdown['finalScore'],
            'strengthBreakdown' => $breakdown,
            'lifecycleStatus' => $status,
        ];
    }

    public function overlaps(array $zone, array $bar): bool
    {
        return (float)($bar['low'] ?? 0) <= (float)$zone['high'] && (float)($bar['high'] ?? 0) >= (float)$zone['low'];
    }

    private function lifecycleConfig(array $config): array
    {
        $raw = is_array($config['zoneLifecycle'] ?? null) ? $config['zoneLifecycle'] : $config;
        $merged = array_replace(self::DEFAULT_LIFECYCLE, array_intersect_key($raw, self::DEFAULT_LIFECYCLE));
        return [
            'requireCloseBeyondZoneToBreak' => (bool)$merged['requireCloseBeyondZoneToBreak'],
            'allowWickThrough' => (bool)$merged['allowWickThrough'],
            'wickToleranceAtr' => max(0.0, (float)$merged['wickToleranceAtr']),
            'breakBufferAtr' => max(0.0, (float)$merged['breakBufferAtr']),
            'maxTouchesBeforeWeak' => max(0, (int)$merged['maxTouchesBeforeWeak']),
            'maxTouchesBeforeInvalid' => max(0, (int)$merged['maxTouchesBeforeInvalid']),
            'markTouchedButNotBroken' => (bool)$merged['markTouchedButNotBroken'],
        ];
    }

    private function touchesAt(array $zone, int $barIndex): array
    {
        $touches = [];
        if (is_array($zone['touches'] ?? null) && $zone['touches']) {
            foreach ($zone['touches'] as $touch) {
                if (is_array($touch) && (int)($touch['index'] ?? PHP_INT_MAX) <= $barIndex) {
                    $touches[] = $touch;
                }
            }
            return array_values($touches);
        }

        foreach ((array)($zone['touchedIndexes'] ?? []) as $index) {
            if ((int)$index <= $barIndex) {
                $touches[] = [
                    'index' => (int)$index,
                    'time' => '',
                    'timestamp' => 0,
                    'candle' => null,
                ];
            }
        }
        return array_values($touches);
    }

    private function breakDecision(array $zone, array $bar, float $medianRange, array $lifecycle): array
    {
        $atr = max(0.0000001, $medianRange);
        $breakBuffer = (float)$lifecycle['breakBufferAtr'] * $atr;
        $wickTolerance = (float)$lifecycle['wickToleranceAtr'] * $atr;
        $type = (string)($zone['type'] ?? '');
        $close = (float)($bar['close'] ?? 0);
        $high = (float)($bar['high'] ?? 0);
        $low = (float)($bar['low'] ?? 0);
        $open = (float)($bar['open'] ?? $close);
        $range = max(0.0000001, $high - $low);
        $body = abs($close - $open);
        $isDisplacement = $body >= $atr * 1.20 && $range >= $atr * 1.40;

        if ($type === 'demand') {
            $closeBeyond = $close < (float)$zone['low'] - $breakBuffer;
            $wickBeyond = $low < (float)$zone['low'] - $wickTolerance;
            if ($closeBeyond) {
                return [
                    'broken' => true,
                    'reason' => $isDisplacement ? 'DISPLACEMENT_CLOSE_BELOW_DEMAND_LOW' : 'CLOSE_BELOW_DEMAND_LOW',
                ];
            }
            if (!(bool)$lifecycle['requireCloseBeyondZoneToBreak'] && !(bool)$lifecycle['allowWickThrough'] && $wickBeyond) {
                return ['broken' => true, 'reason' => 'WICK_BELOW_DEMAND_LOW'];
            }
            return ['broken' => false, 'reason' => ''];
        }

        if ($type === 'supply') {
            $closeBeyond = $close > (float)$zone['high'] + $breakBuffer;
            $wickBeyond = $high > (float)$zone['high'] + $wickTolerance;
            if ($closeBeyond) {
                return [
                    'broken' => true,
                    'reason' => $isDisplacement ? 'DISPLACEMENT_CLOSE_ABOVE_SUPPLY_HIGH' : 'CLOSE_ABOVE_SUPPLY_HIGH',
                ];
            }
            if (!(bool)$lifecycle['requireCloseBeyondZoneToBreak'] && !(bool)$lifecycle['allowWickThrough'] && $wickBeyond) {
                return ['broken' => true, 'reason' => 'WICK_ABOVE_SUPPLY_HIGH'];
            }
        }

        return ['broken' => false, 'reason' => ''];
    }

    private function findOriginIndex(array $bars, int $eventIndex, string $direction): int
    {
        $fallback = -1;
        $fallbackRange = PHP_FLOAT_MAX;
        for ($i = $eventIndex - 1; $i >= max(0, $eventIndex - 9); $i--) {
            $open = (float)$bars[$i]['open'];
            $close = (float)$bars[$i]['close'];
            $range = abs((float)$bars[$i]['high'] - (float)$bars[$i]['low']);
            if ($range < $fallbackRange) {
                $fallback = $i;
                $fallbackRange = $range;
            }
            if ($direction === 'bullish' && $close < $open) {
                return $i;
            }
            if ($direction === 'bearish' && $close > $open) {
                return $i;
            }
        }
        return $fallback;
    }

    private function impulseStrength(array $bars, int $originIndex, int $eventIndex, string $direction, float $medianRange): float
    {
        $medianRange = max(0.0000001, $medianRange);
        $originPrice = $direction === 'bullish' ? (float)$bars[$originIndex]['low'] : (float)$bars[$originIndex]['high'];
        $eventPrice = (float)$bars[$eventIndex]['close'];
        return abs($eventPrice - $originPrice) / $medianRange;
    }

    private function baseStrengthBreakdown(array $event, float $impulseStrength, array $bars, int $originIndex, int $eventIndex): array
    {
        $bosScore = ($event['type'] ?? '') === 'breakOfStructure' ? 63.0 : 57.0;
        $impulseScore = min(24.0, $impulseStrength * 5.0);
        $trendContextScore = 0.0;
        $travelBars = max(1, $eventIndex - $originIndex);
        if ($travelBars <= 4) {
            $trendContextScore += 8.0;
        }
        $originRange = abs((float)$bars[$originIndex]['high'] - (float)$bars[$originIndex]['low']);
        $medianRange = max(0.0000001, $this->medianRange(array_slice($bars, max(0, $originIndex - 10), 12)));
        if ($originRange <= $medianRange * 1.4) {
            $trendContextScore += 5.0;
        }

        $final = $bosScore + $impulseScore + $trendContextScore;
        return [
            'bosScore' => round($bosScore, 2),
            'impulseScore' => round($impulseScore, 2),
            'freshnessScore' => 0.0,
            'touchPenalty' => 0.0,
            'brokenPenalty' => 0.0,
            'trendContextScore' => round($trendContextScore, 2),
            'finalScore' => round(max(0, min(100, $final)), 2),
        ];
    }

    private function strengthBreakdownAtState(array $zone, bool $fresh, int $touchCount, bool $broken): array
    {
        $base = is_array($zone['baseStrengthBreakdown'] ?? null)
            ? $zone['baseStrengthBreakdown']
            : (is_array($zone['strengthBreakdown'] ?? null) ? $zone['strengthBreakdown'] : []);
        $bosScore = (float)($base['bosScore'] ?? 63);
        $impulseScore = (float)($base['impulseScore'] ?? 0);
        $trendContextScore = (float)($base['trendContextScore'] ?? 0);
        $freshnessScore = $fresh ? 8.0 : 0.0;
        $touchPenalty = min(34.0, $touchCount * 7.0);
        $brokenPenalty = $broken ? 70.0 : 0.0;
        $final = $bosScore + $impulseScore + $freshnessScore + $trendContextScore - $touchPenalty - $brokenPenalty;
        if ($broken) {
            $final = min($final, 25.0);
        }

        return [
            'bosScore' => round($bosScore, 2),
            'impulseScore' => round($impulseScore, 2),
            'freshnessScore' => round($freshnessScore, 2),
            'touchPenalty' => round($touchPenalty, 2),
            'brokenPenalty' => round($brokenPenalty, 2),
            'trendContextScore' => round($trendContextScore, 2),
            'finalScore' => round(max(0, min(100, $final)), 2),
        ];
    }

    private function reason(string $type, array $event, float $impulseStrength): string
    {
        $direction = $type === 'demand' ? 'bullish' : 'bearish';
        return strtoupper($type) . " zone before {$direction} " . ($event['type'] ?? 'structure break') . ', impulse ' . round($impulseStrength, 2) . 'x median range.';
    }

    private function zoneId(string $symbol, string $timeframe, string $type, int $originIndex, int $eventIndex): string
    {
        return 'zone_' . substr(sha1(strtoupper($symbol) . '_' . strtoupper($timeframe) . '_' . $type . '_' . $originIndex . '_' . $eventIndex), 0, 12);
    }

    private function candleSnapshots(array $bars, int $offset): array
    {
        $snapshots = [];
        foreach ($bars as $index => $bar) {
            if (is_array($bar)) {
                $snapshots[] = $this->candleSnapshot($bar, $offset + $index);
            }
        }
        return $snapshots;
    }

    private function candleSnapshot(array $bar, int $index): array
    {
        return [
            'index' => $index,
            'sourceIndex' => (int)($bar['_source_index'] ?? $index),
            'time' => $this->barIso($bar),
            'timestamp' => $this->barTime($bar),
            'open' => (float)($bar['open'] ?? 0),
            'high' => (float)($bar['high'] ?? 0),
            'low' => (float)($bar['low'] ?? 0),
            'close' => (float)($bar['close'] ?? 0),
        ];
    }

    private function medianRange(array $bars): float
    {
        $ranges = [];
        foreach ($bars as $bar) {
            if (!is_array($bar)) {
                continue;
            }
            $range = abs((float)($bar['high'] ?? 0) - (float)($bar['low'] ?? 0));
            if ($range > 0) {
                $ranges[] = $range;
            }
        }
        if (!$ranges) {
            return 0.0;
        }
        sort($ranges);
        $count = count($ranges);
        $middle = intdiv($count, 2);
        return $count % 2 === 1 ? (float)$ranges[$middle] : ((float)$ranges[$middle - 1] + (float)$ranges[$middle]) / 2;
    }

    private function barTime(array $bar): int
    {
        if (isset($bar['time']) && is_numeric($bar['time'])) {
            return (int)$bar['time'];
        }
        $time = strtotime((string)($bar['time_iso'] ?? ''));
        return $time === false ? 0 : $time;
    }

    private function barIso(array $bar): string
    {
        $time = $this->barTime($bar);
        return (string)($bar['time_iso'] ?? ($time > 0 ? gmdate('c', $time) : ''));
    }
}
