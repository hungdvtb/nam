<?php

declare(strict_types=1);

final class M15EntryConfirmation
{
    public function evaluate(array $bars, int $index, array $zone, array $m15Structure, array $rules, string $symbol): array
    {
        $details = [
            'priceReturnedToZone' => false,
            'm15Reaction' => false,
            'm15StructureBreak' => false,
            'qmlDetected' => false,
            'priceActionPatterns' => [],
            'selectedPriceActionPattern' => '',
            'dirtySideway' => false,
            'rr' => null,
            'proposedEntry' => null,
            'proposedSL' => null,
            'proposedTP' => null,
        ];
        if (!isset($bars[$index])) {
            return $this->reject('DATA_MISSING', 'M15 candle is missing for setup confirmation.', [], $details);
        }

        $direction = ($zone['type'] ?? '') === 'demand' ? 'BUY' : 'SELL';
        $bar = $bars[$index];
        if ((float)($zone['high'] ?? 0) <= (float)($zone['low'] ?? 0)) {
            return $this->reject('BAD_RR', 'H4 zone high/low geometry is invalid.', ['BAD_RR'], $details);
        }
        $details['priceReturnedToZone'] = $this->priceTouchedZone($bar, $zone);
        if (!$details['priceReturnedToZone']) {
            return $this->reject('NO_H4_ZONE', 'Price has not returned to the selected H4 zone.', ['NO_H4_ZONE'], $details);
        }

        $details['dirtySideway'] = $this->isDirtySideway(
            $bars,
            $index,
            (int)($rules['m15Rules']['sidewayLookback'] ?? 20),
            (float)($rules['m15Rules']['sidewayMaxRangeAtr'] ?? 0)
        );
        if (($rules['m15Rules']['avoidDirtySideway'] ?? true) && $details['dirtySideway']) {
            return $this->reject('DIRTY_SIDEWAY', 'M15 range is compressed and overlapping around the zone.', ['DIRTY_SIDEWAY'], $details);
        }

        $minWickRatio = (float)($rules['m15Rules']['minRejectionWickRatio'] ?? 0.25);
        $reaction = $this->reactionAtZone($bars, $index, $zone, $direction, $minWickRatio);
        $details['m15Reaction'] = $reaction;

        $structureBreak = $this->hasStructureBreak($bars, $index, $m15Structure, $direction);
        $details['m15StructureBreak'] = $structureBreak;

        $fakeBreak = $this->fakeBreakOrQml($bars, $index, $zone, $direction);
        $details['qmlDetected'] = $fakeBreak;

        $patterns = $this->priceActionPatterns($bars, $index, $zone, $direction, $rules, $reaction, $structureBreak, $fakeBreak);
        $details['priceActionPatterns'] = $patterns;
        $details['selectedPriceActionPattern'] = $patterns[0] ?? '';
        $paCanConfirmReaction = (bool)($rules['m15Rules']['requirePriceActionPattern'] ?? false)
            && array_intersect($patterns, ['engulfing', 'liquidity_sweep']);

        if (($rules['m15Rules']['requireReactionAtZone'] ?? true) && !$reaction && !$paCanConfirmReaction) {
            return $this->reject('NO_M15_CONFIRMATION', 'No clear M15 rejection/reaction candle at the H4 zone.', ['NO_M15_REACTION'], $details);
        }

        if (($rules['m15Rules']['requireStructureBreak'] ?? true) && !$structureBreak) {
            return $this->reject('NO_M15_CONFIRMATION', 'M15 has not broken local structure in trade direction.', ['NO_M15_BOS'], $details);
        }

        if (($rules['m15Rules']['requireQmlOrFakeBreak'] ?? false) && !$fakeBreak) {
            return $this->reject('NO_M15_CONFIRMATION', 'No basic fake-break/QML pattern found at the zone.', ['NO_QML_FAKE_BREAK'], $details);
        }

        if (($rules['m15Rules']['requirePriceActionPattern'] ?? false) && $patterns === []) {
            return $this->reject('NO_M15_CONFIRMATION', 'No allowed M15 Price Action reversal pattern found at the H4 zone.', ['NO_M15_PRICE_ACTION'], $details);
        }

        $entry = $this->entryPrice($zone, $direction, (string)($rules['entryRules']['entryAt'] ?? 'zone_50_percent'));
        $stop = $this->stopLoss($bars, $index, $zone, $direction, $symbol, (float)($rules['entryRules']['stopBufferPoints'] ?? 2));
        $details['proposedEntry'] = $entry;
        $details['proposedSL'] = $stop;
        $risk = $direction === 'BUY' ? $entry - $stop : $stop - $entry;
        if ($risk <= $this->pointSize($symbol)) {
            return $this->reject('BAD_RR', 'Stop distance is invalid for this zone and entry.', ['BAD_RR'], $details);
        }

        $rr = max((float)($rules['minRR'] ?? 3), (float)($rules['entryRules']['minRR'] ?? 3), (float)($rules['entryRules']['minDistanceToTPR'] ?? 3));
        $takeProfit = $direction === 'BUY' ? $entry + $risk * $rr : $entry - $risk * $rr;
        $details['rr'] = $rr;
        $details['proposedTP'] = $takeProfit;
        if (!$this->rrIsValid($entry, $stop, $takeProfit, $direction, (float)($rules['minRR'] ?? 3))) {
            return $this->reject('BAD_RR', 'Minimum RR is not available from entry to target.', ['BAD_RR'], $details);
        }

        $tags = [
            $direction === 'BUY' ? 'DEMAND_LIMIT' : 'SUPPLY_LIMIT',
            'H4_ZONE',
            'M15_REACTION',
        ];
        if ($structureBreak) {
            $tags[] = 'M15_BREAK_STRUCTURE';
        }
        if ($fakeBreak) {
            $tags[] = 'QML_OR_FAKE_BREAK';
        }
        foreach ($patterns as $pattern) {
            $tags[] = $this->patternTag($pattern);
        }
        $patternText = $patterns !== [] ? implode(', ', array_map([$this, 'patternLabel'], $patterns)) : 'reaction/structure';

        return [
            'ok' => true,
            'reasonCode' => 'SETUP_VALID',
            'reason' => 'H4 ' . $zone['type'] . ' zone touched; M15 Price Action confirmed: ' . $patternText . '.',
            'direction' => $direction,
            'entryPrice' => $entry,
            'stopLoss' => $stop,
            'takeProfit' => $takeProfit,
            'riskDistance' => $risk,
            'rr' => $rr,
            'setupType' => $patterns !== [] ? 'h4_zone_m15_pa_' . $patterns[0] : ($fakeBreak ? 'qml_or_fake_break' : 'h4_zone_m15_bos'),
            'tags' => array_values(array_unique($tags)),
            'details' => $details,
        ];
    }

