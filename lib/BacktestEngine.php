<?php

declare(strict_types=1);

require_once __DIR__ . '/StrategyRuleParser.php';
require_once __DIR__ . '/MarketStructureDetector.php';
require_once __DIR__ . '/SupplyDemandZoneDetector.php';
require_once __DIR__ . '/M15EntryConfirmation.php';
require_once __DIR__ . '/Backtests/BacktestV2StorageAdapter.php';

final class BacktestEngine
{
    private const RUNS_FILE = 'backtests/backtest_runs.json';
    private const TRADES_FILE = 'backtests/backtest_trades.json';
    private const TIMEFRAME_SECONDS = [
        'M1' => 60,
        'M5' => 300,
        'M15' => 900,
        'M30' => 1800,
        'H1' => 3600,
        'H4' => 14400,
        'D1' => 86400,
    ];

    public function __construct(private ?JsonStore $store = null, private ?BacktestV2StorageAdapter $v2Storage = null)
    {
    }

    public function listRunsWithTrades(int $limit = 20): array
    {
        if ($this->store === null) {
            return [];
        }

        $runs = $this->store->readJson(self::RUNS_FILE, []);
        $trades = $this->store->readJson(self::TRADES_FILE, []);
        if (!is_array($runs)) {
            $runs = [];
        }
        if (!is_array($trades)) {
            $trades = [];
        }

        usort($runs, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        $runs = array_slice(array_values(array_filter($runs, 'is_array')), 0, $limit);
        $tradeMap = [];
        foreach ($trades as $trade) {
            if (!is_array($trade)) {
                continue;
            }
            $runId = (string)($trade['backtestRunId'] ?? '');
            if ($runId !== '') {
                $tradeMap[$runId][] = $trade;
            }
        }

        foreach ($runs as &$run) {
            $run['trades'] = $tradeMap[$run['backtestRunId'] ?? $run['id'] ?? ''] ?? [];
        }
        unset($run);
        return $runs;
    }

    public function latestRunForVersion(string $versionId, string $rangeType = ''): ?array
    {
        foreach ($this->listRunsWithTrades(200) as $run) {
            if (($run['strategyVersionId'] ?? '') !== $versionId) {
                continue;
            }
            if ($rangeType !== '' && ($run['rangeType'] ?? '') !== $rangeType) {
                continue;
            }
            return $run;
        }
        return null;
    }

    public function run(array $strategyVersion, array $input): array
    {
        if ($this->store === null) {
            throw new RuntimeException('Bộ kiểm thử cần kho JSON khi tải dữ liệu thị trường.');
        }

        $rules = is_array($strategyVersion['rulesJson'] ?? null) ? $strategyVersion['rulesJson'] : [];
        $symbol = strtoupper(trim((string)($input['symbol'] ?? ($rules['symbols'][0] ?? ''))));
        if ($symbol === '') {
            throw new InvalidArgumentException('Bạn chưa chọn cặp tiền để kiểm thử.');
        }

        $timeframe = strtoupper(trim((string)($input['timeframe'] ?? ($rules['entryTimeframe'] ?? 'M15'))));
        $contextTimeframe = strtoupper(trim((string)($input['contextTimeframe'] ?? ($rules['contextTimeframe'] ?? 'H4'))));
        $mainPayload = $this->loadMarketPayload($symbol, $timeframe);
        $contextPayload = $contextTimeframe !== '' ? $this->loadMarketPayload($symbol, $contextTimeframe, false) : null;

        return $this->runFromPayloads($strategyVersion, $mainPayload, $contextPayload, $input);
    }

    public function runFromPayloads(array $strategyVersion, array $mainPayload, ?array $contextPayload, array $input = []): array
    {
        $this->validateRawBars($mainPayload['bars'] ?? [], (string)($mainPayload['symbol'] ?? 'UNKNOWN'), (string)($mainPayload['timeframe'] ?? ''));
        if ($contextPayload !== null) {
            $this->validateRawBars($contextPayload['bars'] ?? [], (string)($contextPayload['symbol'] ?? 'UNKNOWN'), (string)($contextPayload['timeframe'] ?? ''), false);
        }

        $bars = $this->normalizeBars($mainPayload['bars'] ?? []);
        $contextBars = $contextPayload ? $this->normalizeBars($contextPayload['bars'] ?? []) : [];
        if (count($bars) < 80) {
            throw new InvalidArgumentException('Chưa đủ nến để kiểm thử. Cần ít nhất 80 nến.');
        }

        $rawRules = is_array($strategyVersion['rulesJson'] ?? null) ? $strategyVersion['rulesJson'] : [];
        $preset = strtolower(trim((string)($input['rulesPreset'] ?? $input['preset'] ?? $rawRules['backtestPreset'] ?? 'current')));
        $rules = $this->normalizeRules(StrategyRuleParser::applyBacktestPreset($rawRules, $preset));
        if (array_key_exists('debugLevel', $input)) {
            $rules['debugLevel'] = max(0, min(3, (int)$input['debugLevel']));
        }
        $symbol = strtoupper((string)($input['symbol'] ?? $mainPayload['symbol'] ?? ($rules['symbols'][0] ?? 'UNKNOWN')));
        $timeframe = strtoupper((string)($input['timeframe'] ?? $mainPayload['timeframe'] ?? $rules['entryTimeframe']));
        $contextTimeframe = strtoupper((string)($input['contextTimeframe'] ?? $contextPayload['timeframe'] ?? $rules['contextTimeframe']));
        $rangeType = strtolower((string)($input['dataSplit'] ?? $input['data_split'] ?? $input['rangeType'] ?? $input['range_type'] ?? 'full'));
        $range = $this->resolveDateRange($bars, $rangeType, (string)($input['startDate'] ?? ''), (string)($input['endDate'] ?? ''));
        $filteredBars = $this->filterBars($bars, $range['startTs'], $range['endTs']);
        if (count($filteredBars) < 80) {
            throw new InvalidArgumentException('Khoảng ngày đã chọn có ít hơn 80 nến.');
        }

        $spreadPoints = $input['spreadPoints'] ?? $input['spread_points'] ?? $input['spread'] ?? null;
        $settings = [
            'initialBalance' => max(1, (float)($input['initialBalance'] ?? $input['initial_balance'] ?? 10000)),
            'riskPercent' => max(0.01, (float)($input['riskPercent'] ?? $rules['riskPercent'] ?? 1)),
            'spreadPoints' => $spreadPoints === null || $spreadPoints === '' ? null : max(0, (float)$spreadPoints),
            'slippagePoints' => max(0, (float)($input['slippagePoints'] ?? $input['slippage_points'] ?? 2)),
            'feeR' => max(0, (float)($input['feeR'] ?? $input['fee_r'] ?? $input['fee'] ?? 0.02)),
            'maxBarsInTrade' => max(5, (int)($input['maxBarsInTrade'] ?? 96)),
            'debugLevel' => max(0, min(3, (int)($input['debugLevel'] ?? $rules['debugLevel'] ?? 1))),
            'rulesPreset' => (string)($rules['backtestPreset'] ?? 'current'),
        ];

        $result = $this->simulate($strategyVersion, $rules, $filteredBars, $contextBars, $symbol, $timeframe, $contextTimeframe, $range, $rangeType, $settings);
        if (($input['persist'] ?? true) !== false && $this->store !== null) {
            $this->persistRun($result);
            $this->persistV2Copy($result, $strategyVersion, $input);
        }
        return $result;
    }

    public function scoreSummary(array $summary): float
    {
        $profitFactor = (float)($summary['profitFactor'] ?? 0);
        if (!is_finite($profitFactor)) {
            $profitFactor = 10;
        }
        return round(
            (float)($summary['netProfitPercent'] ?? 0)
            - (float)($summary['maxDrawdownPercent'] ?? 0) * 2
            + $profitFactor * 10
            - (float)($summary['longestLossStreak'] ?? 0) * 2,
            2
        );
    }

    private function simulate(array $strategyVersion, array $rules, array $bars, array $contextBars, string $symbol, string $timeframe, string $contextTimeframe, array $range, string $rangeType, array $settings): array
    {
        $structureDetector = new MarketStructureDetector();
        $zoneDetector = new SupplyDemandZoneDetector();
        $entryConfirmation = new M15EntryConfirmation();
        $m15Structure = $structureDetector->analyze($bars, ['fractalPeriod' => $rules['m15Rules']['fractalPeriod']]);
        $h4Structure = $contextBars
            ? $structureDetector->analyze($contextBars, ['fractalPeriod' => $rules['h4Rules']['fractalPeriod']])
            : ['swings' => [], 'events' => [], 'states' => [], 'trend' => 'sideway'];
        $h4Zones = $contextBars && (bool)$rules['h4Rules']['detectSupplyDemandZones']
            ? $zoneDetector->detect($contextBars, $h4Structure, $symbol, $contextTimeframe, $rules['h4Rules'])
            : [];
        $debugLevel = max(0, min(3, (int)($settings['debugLevel'] ?? $rules['debugLevel'] ?? 1)));
        $diagnostics = $this->initialDiagnostics($symbol, $rangeType, $range, $bars, $contextBars, $contextTimeframe, $h4Structure, $m15Structure, $h4Zones, $rules);
        $setupCandidates = [];
        $setupSteps = [];
        $directionAllowed = strtoupper((string)$rules['directionAllowed']);
        $initialBalance = (float)$settings['initialBalance'];
        $balance = $initialBalance;
        $peak = $initialBalance;
        $equityCurve = [['time' => $this->iso($range['startTs']), 'balance' => $balance, 'drawdownPercent' => 0.0]];
        $trades = [];
        $rejected = 0;
        $open = null;
        $pending = null;
        $contextMissingLogged = false;
        $startIndex = max(30, (int)$rules['m15Rules']['fractalPeriod'] * 4 + 20);

        for ($i = $startIndex; $i < count($bars); $i++) {
            $bar = $bars[$i];

            if ($open !== null) {
                $exit = $this->manageOpenTrade($open, $bars, $i, $settings);
                if ($exit !== null) {
                    $trade = $this->closeTrade($open, $exit, $bars, $settings, $symbol);
                    $balance += $trade['profitLoss'];
                    $peak = max($peak, $balance);
                    $trade['equityAfter'] = round($balance, 2);
                    $trade['drawdownPct'] = $peak > 0 ? round(max(0, ($peak - $balance) / $peak * 100), 4) : 0;
                    $trades[] = $trade;
                    $equityCurve[] = ['time' => $trade['exitTime'], 'balance' => round($balance, 2), 'drawdownPercent' => $trade['drawdownPct']];
                    $open = null;
                }
            }

            if ($open !== null) {
                continue;
            }

            if ($pending !== null) {
                $pendingResult = $this->resolvePendingLimit($pending, $bars, $i, $settings, $balance, $symbol);
                if ($pendingResult['status'] === 'cancel') {
                    $diagnostics['pendingLimitCanceled']++;
                    $diagnostics['setupsRejected']++;
                    $this->incrementSkippedReason($diagnostics, 'LIMIT_NOT_FILLED');
                    $this->appendSetupStep($setupSteps, $debugLevel, $symbol, $bar, 'LIMIT_NOT_FILLED', 'Pending limit expired before fill.');
                    $rejected++;
                    $trades[] = $this->skippedTrade(
                        $strategyVersion,
                        $symbol,
                        $bar,
                        $i,
                        (string)$pending['direction'],
                        'LIMIT_NOT_FILLED: pending limit expired after configured M15 candles.',
                        array_values(array_unique(array_merge($pending['tags'], ['LIMIT_NOT_FILLED']))),
                        ['reasonCode' => 'LIMIT_NOT_FILLED', 'zoneId' => $pending['zoneId'] ?? '', 'zoneType' => $pending['zoneType'] ?? '', 'setupType' => $pending['setupType'] ?? '']
                        + [
                            'proposedEntry' => $pending['limitPrice'] ?? null,
                            'proposedSL' => $pending['stopLoss'] ?? null,
                            'proposedTP' => $pending['takeProfit'] ?? null,
                            'rr' => $pending['rr'] ?? null,
                            'zoneStrength' => $pending['zoneStrength'] ?? null,
                            'h4Trend' => $pending['h4Trend'] ?? '',
                        ]
                    );
                    $pending = null;
                } elseif ($pendingResult['status'] === 'filled') {
                    $diagnostics['pendingLimitFilled']++;
                    $diagnostics['tradesOpened']++;
                    $this->appendSetupStep($setupSteps, $debugLevel, $symbol, $bar, 'LIMIT_FILLED', 'Pending limit was filled and became an open trade.');
                    $open = $pendingResult['trade'];
                    $pending = null;
                    $exit = $this->manageOpenTrade($open, $bars, $i, $settings);
                    if ($exit !== null) {
                        $trade = $this->closeTrade($open, $exit, $bars, $settings, $symbol);
                        $balance += $trade['profitLoss'];
                        $peak = max($peak, $balance);
                        $trade['equityAfter'] = round($balance, 2);
                        $trade['drawdownPct'] = $peak > 0 ? round(max(0, ($peak - $balance) / $peak * 100), 4) : 0;
                        $trades[] = $trade;
                        $equityCurve[] = ['time' => $trade['exitTime'], 'balance' => round($balance, 2), 'drawdownPercent' => $trade['drawdownPct']];
                        $open = null;
                    }
                }
                if ($pending !== null || $open !== null) {
                    continue;
                }
            }

            $signalIndex = $i - 1;
            if (!isset($bars[$signalIndex])) {
                continue;
            }

            $contextIndex = $this->closedContextIndexAt($contextBars, $this->barTime($bars[$signalIndex]), $contextTimeframe);
            $diagnostics['setupsConsidered']++;
            if ($contextIndex === null) {
                $diagnostics['setupsRejected']++;
                $this->incrementSkippedReason($diagnostics, 'DATA_MISSING');
                $this->appendSetupStep($setupSteps, $debugLevel, $symbol, $bars[$signalIndex], 'DATA_MISSING', 'No closed H4 context candle is available.');
                if (!$contextMissingLogged) {
                    $rejected++;
                    $trades[] = $this->skippedTrade($strategyVersion, $symbol, $bars[$signalIndex], $signalIndex, 'BUY_SELL', 'DATA_MISSING: closed H4 context data is required before creating a setup.', ['DATA_MISSING'], ['reasonCode' => 'DATA_MISSING']);
                    $contextMissingLogged = true;
                }
                continue;
            }

            $zoneSelection = $this->selectH4Zone($h4Zones, $zoneDetector, $h4Structure, $contextBars, $contextIndex, $bars[$signalIndex], $rules, $directionAllowed);
            if (!$zoneSelection['record']) {
                $diagnostics['setupsRejected']++;
                $this->incrementSkippedReason($diagnostics, (string)($zoneSelection['reasonCode'] ?? 'NO_H4_ZONE'));
                $this->appendSetupStep($setupSteps, $debugLevel, $symbol, $bars[$signalIndex], (string)($zoneSelection['reasonCode'] ?? 'NO_H4_ZONE'), (string)($zoneSelection['reason'] ?? 'No H4 zone candidate.'));
                continue;
            }
            $diagnostics['priceReturnsToH4Zone']++;
            if (!$zoneSelection['ok']) {
                $diagnostics['setupsRejected']++;
                $this->incrementSkippedReason($diagnostics, (string)$zoneSelection['reasonCode']);
                $this->appendSetupCandidate($setupCandidates, $debugLevel, $symbol, $bars[$signalIndex], $zoneSelection, null, 'REJECT', (string)$zoneSelection['reasonCode'], $this->nearbyCandlesForDebug($bars, $signalIndex));
                $this->appendSetupStep($setupSteps, $debugLevel, $symbol, $bars[$signalIndex], (string)$zoneSelection['reasonCode'], (string)$zoneSelection['reason']);
                $rejected++;
                $trades[] = $this->skippedTrade(
                    $strategyVersion,
                    $symbol,
                    $bars[$signalIndex],
                    $signalIndex,
                    (string)$zoneSelection['direction'],
                    $zoneSelection['reasonCode'] . ': ' . $zoneSelection['reason'],
                    $zoneSelection['tags'],
                    [
                        'reasonCode' => $zoneSelection['reasonCode'],
                        'zoneId' => $zoneSelection['zone']['id'] ?? '',
                        'zoneType' => $zoneSelection['zone']['type'] ?? '',
                        'h4Trend' => $zoneSelection['h4Trend'] ?? 'sideway',
                        'zoneStrength' => $zoneSelection['zone']['strengthScore'] ?? null,
                    ]
                );
                continue;
            }

            $basicFilter = $this->checkBasicPriceActionFilters($rules, $bars[$signalIndex]);
            if (!$basicFilter['ok']) {
                $diagnostics['setupsRejected']++;
                $this->incrementSkippedReason($diagnostics, (string)$basicFilter['reasonCode']);
                $this->appendSetupCandidate($setupCandidates, $debugLevel, $symbol, $bars[$signalIndex], $zoneSelection, null, 'REJECT', (string)$basicFilter['reasonCode'], $this->nearbyCandlesForDebug($bars, $signalIndex));
                $this->appendSetupStep($setupSteps, $debugLevel, $symbol, $bars[$signalIndex], (string)$basicFilter['reasonCode'], (string)$basicFilter['reason']);
                $rejected++;
                $trades[] = $this->skippedTrade(
                    $strategyVersion,
                    $symbol,
                    $bars[$signalIndex],
                    $signalIndex,
                    (string)$zoneSelection['direction'],
                    $basicFilter['reasonCode'] . ': ' . $basicFilter['reason'],
                    array_values(array_unique(array_merge($zoneSelection['tags'], $basicFilter['tags']))),
                    [
                        'reasonCode' => $basicFilter['reasonCode'],
                        'zoneId' => $zoneSelection['zone']['id'] ?? '',
                        'zoneType' => $zoneSelection['zone']['type'] ?? '',
                        'h4Trend' => $zoneSelection['h4Trend'] ?? 'sideway',
                        'zoneStrength' => $zoneSelection['zone']['strengthScore'] ?? null,
                    ]
                );
                continue;
            }

            $confirmation = $entryConfirmation->evaluate($bars, $signalIndex, $zoneSelection['zone'], $m15Structure, $rules, $symbol);
            if (!$confirmation['ok']) {
                $diagnostics['setupsRejected']++;
                $this->incrementSkippedReason($diagnostics, (string)$confirmation['reasonCode']);
                $this->appendSetupCandidate($setupCandidates, $debugLevel, $symbol, $bars[$signalIndex], $zoneSelection, $confirmation, 'REJECT', (string)$confirmation['reasonCode'], $this->nearbyCandlesForDebug($bars, $signalIndex));
                $this->appendSetupStep($setupSteps, $debugLevel, $symbol, $bars[$signalIndex], (string)$confirmation['reasonCode'], (string)$confirmation['reason']);
                $rejected++;
                $trades[] = $this->skippedTrade(
                    $strategyVersion,
                    $symbol,
                    $bars[$signalIndex],
                    $signalIndex,
                    (string)$zoneSelection['direction'],
                    $confirmation['reasonCode'] . ': ' . $confirmation['reason'],
                    array_values(array_unique(array_merge($zoneSelection['tags'], $confirmation['tags']))),
                    [
                        'reasonCode' => $confirmation['reasonCode'],
                        'zoneId' => $zoneSelection['zone']['id'] ?? '',
                        'zoneType' => $zoneSelection['zone']['type'] ?? '',
                        'h4Trend' => $zoneSelection['h4Trend'] ?? 'sideway',
                        'zoneStrength' => $zoneSelection['zone']['strengthScore'] ?? null,
                    ]
                );
                continue;
            }

            $diagnostics['pendingLimitCreated']++;
            $this->appendSetupCandidate($setupCandidates, $debugLevel, $symbol, $bars[$signalIndex], $zoneSelection, $confirmation, 'ACCEPT', '', $this->nearbyCandlesForDebug($bars, $signalIndex));
            $this->appendSetupStep($setupSteps, $debugLevel, $symbol, $bars[$signalIndex], 'ACCEPT', (string)$confirmation['reason']);
            $pending = $this->createPendingLimit($strategyVersion, $symbol, $bars[$signalIndex], $signalIndex, $i, count($trades) + 1, $confirmation, $zoneSelection, $rules);
        }

        if ($pending !== null) {
            $last = count($bars) - 1;
            $diagnostics['pendingLimitCanceled']++;
            $diagnostics['setupsRejected']++;
            $this->incrementSkippedReason($diagnostics, 'LIMIT_NOT_FILLED');
            $this->appendSetupStep($setupSteps, $debugLevel, $symbol, $bars[$last], 'LIMIT_NOT_FILLED', 'Selected backtest data ended before pending limit filled.');
            $rejected++;
            $trades[] = $this->skippedTrade(
                $strategyVersion,
                $symbol,
                $bars[$last],
                $last,
                (string)$pending['direction'],
                'LIMIT_NOT_FILLED: end of selected backtest data before limit filled.',
                array_values(array_unique(array_merge($pending['tags'], ['LIMIT_NOT_FILLED']))),
                ['reasonCode' => 'LIMIT_NOT_FILLED', 'zoneId' => $pending['zoneId'] ?? '', 'zoneType' => $pending['zoneType'] ?? '', 'setupType' => $pending['setupType'] ?? '']
            );
        }

        if ($open !== null) {
            $last = count($bars) - 1;
            $trade = $this->closeTrade($open, [
                'index' => $last,
                'price' => (float)$bars[$last]['close'],
                'result' => 'BE',
                'exitType' => 'TIME',
                'reasonExit' => 'End of selected backtest data.',
            ], $bars, $settings, $symbol);
            $balance += $trade['profitLoss'];
            $peak = max($peak, $balance);
            $trade['equityAfter'] = round($balance, 2);
            $trade['drawdownPct'] = $peak > 0 ? round(max(0, ($peak - $balance) / $peak * 100), 4) : 0;
            $trades[] = $trade;
            $equityCurve[] = ['time' => $trade['exitTime'], 'balance' => round($balance, 2), 'drawdownPercent' => $trade['drawdownPct']];
        }

        $diagnostics = $this->finalizeDiagnostics($diagnostics, $trades);
        $summary = $this->summary($trades, $equityCurve, $initialBalance, $balance, $rejected);
        $runId = $this->runId($strategyVersion, $symbol, $timeframe, $rangeType, $range, $settings);
        foreach ($trades as &$trade) {
            $trade['backtestRunId'] = $runId;
        }
        unset($trade);

        $summary['backtestRunId'] = $runId;
        $summary['id'] = $runId;
        $summary['strategyVersionId'] = (string)($strategyVersion['id'] ?? '');
        $summary['strategyVersionName'] = (string)($strategyVersion['name'] ?? '');
        $summary['symbol'] = $symbol;
        $summary['timeframe'] = $timeframe;
        $summary['contextTimeframe'] = $contextTimeframe;
        $summary['rangeType'] = $rangeType;
        $summary['dataSplit'] = in_array($rangeType, ['train', 'validation', 'final'], true) ? $rangeType : 'custom';
        $summary['startDate'] = $this->iso($range['startTs']);
        $summary['endDate'] = $this->iso($range['endTs']);
        $summary['createdAt'] = gmdate('c');
        $summary['settings'] = $settings;
        $summary['debugLevel'] = $debugLevel;
        $summary['rulesPreset'] = (string)($rules['backtestPreset'] ?? 'current');
        $summary['skippedSetups'] = (int)$diagnostics['setupsRejected'];
        $summary['skippedTradeRecords'] = count(array_values(array_filter($trades, fn($trade) => ($trade['result'] ?? '') === 'SKIPPED')));
        $summary['rejectedSetupCount'] = (int)$diagnostics['setupsRejected'];
        $sample = $this->sampleStatus($summary);
        $summary['sampleStatus'] = $sample['status'];
        $summary['result'] = $sample['status'];
        $summary['warnings'] = $sample['warnings'];
        $summary['score'] = $this->scoreSummary($summary);
        $summary['summaryNotes'] = $this->summaryNotes($summary, $rules, $contextBars);
        $summary['stats'] = $this->chartStats($summary);
        $summary['breakdowns'] = $this->breakdowns($trades);
        $summary['breakdowns']['skippedReasons'] = $diagnostics['skippedReasons'];
        if ($debugLevel >= 1) {
            $summary['diagnostics'] = $diagnostics;
        }
        $debug = $debugLevel >= 1 ? $this->debugPayload($h4Zones, $h4Structure, $m15Structure, $contextBars, $range, $contextTimeframe) : [];
        if ($debugLevel >= 2) {
            $debug['setupCandidates'] = $setupCandidates;
            $summary['setupCandidates'] = $setupCandidates;
            $summary['setupCandidatesSaved'] = count($setupCandidates);
        }
        if ($debugLevel >= 3) {
            $debug['setupSteps'] = $setupSteps;
            $summary['setupSteps'] = $setupSteps;
        }

        return [
            ...$summary,
            'summary' => $summary,
            'trades' => $trades,
            'equityCurve' => $equityCurve,
            'debug' => $debug,
            'diagnostics' => $debugLevel >= 1 ? $diagnostics : null,
            'setup_candidates' => $debugLevel >= 2 ? $setupCandidates : [],
        ];
    }

    private function initialDiagnostics(string $symbol, string $rangeType, array $range, array $bars, array $contextBars, string $contextTimeframe, array $h4Structure, array $m15Structure, array $h4Zones, array $rules): array
    {
        $zoneDetector = new SupplyDemandZoneDetector();
        $lastContextIndex = $this->closedContextIndexAt($contextBars, (int)$range['endTs'], $contextTimeframe);
        $lastContextIndex = $lastContextIndex ?? -1;
        $zonesInScope = array_values(array_filter($h4Zones, function ($zone) use ($lastContextIndex) {
            return (int)($zone['createdIndex'] ?? PHP_INT_MAX) <= $lastContextIndex;
        }));
        $zoneStates = array_map(fn($zone) => $zoneDetector->stateAt($zone, $lastContextIndex), $zonesInScope);
        $minStrength = (float)($rules['h4Rules']['minZoneStrength'] ?? 0);

        return [
            'symbol' => $symbol,
            'dataSplit' => in_array($rangeType, ['train', 'validation', 'final'], true) ? $rangeType : 'custom',
            'dateRange' => [
                'start' => $this->iso((int)$range['startTs']),
                'end' => $this->iso((int)$range['endTs']),
            ],
            'totalM15Candles' => count($bars),
            'totalH4Candles' => count($this->filterBars($contextBars, (int)$range['startTs'], (int)$range['endTs'])),
            'h4SwingCount' => $this->countTimestampedInRange($h4Structure['swings'] ?? [], $range),
            'm15SwingCount' => $this->countTimestampedInRange($m15Structure['swings'] ?? [], $range),
            'h4SupplyZones' => count(array_filter($zonesInScope, fn($zone) => ($zone['type'] ?? '') === 'supply')),
            'h4DemandZones' => count(array_filter($zonesInScope, fn($zone) => ($zone['type'] ?? '') === 'demand')),
            'zoneActive' => count(array_filter($zoneStates, fn($zone) => (bool)($zone['active'] ?? false))),
            'zoneFresh' => count(array_filter($zoneStates, fn($zone) => (bool)($zone['fresh'] ?? false))),
            'zoneTouched' => count(array_filter($zoneStates, fn($zone) => (bool)($zone['touched'] ?? false) && !(bool)($zone['broken'] ?? false))),
            'zoneWeakened' => count(array_filter($zoneStates, fn($zone) => (bool)($zone['weakened'] ?? false))),
            'zoneBroken' => count(array_filter($zoneStates, fn($zone) => (bool)($zone['broken'] ?? false))),
            'zoneInvalidatedByTouches' => count(array_filter($zoneStates, fn($zone) => (bool)($zone['invalidatedByTouches'] ?? false))),
            'zoneEnoughStrength' => count(array_filter($zoneStates, fn($zone) => (float)($zone['strengthScore'] ?? 0) >= $minStrength)),
            'zoneLifecycle' => [
                'requireCloseBeyondZoneToBreak' => (bool)($rules['h4Rules']['zoneLifecycle']['requireCloseBeyondZoneToBreak'] ?? true),
                'allowWickThrough' => (bool)($rules['h4Rules']['zoneLifecycle']['allowWickThrough'] ?? true),
                'wickToleranceAtr' => (float)($rules['h4Rules']['zoneLifecycle']['wickToleranceAtr'] ?? 0.25),
                'breakBufferAtr' => (float)($rules['h4Rules']['zoneLifecycle']['breakBufferAtr'] ?? 0.1),
                'maxTouchesBeforeWeak' => (int)($rules['h4Rules']['zoneLifecycle']['maxTouchesBeforeWeak'] ?? 2),
                'maxTouchesBeforeInvalid' => (int)($rules['h4Rules']['zoneLifecycle']['maxTouchesBeforeInvalid'] ?? 4),
                'markTouchedButNotBroken' => (bool)($rules['h4Rules']['zoneLifecycle']['markTouchedButNotBroken'] ?? true),
            ],
            'priceReturnsToH4Zone' => 0,
            'setupsConsidered' => 0,
            'setupsRejected' => 0,
            'pendingLimitCreated' => 0,
            'pendingLimitFilled' => 0,
            'pendingLimitCanceled' => 0,
            'tradesOpened' => 0,
            'tpCount' => 0,
            'slCount' => 0,
            'beCount' => 0,
            'skippedReasons' => $this->skippedReasonTemplate(),
        ];
    }

    private function finalizeDiagnostics(array $diagnostics, array $trades): array
    {
        foreach ($trades as $trade) {
            if (!is_array($trade) || ($trade['result'] ?? '') === 'SKIPPED') {
                continue;
            }
            $exitType = (string)($trade['exitType'] ?? '');
            if ($exitType === 'TP') {
                $diagnostics['tpCount']++;
            } elseif ($exitType === 'SL') {
                $diagnostics['slCount']++;
            }
            if (($trade['result'] ?? '') === 'BE' || $exitType === 'BE') {
                $diagnostics['beCount']++;
            }
        }
        arsort($diagnostics['skippedReasons']);
        return $diagnostics;
    }

    private function skippedReasonTemplate(): array
    {
        return [
            'NO_H4_ZONE' => 0,
            'WEAK_H4_ZONE' => 0,
            'NO_M15_CONFIRMATION' => 0,
            'BAD_RR' => 0,
            'DIRTY_SIDEWAY' => 0,
            'ZONE_ALREADY_BROKEN' => 0,
            'LIMIT_NOT_FILLED' => 0,
            'DATA_MISSING' => 0,
        ];
    }

    private function incrementSkippedReason(array &$diagnostics, string $reasonCode): void
    {
        $reasonCode = $reasonCode !== '' ? $reasonCode : 'DATA_MISSING';
        if (!array_key_exists($reasonCode, $diagnostics['skippedReasons'])) {
            $diagnostics['skippedReasons'][$reasonCode] = 0;
        }
        $diagnostics['skippedReasons'][$reasonCode]++;
    }

    private function appendSetupCandidate(array &$candidates, int $debugLevel, string $symbol, array $bar, array $zoneSelection, ?array $confirmation, string $decision, string $rejectReason, array $nearbyM15Candles = []): void
    {
        $limit = $debugLevel >= 3 ? 10000 : 1000;
        if ($debugLevel < 2 || count($candidates) >= $limit) {
            return;
        }
        $zone = is_array($zoneSelection['zone'] ?? null) ? $zoneSelection['zone'] : [];
        $details = is_array($confirmation['details'] ?? null) ? $confirmation['details'] : [];
        $candidates[] = [
            'id' => 'cand_' . substr(sha1($symbol . '_' . $this->barTime($bar) . '_' . ($zone['id'] ?? '') . '_' . $decision . '_' . $rejectReason), 0, 14),
            'time' => $this->barIso($bar),
            'timestamp' => $this->barTime($bar),
            'symbol' => $symbol,
            'h4ZoneId' => (string)($zone['id'] ?? ''),
            'zoneId' => (string)($zone['id'] ?? ''),
            'zoneType' => (string)($zone['type'] ?? ''),
            'zoneHigh' => isset($zone['high']) ? (float)$zone['high'] : null,
            'zoneLow' => isset($zone['low']) ? (float)$zone['low'] : null,
            'zoneStrength' => isset($zone['strengthScore']) ? (float)$zone['strengthScore'] : null,
            'strengthScore' => isset($zone['strengthScore']) ? (float)$zone['strengthScore'] : null,
            'strengthBreakdown' => is_array($zone['strengthBreakdown'] ?? null) ? $zone['strengthBreakdown'] : null,
            'zoneStateAtCandidate' => [
                'fresh' => (bool)($zone['fresh'] ?? false),
                'touched' => (bool)($zone['touched'] ?? false),
                'weakened' => (bool)($zone['weakened'] ?? false),
                'broken' => (bool)($zone['broken'] ?? false),
                'active' => (bool)($zone['active'] ?? false),
                'touchedCount' => (int)($zone['touchedCount'] ?? 0),
                'firstTouchTime' => $zone['firstTouchTime'] ?? null,
                'brokenTime' => $zone['brokenTime'] ?? null,
                'brokenByCandle' => $zone['brokenByCandle'] ?? null,
                'brokenReason' => (string)($zone['brokenReason'] ?? ''),
                'lifecycleStatus' => (string)($zone['lifecycleStatus'] ?? ''),
            ],
            'createdAt' => (string)($zone['createdAt'] ?? ''),
            'originCandleTime' => (string)($zone['originCandleTime'] ?? ''),
            'originCandle' => $zone['originCandle'] ?? null,
            'touches' => array_slice(is_array($zone['touches'] ?? null) ? $zone['touches'] : [], 0, 20),
            'brokenTime' => $zone['brokenTime'] ?? null,
            'brokenByCandle' => $zone['brokenByCandle'] ?? null,
            'brokenReason' => (string)($zone['brokenReason'] ?? ''),
            'h4Trend' => (string)($zoneSelection['h4Trend'] ?? ''),
            'priceReturnedToZone' => (bool)($details['priceReturnedToZone'] ?? (bool)$zone),
            'm15Reaction' => (bool)($details['m15Reaction'] ?? false),
            'm15StructureBreak' => (bool)($details['m15StructureBreak'] ?? false),
            'qmlDetected' => (bool)($details['qmlDetected'] ?? false),
            'rr' => isset($details['rr']) ? (float)$details['rr'] : (isset($confirmation['rr']) ? (float)$confirmation['rr'] : null),
            'decision' => $decision,
            'rejectReason' => $rejectReason,
            'zoneRejectReason' => (string)($zoneSelection['reason'] ?? ''),
            'zoneRejectCode' => (string)($zoneSelection['reasonCode'] ?? ''),
            'proposedEntry' => isset($details['proposedEntry']) ? (float)$details['proposedEntry'] : (isset($confirmation['entryPrice']) ? (float)$confirmation['entryPrice'] : null),
            'proposedSL' => isset($details['proposedSL']) ? (float)$details['proposedSL'] : (isset($confirmation['stopLoss']) ? (float)$confirmation['stopLoss'] : null),
            'proposedTP' => isset($details['proposedTP']) ? (float)$details['proposedTP'] : (isset($confirmation['takeProfit']) ? (float)$confirmation['takeProfit'] : null),
            'nearbyM15Candles' => $nearbyM15Candles,
            'reason' => (string)($confirmation['reason'] ?? $zoneSelection['reason'] ?? ''),
        ];
    }

    private function appendSetupStep(array &$steps, int $debugLevel, string $symbol, array $bar, string $step, string $reason): void
    {
        if ($debugLevel < 3 || count($steps) >= 3000) {
            return;
        }
        $steps[] = [
            'time' => $this->barIso($bar),
            'symbol' => $symbol,
            'step' => $step,
            'reason' => $reason,
        ];
    }

    private function nearbyCandlesForDebug(array $bars, int $index, int $radius = 6): array
    {
        $slice = array_slice($bars, max(0, $index - $radius), $radius * 2 + 1, true);
        $result = [];
        foreach ($slice as $localIndex => $bar) {
            if (!is_array($bar)) {
                continue;
            }
            $result[] = [
                'index' => (int)$localIndex,
                'sourceIndex' => (int)($bar['_source_index'] ?? $localIndex),
                'time' => $this->barIso($bar),
                'open' => (float)($bar['open'] ?? 0),
                'high' => (float)($bar['high'] ?? 0),
                'low' => (float)($bar['low'] ?? 0),
                'close' => (float)($bar['close'] ?? 0),
            ];
        }
        return $result;
    }

    private function countTimestampedInRange(array $items, array $range): int
    {
        $count = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $time = (int)($item['timestamp'] ?? 0);
            if ($time >= (int)$range['startTs'] && $time <= (int)$range['endTs']) {
                $count++;
            }
        }
        return $count;
    }

