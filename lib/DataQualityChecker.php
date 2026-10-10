<?php

declare(strict_types=1);

final class DataQualityChecker
{
    private const TIMEFRAME_SECONDS = [
        'M1' => 60,
        'M5' => 300,
        'M15' => 900,
        'M30' => 1800,
        'H1' => 3600,
        'H4' => 14400,
        'D1' => 86400,
    ];

    public function __construct(private JsonStore $store)
    {
    }

    public function analyzeMarketFiles(array $marketFiles): array
    {
        $reports = [];
        foreach ($marketFiles as $file) {
            if (!is_array($file)) {
                continue;
            }
            $path = (string)($file['file'] ?? '');
            if (!str_starts_with($path, 'market_data/')) {
                continue;
            }
            $payload = $this->store->readJson($path, null);
            if (!is_array($payload)) {
                $reports[] = $this->emptyReport($file, 'Không đọc được tệp dữ liệu thị trường.');
                continue;
            }
            $reports[] = $this->analyzePayload($payload, $path);
        }

        usort($reports, fn($a, $b) => [$a['symbol'], $a['timeframe']] <=> [$b['symbol'], $b['timeframe']]);
        return $reports;
    }

    public function analyzePayload(array $payload, string $file = ''): array
    {
        $symbol = strtoupper((string)($payload['symbol'] ?? 'UNKNOWN'));
        $timeframe = strtoupper((string)($payload['timeframe'] ?? ''));
        $expected = self::TIMEFRAME_SECONDS[$timeframe] ?? null;
        $bars = array_values(array_filter($payload['bars'] ?? [], 'is_array'));
        usort($bars, fn($a, $b) => $this->barTime($a) <=> $this->barTime($b));

        $report = [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'file' => $file,
            'source' => (string)($payload['source'] ?? ''),
            'fetchedAt' => (string)($payload['fetched_at'] ?? ''),
            'timezone' => $this->detectTimezone($bars),
            'startDate' => null,
            'endDate' => null,
            'totalCandles' => count($bars),
            'missingCandles' => 0,
            'duplicateTimestamps' => 0,
            'abnormalGapCount' => 0,
            'invalidOhlcCount' => 0,
            'nullVolumeCount' => 0,
            'timeframeMismatchCount' => 0,
            'timeframeMatches' => true,
            'largestGaps' => [],
            'splits' => [],
            'status' => 'ok',
            'issues' => [],
        ];

        if (!$bars) {
            $report['status'] = 'error';
            $report['issues'][] = 'Tệp không có dữ liệu nến.';
            return $report;
        }

        $firstTime = $this->barTime($bars[0]);
        $lastTime = $this->barTime($bars[count($bars) - 1]);
        $report['startDate'] = $this->iso($firstTime);
        $report['endDate'] = $this->iso($lastTime);
        $report['splits'] = $this->splitRanges($bars);

        $seen = [];
        $gapRows = [];
        $previousTime = null;
        foreach ($bars as $index => $bar) {
            $time = $this->barTime($bar);
            if ($time <= 0) {
                $report['issues'][] = 'Mốc thời gian không hợp lệ ở dòng ' . $index . '.';
            }

            if (isset($seen[$time])) {
                $report['duplicateTimestamps']++;
            }
            $seen[$time] = true;

            if (!$this->validOhlc($bar)) {
                $report['invalidOhlcCount']++;
            }
            if (!$this->hasVolume($bar)) {
                $report['nullVolumeCount']++;
            }

            if ($previousTime !== null && $expected !== null) {
                $diff = $time - $previousTime;
                if ($diff !== $expected) {
                    $report['timeframeMismatchCount']++;
                }
                if ($diff > (int)round($expected * 1.5)) {
                    $missing = max(0, (int)floor($diff / $expected) - 1);
                    $report['missingCandles'] += $missing;
                    $report['abnormalGapCount']++;
                    $gapRows[] = [
                        'from' => $this->iso($previousTime),
                        'to' => $this->iso($time),
                        'seconds' => $diff,
                        'missingCandles' => $missing,
                    ];
                }
            }
            $previousTime = $time;
        }

        usort($gapRows, fn($a, $b) => $b['seconds'] <=> $a['seconds']);
        $report['largestGaps'] = array_slice($gapRows, 0, 8);

        $mismatchRatio = count($bars) > 1 ? $report['timeframeMismatchCount'] / (count($bars) - 1) : 0;
        $report['timeframeMatches'] = $expected !== null && $mismatchRatio <= 0.05;

        if ($expected === null) {
            $report['issues'][] = 'Khung thời gian chưa được hỗ trợ.';
        }
        if ($report['duplicateTimestamps'] > 0) {
            $report['issues'][] = 'Duplicate timestamps found.';
        }
        if ($report['invalidOhlcCount'] > 0) {
            $report['issues'][] = 'Phát hiện dòng OHLC không hợp lệ.';
        }
        if ($report['nullVolumeCount'] > 0) {
            $report['issues'][] = 'Null volume rows found.';
        }
        if (!$report['timeframeMatches']) {
            $report['issues'][] = 'Candle spacing does not match timeframe consistently.';
        }

        $report['status'] = !$report['issues'] ? 'ok' : ($report['invalidOhlcCount'] || $report['duplicateTimestamps'] ? 'error' : 'warning');
        return $report;
    }

