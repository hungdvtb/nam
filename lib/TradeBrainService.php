<?php

declare(strict_types=1);

final class TradeBrainService
{
    private const PROFILE_FILE = 'tradebrain/tradebrain_profile.json';
    private const MEMORY_FILE = 'tradebrain/tradebrain_memory.json';
    private const DECISIONS_FILE = 'tradebrain/ai_decisions.json';
    private const PROPOSALS_FILE = 'tradebrain/strategy_upgrade_proposals.json';

    public function __construct(
        private JsonStore $store,
        private StrategyVersionService $versions,
        private BacktestEngine $backtests,
        private StrategyEvaluator $evaluator,
        private TradeBrainAiService $aiService,
        private AiUsageTracker $usageTracker,
    ) {
        $this->ensureProfile();
    }

    public function profile(): array
    {
        return $this->ensureProfile();
    }

    public function memory(string $strategyVersionId = '', string $type = ''): array
    {
        $items = $this->store->readJson(self::MEMORY_FILE, []);
        if (!is_array($items)) {
            return [];
        }

        $items = array_values(array_filter($items, function ($item) use ($strategyVersionId, $type) {
            if (!is_array($item)) {
                return false;
            }
            if ($strategyVersionId !== '' && (string)($item['relatedStrategyVersionId'] ?? '') !== $strategyVersionId) {
                return false;
            }
            if ($type !== '' && (string)($item['type'] ?? '') !== $type) {
                return false;
            }
            return true;
        }));
        usort($items, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        return $items;
    }

    public function decisions(int $limit = 50): array
    {
        $items = $this->store->readJson(self::DECISIONS_FILE, []);
        if (!is_array($items)) {
            return [];
        }
        usort($items, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        return array_slice(array_values(array_filter($items, 'is_array')), 0, $limit);
    }

    public function proposals(int $limit = 50): array
    {
        $items = $this->store->readJson(self::PROPOSALS_FILE, []);
        if (!is_array($items)) {
            return [];
        }
        usort($items, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        return array_slice(array_values(array_filter($items, 'is_array')), 0, $limit);
    }

    public function addMemory(array $lesson): ?array
    {
        $type = (string)($lesson['type'] ?? 'backtest_lesson');
        if (!in_array($type, ['strategy_lesson', 'backtest_lesson', 'live_lesson', 'risk_warning', 'system_note'], true)) {
            $type = 'system_note';
        }

        $record = [
            'id' => (string)($lesson['id'] ?? 'mem_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4))),
            'type' => $type,
            'title' => trim((string)($lesson['title'] ?? 'TradeBrain lesson')),
            'content' => trim((string)($lesson['content'] ?? '')),
            'source' => (string)($lesson['source'] ?? 'tradebrain'),
            'confidence' => max(0, min(100, (float)($lesson['confidence'] ?? 0))),
            'relatedStrategyVersionId' => (string)($lesson['relatedStrategyVersionId'] ?? ''),
            'createdAt' => (string)($lesson['createdAt'] ?? gmdate('c')),
        ];

        if ($record['content'] === '') {
            return null;
        }

        $items = $this->store->readJson(self::MEMORY_FILE, []);
        if (!is_array($items)) {
            $items = [];
        }

        $hash = $this->lessonHash($record);
        foreach ($items as $item) {
            if (is_array($item) && $this->lessonHash($item) === $hash) {
                return $item;
            }
        }

        array_unshift($items, $record);
        $this->store->writeJson(self::MEMORY_FILE, array_slice($items, 0, 1000));
        return $record;
    }

    public function evolve(string $parentVersionId, string $symbol, array $options = []): array
    {
        $parent = $this->versions->find($parentVersionId);
        if ($parent === null) {
            throw new InvalidArgumentException('Không tìm thấy phiên bản chiến lược gốc.');
        }

        $rules = is_array($parent['rulesJson'] ?? null) ? $parent['rulesJson'] : [];
        $symbol = strtoupper(trim($symbol !== '' ? $symbol : (string)($rules['symbols'][0] ?? '')));
        if ($symbol === '') {
            throw new InvalidArgumentException('Bạn chưa chọn cặp tiền cho Tiến hóa chiến lược.');
        }

        $parentTrain = $this->runBacktest($parent, $symbol, 'train', $options);
        $parentTrainSummary = $parentTrain['summary'] ?? $parentTrain;
        try {
            $parentValidation = $this->runBacktest($parent, $symbol, 'validation', $options);
        } catch (Throwable $error) {
            $reasons = $this->manualCalibrationBaseReasons();
            $reasons[] = 'kiểm thử dữ liệu xác thực bị lỗi: ' . $error->getMessage();
            $reasons[] = 'dữ liệu xác thực chưa đủ mẫu (INSUFFICIENT_SAMPLE)';
            throw new RuntimeException('Chưa đủ dữ liệu hiệu chỉnh thủ công (manual calibration) để TradeBrain học chiến lược này. ' . implode('; ', $reasons) . '.');
        }
        $parentValidationSummary = $parentValidation['summary'] ?? $parentValidation;
        $this->assertEvolutionCalibrationReady($parentTrainSummary, $parentValidationSummary);

        $usageGate = $this->usageTracker->canCall(2500, 0.0);
        if (!$usageGate['allowed']) {
            throw new RuntimeException('TradeBrain AI chưa sẵn sàng: ' . $usageGate['reason']);
        }

        $memoryLessons = $this->memory((string)$parent['id']);
        $aiInput = [
            'profile' => $this->profile(),
            'strategyVersionId' => $parent['id'],
            'rulesJson' => $parent['rulesJson'],
            'trainSummary' => $parentTrain['summary'] ?? $parentTrain,
            'trainTrades' => $this->summarizeTradesForAi($parentTrain['trades'] ?? []),
            'memoryLessons' => array_slice($memoryLessons, 0, 30),
        ];
        $proposalJson = $this->aiService->proposeStrategyUpgrade($aiInput);
        $this->assertAiProposal($proposalJson);
        $this->usageTracker->record('mock_tradebrain_strategy_upgrade', 2500, 900, 0.0, true);

        $newRules = $this->versions->applyChanges($parent['rulesJson'] ?? [], $proposalJson['proposedChanges']);
        $newVersion = $this->versions->createFromParent(
            (string)$parent['id'],
            (string)$proposalJson['newVersionName'],
            'ai',
            'Created by TradeBrain Strategy Evolution Engine. Pending deterministic validation.',
            $newRules
        );

        $proposal = [
            'id' => 'prop_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)),
            'parentStrategyVersionId' => (string)$parent['id'],
            'newStrategyVersionId' => (string)$newVersion['id'],
            'diagnosis' => $proposalJson['diagnosis'],
            'lessons' => $proposalJson['lessons'],
            'proposedChanges' => $proposalJson['proposedChanges'],
            'expectedImpact' => (string)$proposalJson['expectedImpact'],
            'riskWarning' => (string)$proposalJson['riskWarning'],
            'status' => 'testing',
            'createdAt' => gmdate('c'),
        ];
        $this->persistProposal($proposal);

        $this->logDecision([
            'decisionType' => 'strategy_upgrade',
            'inputSummary' => [
                'parentStrategyVersionId' => $parent['id'],
                'symbol' => $symbol,
                'trainSummary' => $parentTrain['summary'] ?? $parentTrain,
                'memoryLessonCount' => count($memoryLessons),
            ],
            'outputJson' => $proposalJson,
            'relatedStrategyVersionId' => (string)$newVersion['id'],
            'relatedBacktestRunId' => (string)($parentTrain['backtestRunId'] ?? $parentTrain['id'] ?? ''),
        ]);

        foreach ($proposalJson['lessons'] as $lesson) {
            $this->addMemory([
                'type' => 'backtest_lesson',
                'title' => (string)($lesson['title'] ?? 'TradeBrain lesson'),
                'content' => (string)($lesson['content'] ?? ''),
                'source' => $proposal['id'],
                'confidence' => (float)($lesson['confidence'] ?? 0),
                'relatedStrategyVersionId' => (string)$newVersion['id'],
            ]);
        }

        $newTrain = $this->runBacktest($newVersion, $symbol, 'train', $options);
        $newValidation = $this->runBacktest($newVersion, $symbol, 'validation', $options);
        $parentRuns = [
            'train' => $parentTrain,
            'validation' => $parentValidation,
        ];
        $newRuns = [
            'train' => $newTrain,
            'validation' => $newValidation,
        ];

        $validationLooksPassable = $this->validationLooksPassable($parentValidation, $newValidation, $parent, $newVersion);
        if ($validationLooksPassable) {
            $parentRuns['final'] = $this->runBacktest($parent, $symbol, 'final', $options);
            $newRuns['final'] = $this->runBacktest($newVersion, $symbol, 'final', $options);
        }

        $evaluation = $this->evaluator->evaluate($parent, $newVersion, $parentRuns, $newRuns);
        $proposal['status'] = $evaluation['passed'] ? 'accepted' : 'rejected';
        $this->persistProposal($proposal);

        $newStatus = $evaluation['passed'] ? 'final_test_passed' : 'rejected';
        $newVersion = $this->versions->updateStatus(
            (string)$newVersion['id'],
            $newStatus,
            $evaluation['passed']
                ? 'TradeBrain evaluator passed. Human approval is still required before paper/live.'
                : implode('; ', $evaluation['failReasons'])
        );

        $this->logDecision([
            'decisionType' => 'backtest_review',
            'inputSummary' => [
                'parentStrategyVersionId' => $parent['id'],
                'newStrategyVersionId' => $newVersion['id'],
                'symbol' => $symbol,
            ],
            'outputJson' => [
                'evaluationId' => $evaluation['id'],
                'passed' => $evaluation['passed'],
                'failReasons' => $evaluation['failReasons'],
            ],
            'relatedStrategyVersionId' => (string)$newVersion['id'],
            'relatedBacktestRunId' => (string)($newValidation['backtestRunId'] ?? $newValidation['id'] ?? ''),
        ]);

        return [
            'ok' => true,
            'parentVersion' => $parent,
            'newVersion' => $newVersion,
            'proposal' => $proposal,
            'runs' => [
                'parent' => $parentRuns,
                'new' => $newRuns,
            ],
            'evaluation' => $evaluation,
            'memory' => $this->memory((string)$newVersion['id']),
            'aiUsage' => $this->usageTracker->today(),
        ];
    }

    private function runBacktest(array $version, string $symbol, string $split, array $options): array
    {
        $input = [
            'symbol' => $symbol,
            'dataSplit' => $split,
            'initialBalance' => $options['initialBalance'] ?? $options['initial_balance'] ?? 10000,
            'spreadPoints' => $options['spreadPoints'] ?? $options['spread_points'] ?? null,
            'feeR' => $options['feeR'] ?? $options['fee_r'] ?? 0.02,
            'slippagePoints' => $options['slippagePoints'] ?? $options['slippage_points'] ?? 2,
            'maxBarsInTrade' => $options['maxBarsInTrade'] ?? 96,
            'persist' => true,
        ];
        $result = $this->backtests->run($version, $input);
        $summary = $result['summary'] ?? $result;
        if ($split === 'validation') {
            $this->versions->attachSplitSummary((string)($version['id'] ?? ''), 'validation', $summary);
        } elseif ($split === 'final') {
            $this->versions->attachSplitSummary((string)($version['id'] ?? ''), 'final', $summary);
        } else {
            $this->versions->attachBacktestSummary((string)($version['id'] ?? ''), $summary);
        }
        return $result;
    }

    private function assertEvolutionCalibrationReady(array $trainSummary, array $validationSummary): void
    {
        $reasons = $this->manualCalibrationBaseReasons();
        if ((int)($trainSummary['totalTrades'] ?? 0) < 30 || (string)($trainSummary['sampleStatus'] ?? '') === 'INSUFFICIENT_SAMPLE') {
            $reasons[] = 'dữ liệu huấn luyện chưa đủ mẫu hoặc có dưới 30 lệnh (INSUFFICIENT_SAMPLE)';
        }
        if ((int)($validationSummary['totalTrades'] ?? 0) < 50) {
            $reasons[] = 'dữ liệu xác thực có dưới 50 lệnh';
        }
        if ((string)($validationSummary['sampleStatus'] ?? '') === 'INSUFFICIENT_SAMPLE') {
            $reasons[] = 'dữ liệu xác thực chưa đủ mẫu (INSUFFICIENT_SAMPLE)';
        }
        if ($reasons) {
            throw new RuntimeException('Chưa đủ dữ liệu hiệu chỉnh thủ công (manual calibration) để TradeBrain học chiến lược này. ' . implode('; ', $reasons) . '.');
        }
    }

    private function manualCalibrationBaseReasons(): array
    {
        $reviews = new ManualReviewService($this->store);
        $counts = $reviews->calibrationCounts();
        $reasons = [];
        if ((int)$counts['total'] < 30) {
            $reasons[] = 'manualReviewsTotal < 30 - tổng số mẫu đã chấm dưới 30';
        }
        if ((int)$counts['zone'] < 10) {
            $reasons[] = 'mẫu vùng giá đã chấm dưới 10';
        }
        if ((int)$counts['candidate'] < 10) {
            $reasons[] = 'mẫu ứng viên đã chấm dưới 10';
        }
        return $reasons;
    }

    private function validationLooksPassable(array $parentValidation, array $newValidation, array $parent, array $newVersion): bool
    {
        $parentSummary = $parentValidation['summary'] ?? $parentValidation;
        $newSummary = $newValidation['summary'] ?? $newValidation;
        if (($newSummary['sampleStatus'] ?? '') === 'INSUFFICIENT_SAMPLE') {
            return false;
        }
        if ((int)($newSummary['totalTrades'] ?? 0) < 50) {
            return false;
        }
        if ((float)($newVersion['rulesJson']['riskPercent'] ?? 0) > (float)($parent['rulesJson']['riskPercent'] ?? 0)) {
            return false;
        }
        if ((float)($newSummary['profitFactor'] ?? 0) < (float)($parentSummary['profitFactor'] ?? 0)) {
            return false;
        }
        if ((float)($newSummary['maxDrawdownPercent'] ?? 0) > (float)($parentSummary['maxDrawdownPercent'] ?? 0) * 1.20) {
            return false;
        }
        $parentProfit = (float)($parentSummary['netProfitPercent'] ?? 0);
        $newProfit = (float)($newSummary['netProfitPercent'] ?? 0);
        $profitBetter = $newProfit >= $parentProfit + max(0.01, abs($parentProfit) * 0.10);
        $profitNear = abs($newProfit - $parentProfit) <= 2.0;
        $drawdownBetter = (float)($newSummary['maxDrawdownPercent'] ?? 0) <= (float)($parentSummary['maxDrawdownPercent'] ?? 0) * 0.80;
        return $profitBetter || ($profitNear && $drawdownBetter);
    }

    private function ensureProfile(): array
    {
        $profile = $this->store->readJson(self::PROFILE_FILE, null);
        if (is_array($profile)) {
            return $profile;
        }

        $profile = [
            'id' => 'tradebrain',
            'name' => 'TradeBrain',
            'basePrompt' => 'You are TradeBrain, the single AI responsible for strategy analysis. You never invent backtest results and never place live orders.',
            'tradingStyle' => 'H4 context with M15 entry confirmation, supply/demand zones, strict risk validation.',
            'riskMode' => 'conservative',
            'allowedMarkets' => ['forex'],
            'forbiddenActions' => [
                'live_trading',
                'mt5_order_execution',
                'order_send',
                'increase_risk_to_improve_results',
                'overwrite_parent_strategy_version',
            ],
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
        ];
        $this->store->writeJson(self::PROFILE_FILE, $profile);
        return $profile;
    }

    private function persistProposal(array $proposal): void
    {
        $items = $this->store->readJson(self::PROPOSALS_FILE, []);
        if (!is_array($items)) {
            $items = [];
        }
        $found = false;
        foreach ($items as $index => $item) {
            if (is_array($item) && ($item['id'] ?? '') === $proposal['id']) {
                $items[$index] = $proposal;
                $found = true;
                break;
            }
        }
        if (!$found) {
            array_unshift($items, $proposal);
        }
        $this->store->writeJson(self::PROPOSALS_FILE, array_slice($items, 0, 200));
    }

    private function logDecision(array $decision): array
    {
        $items = $this->store->readJson(self::DECISIONS_FILE, []);
        if (!is_array($items)) {
            $items = [];
        }

        $record = [
            'id' => 'dec_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)),
            'decisionType' => (string)($decision['decisionType'] ?? 'backtest_review'),
            'inputSummary' => $decision['inputSummary'] ?? [],
            'outputJson' => $decision['outputJson'] ?? [],
            'relatedStrategyVersionId' => (string)($decision['relatedStrategyVersionId'] ?? ''),
            'relatedBacktestRunId' => (string)($decision['relatedBacktestRunId'] ?? ''),
            'createdAt' => gmdate('c'),
        ];
        array_unshift($items, $record);
        $this->store->writeJson(self::DECISIONS_FILE, array_slice($items, 0, 500));
        return $record;
    }

    private function assertAiProposal(array $proposal): void
    {
        foreach (['diagnosis', 'lessons', 'proposedChanges', 'newVersionName', 'expectedImpact', 'riskWarning'] as $key) {
            if (!array_key_exists($key, $proposal)) {
                throw new RuntimeException('TradeBrain AI trả về JSON không hợp lệ: thiếu ' . $key);
            }
        }
        if (!is_array($proposal['diagnosis']) || !is_array($proposal['lessons']) || !is_array($proposal['proposedChanges'])) {
            throw new RuntimeException('TradeBrain AI trả về các trường mảng không hợp lệ.');
        }
        foreach ($proposal['proposedChanges'] as $change) {
            if (!is_array($change) || !isset($change['field'], $change['newValue'], $change['reason'])) {
                throw new RuntimeException('TradeBrain AI trả về mục đề xuất thay đổi không hợp lệ.');
            }
        }
    }

    private function summarizeTradesForAi(array $trades): array
    {
        $groups = [
            ...array_slice(array_values(array_filter($trades, fn($trade) => ($trade['result'] ?? '') === 'LOSS')), 0, 120),
            ...array_slice(array_values(array_filter($trades, fn($trade) => ($trade['result'] ?? '') === 'WIN')), 0, 80),
            ...array_slice(array_values(array_filter($trades, fn($trade) => ($trade['result'] ?? '') === 'SKIPPED')), 0, 80),
        ];

        return array_map(fn($trade) => [
            'id' => $trade['id'] ?? '',
            'result' => $trade['result'] ?? '',
            'direction' => $trade['direction'] ?? '',
            'entryTime' => $trade['entryTime'] ?? '',
            'rrResult' => $trade['rrResult'] ?? 0,
            'profitLoss' => $trade['profitLoss'] ?? 0,
            'reasonEnter' => $trade['reasonEnter'] ?? '',
            'reasonExit' => $trade['reasonExit'] ?? '',
            'reasonSkipped' => $trade['reasonSkipped'] ?? '',
            'tags' => $trade['tags'] ?? [],
        ], $groups);
    }

    private function lessonHash(array $lesson): string
    {
        return sha1(strtolower(trim((string)($lesson['title'] ?? '')) . "\n" . trim((string)($lesson['content'] ?? ''))));
    }
}