    private function sampleStatus(array $summary): array
    {
        $trades = (int)($summary['totalTrades'] ?? 0);
        $split = (string)($summary['dataSplit'] ?? $summary['rangeType'] ?? '');
        $warnings = [];
        if ($trades < 30) {
            $warnings[] = 'INSUFFICIENT_SAMPLE: totalTrades is below 30; do not conclude this strategy is good or bad.';
        }
        if ($split === 'validation' && $trades < 50) {
            $warnings[] = 'INSUFFICIENT_SAMPLE: validation totalTrades is below 50; evaluator must reject or require more data/setup.';
        }
        return [
            'status' => $warnings ? 'INSUFFICIENT_SAMPLE' : 'OK',
            'warnings' => $warnings,
        ];
    }

    private function selectH4Zone(array $zones, SupplyDemandZoneDetector $zoneDetector, array $h4Structure, array $contextBars, int $contextIndex, array $m15Bar, array $rules, string $directionAllowed): array
    {
        if (!$zones) {
            return ['ok' => false, 'record' => false, 'direction' => 'BUY_SELL', 'reasonCode' => 'NO_H4_ZONE', 'reason' => 'No H4 supply/demand zones have been detected.', 'tags' => ['NO_H4_ZONE'], 'zone' => null];
        }

        $candidates = [];
        foreach ($zones as $zone) {
            if ((int)($zone['createdIndex'] ?? PHP_INT_MAX) > $contextIndex) {
                continue;
            }
            $state = $zoneDetector->stateAt($zone, $contextIndex);
            if (!$zoneDetector->overlaps($state, $m15Bar)) {
                continue;
            }
            $candidates[] = $state;
        }
        if (!$candidates) {
            return ['ok' => false, 'record' => false, 'direction' => 'BUY_SELL', 'reasonCode' => 'NO_H4_ZONE', 'reason' => 'M15 price is not inside a detected H4 zone.', 'tags' => ['NO_H4_ZONE'], 'zone' => null];
        }

        usort($candidates, function ($a, $b) {
            $score = (float)($b['strengthScore'] ?? 0) <=> (float)($a['strengthScore'] ?? 0);
            if ($score !== 0) {
                return $score;
            }
            return (int)($b['createdIndex'] ?? 0) <=> (int)($a['createdIndex'] ?? 0);
        });

        $zone = $candidates[0];
        foreach ($candidates as $candidate) {
            if (!(bool)($candidate['broken'] ?? false) && !(bool)($candidate['invalidatedByTouches'] ?? false)) {
                $zone = $candidate;
                break;
            }
        }
        $direction = ($zone['type'] ?? '') === 'demand' ? 'BUY' : 'SELL';
        $h4State = (new MarketStructureDetector())->stateAt($h4Structure, $contextIndex);
        $h4Trend = (string)($h4State['trend'] ?? 'sideway');
        $tags = [
            'H4_' . strtoupper((string)$zone['type']) . '_ZONE',
            'ZONE_STRENGTH_' . $this->zoneStrengthBucket((float)($zone['strengthScore'] ?? 0)),
            'H4_TREND_' . strtoupper($h4Trend),
        ];

        if ((bool)($zone['broken'] ?? false)) {
            $brokenTime = (string)($zone['brokenTime'] ?? '');
            $brokenReason = (string)($zone['brokenReason'] ?? 'close beyond zone');
            return ['ok' => false, 'record' => true, 'direction' => $direction, 'reasonCode' => 'ZONE_ALREADY_BROKEN', 'reason' => 'Selected H4 zone was already broken before this M15 setup by ' . $brokenReason . ($brokenTime !== '' ? ' at ' . $brokenTime : '') . '.', 'tags' => array_merge($tags, ['ZONE_ALREADY_BROKEN']), 'zone' => $zone, 'h4Trend' => $h4Trend];
        }
        if ((bool)($zone['invalidatedByTouches'] ?? false)) {
            return ['ok' => false, 'record' => true, 'direction' => $direction, 'reasonCode' => 'WEAK_H4_ZONE', 'reason' => 'H4 zone reached zoneLifecycle.maxTouchesBeforeInvalid without a clean break.', 'tags' => array_merge($tags, ['TOO_MANY_ZONE_TOUCHES', 'WEAK_H4_ZONE']), 'zone' => $zone, 'h4Trend' => $h4Trend];
        }
        if ((bool)($rules['h4Rules']['freshZoneOnly'] ?? false) && !(bool)($zone['fresh'] ?? false)) {
            return ['ok' => false, 'record' => true, 'direction' => $direction, 'reasonCode' => 'WEAK_H4_ZONE', 'reason' => 'h4Rules.freshZoneOnly requires an untouched H4 zone.', 'tags' => array_merge($tags, ['NOT_FRESH_ZONE', 'WEAK_H4_ZONE']), 'zone' => $zone, 'h4Trend' => $h4Trend];
        }
        $maxTouches = (int)($rules['h4Rules']['maxZoneTouches'] ?? 0);
        if ($maxTouches > 0 && (int)($zone['touchedCount'] ?? 0) > $maxTouches) {
            return ['ok' => false, 'record' => true, 'direction' => $direction, 'reasonCode' => 'WEAK_H4_ZONE', 'reason' => 'H4 zone touched more often than h4Rules.maxZoneTouches.', 'tags' => array_merge($tags, ['TOO_MANY_ZONE_TOUCHES', 'WEAK_H4_ZONE']), 'zone' => $zone, 'h4Trend' => $h4Trend];
        }
        if ($this->directionBlocked($direction, $directionAllowed)) {
            return ['ok' => false, 'record' => true, 'direction' => $direction, 'reasonCode' => 'DIRECTION_BLOCKED', 'reason' => 'rulesJson.directionAllowed blocks this H4 zone direction.', 'tags' => array_merge($tags, ['DIRECTION_BLOCKED']), 'zone' => $zone, 'h4Trend' => $h4Trend];
        }
        if ((float)($zone['strengthScore'] ?? 0) < (float)$rules['h4Rules']['minZoneStrength']) {
            return ['ok' => false, 'record' => true, 'direction' => $direction, 'reasonCode' => 'WEAK_H4_ZONE', 'reason' => 'H4 zone strength is below h4Rules.minZoneStrength.', 'tags' => array_merge($tags, ['WEAK_H4_ZONE']), 'zone' => $zone, 'h4Trend' => $h4Trend];
        }

        $aligned = ($direction === 'BUY' && $h4Trend === 'bullish') || ($direction === 'SELL' && $h4Trend === 'bearish') || $h4Trend === 'sideway';
        if (!$aligned && (bool)$rules['h4Rules']['allowCounterTrendOnlyIfZoneStrong'] && (float)($zone['strengthScore'] ?? 0) < (float)$rules['h4Rules']['counterTrendZoneStrengthMin']) {
            return ['ok' => false, 'record' => true, 'direction' => $direction, 'reasonCode' => 'WEAK_H4_ZONE', 'reason' => 'Counter-trend H4 zone is not strong enough.', 'tags' => array_merge($tags, ['COUNTER_TREND_WEAK', 'WEAK_H4_ZONE']), 'zone' => $zone, 'h4Trend' => $h4Trend];
        }

        return ['ok' => true, 'record' => true, 'direction' => $direction, 'reasonCode' => 'H4_ZONE_OK', 'reason' => '', 'tags' => $tags, 'zone' => $zone, 'h4Trend' => $h4Trend];
    }