    private function reject(string $code, string $reason, array $tags, array $details): array
    {
        return [
            'ok' => false,
            'reasonCode' => $code,
            'reason' => $reason,
            'tags' => array_values(array_unique(array_merge([$code], $tags))),
            'details' => $details,
        ];
    }

    private function priceTouchedZone(array $bar, array $zone): bool
    {
        return (float)($bar['low'] ?? 0) <= (float)$zone['high'] && (float)($bar['high'] ?? 0) >= (float)$zone['low'];
    }

    private function reactionAtZone(array $bars, int $index, array $zone, string $direction, float $minWickRatio = 0.25): bool
    {
        $start = max(0, $index - 2);
        $touched = false;
        for ($i = $start; $i <= $index; $i++) {
            if ($this->priceTouchedZone($bars[$i], $zone)) {
                $touched = true;
                break;
            }
        }
        if (!$touched) {
            return false;
        }

        $bar = $bars[$index];
        $open = (float)$bar['open'];
        $close = (float)$bar['close'];
        $high = (float)$bar['high'];
        $low = (float)$bar['low'];
        $range = max(0.0000001, $high - $low);
        $upperWick = $high - max($open, $close);
        $lowerWick = min($open, $close) - $low;
        $zoneMid = ((float)$zone['high'] + (float)$zone['low']) / 2;
        $minRatio = max(0.05, min(0.9, $minWickRatio));

        if ($direction === 'BUY') {
            return $close > $open && $close >= $zoneMid && $lowerWick >= $range * $minRatio;
        }
        return $close < $open && $close <= $zoneMid && $upperWick >= $range * $minRatio;
    }

