<?php

declare(strict_types=1);

final class MarketStructureDetector
{
    public function analyze(array $bars, array $config = []): array
    {
        $period = max(1, (int)($config['fractalPeriod'] ?? $config['lookback'] ?? 2));
        $breakBuffer = max(0.0, (float)($config['breakBuffer'] ?? 0.0));
        $swingsByConfirm = $this->candidateSwings($bars, $period);
        $swings = [];
        $events = [];
        $states = [];
        $lastHigh = null;
        $lastLow = null;
        $lastBrokenHighIndex = null;
        $lastBrokenLowIndex = null;
        $trend = 'sideway';

        for ($i = 0; $i < count($bars); $i++) {
            foreach ($swingsByConfirm[$i] ?? [] as $swing) {
                if ($swing['type'] === 'swingHigh') {
                    $swing['structure'] = $lastHigh === null
                        ? 'swingHigh'
                        : (((float)$swing['price'] > (float)$lastHigh['price']) ? 'higherHigh' : 'lowerHigh');
                    $lastHigh = $swing;
                } else {
                    $swing['structure'] = $lastLow === null
                        ? 'swingLow'
                        : (((float)$swing['price'] > (float)$lastLow['price']) ? 'higherLow' : 'lowerLow');
                    $lastLow = $swing;
                }
                $swings[] = $swing;
            }

            $close = (float)($bars[$i]['close'] ?? 0);
            if ($lastHigh !== null && $lastBrokenHighIndex !== $lastHigh['index'] && $close > (float)$lastHigh['price'] + $breakBuffer) {
                $eventType = $trend === 'bearish' ? 'changeOfCharacter' : 'breakOfStructure';
                $trend = 'bullish';
                $lastBrokenHighIndex = (int)$lastHigh['index'];
                $events[] = $this->event($bars, $i, 'bullish', $eventType, $lastHigh);
            }
            if ($lastLow !== null && $lastBrokenLowIndex !== $lastLow['index'] && $close < (float)$lastLow['price'] - $breakBuffer) {
                $eventType = $trend === 'bullish' ? 'changeOfCharacter' : 'breakOfStructure';
                $trend = 'bearish';
                $lastBrokenLowIndex = (int)$lastLow['index'];
                $events[] = $this->event($bars, $i, 'bearish', $eventType, $lastLow);
            }

            $states[$i] = [
                'index' => $i,
                'time' => $this->barIso($bars[$i] ?? []),
                'timestamp' => $this->barTime($bars[$i] ?? []),
                'trend' => $this->dirtySideway($bars, $i) ? 'sideway' : $trend,
                'rawTrend' => $trend,
                'lastSwingHigh' => $lastHigh,
                'lastSwingLow' => $lastLow,
            ];
        }

        return [
            'period' => $period,
            'swings' => $swings,
            'events' => $events,
            'states' => $states,
            'trend' => $states ? (string)$states[array_key_last($states)]['trend'] : 'sideway',
        ];
    }

    public function stateAt(array $analysis, int $index): ?array
    {
        $states = $analysis['states'] ?? [];
        if (!is_array($states) || !$states) {
            return null;
        }
        $index = max(0, min($index, count($states) - 1));
        return is_array($states[$index] ?? null) ? $states[$index] : null;
    }

    public function latestEventAt(array $analysis, int $index, ?string $direction = null): ?array
    {
        $selected = null;
        foreach (($analysis['events'] ?? []) as $event) {
            if (!is_array($event) || (int)($event['index'] ?? PHP_INT_MAX) > $index) {
                continue;
            }
            if ($direction !== null && ($event['direction'] ?? '') !== $direction) {
                continue;
            }
            $selected = $event;
        }
        return $selected;
    }

    private function candidateSwings(array $bars, int $period): array
    {
        $byConfirm = [];
        $count = count($bars);
        for ($i = $period; $i < $count - $period; $i++) {
            $high = (float)($bars[$i]['high'] ?? 0);
            $low = (float)($bars[$i]['low'] ?? 0);
            $isHigh = true;
            $isLow = true;
            for ($j = $i - $period; $j <= $i + $period; $j++) {
                if ($j === $i) {
                    continue;
                }
                if ((float)($bars[$j]['high'] ?? 0) >= $high) {
                    $isHigh = false;
                }
                if ((float)($bars[$j]['low'] ?? 0) <= $low) {
                    $isLow = false;
                }
            }
            $confirmedAt = $i + $period;
            if ($isHigh) {
                $byConfirm[$confirmedAt][] = $this->swing($bars, $i, $confirmedAt, 'swingHigh', $high);
            }
            if ($isLow) {
                $byConfirm[$confirmedAt][] = $this->swing($bars, $i, $confirmedAt, 'swingLow', $low);
            }
        }
        ksort($byConfirm);
        return $byConfirm;
    }

    private function swing(array $bars, int $index, int $confirmedAt, string $type, float $price): array
    {
        return [
            'index' => $index,
            'sourceIndex' => (int)($bars[$index]['_source_index'] ?? $index),
            'confirmedAt' => $confirmedAt,
            'confirmedSourceIndex' => (int)($bars[$confirmedAt]['_source_index'] ?? $confirmedAt),
            'time' => $this->barIso($bars[$index] ?? []),
            'timestamp' => $this->barTime($bars[$index] ?? []),
            'type' => $type,
            'price' => $price,
            'structure' => $type,
        ];
    }

    private function event(array $bars, int $index, string $direction, string $eventType, array $brokenSwing): array
    {
        return [
            'index' => $index,
            'sourceIndex' => (int)($bars[$index]['_source_index'] ?? $index),
            'time' => $this->barIso($bars[$index] ?? []),
            'timestamp' => $this->barTime($bars[$index] ?? []),
            'type' => $eventType,
            'direction' => $direction,
            'brokenSwingIndex' => (int)($brokenSwing['index'] ?? -1),
            'brokenSwingType' => (string)($brokenSwing['type'] ?? ''),
            'brokenSwingPrice' => (float)($brokenSwing['price'] ?? 0),
        ];
    }

    private function dirtySideway(array $bars, int $index): bool
    {
        if ($index < 18) {
            return false;
        }
        $slice = array_slice($bars, $index - 18, 18);
        $ranges = array_map(fn($bar) => abs((float)$bar['high'] - (float)$bar['low']), $slice);
        $medianRange = $this->median(array_filter($ranges, fn($value) => $value > 0));
        if ($medianRange <= 0) {
            return true;
        }
        $high = max(array_map(fn($bar) => (float)$bar['high'], $slice));
        $low = min(array_map(fn($bar) => (float)$bar['low'], $slice));
        return ($high - $low) < $medianRange * 3.2;
    }

    private function median(array $values): float
    {
        if (!$values) {
            return 0.0;
        }
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float)$values[$middle];
        }
        return ((float)$values[$middle - 1] + (float)$values[$middle]) / 2;
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