    private function checkBasicPriceActionFilters(array $rules, array $bar): array
    {
        if ((bool)$rules['filters']['avoidWeekend']) {
            $day = (int)gmdate('N', $this->barTime($bar));
            if ($day >= 6) {
                return ['ok' => false, 'reasonCode' => 'DATA_MISSING', 'reason' => 'Weekend candle is outside normal FX session quality assumptions.', 'tags' => ['WEEKEND']];
            }
        }
        $maxSpread = $rules['filters']['maxSpread'];
        if ($maxSpread !== null && isset($bar['spread']) && (float)$bar['spread'] > (float)$maxSpread) {
            return ['ok' => false, 'reasonCode' => 'BAD_RR', 'reason' => 'Spread is higher than filters.maxSpread.', 'tags' => ['HIGH_SPREAD', 'BAD_RR']];
        }
        return ['ok' => true, 'reasonCode' => '', 'reason' => '', 'tags' => []];
    }

    private function createPendingLimit(array $strategyVersion, string $symbol, array $signalBar, int $signalIndex, int $firstFillIndex, int $sequence, array $confirmation, array $zoneSelection, array $rules): array
    {
        $zone = $zoneSelection['zone'];
        $zoneStrength = (float)($zone['strengthScore'] ?? 0);
        $tags = array_values(array_unique(array_merge(
            $zoneSelection['tags'],
            $confirmation['tags'],
            ['LIMIT_PENDING', 'H4_' . strtoupper((string)($zone['type'] ?? 'zone'))]
        )));
        return [
            'id' => $this->tradeId($strategyVersion, $symbol, $signalBar, $sequence),
            'backtestRunId' => '',
            'strategyVersionId' => (string)($strategyVersion['id'] ?? ''),
            'symbol' => $symbol,
            'direction' => (string)$confirmation['direction'],
            'signalIndex' => (int)($signalBar['_source_index'] ?? $signalIndex),
            'signalLocalIndex' => $signalIndex,
            'firstFillLocalIndex' => $firstFillIndex,
            'expireLocalIndex' => $firstFillIndex + (int)$rules['managementRules']['cancelLimitAfterCandles'],
            'limitPrice' => (float)$confirmation['entryPrice'],
            'stopLoss' => (float)$confirmation['stopLoss'],
            'takeProfit' => (float)$confirmation['takeProfit'],
            'rr' => (float)$confirmation['rr'],
            'moveSlAfterR' => (float)$rules['managementRules']['moveSlToBEAfterR'],
            'reasonEnter' => (string)$confirmation['reason'],
            'reasonEnterCode' => 'H4_ZONE_M15_CONFIRMATION',
            'tags' => $tags,
            'zoneId' => (string)($zone['id'] ?? ''),
            'zoneType' => (string)($zone['type'] ?? ''),
            'zoneStrength' => $zoneStrength,
            'zoneStrengthBucket' => $this->zoneStrengthBucket($zoneStrength),
            'h4Trend' => (string)($zoneSelection['h4Trend'] ?? 'sideway'),
            'setupType' => (string)($confirmation['setupType'] ?? 'h4_zone_m15_bos'),
            'priceActionPattern' => (string)($confirmation['details']['selectedPriceActionPattern'] ?? ''),
            'priceActionPatterns' => array_values(array_filter(array_map('strval', is_array($confirmation['details']['priceActionPatterns'] ?? null) ? $confirmation['details']['priceActionPatterns'] : []))),
        ];
    }