    private function hasStructureBreak(array $bars, int $index, array $structure, string $direction): bool
    {
        $eventDirection = $direction === 'BUY' ? 'bullish' : 'bearish';
        foreach (array_reverse($structure['events'] ?? []) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $eventIndex = (int)($event['index'] ?? -1);
            if ($eventIndex <= $index && $eventIndex >= $index - 8 && ($event['direction'] ?? '') === $eventDirection) {
                return true;
            }
        }

        $lookbackStart = max(0, $index - 8);
        $prior = array_slice($bars, $lookbackStart, max(0, $index - $lookbackStart));
        if (!$prior) {
            return false;
        }
        $close = (float)$bars[$index]['close'];
        if ($direction === 'BUY') {
            $priorHigh = max(array_map(fn($bar) => (float)$bar['high'], $prior));
            return $close > $priorHigh;
        }
        $priorLow = min(array_map(fn($bar) => (float)$bar['low'], $prior));
        return $close < $priorLow;
    }

    private function fakeBreakOrQml(array $bars, int $index, array $zone, string $direction): bool
    {
        if ($index < 5) {
            return false;
        }
        $slice = array_slice($bars, max(0, $index - 5), 6);
        $last = $bars[$index];
        if ($direction === 'BUY') {
            $lowestBefore = min(array_map(fn($bar) => (float)$bar['low'], array_slice($slice, 0, -1)));
            return (float)$last['low'] < min($lowestBefore, (float)$zone['low'])
                && (float)$last['close'] > (float)$zone['low'];
        }
        $highestBefore = max(array_map(fn($bar) => (float)$bar['high'], array_slice($slice, 0, -1)));
        return (float)$last['high'] > max($highestBefore, (float)$zone['high'])
            && (float)$last['close'] < (float)$zone['high'];
    }

    private function priceActionPatterns(array $bars, int $index, array $zone, string $direction, array $rules, bool $reaction, bool $structureBreak, bool $fakeBreak): array
    {
        $allowed = is_array($rules['m15Rules']['allowedPriceActionPatterns'] ?? null)
            ? array_fill_keys(array_map('strval', $rules['m15Rules']['allowedPriceActionPatterns']), true)
            : ['rejection_wick' => true, 'engulfing' => true, 'liquidity_sweep' => true, 'choch' => true];
        $patterns = [];
        if ($reaction && isset($allowed['rejection_wick'])) {
            $patterns[] = 'rejection_wick';
        }
        if (isset($allowed['engulfing']) && $this->engulfingPattern($bars, $index, $direction, (bool)($rules['m15Rules']['engulfingRequiresBodyClose'] ?? true))) {
            $patterns[] = 'engulfing';
        }
        if ($fakeBreak && isset($allowed['liquidity_sweep'])) {
            $patterns[] = 'liquidity_sweep';
        }
        if ($structureBreak && isset($allowed['choch'])) {
            $patterns[] = 'choch';
        }

        return array_values(array_unique($patterns));
    }

    private function engulfingPattern(array $bars, int $index, string $direction, bool $requireBodyClose): bool
    {
        if ($index < 1 || !isset($bars[$index - 1])) {
            return false;
        }
        $prev = $bars[$index - 1];
        $bar = $bars[$index];
        $prevOpen = (float)($prev['open'] ?? 0);
        $prevClose = (float)($prev['close'] ?? 0);
        $open = (float)($bar['open'] ?? 0);
        $close = (float)($bar['close'] ?? 0);
        $body = abs($close - $open);
        $prevBody = abs($prevClose - $prevOpen);
        if ($body <= 0 || $prevBody <= 0) {
            return false;
        }
        if ($direction === 'BUY') {
            $engulfs = $close > $open && $prevClose < $prevOpen && $open <= $prevClose && $close >= $prevOpen;
            return $engulfs && (!$requireBodyClose || $close > max($prevOpen, $prevClose));
        }
        $engulfs = $close < $open && $prevClose > $prevOpen && $open >= $prevClose && $close <= $prevOpen;
        return $engulfs && (!$requireBodyClose || $close < min($prevOpen, $prevClose));
    }