    public function splitRangesForPayload(array $payload): array
    {
        $bars = array_values(array_filter($payload['bars'] ?? [], 'is_array'));
        usort($bars, fn($a, $b) => $this->barTime($a) <=> $this->barTime($b));
        return $this->splitRanges($bars);
    }

    private function emptyReport(array $file, string $issue): array
    {
        return [
            'symbol' => (string)($file['symbol'] ?? 'UNKNOWN'),
            'timeframe' => (string)($file['timeframe'] ?? ''),
            'file' => (string)($file['file'] ?? ''),
            'source' => '',
            'fetchedAt' => '',
            'timezone' => '',
            'startDate' => null,
            'endDate' => null,
            'totalCandles' => 0,
            'missingCandles' => 0,
            'duplicateTimestamps' => 0,
            'abnormalGapCount' => 0,
            'invalidOhlcCount' => 0,
            'nullVolumeCount' => 0,
            'timeframeMismatchCount' => 0,
            'timeframeMatches' => false,
            'largestGaps' => [],
            'splits' => [],
            'status' => 'error',
            'issues' => [$issue],
        ];
    }

    private function validOhlc(array $bar): bool
    {
        $open = $this->number($bar['open'] ?? null);
        $high = $this->number($bar['high'] ?? null);
        $low = $this->number($bar['low'] ?? null);
        $close = $this->number($bar['close'] ?? null);
        if ($open === null || $high === null || $low === null || $close === null) {
            return false;
        }
        return $high >= max($open, $close) && $low <= min($open, $close) && $high >= $low;
    }

    private function hasVolume(array $bar): bool
    {
        return array_key_exists('tick_volume', $bar) && $bar['tick_volume'] !== null
            || array_key_exists('real_volume', $bar) && $bar['real_volume'] !== null;
    }

    private function detectTimezone(array $bars): string
    {
        foreach ($bars as $bar) {
            $iso = (string)($bar['time_iso'] ?? '');
            if (preg_match('/(Z|[+-]\d{2}:\d{2})$/', $iso, $match)) {
                return $match[1] === 'Z' ? 'UTC' : 'UTC' . $match[1];
            }
        }
        return 'unknown';
    }

    private function splitRanges(array $bars): array
    {
        $count = count($bars);
        if ($count === 0) {
            return [
                'train' => ['startDate' => null, 'endDate' => null, 'totalCandles' => 0],
                'validation' => ['startDate' => null, 'endDate' => null, 'totalCandles' => 0],
                'final' => ['startDate' => null, 'endDate' => null, 'totalCandles' => 0],
            ];
        }

        $trainEnd = max(0, (int)floor($count * 0.70) - 1);
        $validationEnd = max($trainEnd + 1, (int)floor($count * 0.85) - 1);
        $ranges = [
            'train' => [0, $trainEnd],
            'validation' => [$trainEnd + 1, $validationEnd],
            'final' => [$validationEnd + 1, $count - 1],
        ];

        $result = [];
        foreach ($ranges as $name => [$start, $end]) {
            if ($start > $end || !isset($bars[$start], $bars[$end])) {
                $result[$name] = ['startDate' => null, 'endDate' => null, 'totalCandles' => 0];
                continue;
            }

            $result[$name] = [
                'startDate' => $this->iso($this->barTime($bars[$start])),
                'endDate' => $this->iso($this->barTime($bars[$end])),
                'totalCandles' => $end - $start + 1,
            ];
        }

        return $result;
    }

    private function number(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float)$value;
        return is_finite($number) ? $number : null;
    }

    private function barTime(array $bar): int
    {
        if (isset($bar['time']) && is_numeric($bar['time'])) {
            return (int)$bar['time'];
        }
        $iso = (string)($bar['time_iso'] ?? '');
        $time = $iso !== '' ? strtotime($iso) : false;
        return $time === false ? 0 : $time;
    }

    private function iso(int $timestamp): ?string
    {
        return $timestamp > 0 ? gmdate('c', $timestamp) : null;
    }
}