    private function resolvePendingLimit(array $pending, array $bars, int $index, array $settings, float $balance, string $symbol): array
    {
        if (!isset($bars[$index])) {
            return ['status' => 'wait'];
        }
        $bar = $bars[$index];
        $direction = (string)$pending['direction'];
        $limit = (float)$pending['limitPrice'];
        $filled = $direction === 'BUY'
            ? (float)$bar['low'] <= $limit
            : (float)$bar['high'] >= $limit;
        if (!$filled) {
            if ($index >= (int)$pending['expireLocalIndex']) {
                return ['status' => 'cancel'];
            }
            return ['status' => 'wait'];
        }

        $point = $this->pointSize($symbol);
        $spreadPoints = $settings['spreadPoints'] === null ? max(0, (float)($bar['spread'] ?? 0)) : (float)$settings['spreadPoints'];
        $spreadPrice = $spreadPoints * $point;
        $slippagePrice = (float)$settings['slippagePoints'] * $point;
        $entryPrice = $direction === 'BUY'
            ? $limit + $spreadPrice / 2 + $slippagePrice
            : $limit - $spreadPrice / 2 - $slippagePrice;
        $stopLoss = (float)$pending['stopLoss'];
        $riskDistance = $direction === 'BUY' ? $entryPrice - $stopLoss : $stopLoss - $entryPrice;
        if ($riskDistance <= $point) {
            return ['status' => 'cancel'];
        }
        $takeProfit = $direction === 'BUY'
            ? $entryPrice + $riskDistance * (float)$pending['rr']
            : $entryPrice - $riskDistance * (float)$pending['rr'];
        $riskAmount = max(0.01, $balance * (float)$settings['riskPercent'] / 100);

        return [
            'status' => 'filled',
            'trade' => [
                ...$pending,
                'entryIndex' => (int)$bar['_source_index'],
                'entryLocalIndex' => $index,
                'entryTime' => $this->barIso($bar),
                'entryPrice' => $entryPrice,
                'stopLoss' => $stopLoss,
                'takeProfit' => $takeProfit,
                'riskDistance' => $riskDistance,
                'riskAmount' => $riskAmount,
                'feeR' => (float)$settings['feeR'],
                'slippageR' => $riskDistance > 0 ? abs($slippagePrice) / $riskDistance : 0,
                'stopPrice' => $stopLoss,
                'mfeR' => 0.0,
                'maeR' => 0.0,
                'beIndex' => null,
            ],
        ];
    }