    private function patternTag(string $pattern): string
    {
        return match ($pattern) {
            'engulfing' => 'PA_ENGULFING',
            'liquidity_sweep' => 'PA_LIQUIDITY_SWEEP',
            'choch' => 'PA_CHOCH',
            default => 'PA_REJECTION_WICK',
        };
    }

    private function patternLabel(string $pattern): string
    {
        return match ($pattern) {
            'engulfing' => 'engulfing reversal',
            'liquidity_sweep' => 'liquidity sweep / false break',
            'choch' => 'M15 CHoCH/BOS',
            default => 'rejection wick',
        };
    }

    private function entryPrice(array $zone, string $direction, string $mode): float
    {
        $high = (float)$zone['high'];
        $low = (float)$zone['low'];
        $mid = ($high + $low) / 2;
        if ($mode === 'zone_extreme') {
            return $direction === 'BUY' ? $low : $high;
        }
        if ($mode === 'zone_open' && isset($zone['originOpen']) && is_numeric($zone['originOpen'])) {
            return max($low, min($high, (float)$zone['originOpen']));
        }
        return $mid;
    }

    private function stopLoss(array $bars, int $index, array $zone, string $direction, string $symbol, float $bufferPoints): float
    {
        $point = $this->pointSize($symbol);
        $buffer = max($point, $bufferPoints * $point);
        $swing = $this->nearestSwingInvalidation($bars, $index, $direction);
        if ($direction === 'BUY') {
            return min((float)$zone['low'], $swing ?? (float)$zone['low']) - $buffer;
        }
        return max((float)$zone['high'], $swing ?? (float)$zone['high']) + $buffer;
    }

    private function nearestSwingInvalidation(array $bars, int $index, string $direction): ?float
    {
        if ($index < 3) {
            return null;
        }
        $slice = array_slice($bars, max(0, $index - 8), 8);
        if ($direction === 'BUY') {
            $lows = array_map(fn($bar) => (float)$bar['low'], $slice);
            return $lows ? min($lows) : null;
        }
        $highs = array_map(fn($bar) => (float)$bar['high'], $slice);
        return $highs ? max($highs) : null;
    }

    private function rrIsValid(float $entry, float $stop, float $target, string $direction, float $minRR): bool
    {
        $risk = $direction === 'BUY' ? $entry - $stop : $stop - $entry;
        $reward = $direction === 'BUY' ? $target - $entry : $entry - $target;
        if ($risk <= 0 || $reward <= 0) {
            return false;
        }
        return ($reward / $risk) + 0.000001 >= $minRR;
    }

    private function isDirtySideway(array $bars, int $index, int $lookback, float $maxRangeAtr): bool
    {
        $lookback = max(5, $lookback);
        if ($index < $lookback) {
            return false;
        }
        $slice = array_slice($bars, $index - $lookback, $lookback);
        $ranges = array_map(fn($bar) => abs((float)$bar['high'] - (float)$bar['low']), $slice);
        $medianRange = $this->median(array_filter($ranges, fn($value) => $value > 0));
        if ($medianRange <= 0) {
            return true;
        }
        $high = max(array_map(fn($bar) => (float)$bar['high'], $slice));
        $low = min(array_map(fn($bar) => (float)$bar['low'], $slice));
        $close = max(0.0000001, abs((float)$bars[$index]['close']));
        $totalRange = $high - $low;
        $compressed = ($totalRange / $close) < 0.0018;
        if ($maxRangeAtr > 0) {
            $compressed = $compressed || $totalRange <= $medianRange * $maxRangeAtr;
        }
        $overlapCount = 0;
        for ($i = 1; $i < count($slice); $i++) {
            if ((float)$slice[$i]['low'] <= (float)$slice[$i - 1]['high'] && (float)$slice[$i]['high'] >= (float)$slice[$i - 1]['low']) {
                $overlapCount++;
            }
        }
        return $compressed && $overlapCount >= 14;
    }

    private function median(array $values): float
    {
        if (!$values) {
            return 0.0;
        }
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);
        return $count % 2 === 1 ? (float)$values[$middle] : ((float)$values[$middle - 1] + (float)$values[$middle]) / 2;
    }

    private function pointSize(string $symbol): float
    {
        return str_contains(strtoupper($symbol), 'JPY') ? 0.001 : 0.00001;
    }
}