    private function closedContextIndexAt(array $contextBars, int $time, string $timeframe): ?int
    {
        if (!$contextBars) {
            return null;
        }
        $seconds = self::TIMEFRAME_SECONDS[strtoupper($timeframe)] ?? 14400;
        $selected = null;
        foreach ($contextBars as $index => $bar) {
            if ($this->barTime($bar) + $seconds <= $time) {
                $selected = $index;
                continue;
            }
            break;
        }
        return $selected;
    }

    private function directionBlocked(string $direction, string $directionAllowed): bool
    {
        $directionAllowed = strtoupper($directionAllowed);
        if ($directionAllowed === 'BUY_SELL' || $directionAllowed === 'BOTH') {
            return false;
        }
        return $directionAllowed !== strtoupper($direction);
    }

    private function zoneStrengthBucket(float $strength): string
    {
        if ($strength >= 85) {
            return '85_100';
        }
        if ($strength >= 70) {
            return '70_84';
        }
        if ($strength >= 50) {
            return '50_69';
        }
        return '0_49';
    }

    private function breakdowns(array $trades): array
    {
        return [
            'winrateBySymbol' => $this->winrateBreakdown($trades, fn($trade) => (string)($trade['symbol'] ?? 'UNKNOWN')),
            'winrateBySetupType' => $this->winrateBreakdown($trades, fn($trade) => (string)($trade['setupType'] ?? 'unknown')),
            'winrateByH4Trend' => $this->winrateBreakdown($trades, fn($trade) => (string)($trade['h4Trend'] ?? 'unknown')),
            'winrateByZoneStrengthBucket' => $this->winrateBreakdown($trades, fn($trade) => (string)($trade['zoneStrengthBucket'] ?? 'unknown')),
            'winrateByTags' => $this->tagWinrateBreakdown($trades),
            'lossReasons' => $this->reasonCounts($trades, 'LOSS'),
            'skippedReasons' => $this->reasonCounts($trades, 'SKIPPED'),
        ];
    }

    private function winrateBreakdown(array $trades, callable $keyFn): array
    {
        $buckets = [];
        foreach ($trades as $trade) {
            if (($trade['result'] ?? '') === 'SKIPPED') {
                continue;
            }
            $key = $keyFn($trade) ?: 'unknown';
            $buckets[$key] ??= ['total' => 0, 'wins' => 0, 'losses' => 0, 'breakEven' => 0, 'winRate' => 0.0];
            $buckets[$key]['total']++;
            if (($trade['result'] ?? '') === 'WIN') {
                $buckets[$key]['wins']++;
            } elseif (($trade['result'] ?? '') === 'LOSS') {
                $buckets[$key]['losses']++;
            } else {
                $buckets[$key]['breakEven']++;
            }
        }
        foreach ($buckets as &$bucket) {
            $bucket['winRate'] = $bucket['total'] > 0 ? round($bucket['wins'] / $bucket['total'] * 100, 2) : 0.0;
        }
        unset($bucket);
        ksort($buckets);
        return $buckets;
    }

    private function tagWinrateBreakdown(array $trades): array
    {
        $expanded = [];
        foreach ($trades as $trade) {
            foreach (($trade['tags'] ?? []) as $tag) {
                $copy = $trade;
                $copy['_tagBucket'] = (string)$tag;
                $expanded[] = $copy;
            }
        }
        return $this->winrateBreakdown($expanded, fn($trade) => (string)($trade['_tagBucket'] ?? 'unknown'));
    }

    private function reasonCounts(array $trades, string $result): array
    {
        $counts = [];
        foreach ($trades as $trade) {
            if (($trade['result'] ?? '') !== $result) {
                continue;
            }
            $reason = (string)($trade['reasonCode'] ?? '');
            if ($reason === '') {
                $reason = $result === 'SKIPPED'
                    ? strtok((string)($trade['reasonSkipped'] ?? 'UNKNOWN'), ':') ?: 'UNKNOWN'
                    : (string)($trade['exitType'] ?? 'UNKNOWN');
            }
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }
        arsort($counts);
        return $counts;
    }

    private function debugPayload(array $h4Zones, array $h4Structure, array $m15Structure, array $contextBars, array $range, string $contextTimeframe): array
    {
        $lastContextIndex = $this->closedContextIndexAt($contextBars, (int)($range['endTs'] ?? 0), $contextTimeframe);
        $lastContextIndex = $lastContextIndex ?? -1;
        $zoneDetector = new SupplyDemandZoneDetector();
        $zones = array_map(
            fn($zone) => $zoneDetector->stateAt($zone, $lastContextIndex),
            array_values(array_filter($h4Zones, fn($zone) => (int)($zone['createdIndex'] ?? PHP_INT_MAX) <= $lastContextIndex))
        );
        $m15Swings = array_values(array_filter(
            $m15Structure['swings'] ?? [],
            fn($swing) => ($swing['timestamp'] ?? 0) >= ($range['startTs'] ?? 0) && ($swing['timestamp'] ?? 0) <= ($range['endTs'] ?? PHP_INT_MAX)
        ));
        $m15Events = array_values(array_filter(
            $m15Structure['events'] ?? [],
            fn($event) => ($event['timestamp'] ?? 0) >= ($range['startTs'] ?? 0) && ($event['timestamp'] ?? 0) <= ($range['endTs'] ?? PHP_INT_MAX)
        ));
        return [
            'h4Zones' => array_values($zones),
            'h4Structure' => [
                'swings' => array_slice($h4Structure['swings'] ?? [], -240),
                'events' => array_slice($h4Structure['events'] ?? [], -160),
                'trend' => $h4Structure['trend'] ?? 'sideway',
            ],
            'm15Structure' => [
                'swings' => array_slice($m15Swings, -500),
                'events' => array_slice($m15Events, -300),
                'trend' => $m15Structure['trend'] ?? 'sideway',
            ],
        ];
    }

    private function checkEntryFilters(array $rules, array $bars, int $signalIndex, array $context, string $direction, string $symbol): array
    {
        $tags = [];
        $bar = $bars[$signalIndex];
        $filters = $rules['filters'];

        if ((bool)$filters['avoidWeekend']) {
            $day = (int)gmdate('N', $this->barTime($bar));
            if ($day >= 6) {
                return ['ok' => false, 'reason' => 'Weekend filter blocked setup.', 'tags' => ['WEEKEND']];
            }
        }

        $maxSpread = (float)$filters['maxSpread'];
        if ($maxSpread > 0 && isset($bar['spread']) && (float)$bar['spread'] > $maxSpread) {
            return ['ok' => false, 'reason' => 'Spread is higher than maxSpread.', 'tags' => ['HIGH_SPREAD']];
        }

        if ((bool)$filters['avoidSideway'] && $this->isSideway($bars, $signalIndex)) {
            return ['ok' => false, 'reason' => 'Sideway filter blocked low-momentum setup.', 'tags' => ['SIDEWAY']];
        }

        $contextState = $this->contextStateAt($context, $this->barTime($bar));
        if ((bool)$filters['requireH4Trend']) {
            if ($contextState === null) {
                return ['ok' => false, 'reason' => 'Missing closed context timeframe trend.', 'tags' => ['NO_H4_CONTEXT']];
            }
            if (($direction === 'BUY' && $contextState['trend'] !== 'bullish') || ($direction === 'SELL' && $contextState['trend'] !== 'bearish')) {
                return ['ok' => false, 'reason' => 'Setup is against the latest closed H4 trend.', 'tags' => ['H4_TREND_MISMATCH']];
            }
            $tags[] = 'H4_TREND';
        }

        $zoneStrength = $contextState['strength'] ?? 50;
        if ((bool)$filters['requireStrongH4Zone'] && $zoneStrength < (float)$rules['zoneStrengthMin']) {
            return ['ok' => false, 'reason' => 'Context zone strength proxy is below zoneStrengthMin.', 'tags' => ['WEAK_H4_ZONE']];
        }
        if ($zoneStrength >= (float)$rules['zoneStrengthMin']) {
            $tags[] = 'H4_STRONG_ZONE';
        }

        $score = $this->aiScoreProxy($direction, $contextState, $zoneStrength, $rules);
        if ($score < (float)$rules['aiScoreMin']) {
            return ['ok' => false, 'reason' => 'Deterministic AI score proxy is below aiScoreMin.', 'tags' => ['LOW_AI_SCORE']];
        }
        $tags[] = 'AI_SCORE_PASS';

        return ['ok' => true, 'reason' => '', 'tags' => $tags];
    }

    private function manageOpenTrade(array &$open, array $bars, int $i, array $settings): ?array
    {
        $bar = $bars[$i];
        $high = (float)$bar['high'];
        $low = (float)$bar['low'];
        $close = (float)$bar['close'];
        $isBuy = $open['direction'] === 'BUY';
        $riskDistance = (float)$open['riskDistance'];

        $favorableR = $isBuy ? ($high - $open['entryPrice']) / $riskDistance : ($open['entryPrice'] - $low) / $riskDistance;
        $adverseR = $isBuy ? ($open['entryPrice'] - $low) / $riskDistance : ($high - $open['entryPrice']) / $riskDistance;
        $open['mfeR'] = max((float)$open['mfeR'], $favorableR);
        $open['maeR'] = max((float)$open['maeR'], $adverseR);

        if ($open['beIndex'] === null && $open['moveSlAfterR'] > 0 && $favorableR >= $open['moveSlAfterR']) {
            $open['stopPrice'] = $open['entryPrice'];
            $open['beIndex'] = (int)$bar['_source_index'];
        }

        $hitStop = $isBuy ? $low <= $open['stopPrice'] : $high >= $open['stopPrice'];
        $hitTarget = $isBuy ? $high >= $open['takeProfit'] : $low <= $open['takeProfit'];

        if ($hitStop && $hitTarget) {
            return [
                'index' => $i,
                'price' => $open['stopPrice'],
                'result' => abs($open['stopPrice'] - $open['entryPrice']) < $riskDistance * 0.02 ? 'BE' : 'LOSS',
                'exitType' => abs($open['stopPrice'] - $open['entryPrice']) < $riskDistance * 0.02 ? 'BE' : 'SL',
                'reasonExit' => 'Both SL and TP touched inside one candle; deterministic conservative rule chose SL first.',
            ];
        }
        if ($hitStop) {
            $be = abs($open['stopPrice'] - $open['entryPrice']) < $riskDistance * 0.02;
            return [
                'index' => $i,
                'price' => $open['stopPrice'],
                'result' => $be ? 'BE' : 'LOSS',
                'exitType' => $be ? 'BE' : 'SL',
                'reasonExit' => $be ? 'Moved SL to entry and was stopped at breakeven.' : 'Stop loss touched.',
            ];
        }
        if ($hitTarget) {
            return [
                'index' => $i,
                'price' => $open['takeProfit'],
                'result' => 'WIN',
                'exitType' => 'TP',
                'reasonExit' => 'Take profit touched.',
            ];
        }
        if ($i - (int)$open['entryLocalIndex'] >= (int)$settings['maxBarsInTrade']) {
            return [
                'index' => $i,
                'price' => $close,
                'result' => 'BE',
                'exitType' => 'TIME',
                'reasonExit' => 'Max bars in trade reached.',
            ];
        }
        return null;
    }

    private function closeTrade(array $open, array $exit, array $bars, array $settings, string $symbol): array
    {
        $exitBar = $bars[$exit['index']];
        $isBuy = $open['direction'] === 'BUY';
        $grossR = $isBuy
            ? ((float)$exit['price'] - (float)$open['entryPrice']) / (float)$open['riskDistance']
            : ((float)$open['entryPrice'] - (float)$exit['price']) / (float)$open['riskDistance'];
        $netR = $grossR - (float)$open['feeR'] - (float)$open['slippageR'];
        $profitLoss = $netR * (float)$open['riskAmount'];
        $result = $netR > 0.05 ? 'WIN' : ($netR < -0.05 ? 'LOSS' : 'BE');

        return [
            'id' => $open['id'],
            'backtestRunId' => $open['backtestRunId'],
            'strategyVersionId' => $open['strategyVersionId'],
            'symbol' => $symbol,
            'direction' => $open['direction'],
            'chartDirection' => $open['direction'] === 'BUY' ? 'long' : 'short',
            'entryIndex' => $open['entryIndex'],
            'exitIndex' => (int)$exitBar['_source_index'],
            'entryTime' => $open['entryTime'],
            'entryPrice' => round((float)$open['entryPrice'], $this->priceDigits($symbol)),
            'stopLoss' => round((float)$open['stopLoss'], $this->priceDigits($symbol)),
            'takeProfit' => round((float)$open['takeProfit'], $this->priceDigits($symbol)),
            'initialStop' => round((float)$open['stopLoss'], $this->priceDigits($symbol)),
            'targetPrice' => round((float)$open['takeProfit'], $this->priceDigits($symbol)),
            'exitTime' => $this->barIso($exitBar),
            'exitPrice' => round((float)$exit['price'], $this->priceDigits($symbol)),
            'result' => $result,
            'exitType' => (string)$exit['exitType'],
            'rrResult' => round($netR, 4),
            'netR' => round($netR, 4),
            'grossR' => round($grossR, 4),
            'profitLoss' => round($profitLoss, 2),
            'pnlUsd' => round($profitLoss, 2),
            'profitLossPercent' => round($profitLoss / max(1, (float)$settings['initialBalance']) * 100, 4),
            'reasonEnter' => $open['reasonEnter'],
            'entryReason' => $open['reasonEnter'],
            'reasonEnterCode' => (string)($open['reasonEnterCode'] ?? ''),
            'reasonExit' => (string)$exit['reasonExit'],
            'exitReason' => (string)$exit['reasonExit'],
            'reasonCode' => (string)($exit['exitType'] ?? ''),
            'reasonSkipped' => '',
            'tags' => $open['tags'],
            'setupType' => (string)($open['setupType'] ?? ''),
            'priceActionPattern' => (string)($open['priceActionPattern'] ?? ''),
            'priceActionPatterns' => array_values(array_filter(array_map('strval', is_array($open['priceActionPatterns'] ?? null) ? $open['priceActionPatterns'] : []))),
            'h4Trend' => (string)($open['h4Trend'] ?? ''),
            'zoneId' => (string)($open['zoneId'] ?? ''),
            'zoneType' => (string)($open['zoneType'] ?? ''),
            'zoneStrength' => $open['zoneStrength'] ?? null,
            'zoneStrengthBucket' => (string)($open['zoneStrengthBucket'] ?? ''),
            'feeR' => round((float)$open['feeR'], 4),
            'slippageR' => round((float)$open['slippageR'], 4),
            'mfeR' => round((float)$open['mfeR'], 4),
            'maeR' => round((float)$open['maeR'], 4),
            'holdingBars' => max(0, (int)$exit['index'] - (int)$open['entryLocalIndex']),
            'beIndex' => $open['beIndex'],
        ];
    }

    private function skippedTrade(array $strategyVersion, string $symbol, array $bar, int $index, string $direction, string $reason, array $tags, array $extra = []): array
    {
        $price = (float)($bar['close'] ?? $bar['open'] ?? 0);
        return [
            'id' => $this->tradeId($strategyVersion, $symbol, $bar, $index + 1),
            'backtestRunId' => '',
            'strategyVersionId' => (string)($strategyVersion['id'] ?? ''),
            'symbol' => $symbol,
            'direction' => $direction,
            'chartDirection' => $direction === 'BUY' ? 'long' : 'short',
            'entryIndex' => (int)($bar['_source_index'] ?? $index),
            'exitIndex' => (int)($bar['_source_index'] ?? $index),
            'entryTime' => $this->barIso($bar),
            'entryPrice' => $price,
            'stopLoss' => null,
            'takeProfit' => null,
            'initialStop' => $price,
            'targetPrice' => $price,
            'exitTime' => $this->barIso($bar),
            'exitPrice' => $price,
            'result' => 'SKIPPED',
            'exitType' => 'SKIPPED',
            'rrResult' => 0,
            'netR' => 0,
            'grossR' => 0,
            'profitLoss' => 0,
            'pnlUsd' => 0,
            'profitLossPercent' => 0,
            'reasonEnter' => '',
            'entryReason' => '',
            'reasonEnterCode' => '',
            'reasonExit' => '',
            'exitReason' => '',
            'reasonCode' => (string)($extra['reasonCode'] ?? (strtok($reason, ':') ?: 'SKIPPED')),
            'reasonSkipped' => $reason,
            'tags' => $tags,
            'setupType' => (string)($extra['setupType'] ?? ''),
            'h4Trend' => (string)($extra['h4Trend'] ?? ''),
            'zoneId' => (string)($extra['zoneId'] ?? ''),
            'zoneType' => (string)($extra['zoneType'] ?? ''),
            'zoneStrength' => $extra['zoneStrength'] ?? null,
            'zoneStrengthBucket' => isset($extra['zoneStrength']) && is_numeric($extra['zoneStrength']) ? $this->zoneStrengthBucket((float)$extra['zoneStrength']) : '',
            'proposedEntry' => $extra['proposedEntry'] ?? null,
            'proposedSL' => $extra['proposedSL'] ?? null,
            'proposedTP' => $extra['proposedTP'] ?? null,
            'rr' => $extra['rr'] ?? null,
            'feeR' => 0,
            'slippageR' => 0,
            'mfeR' => 0,
            'maeR' => 0,
            'holdingBars' => 0,
            'beIndex' => null,
        ];
    }

    private function summary(array $trades, array $equityCurve, float $initialBalance, float $finalBalance, int $rejected): array
    {
        $closed = array_values(array_filter($trades, fn($trade) => ($trade['result'] ?? '') !== 'SKIPPED'));
        $wins = array_values(array_filter($closed, fn($trade) => ($trade['result'] ?? '') === 'WIN'));
        $losses = array_values(array_filter($closed, fn($trade) => ($trade['result'] ?? '') === 'LOSS'));
        $breakEven = array_values(array_filter($closed, fn($trade) => ($trade['result'] ?? '') === 'BE'));
        $skipped = array_values(array_filter($trades, fn($trade) => ($trade['result'] ?? '') === 'SKIPPED'));
        $grossWin = array_sum(array_map(fn($trade) => max(0, (float)$trade['profitLoss']), $closed));
        $grossLoss = abs(array_sum(array_map(fn($trade) => min(0, (float)$trade['profitLoss']), $closed)));
        $totalR = array_sum(array_map(fn($trade) => (float)$trade['rrResult'], $closed));
        $maxDrawdownPercent = 0.0;
        foreach ($equityCurve as $point) {
            $maxDrawdownPercent = max($maxDrawdownPercent, (float)($point['drawdownPercent'] ?? 0));
        }

        return [
            'initialBalance' => round($initialBalance, 2),
            'finalBalance' => round($finalBalance, 2),
            'totalTrades' => count($closed),
            'winTrades' => count($wins),
            'lossTrades' => count($losses),
            'breakEvenTrades' => count($breakEven),
            'skippedSetups' => count($skipped),
            'winRate' => count($closed) > 0 ? round(count($wins) / count($closed) * 100, 2) : 0,
            'netProfit' => round($finalBalance - $initialBalance, 2),
            'netProfitPercent' => round(($finalBalance - $initialBalance) / max(1, $initialBalance) * 100, 4),
            'netProfitR' => round($totalR, 4),
            'maxDrawdown' => round($initialBalance * $maxDrawdownPercent / 100, 2),
            'maxDrawdownPercent' => round($maxDrawdownPercent, 4),
            'profitFactor' => $grossLoss > 0 ? round($grossWin / $grossLoss, 4) : ($grossWin > 0 ? 999 : 0),
            'averageRR' => count($closed) > 0 ? round($totalR / count($closed), 4) : 0,
            'longestWinStreak' => $this->longestStreak($closed, 'WIN'),
            'longestLossStreak' => $this->longestStreak($closed, 'LOSS'),
            'expectancy' => count($closed) > 0 ? round(($grossWin - $grossLoss) / count($closed), 4) : 0,
            'rejectedSetupCount' => $rejected,
        ];
    }

    private function chartStats(array $summary): array
    {
        return [
            'trades' => (int)$summary['totalTrades'],
            'wins' => (int)$summary['winTrades'],
            'losses' => (int)$summary['lossTrades'],
            'winRate' => (float)$summary['winRate'],
            'totalR' => (float)$summary['netProfitR'],
            'profitFactor' => (float)$summary['profitFactor'],
            'expectancy' => (float)$summary['averageRR'],
            'maxDrawdownPct' => -1 * (float)$summary['maxDrawdownPercent'],
            'finalEquity' => (float)$summary['finalBalance'],
            'returnPct' => (float)$summary['netProfitPercent'],
        ];
    }

    private function entrySignal(array $fast, array $slow, array $bars, int $index, bool $requireStructureBreak): ?string
    {
        if ($index < 2 || !isset($fast[$index], $slow[$index], $fast[$index - 1], $slow[$index - 1])) {
            return null;
        }
        if (!is_numeric($fast[$index]) || !is_numeric($slow[$index]) || !is_numeric($fast[$index - 1]) || !is_numeric($slow[$index - 1])) {
            return null;
        }

        $buyCross = $fast[$index - 1] <= $slow[$index - 1] && $fast[$index] > $slow[$index];
        $sellCross = $fast[$index - 1] >= $slow[$index - 1] && $fast[$index] < $slow[$index];
        if (!$buyCross && !$sellCross) {
            return null;
        }
        if (!$requireStructureBreak) {
            return $buyCross ? 'BUY' : 'SELL';
        }

        $lookbackStart = max(0, $index - 8);
        $prior = array_slice($bars, $lookbackStart, max(0, $index - $lookbackStart));
        if (!$prior) {
            return null;
        }
        $priorHigh = max(array_map(fn($bar) => (float)$bar['high'], $prior));
        $priorLow = min(array_map(fn($bar) => (float)$bar['low'], $prior));
        $close = (float)$bars[$index]['close'];
        if ($buyCross && $close > $priorHigh) {
            return 'BUY';
        }
        if ($sellCross && $close < $priorLow) {
            return 'SELL';
        }
        return null;
    }

    private function isSideway(array $bars, int $index): bool
    {
        if ($index < 20) {
            return false;
        }
        $slice = array_slice($bars, $index - 20, 20);
        $high = max(array_map(fn($bar) => (float)$bar['high'], $slice));
        $low = min(array_map(fn($bar) => (float)$bar['low'], $slice));
        $close = max(0.0000001, (float)$bars[$index]['close']);
        $rangePct = ($high - $low) / $close * 100;
        return $rangePct < 0.18;
    }

    private function aiScoreProxy(string $direction, ?array $contextState, float $zoneStrength, array $rules): float
    {
        $score = 52;
        if ($contextState !== null) {
            if (($direction === 'BUY' && $contextState['trend'] === 'bullish') || ($direction === 'SELL' && $contextState['trend'] === 'bearish')) {
                $score += 18;
            } else {
                $score -= 18;
            }
        }
        $score += min(20, max(0, ($zoneStrength - 50) / 2));
        $score += min(10, max(0, ((float)$rules['rrMin'] - 2) * 5));
        return max(0, min(100, round($score, 2)));
    }

    private function contextIndicators(array $bars): array
    {
        if (!$bars) {
            return [];
        }
        $closes = array_map(fn($bar) => (float)$bar['close'], $bars);
        $ema20 = $this->ema($closes, 20);
        $ema50 = $this->ema($closes, 50);
        $atr = $this->atr($bars, 14);
        $states = [];
        foreach ($bars as $index => $bar) {
            if (!is_numeric($ema20[$index] ?? null) || !is_numeric($ema50[$index] ?? null)) {
                continue;
            }
            $atrValue = max(0.0000001, (float)($atr[$index] ?? 0));
            $range = abs((float)$bar['high'] - (float)$bar['low']);
            $separation = abs((float)$ema20[$index] - (float)$ema50[$index]) / $atrValue;
            $strength = min(100, max(0, 50 + $separation * 18 + min(20, ($range / $atrValue) * 8)));
            $states[] = [
                'time' => $this->barTime($bar),
                'closeTime' => $this->barTime($bar) + (self::TIMEFRAME_SECONDS[strtoupper((string)($bar['_timeframe'] ?? 'H4'))] ?? 14400),
                'trend' => $ema20[$index] >= $ema50[$index] ? 'bullish' : 'bearish',
                'strength' => round($strength, 2),
            ];
        }
        return $states;
    }

    private function contextStateAt(array $states, int $time): ?array
    {
        $selected = null;
        foreach ($states as $state) {
            if (($state['closeTime'] ?? 0) <= $time) {
                $selected = $state;
            } else {
                break;
            }
        }
        return $selected;
    }

    private function ema(array $values, int $period): array
    {
        $result = array_fill(0, count($values), null);
        if (count($values) < $period) {
            return $result;
        }
        $sum = 0.0;
        for ($i = 0; $i < count($values); $i++) {
            $value = (float)$values[$i];
            if ($i < $period) {
                $sum += $value;
                if ($i === $period - 1) {
                    $result[$i] = $sum / $period;
                }
                continue;
            }
            $previous = (float)$result[$i - 1];
            $k = 2 / ($period + 1);
            $result[$i] = $value * $k + $previous * (1 - $k);
        }
        return $result;
    }

    private function atr(array $bars, int $period): array
    {
        $tr = array_fill(0, count($bars), null);
        for ($i = 0; $i < count($bars); $i++) {
            $high = (float)$bars[$i]['high'];
            $low = (float)$bars[$i]['low'];
            $prevClose = $i > 0 ? (float)$bars[$i - 1]['close'] : (float)$bars[$i]['close'];
            $tr[$i] = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
        }
        return $this->ema($tr, $period);
    }

    private function resolveDateRange(array $bars, string $rangeType, string $startDate, string $endDate): array
    {
        $first = $this->barTime($bars[0]);
        $last = $this->barTime($bars[count($bars) - 1]);
        if ($startDate !== '' || $endDate !== '') {
            $start = $startDate !== '' ? strtotime($startDate . ' 00:00:00 UTC') : $first;
            $end = $endDate !== '' ? strtotime($endDate . ' 23:59:59 UTC') : $last;
            return [
                'startTs' => $start === false ? $first : $start,
                'endTs' => $end === false ? $last : $end,
            ];
        }

        if (!in_array($rangeType, ['train', 'validation', 'final'], true)) {
            return ['startTs' => $first, 'endTs' => $last];
        }
        $count = count($bars);
        $trainEnd = max(0, (int)floor($count * 0.70) - 1);
        $validationEnd = max($trainEnd + 1, (int)floor($count * 0.85) - 1);
        if ($rangeType === 'train') {
            return ['startTs' => $this->barTime($bars[0]), 'endTs' => $this->barTime($bars[$trainEnd])];
        }
        if ($rangeType === 'validation') {
            return ['startTs' => $this->barTime($bars[$trainEnd + 1]), 'endTs' => $this->barTime($bars[$validationEnd])];
        }
        return ['startTs' => $this->barTime($bars[$validationEnd + 1]), 'endTs' => $last];
    }

    private function filterBars(array $bars, int|false $startTs, int|false $endTs): array
    {
        $start = $startTs === false ? PHP_INT_MIN : $startTs;
        $end = $endTs === false ? PHP_INT_MAX : $endTs;
        return array_values(array_filter($bars, fn($bar) => $this->barTime($bar) >= $start && $this->barTime($bar) <= $end));
    }

    private function normalizeBars(array $bars): array
    {
        $normalized = [];
        foreach ($bars as $index => $bar) {
            if (!is_array($bar)) {
                continue;
            }
            foreach (['open', 'high', 'low', 'close'] as $key) {
                if (!isset($bar[$key]) || !is_numeric($bar[$key])) {
                    continue 2;
                }
                $bar[$key] = (float)$bar[$key];
            }
            $bar['_source_index'] = $index;
            $normalized[] = $bar;
        }
        usort($normalized, fn($a, $b) => $this->barTime($a) <=> $this->barTime($b));
        return $normalized;
    }

    private function validateRawBars(mixed $bars, string $symbol, string $timeframe, bool $required = true): void
    {
        if (!is_array($bars) || count($bars) === 0) {
            if ($required) {
                throw new InvalidArgumentException("Thiếu dữ liệu nến cho {$symbol} {$timeframe}.");
            }
            return;
        }

        $seen = [];
        $invalidOhlc = 0;
        $duplicates = 0;
        foreach ($bars as $index => $bar) {
            if (!is_array($bar)) {
                $invalidOhlc++;
                continue;
            }
            $time = $this->barTime($bar);
            if ($time <= 0) {
                throw new InvalidArgumentException("Mốc thời gian nến không hợp lệ tại {$symbol} {$timeframe}, dòng {$index}.");
            }
            if (isset($seen[$time])) {
                $duplicates++;
            }
            $seen[$time] = true;

            foreach (['open', 'high', 'low', 'close'] as $key) {
                if (!isset($bar[$key]) || !is_numeric($bar[$key]) || !is_finite((float)$bar[$key])) {
                    $invalidOhlc++;
                    continue 2;
                }
            }
            $open = (float)$bar['open'];
            $high = (float)$bar['high'];
            $low = (float)$bar['low'];
            $close = (float)$bar['close'];
            if ($high < max($open, $close) || $low > min($open, $close) || $high < $low) {
                $invalidOhlc++;
            }
        }

        if ($duplicates > 0) {
            throw new InvalidArgumentException("Phát hiện mốc thời gian nến bị trùng cho {$symbol} {$timeframe}.");
        }
        if ($invalidOhlc > 0) {
            throw new InvalidArgumentException("Phát hiện {$invalidOhlc} dòng OHLC không hợp lệ cho {$symbol} {$timeframe}.");
        }
    }

    private function loadMarketPayload(string $symbol, string $timeframe, bool $required = true): ?array
    {
        $file = 'market_data/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', strtoupper($symbol)) . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', strtoupper($timeframe)) . '.json';
        $payload = $this->store?->readJson($file, null);
        if (!is_array($payload)) {
            if ($required) {
                throw new InvalidArgumentException('Thiếu tệp dữ liệu thị trường: ' . $file);
            }
            return null;
        }
        foreach (($payload['bars'] ?? []) as &$bar) {
            if (is_array($bar)) {
                $bar['_timeframe'] = strtoupper($timeframe);
            }
        }
        unset($bar);
        return $payload;
    }

    private function persistRun(array $result): void
    {
        if ($this->store === null) {
            return;
        }
        $summary = $result['summary'] ?? [];
        $trades = $result['trades'] ?? [];
        $runs = $this->store->readJson(self::RUNS_FILE, []);
        $storedTrades = $this->store->readJson(self::TRADES_FILE, []);
        if (!is_array($runs)) {
            $runs = [];
        }
        if (!is_array($storedTrades)) {
            $storedTrades = [];
        }
        $runId = (string)($summary['backtestRunId'] ?? $summary['id'] ?? '');
        if ($runId !== '') {
            $runs = array_values(array_filter($runs, fn($run) => ($run['backtestRunId'] ?? $run['id'] ?? '') !== $runId));
            $storedTrades = array_values(array_filter($storedTrades, fn($trade) => ($trade['backtestRunId'] ?? '') !== $runId));
        }
        $runs[] = $summary;
        foreach ($this->tradesForPersistence($trades) as $trade) {
            if (is_array($trade)) {
                $storedTrades[] = $trade;
            }
        }
        if (count($runs) > 200) {
            $keepIds = array_map(fn($run) => $run['backtestRunId'] ?? '', array_slice($runs, -200));
            $runs = array_values(array_filter($runs, fn($run) => in_array($run['backtestRunId'] ?? '', $keepIds, true)));
            $storedTrades = array_values(array_filter($storedTrades, fn($trade) => in_array($trade['backtestRunId'] ?? '', $keepIds, true)));
        }
        $storedTrades = $this->compactPersistedTrades($storedTrades);
        $this->store->writeJson(self::RUNS_FILE, $runs);
        $this->store->writeJson(self::TRADES_FILE, $storedTrades);
    }

    private function persistV2Copy(array $result, array $strategyVersion, array $input): void
    {
        if ($this->v2Storage === null) {
            return;
        }

        try {
            $this->v2Storage->storeAfterLegacyBacktest($result, $strategyVersion, $input);
        } catch (Throwable) {
            // V2 storage is additive. Legacy backtest persistence must remain fail-open.
        }
    }

    private function tradesForPersistence(array $trades): array
    {
        $result = [];
        $skipped = 0;
        foreach ($trades as $trade) {
            if (!is_array($trade)) {
                continue;
            }
            if (($trade['result'] ?? '') !== 'SKIPPED') {
                $result[] = $trade;
                continue;
            }
            if ((string)($trade['reasonCode'] ?? '') === 'LIMIT_NOT_FILLED' || $skipped < 250) {
                $result[] = $trade;
                $skipped++;
            }
        }
        return $result;
    }

    private function compactPersistedTrades(array $trades): array
    {
        $result = [];
        $skippedByRun = [];
        foreach ($trades as $trade) {
            if (!is_array($trade)) {
                continue;
            }
            if (($trade['result'] ?? '') !== 'SKIPPED') {
                $result[] = $trade;
                continue;
            }
            $runId = (string)($trade['backtestRunId'] ?? '');
            $skippedByRun[$runId] = ($skippedByRun[$runId] ?? 0) + 1;
            if ((string)($trade['reasonCode'] ?? '') === 'LIMIT_NOT_FILLED' || $skippedByRun[$runId] <= 250) {
                $result[] = $trade;
            }
        }
        return $result;
    }

    private function summaryNotes(array $summary, array $rules, array $contextBars): string
    {
        $notes = [];
        $notes[] = 'Deterministic H4/M15 price-action backtest. No live trading API is called.';
        $notes[] = 'Entries use detected H4 supply/demand zones, closed-candle M15 reaction/structure confirmation, and pending limit orders.';
        $notes[] = 'If SL and TP are both touched in one candle, SL is chosen first by a conservative deterministic rule.';
        if (!$contextBars) {
            $notes[] = 'Context timeframe data was missing; H4 zone detection cannot create setups.';
        }
        if (!empty($rules['filters']['avoidNewsWindow'])) {
            $notes[] = 'News filter is reserved because no economic-calendar feed exists yet.';
        }
        return implode(' ', $notes);
    }

    private function longestStreak(array $trades, string $result): int
    {
        $best = 0;
        $current = 0;
        foreach ($trades as $trade) {
            if (($trade['result'] ?? '') === $result) {
                $current++;
                $best = max($best, $current);
            } else {
                $current = 0;
            }
        }
        return $best;
    }

    private function normalizeRules(array $rules): array
    {
        return StrategyRuleParser::normalize($rules);
    }

    private function runId(array $strategyVersion, string $symbol, string $timeframe, string $rangeType, array $range, array $settings): string
    {
        $seed = json_encode([
            'version' => $strategyVersion['id'] ?? '',
            'rules' => $strategyVersion['rulesJson'] ?? [],
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'rangeType' => $rangeType,
            'range' => $range,
            'settings' => $settings,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return 'bt_' . substr(sha1((string)$seed), 0, 16);
    }

    private function tradeId(array $strategyVersion, string $symbol, array $bar, int $index): string
    {
        return 'tr_' . substr(sha1((string)($strategyVersion['id'] ?? 'strategy') . '_' . $symbol . '_' . $this->barTime($bar) . '_' . $index), 0, 12);
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
        return (string)($bar['time_iso'] ?? $this->iso($this->barTime($bar)));
    }

    private function iso(int $timestamp): string
    {
        return gmdate('c', $timestamp);
    }

    private function pointSize(string $symbol): float
    {
        return str_contains(strtoupper($symbol), 'JPY') ? 0.001 : 0.00001;
    }

    private function priceDigits(string $symbol): int
    {
        return str_contains(strtoupper($symbol), 'JPY') ? 3 : 5;
    }
}
