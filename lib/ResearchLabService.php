<?php

declare(strict_types=1);

require_once __DIR__ . '/BacktestEngine.php';
require_once __DIR__ . '/StrategyVersionService.php';
require_once __DIR__ . '/StrategyRuleParser.php';

final class ResearchLabService
{
    private const FILE = 'research_lab/sessions.json';

    public function __construct(
        private JsonStore $store,
        private StrategyVersionService $versions,
        private BacktestEngine $backtests,
        private ?object $llmGateway = null
    ) {}

    public function sessions(int $limit = 20): array
    {
        $items = $this->load();
        usort($items, fn($a, $b) => strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? '')));
        return array_slice($items, 0, max(1, min(100, $limit)));
    }

    public function create(array $payload): array
    {
        $symbol = strtoupper(trim((string)($payload['symbol'] ?? '')));
        if ($symbol === '') throw new InvalidArgumentException('Bạn chưa chọn cặp tiền cho AI Lab.');
        $method = $this->method((string)($payload['methodProfile'] ?? 'h4_trend_zone_m15_pa'));
        $aiProvider = $this->aiProvider((string)($payload['aiProvider'] ?? $payload['ai_provider'] ?? 'openai_chat'));
        $now = gmdate('c');
        $session = [
            'id' => 'lab_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)),
            'title' => 'AI Lab ' . $symbol . ' - ' . $this->methodLabel($method),
            'symbol' => $symbol,
            'status' => 'ACTIVE',
            'statusText' => 'Sẵn sàng chạy vòng nghiên cứu đầu tiên.',
            'methodProfile' => $method,
            'methodProfileLabel' => $this->methodLabel($method),
            'aiProvider' => $aiProvider,
            'aiProviderLabel' => $this->aiProviderLabel($aiProvider),
            'goal' => 'AI nghiên cứu đề xuất rule, Backtester kiểm chứng, rồi AI dùng kết quả để sửa vòng tiếp theo.',
            'kpi' => $this->kpi(is_array($payload['kpi'] ?? null) ? $payload['kpi'] : []),
            'settings' => $this->settings(is_array($payload['settings'] ?? null) ? $payload['settings'] : []),
            'maxCycles' => max(1, min(100, (int)($payload['maxCycles'] ?? 20))),
            'cycles' => [],
            'bestCycle' => null,
            'bestStrategyVersionId' => '',
            'safety' => ['liveTrading' => false, 'mt5OrderExecution' => false, 'backtestOnly' => true],
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
        $items = $this->load();
        array_unshift($items, $session);
        $this->save($items);
        return ['ok' => true, 'research_lab_session' => $session, 'research_lab_sessions' => $this->sessions(20)];
    }

    public function runCycle(string $id): array
    {
        $items = $this->load();
        $idx = $this->indexOf($items, $id);
        if ($idx < 0) throw new InvalidArgumentException('Không tìm thấy phiên AI Lab.');
        $session = $items[$idx];
        $cycles = array_values(array_filter(is_array($session['cycles'] ?? null) ? $session['cycles'] : [], 'is_array'));
        $max = max(1, min(100, (int)($session['maxCycles'] ?? 20)));
        if (($session['status'] ?? '') === 'PASSED' || count($cycles) >= $max) {
            if (($session['status'] ?? '') !== 'PASSED') {
                $session['status'] = 'FAILED';
                $session['statusText'] = 'Đã chạy hết số vòng tối đa nhưng chưa đạt KPI.';
                $session['updatedAt'] = gmdate('c');
                $items[$idx] = $session;
                $this->save($items);
            }
            return ['ok' => true, 'research_lab_session' => $session, 'research_lab_sessions' => $this->sessions(20)];
        }

        $cycleNo = count($cycles) + 1;
        $symbol = strtoupper((string)($session['symbol'] ?? ''));
        $kpi = $this->kpi(is_array($session['kpi'] ?? null) ? $session['kpi'] : []);
        $settings = $this->settings(is_array($session['settings'] ?? null) ? $session['settings'] : []);
        $proposal = $this->proposal($session, $symbol, $this->method((string)($session['methodProfile'] ?? 'h4_trend_zone_m15_pa')), $cycleNo, $kpi, is_array($session['bestCycle'] ?? null) ? $session['bestCycle'] : null, $cycles[count($cycles) - 1] ?? null);
        $version = [
            'id' => $id . '_cycle_' . $cycleNo,
            'name' => $proposal['title'],
            'createdAt' => gmdate('c'),
            'createdBy' => 'ai',
            'status' => 'draft',
            'rulesJson' => $proposal['rulesJson'],
            'notes' => 'Virtual AI Lab candidate. Backtest only.',
        ];
        $result = $this->backtests->run($version, [
            'symbol' => $symbol,
            'dataSplit' => 'full',
            'initialBalance' => $settings['initialBalance'],
            'spreadPoints' => $settings['spreadPoints'],
            'feeR' => $settings['feeR'],
            'slippagePoints' => $settings['slippagePoints'],
            'maxBarsInTrade' => $settings['maxBarsInTrade'],
            'rulesPreset' => 'current',
            'debugLevel' => 1,
            'persist' => false,
        ]);
        $summary = $this->summary(is_array($result['summary'] ?? null) ? $result['summary'] : $result);
        $diagnostics = $this->diagnostics(is_array(($result['summary']['diagnostics'] ?? null)) ? $result['summary']['diagnostics'] : []);
        $trades = $this->trades(is_array($result['trades'] ?? null) ? $result['trades'] : []);
        $eval = $this->evaluate($summary, $kpi);
        $review = $this->review($summary, $diagnostics, $eval, $kpi);
        $cycle = [
            'cycle' => $cycleNo,
            'status' => $eval['passed'] ? 'PASSED' : 'NEEDS_WORK',
            'score' => $eval['score'],
            'failReasons' => $eval['failReasons'],
            'researchAgent' => [
                'role' => 'AI số 1 - Người nghiên cứu',
                'provider' => $proposal['provider'],
                'providerReason' => $proposal['providerReason'],
                'llm' => $proposal['llm'],
                'hypothesis' => $proposal['hypothesis'],
                'methodFamily' => $proposal['family'],
                'changePlan' => $proposal['changes'],
                'rulesJson' => $proposal['rulesJson'],
            ],
            'backtestAgent' => [
                'role' => 'AI số 2 - Máy kiểm chứng',
                'engine' => 'BacktestEngine::run',
                'data' => 'Quét dữ liệu nến đã tải trong app, không đặt lệnh MT5.',
                'summary' => $summary,
                'diagnostics' => $diagnostics,
                'trades' => $trades,
            ],
            'reviewAgent' => [
                'role' => 'AI số 1 - Kết luận & bước sau',
                'verdict' => $review['verdict'],
                'nextAction' => $review['nextAction'],
                'nextInstruction' => $review['nextInstruction'],
            ],
            'createdAt' => gmdate('c'),
        ];
        $cycles[] = $cycle;
        $session['cycles'] = $cycles;
        $session['bestCycle'] = $this->best($session['bestCycle'] ?? null, $cycle);
        $session['status'] = $eval['passed'] ? 'PASSED' : ($cycleNo >= $max ? 'FAILED' : 'ACTIVE');
        $session['statusText'] = $eval['passed'] ? 'Đã có bộ luật đạt KPI. Hãy xem lại từng lệnh trên chart trước khi dùng thật.' : ($cycleNo >= $max ? 'Đã chạy hết số vòng tối đa nhưng chưa đạt KPI.' : $review['nextInstruction']);
        $session['updatedAt'] = gmdate('c');
        $savedVersion = null;
        if ($eval['passed']) {
            $savedVersion = $this->versions->createRootFromRules('AI Lab ' . $symbol . ' passed - ' . (string)$session['methodProfileLabel'], 'ai', 'Created by AI Lab two-agent loop. Human chart review is still required.', $proposal['rulesJson'], $this->sourceText($session, $cycle));
            $savedVersion = $this->versions->attachBacktestSummary((string)$savedVersion['id'], $summary) ?? $savedVersion;
            $session['bestStrategyVersionId'] = (string)($savedVersion['id'] ?? '');
        }
        $items[$idx] = $session;
        $this->save($items);
        return ['ok' => true, 'research_lab_session' => $session, 'research_lab_cycle' => $cycle, 'best_strategy_version' => $savedVersion, 'strategy_versions' => $savedVersion ? $this->versions->list() : null, 'research_lab_sessions' => $this->sessions(20)];
    }
    private function proposal(array $session, string $symbol, string $method, int $cycle, array $kpi, ?array $best, ?array $prev): array
    {
        $rules = is_array($best['researchAgent']['rulesJson'] ?? null) ? $best['researchAgent']['rulesJson'] : $this->seed($symbol, $method);
        $rules = StrategyRuleParser::normalize($rules);
        $rules['symbols'] = [$symbol];
        $changes = [];
        $aiProvider = $this->aiProvider((string)($session['aiProvider'] ?? 'internal'));
        $aiPlan = $this->buildAiResearchPlan($aiProvider, $symbol, $method, $cycle, $kpi, $rules, $best, $prev);
        $family = (string)($aiPlan['family'] ?? $this->family($cycle, $prev, $kpi));
        if (!isset($this->researchFamilies()[$family])) $family = $this->family($cycle, $prev, $kpi);
        $family = $this->avoidStaleFamily($family, $session, $prev, $cycle, $kpi);
        $apply = function (string $path, mixed $value, string $reason) use (&$rules, &$changes): void {
            $old = $this->get($rules, explode('.', $path));
            $this->set($rules, explode('.', $path), $value);
            $changes[] = ['field' => $path, 'oldValue' => $old, 'newValue' => $value, 'reason' => $reason];
        };
        match ($family) {
            'baseline' => $this->baseline($apply),
            'more_samples' => $this->moreSamples($apply, $cycle),
            'quality' => $this->quality($apply, $cycle),
            'rr' => $this->rr($apply, $kpi, $cycle),
            default => $this->stable($apply, $cycle),
        };
        $rules = StrategyRuleParser::normalize($rules);
        return [
            'title' => 'AI Lab vòng ' . $cycle . ' - ' . $this->familyLabel($family),
            'family' => $family,
            'hypothesis' => $this->hypothesis($family, $symbol),
            'provider' => $aiProvider,
            'providerReason' => (string)($aiPlan['reason'] ?? 'AI nội bộ chọn hướng tối ưu theo KPI còn thiếu.'),
            'llm' => $aiPlan['llm'] ?? null,
            'changes' => $changes,
            'rulesJson' => $rules,
        ];
    }

    private function researchFamilies(): array
    {
        return [
            'baseline' => 'Đo bản chuẩn H4 trend + H4 zone + M15 Price Action.',
            'more_samples' => 'Nới nhẹ vùng/PA để tăng số mẫu nhưng vẫn giữ H4 -> M15.',
            'quality' => 'Siết chất lượng vùng H4 và mô hình Price Action M15.',
            'rr' => 'Tối ưu entry, SL/TP trong vùng H4.',
            'stable' => 'Giữ cấu trúc tốt nhất và chỉnh nhẹ để ổn định.',
        ];
    }

    private function buildAiResearchPlan(string $provider, string $symbol, string $method, int $cycle, array $kpi, array $rules, ?array $best, ?array $prev): ?array
    {
        if ($provider !== 'openai_chat') return null;
        if ($this->llmGateway === null) {
            throw new RuntimeException('OpenAI đã được chọn cho AI Lab nhưng LLM Gateway chưa được cấu hình.');
        }
        $allowed = $this->researchFamilies();
        $bestSummary = is_array($best['backtestAgent']['summary'] ?? null) ? $best['backtestAgent']['summary'] : [];
        $prevSummary = is_array($prev['backtestAgent']['summary'] ?? null) ? $prev['backtestAgent']['summary'] : [];
        $response = $this->llmGateway->call([
            'task_type' => 'strategy_proposal',
            'agent_role' => 'ResearchAgent',
            'prompt_template_key' => 'strategy.idea.generate',
            'preferred_provider' => 'openai_chat',
            'output_format' => 'json_object',
            'variables' => [
                'research_goal' => 'Bạn là AI số 1 trong AI Lab. Chọn đúng 1 hướng thử tiếp theo từ allowed_families. Chỉ trả JSON dạng {"family":"...","reason":"..."}. Khóa phương pháp mẹ: H4 xác định xu hướng/sóng và vùng supply-demand/support-resistance; chỉ ưu tiên BUY tại demand/support thuận H4 hoặc SELL tại supply/resistance thuận H4; khi giá về vùng H4 mới xuống M15 tìm Price Action đảo chiều như rejection wick, engulfing, liquidity sweep hoặc CHoCH/BOS rồi mới tạo lệnh. AI chỉ được tối ưu tham số trong khung này, không đổi sang indicator/scalping/random method. Không bịa KPI/backtest, không đề xuất đặt lệnh live.',
                'strategy_context' => json_encode([
                    'symbol' => $symbol,
                    'method_profile' => $this->methodLabel($method),
                    'method_blueprint' => 'Khóa phương pháp mẹ: H4 xác định xu hướng/sóng và vùng supply-demand/support-resistance; chỉ ưu tiên BUY tại demand/support thuận H4 hoặc SELL tại supply/resistance thuận H4; khi giá về vùng H4 mới xuống M15 tìm Price Action đảo chiều như rejection wick, engulfing, liquidity sweep hoặc CHoCH/BOS rồi mới tạo lệnh. AI chỉ được tối ưu tham số trong khung này, không đổi sang indicator/scalping/random method.',
                    'cycle' => $cycle,
                    'kpi' => $kpi,
                    'allowed_families' => $allowed,
                    'best_summary' => $bestSummary,
                    'previous_summary' => $prevSummary,
                    'current_rules_summary' => [
                        'h4MinZoneStrength' => $rules['h4Rules']['minZoneStrength'] ?? null,
                        'h4MaxZoneTouches' => $rules['h4Rules']['maxZoneTouches'] ?? null,
                        'm15RequireStructureBreak' => $rules['m15Rules']['requireStructureBreak'] ?? null,
                        'm15RequirePriceActionPattern' => $rules['m15Rules']['requirePriceActionPattern'] ?? null,
                        'm15AllowedPriceActionPatterns' => $rules['m15Rules']['allowedPriceActionPatterns'] ?? null,
                        'entryAt' => $rules['entryRules']['entryAt'] ?? null,
                        'minRR' => $rules['entryRules']['minRR'] ?? null,
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'memory_lessons' => 'AI số 2 sẽ backtest bằng engine của app. OpenAI chỉ chọn hướng nghiên cứu an toàn trong allowed_families và giải thích vì sao.',
            ],
            'metadata' => [
                'research_lab' => true,
                'live_trading' => false,
                'cycle' => $cycle,
            ],
        ])->toArray();
        if (($response['ok'] ?? false) !== true) {
            $message = is_array($response['error'] ?? null) ? (string)($response['error']['message'] ?? '') : '';
            throw new RuntimeException($message !== '' ? $message : 'OpenAI không trả được kế hoạch cho AI Lab.');
        }
        $parsed = is_array($response['parsed'] ?? null) ? $response['parsed'] : [];
        $family = strtolower(trim((string)($parsed['family'] ?? '')));
        if ($family === '' && is_array($parsed['families'] ?? null)) {
            $family = strtolower(trim((string)(array_values($parsed['families'])[0] ?? '')));
        }
        if (!isset($allowed[$family])) return null;
        return [
            'provider' => 'openai_chat',
            'family' => $family,
            'reason' => (string)($parsed['reason'] ?? 'OpenAI chọn hướng tối ưu theo KPI còn thiếu.'),
            'llm' => [
                'provider' => $response['provider'] ?? null,
                'model' => $response['model_name'] ?? null,
                'cost' => $response['cost'] ?? null,
                'usage' => $response['usage'] ?? null,
            ],
        ];
    }
    private function family(int $cycle, ?array $prev, array $kpi): string
    {
        if ($cycle === 1) return 'baseline';
        if ($cycle === 2) return 'more_samples';
        if ($cycle === 3) return 'quality';
        if ($cycle === 4) return 'rr';
        $s = is_array($prev['backtestAgent']['summary'] ?? null) ? $prev['backtestAgent']['summary'] : [];
        if ((int)($s['totalTrades'] ?? 0) < (int)$kpi['totalTradesMin']) return 'more_samples';
        if ((float)($s['averageRR'] ?? 0) < (float)$kpi['averageRRMin']) return 'rr';
        if ((float)($s['profitFactor'] ?? 0) < (float)$kpi['profitFactorMin'] || (float)($s['winRate'] ?? 0) < (float)$kpi['winRateMin']) return 'quality';
        return 'stable';
    }

    private function avoidStaleFamily(string $family, array $session, ?array $prev, int $cycle, array $kpi): string
    {
        if ($family === 'baseline') return $family;
        $streak = 0;
        $cycles = array_reverse(is_array($session['cycles'] ?? null) ? $session['cycles'] : []);
        foreach ($cycles as $item) {
            $itemFamily = (string)($item['researchAgent']['methodFamily'] ?? '');
            if ($itemFamily !== $family) break;
            $streak++;
        }
        if ($streak < 2) return $family;
        $fallback = $this->family($cycle, $prev, $kpi);
        if ($fallback !== $family) return $fallback;
        return match ($family) {
            'more_samples' => 'quality',
            'quality' => 'rr',
            'rr' => 'more_samples',
            default => 'quality',
        };
    }

    private function variantIndex(int $cycle, int $firstCycle, int $count): int
    {
        return max(0, ($cycle - $firstCycle) % max(1, $count));
    }

    private function baseline(Closure $a): void
    {
        $a('setupTypes', ['h4_trend_zone_m15_price_action_reversal'], 'Khóa setup theo H4 trend + H4 zone + M15 Price Action.');
        $a('h4Rules.minZoneStrength', 65, 'Đo bản chuẩn với vùng H4 đủ mạnh.');
        $a('h4Rules.maxZoneTouches', 5, 'Không để vùng quá cũ.');
        $a('h4Rules.requireBosForZone', true, 'Vùng H4 nên có lực phá cấu trúc.');
        $a('h4Rules.allowCounterTrendOnlyIfZoneStrong', false, 'Bản chuẩn ưu tiên thuận xu hướng H4.');
        $a('m15Rules.requireStructureBreak', true, 'M15 cần BOS/CHoCH xác nhận.');
        $a('m15Rules.requireReactionAtZone', true, 'Chỉ vào khi M15 có phản ứng tại vùng H4.');
        $a('m15Rules.requirePriceActionPattern', true, 'Bắt buộc có mô hình Price Action đảo chiều M15.');
        $a('m15Rules.allowedPriceActionPatterns', ['rejection_wick', 'engulfing', 'liquidity_sweep', 'choch'], 'Cho phép các mẫu PA chính tại vùng.');
        $a('entryRules.entryAt', 'zone_extreme_or_50_percent', 'Lấy mẫu ở biên vùng hoặc 50% vùng.');
        $a('entryRules.minRR', 2.0, 'RR ban đầu để đo kỳ vọng.');
        $a('managementRules.cancelLimitAfterCandles', 10, 'Không treo limit quá lâu.');
    }

    private function moreSamples(Closure $a, int $cycle): void
    {
        $variants = [
            ['label' => 'balanced', 'minZone' => 52, 'maxTouches' => 8, 'requireBos' => true, 'counterTrend' => false, 'counterMin' => 90, 'requireStructure' => false, 'requireReaction' => true, 'patterns' => ['rejection_wick', 'engulfing', 'liquidity_sweep', 'choch'], 'wick' => 0.18, 'dirty' => false, 'entryAt' => 'zone_50_percent', 'minRR' => 1.8, 'tpR' => 1.8, 'cancel' => 18, 'invalid' => 12, 'breakBuffer' => 0.10],
            ['label' => 'wider_h4_zones', 'minZone' => 45, 'maxTouches' => 10, 'requireBos' => false, 'counterTrend' => false, 'counterMin' => 90, 'requireStructure' => false, 'requireReaction' => true, 'patterns' => ['rejection_wick', 'engulfing', 'liquidity_sweep', 'choch'], 'wick' => 0.12, 'dirty' => false, 'entryAt' => 'zone_50_percent', 'minRR' => 1.5, 'tpR' => 1.5, 'cancel' => 24, 'invalid' => 14, 'breakBuffer' => 0.18],
            ['label' => 'strong_counter_allowed', 'minZone' => 58, 'maxTouches' => 8, 'requireBos' => true, 'counterTrend' => true, 'counterMin' => 75, 'requireStructure' => true, 'requireReaction' => true, 'patterns' => ['rejection_wick', 'engulfing', 'liquidity_sweep', 'choch'], 'wick' => 0.18, 'dirty' => true, 'entryAt' => 'zone_extreme', 'minRR' => 1.8, 'tpR' => 1.8, 'cancel' => 18, 'invalid' => 10, 'breakBuffer' => 0.10],
            ['label' => 'slow_limit_fill', 'minZone' => 50, 'maxTouches' => 12, 'requireBos' => false, 'counterTrend' => true, 'counterMin' => 70, 'requireStructure' => false, 'requireReaction' => true, 'patterns' => ['rejection_wick', 'engulfing', 'liquidity_sweep', 'choch'], 'wick' => 0.10, 'dirty' => false, 'entryAt' => 'zone_open', 'minRR' => 1.6, 'tpR' => 1.6, 'cancel' => 30, 'invalid' => 0, 'breakBuffer' => 0.25],
            ['label' => 'quality_sample', 'minZone' => 62, 'maxTouches' => 6, 'requireBos' => false, 'counterTrend' => false, 'counterMin' => 90, 'requireStructure' => true, 'requireReaction' => true, 'patterns' => ['engulfing', 'liquidity_sweep', 'choch'], 'wick' => 0.20, 'dirty' => false, 'entryAt' => 'zone_extreme', 'minRR' => 1.8, 'tpR' => 1.8, 'cancel' => 20, 'invalid' => 10, 'breakBuffer' => 0.14],
            ['label' => 'wick_only_probe', 'minZone' => 48, 'maxTouches' => 12, 'requireBos' => false, 'counterTrend' => false, 'counterMin' => 90, 'requireStructure' => false, 'requireReaction' => true, 'patterns' => ['rejection_wick'], 'wick' => 0.08, 'dirty' => false, 'entryAt' => 'zone_50_percent', 'minRR' => 1.4, 'tpR' => 1.4, 'cancel' => 30, 'invalid' => 0, 'breakBuffer' => 0.25],
            ['label' => 'sweep_only_probe', 'minZone' => 50, 'maxTouches' => 10, 'requireBos' => false, 'counterTrend' => true, 'counterMin' => 72, 'requireStructure' => false, 'requireReaction' => true, 'patterns' => ['liquidity_sweep', 'choch'], 'wick' => 0.12, 'dirty' => false, 'entryAt' => 'zone_extreme', 'minRR' => 1.6, 'tpR' => 1.6, 'cancel' => 24, 'invalid' => 12, 'breakBuffer' => 0.18],
            ['label' => 'engulfing_probe', 'minZone' => 55, 'maxTouches' => 9, 'requireBos' => false, 'counterTrend' => false, 'counterMin' => 90, 'requireStructure' => false, 'requireReaction' => true, 'patterns' => ['engulfing'], 'wick' => 0.15, 'dirty' => false, 'entryAt' => 'zone_open', 'minRR' => 1.5, 'tpR' => 1.5, 'cancel' => 26, 'invalid' => 12, 'breakBuffer' => 0.18],
        ];
        $v = $variants[$this->variantIndex($cycle, 2, count($variants))];
        $a('researchVariant', 'more_samples_' . $v['label'], 'Không chạy lại cùng preset: thử biến thể tăng mẫu ' . $v['label'] . '.');
        $a('h4Rules.minZoneStrength', $v['minZone'], 'Nới độ mạnh vùng H4 theo biến thể để tăng số mẫu.');
        $a('h4Rules.maxZoneTouches', $v['maxTouches'], 'Cho vùng sống lâu hơn nhưng vẫn giới hạn số lần chạm.');
        $a('h4Rules.requireBosForZone', $v['requireBos'], 'Thử thay đổi yêu cầu BOS của vùng H4.');
        $a('h4Rules.allowCounterTrendOnlyIfZoneStrong', $v['counterTrend'], 'Thử cho phép vùng ngược xu hướng khi đủ mạnh.');
        $a('h4Rules.counterTrendZoneStrengthMin', $v['counterMin'], 'Ngưỡng vùng ngược xu hướng nếu được phép.');
        $a('h4Rules.zoneLifecycle.maxTouchesBeforeInvalid', $v['invalid'], 'Thử ngưỡng vô hiệu vùng để kiểm tra thêm phản ứng.');
        $a('h4Rules.zoneLifecycle.breakBufferAtr', $v['breakBuffer'], 'Thay đổi buffer xác nhận phá vùng H4.');
        $a('m15Rules.requireStructureBreak', $v['requireStructure'], 'Thử nới/siết BOS/CHoCH M15 theo biến thể.');
        $a('m15Rules.requireReactionAtZone', $v['requireReaction'], 'Vẫn ưu tiên phản ứng tại vùng H4.');
        $a('m15Rules.requirePriceActionPattern', true, 'Không bỏ Price Action M15.');
        $a('m15Rules.allowedPriceActionPatterns', $v['patterns'], 'Thử nhóm mẫu Price Action khác nhau.');
        $a('m15Rules.minRejectionWickRatio', $v['wick'], 'Thay đổi tỷ lệ wick tối thiểu để lấy mẫu khác.');
        $a('m15Rules.avoidDirtySideway', $v['dirty'], 'Thử bật/tắt lọc sideway nhiễu.');
        $a('entryRules.entryAt', $v['entryAt'], 'Thử vị trí limit khác trong vùng.');
        $a('entryRules.minRR', $v['minRR'], 'Hạ RR có kiểm soát để đo tần suất.');
        $a('entryRules.minDistanceToTPR', $v['tpR'], 'TP vẫn phải đủ xa theo biến thể.');
        $a('managementRules.cancelLimitAfterCandles', $v['cancel'], 'Thử thời gian chờ limit khác nhau.');
    }

    private function quality(Closure $a, int $cycle): void
    {
        $variants = [
            ['label' => 'strict_all_pa', 'minZone' => 75, 'maxTouches' => 4, 'patterns' => ['rejection_wick', 'engulfing', 'liquidity_sweep', 'choch'], 'minRR' => 2.0, 'entryAt' => 'zone_50_percent'],
            ['label' => 'engulfing_sweep_only', 'minZone' => 82, 'maxTouches' => 3, 'patterns' => ['engulfing', 'liquidity_sweep'], 'minRR' => 2.0, 'entryAt' => 'zone_extreme'],
            ['label' => 'choch_quality', 'minZone' => 70, 'maxTouches' => 4, 'patterns' => ['choch', 'liquidity_sweep'], 'minRR' => 2.2, 'entryAt' => 'zone_extreme'],
            ['label' => 'balanced_quality', 'minZone' => 68, 'maxTouches' => 5, 'patterns' => ['engulfing', 'choch', 'rejection_wick'], 'minRR' => 2.0, 'entryAt' => 'zone_open'],
        ];
        $v = $variants[$this->variantIndex($cycle, 3, count($variants))];
        $a('researchVariant', 'quality_' . $v['label'], 'Không chạy lại cùng preset: thử biến thể chất lượng ' . $v['label'] . '.');
        $a('h4Rules.minZoneStrength', $v['minZone'], 'Siết vùng H4 khi winrate/PF yếu.');
        $a('h4Rules.maxZoneTouches', $v['maxTouches'], 'Ưu tiên vùng ít bị chạm.');
        $a('h4Rules.requireBosForZone', true, 'Vùng cần có lực phá cấu trúc.');
        $a('h4Rules.allowCounterTrendOnlyIfZoneStrong', false, 'Giữ hướng thuận H4 để giảm nhiễu.');
        $a('m15Rules.requireStructureBreak', true, 'M15 phải xác nhận rõ.');
        $a('m15Rules.requireReactionAtZone', true, 'Phải có phản ứng tại vùng.');
        $a('m15Rules.requirePriceActionPattern', true, 'Bắt buộc có PA đảo chiều M15.');
        $a('m15Rules.allowedPriceActionPatterns', $v['patterns'], 'Chỉ giữ nhóm PA chất lượng của biến thể này.');
        $a('m15Rules.avoidDirtySideway', true, 'Loại nhiễu để giảm lệnh xấu.');
        $a('entryRules.entryAt', $v['entryAt'], 'Thử vị trí limit cho nhóm lệnh chất lượng.');
        $a('entryRules.minRR', $v['minRR'], 'Giữ RR tối thiểu khi siết tín hiệu.');
        $a('entryRules.minDistanceToTPR', $v['minRR'], 'TP phải đủ xa sau khi siết tín hiệu.');
    }

    private function rr(Closure $a, array $kpi, int $cycle): void
    {
        $target = max(2.0, (float)$kpi['averageRRMin']);
        $variants = [
            ['label' => 'extreme_entry', 'entryAt' => 'zone_extreme', 'minRR' => max(2.5, $target), 'stopBuffer' => 2, 'be' => 1.8],
            ['label' => 'origin_open_entry', 'entryAt' => 'zone_open', 'minRR' => max(2.2, $target), 'stopBuffer' => 2, 'be' => 1.6],
            ['label' => 'tight_stop', 'entryAt' => 'zone_extreme', 'minRR' => max(2.8, $target), 'stopBuffer' => 1, 'be' => 1.5],
            ['label' => 'balanced_rr', 'entryAt' => 'zone_50_percent', 'minRR' => max(2.0, $target), 'stopBuffer' => 3, 'be' => 1.8],
        ];
        $v = $variants[$this->variantIndex($cycle, 4, count($variants))];
        $a('researchVariant', 'rr_' . $v['label'], 'Không chạy lại cùng preset: thử biến thể RR ' . $v['label'] . '.');
        $a('h4Rules.minZoneStrength', 68, 'Giữ vùng đủ mạnh để tập trung RR.');
        $a('m15Rules.requireStructureBreak', true, 'RR cao vẫn cần xác nhận M15.');
        $a('m15Rules.requirePriceActionPattern', true, 'Không bỏ điều kiện PA khi tối ưu RR.');
        $a('entryRules.entryAt', $v['entryAt'], 'Thử điểm limit để cải thiện RR.');
        $a('entryRules.minRR', $v['minRR'], 'Đẩy RR tối thiểu theo biến thể.');
        $a('entryRules.minDistanceToTPR', $v['minRR'], 'TP phải đủ xa so với rủi ro.');
        $a('entryRules.stopBufferPoints', $v['stopBuffer'], 'Thử buffer SL khác nhau.');
        $a('managementRules.moveSlToBEAfterR', $v['be'], 'Bảo vệ lệnh khi đã đi đúng hướng.');
    }

    private function stable(Closure $a, int $cycle): void
    {
        $variants = [
            ['label' => 'balanced', 'minZone' => 65, 'maxTouches' => 5, 'minRR' => 2.0],
            ['label' => 'slightly_strict', 'minZone' => 70, 'maxTouches' => 4, 'minRR' => 2.0],
            ['label' => 'slightly_loose', 'minZone' => 60, 'maxTouches' => 6, 'minRR' => 1.8],
        ];
        $v = $variants[$this->variantIndex($cycle, 5, count($variants))];
        $a('researchVariant', 'stable_' . $v['label'], 'Ổn định nhưng vẫn đổi nhẹ tham số để tránh lặp preset.');
        $a('h4Rules.minZoneStrength', $v['minZone'], 'Cân bằng lại sau các vòng cực đoan.');
        $a('h4Rules.maxZoneTouches', $v['maxTouches'], 'Không dùng vùng quá cũ.');
        $a('m15Rules.requireStructureBreak', true, 'Giữ xác nhận M15.');
        $a('m15Rules.requirePriceActionPattern', true, 'Giữ mô hình PA đảo chiều M15.');
        $a('m15Rules.avoidDirtySideway', true, 'Giảm nhiễu khi mẫu đã đủ hơn.');
        $a('entryRules.minRR', $v['minRR'], 'Giữ kỳ vọng RR ổn định.');
        $a('entryRules.minDistanceToTPR', $v['minRR'], 'TP theo RR ổn định.');
    }

    private function seed(string $symbol, string $method): array
    {
        $r = [
            'symbols' => [$symbol],
            'contextTimeframe' => 'H4',
            'entryTimeframe' => 'M15',
            'entryMode' => 'LIMIT',
            'directionAllowed' => 'BUY_SELL',
            'minRR' => 2.0,
            'rrMax' => 2.0,
            'riskPercent' => 1.0,
            'maxRiskPercent' => 2.0,
            'maxOpenTrades' => 1,
            'setupTypes' => ['h4_trend_zone_m15_price_action_reversal'],
            'h4Rules' => [
                'detectTrend' => true,
                'trendMethod' => 'market_structure',
                'detectSupplyDemandZones' => true,
                'minZoneStrength' => 65,
                'maxZoneTouches' => 5,
                'requireBosForZone' => true,
                'freshZoneOnly' => false,
                'allowCounterTrendOnlyIfZoneStrong' => false,
                'counterTrendZoneStrengthMin' => 90,
                'zoneLifecycle' => ['maxTouchesBeforeWeak' => 4, 'maxTouchesBeforeInvalid' => 8],
            ],
            'm15Rules' => [
                'requireStructureBreak' => true,
                'requireQmlOrFakeBreak' => false,
                'requireReactionAtZone' => true,
                'requirePriceActionPattern' => true,
                'allowedPriceActionPatterns' => ['rejection_wick', 'engulfing', 'liquidity_sweep', 'choch'],
                'minRejectionWickRatio' => 0.25,
                'engulfingRequiresBodyClose' => true,
                'avoidDirtySideway' => true,
                'sidewayMaxRangeAtr' => 4.5,
            ],
            'entryRules' => ['entryType' => 'LIMIT', 'entryAt' => 'zone_extreme_or_50_percent', 'minRR' => 2.0, 'cancelLimitAfterCandles' => 10, 'stopLossMode' => 'behind_zone_or_swing', 'takeProfitMode' => 'fixed_rr', 'minDistanceToTPR' => 2.0, 'stopBufferPoints' => 2],
            'managementRules' => ['moveSlToBEAfterR' => 1.8, 'partialCloseEnabled' => false, 'cancelLimitAfterCandles' => 10],
            'filters' => ['maxSpread' => null, 'avoidWeekend' => true, 'avoidNewsWindow' => false],
            'exitRules' => ['stopLossMode' => 'behind_zone_or_swing', 'takeProfitMode' => 'fixed_rr', 'partialCloseRule' => 'none', 'emergencyExitRule' => 'time_stop'],
        ];
        if ($method === 'pa_sd_h4_m15') {
            $r['setupTypes'] = ['ai_lab_h4_zone_m15_confirmation'];
            $r['h4Rules']['minZoneStrength'] = 60;
            $r['h4Rules']['maxZoneTouches'] = 6;
            $r['h4Rules']['requireBosForZone'] = false;
            $r['h4Rules']['allowCounterTrendOnlyIfZoneStrong'] = true;
            $r['m15Rules']['requirePriceActionPattern'] = false;
            $r['m15Rules']['avoidDirtySideway'] = false;
        } elseif ($method === 'liquidity_sweep_pa') {
            $r['setupTypes'] = ['liquidity_sweep_false_break_at_h4_zone'];
            $r['h4Rules']['minZoneStrength'] = 55;
            $r['h4Rules']['requireBosForZone'] = false;
            $r['m15Rules']['requireQmlOrFakeBreak'] = true;
            $r['m15Rules']['allowedPriceActionPatterns'] = ['liquidity_sweep', 'rejection_wick', 'choch'];
            $r['entryRules']['entryAt'] = 'zone_extreme';
        } elseif ($method === 'trend_pullback') {
            $r['setupTypes'] = ['trend_pullback_to_h4_supply_demand'];
            $r['h4Rules']['minZoneStrength'] = 65;
            $r['h4Rules']['requireBosForZone'] = true;
            $r['h4Rules']['allowCounterTrendOnlyIfZoneStrong'] = false;
            $r['m15Rules']['avoidDirtySideway'] = true;
            $r['entryRules']['entryAt'] = 'zone_50_percent';
        } elseif ($method === 'range_sd_reversal') {
            $r['setupTypes'] = ['range_reversal_at_supply_demand_edge'];
            $r['h4Rules']['detectTrend'] = false;
            $r['h4Rules']['minZoneStrength'] = 50;
            $r['h4Rules']['maxZoneTouches'] = 10;
            $r['h4Rules']['allowCounterTrendOnlyIfZoneStrong'] = true;
            $r['m15Rules']['requireStructureBreak'] = false;
            $r['managementRules']['cancelLimitAfterCandles'] = 16;
        } elseif ($method === 'free_explore') {
            $r['setupTypes'] = ['ai_lab_multi_family'];
            $r['h4Rules']['minZoneStrength'] = 45;
            $r['h4Rules']['maxZoneTouches'] = 12;
            $r['h4Rules']['allowCounterTrendOnlyIfZoneStrong'] = true;
            $r['m15Rules']['requireStructureBreak'] = false;
            $r['m15Rules']['requirePriceActionPattern'] = false;
            $r['managementRules']['cancelLimitAfterCandles'] = 18;
        }
        return StrategyRuleParser::normalize($r);
    }

    private function kpi(array $p): array
    {
        return ['winRateMin' => max(0, min(100, (float)($p['winRateMin'] ?? 50))), 'averageRRMin' => max(0.1, (float)($p['averageRRMin'] ?? 2)), 'profitFactorMin' => max(0, (float)($p['profitFactorMin'] ?? 1.5)), 'maxDrawdownMax' => max(0, (float)($p['maxDrawdownMax'] ?? 10)), 'totalTradesMin' => max(1, (int)($p['totalTradesMin'] ?? 30)), 'netProfitPercentMin' => (float)($p['netProfitPercentMin'] ?? 0)];
    }

    private function settings(array $p): array
    {
        $spread = $p['spreadPoints'] ?? null;
        return ['initialBalance' => max(1, (float)($p['initialBalance'] ?? 10000)), 'spreadPoints' => $spread === '' ? null : $spread, 'feeR' => max(0, (float)($p['feeR'] ?? 0.02)), 'slippagePoints' => max(0, (float)($p['slippagePoints'] ?? 2)), 'maxBarsInTrade' => max(5, (int)($p['maxBarsInTrade'] ?? 96))];
    }

    private function evaluate(array $m, array $k): array
    {
        $f = [];
        if ((int)$m['totalTrades'] < (int)$k['totalTradesMin']) $f[] = 'Số lệnh chưa đủ KPI.';
        if ((float)$m['winRate'] < (float)$k['winRateMin']) $f[] = 'Winrate chưa đạt KPI.';
        if ((float)$m['averageRR'] < (float)$k['averageRRMin']) $f[] = 'Average RR chưa đạt KPI.';
        if ((float)$m['profitFactor'] < (float)$k['profitFactorMin']) $f[] = 'Profit factor chưa đạt KPI.';
        if ((float)$m['maxDrawdownPercent'] > (float)$k['maxDrawdownMax']) $f[] = 'Max DD vượt KPI.';
        if ((float)$m['netProfitPercent'] < (float)$k['netProfitPercentMin']) $f[] = 'Net profit chưa đạt KPI.';
        $score = (float)$m['netProfitPercent'] + (float)$m['winRate'] * 0.5 + (float)$m['averageRR'] * 15 + (float)$m['profitFactor'] * 10 + log(max(1, (int)$m['totalTrades'])) * 6 - (float)$m['maxDrawdownPercent'] * 1.5 - count($f) * 12;
        return ['passed' => $f === [], 'score' => round($score, 4), 'failReasons' => $f];
    }

    private function review(array $m, array $d, array $e, array $k): array
    {
        if ($e['passed']) return ['verdict' => 'Đạt KPI trên backtest. Vẫn cần xem chart từng lệnh trước khi dùng thật.', 'nextAction' => 'review_chart', 'nextInstruction' => 'Mở bảng lệnh và kiểm tra điểm vào/SL/TP.'];
        $why = [];
        if ((int)$m['totalTrades'] < (int)$k['totalTradesMin']) $why[] = 'thiếu mẫu lệnh';
        if ((float)$m['winRate'] < (float)$k['winRateMin']) $why[] = 'winrate thấp';
        if ((float)$m['averageRR'] < (float)$k['averageRRMin']) $why[] = 'RR thấp';
        if ((float)$m['profitFactor'] < (float)$k['profitFactorMin']) $why[] = 'PF thấp';
        if ((float)$m['maxDrawdownPercent'] > (float)$k['maxDrawdownMax']) $why[] = 'DD cao';
        $text = $why ? implode(', ', $why) : 'chưa đủ KPI';
        $skip = (string)($d['topSkippedReason'] ?? '');
        return ['verdict' => 'Chưa đạt vì ' . $text . ($skip !== '' ? '. Bị bỏ qua nhiều nhất: ' . $skip : '') . '.', 'nextAction' => 'continue_loop', 'nextInstruction' => 'Vòng sau ưu tiên sửa: ' . $text . '.'];
    }
    private function summary(array $s): array
    {
        return ['backtestRunId' => (string)($s['backtestRunId'] ?? $s['id'] ?? ''), 'symbol' => (string)($s['symbol'] ?? ''), 'timeframe' => (string)($s['timeframe'] ?? 'M15'), 'contextTimeframe' => (string)($s['contextTimeframe'] ?? 'H4'), 'dataSplit' => (string)($s['dataSplit'] ?? 'full'), 'startDate' => (string)($s['startDate'] ?? ''), 'endDate' => (string)($s['endDate'] ?? ''), 'initialBalance' => (float)($s['initialBalance'] ?? 0), 'finalBalance' => (float)($s['finalBalance'] ?? 0), 'totalTrades' => (int)($s['totalTrades'] ?? 0), 'winTrades' => (int)($s['winTrades'] ?? 0), 'lossTrades' => (int)($s['lossTrades'] ?? 0), 'winRate' => (float)($s['winRate'] ?? 0), 'averageRR' => (float)($s['averageRR'] ?? 0), 'profitFactor' => (float)($s['profitFactor'] ?? 0), 'netProfitPercent' => (float)($s['netProfitPercent'] ?? 0), 'maxDrawdownPercent' => (float)($s['maxDrawdownPercent'] ?? 0), 'longestLossStreak' => (int)($s['longestLossStreak'] ?? 0), 'sampleStatus' => (string)($s['sampleStatus'] ?? '')];
    }

    private function diagnostics(array $d): array
    {
        $skipped = is_array($d['skippedReasons'] ?? null) ? $d['skippedReasons'] : [];
        arsort($skipped);
        $top = (string)(array_key_first($skipped) ?? '');
        return ['setupsConsidered' => (int)($d['setupsConsidered'] ?? 0), 'setupsRejected' => (int)($d['setupsRejected'] ?? 0), 'pendingLimitCreated' => (int)($d['pendingLimitCreated'] ?? 0), 'pendingLimitFilled' => (int)($d['pendingLimitFilled'] ?? 0), 'topSkippedReason' => $top, 'topSkippedCount' => $top !== '' ? (int)$skipped[$top] : 0, 'topSkippedReasons' => array_slice($skipped, 0, 5, true)];
    }

    private function trades(array $trades): array
    {
        $closed = array_values(array_filter($trades, fn($t): bool => is_array($t) && ($t['result'] ?? '') !== 'SKIPPED'));
        return array_map(fn(array $t): array => ['id' => (string)($t['id'] ?? ''), 'backtestRunId' => (string)($t['backtestRunId'] ?? ''), 'symbol' => (string)($t['symbol'] ?? ''), 'direction' => (string)($t['direction'] ?? ''), 'chartDirection' => (string)($t['chartDirection'] ?? ''), 'entryIndex' => (int)($t['entryIndex'] ?? 0), 'exitIndex' => (int)($t['exitIndex'] ?? 0), 'entryTime' => (string)($t['entryTime'] ?? ''), 'exitTime' => (string)($t['exitTime'] ?? ''), 'entryPrice' => $t['entryPrice'] ?? null, 'exitPrice' => $t['exitPrice'] ?? null, 'stopLoss' => $t['stopLoss'] ?? null, 'takeProfit' => $t['takeProfit'] ?? null, 'initialStop' => $t['initialStop'] ?? ($t['stopLoss'] ?? null), 'targetPrice' => $t['targetPrice'] ?? ($t['takeProfit'] ?? null), 'result' => (string)($t['result'] ?? ''), 'exitType' => (string)($t['exitType'] ?? ''), 'rrResult' => (float)($t['rrResult'] ?? 0), 'netR' => (float)($t['netR'] ?? $t['rrResult'] ?? 0), 'profitLoss' => (float)($t['profitLoss'] ?? 0), 'pnlUsd' => (float)($t['pnlUsd'] ?? $t['profitLoss'] ?? 0), 'reasonEnter' => (string)($t['reasonEnter'] ?? $t['entryReason'] ?? ''), 'reasonExit' => (string)($t['reasonExit'] ?? $t['exitReason'] ?? ''), 'setupType' => (string)($t['setupType'] ?? ''), 'priceActionPattern' => (string)($t['priceActionPattern'] ?? ''), 'priceActionPatterns' => array_values(array_filter(array_map('strval', is_array($t['priceActionPatterns'] ?? null) ? $t['priceActionPatterns'] : []))), 'h4Trend' => (string)($t['h4Trend'] ?? ''), 'zoneId' => (string)($t['zoneId'] ?? ''), 'zoneType' => (string)($t['zoneType'] ?? ''), 'zoneStrength' => $t['zoneStrength'] ?? null, 'mfeR' => (float)($t['mfeR'] ?? 0), 'maeR' => (float)($t['maeR'] ?? 0), 'holdingBars' => (int)($t['holdingBars'] ?? 0)], $closed);
    }

    private function aiProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        return $provider === 'openai_chat' ? 'openai_chat' : 'internal';
    }

    private function aiProviderLabel(string $provider): string
    {
        return $this->aiProvider($provider) === 'openai_chat' ? 'OpenAI API' : 'AI nội bộ';
    }
    private function method(string $m): string
    {
        $m = strtolower(trim($m));
        return in_array($m, ['h4_trend_zone_m15_pa', 'pa_sd_h4_m15', 'liquidity_sweep_pa', 'trend_pullback', 'range_sd_reversal', 'free_explore'], true) ? $m : 'h4_trend_zone_m15_pa';
    }

    private function methodLabel(string $m): string
    {
        return match ($this->method($m)) {'h4_trend_zone_m15_pa' => 'H4 Trend + H4 Zone + M15 Price Action', 'pa_sd_h4_m15' => 'Price Action + Vùng canh buy/sell H4/M15', 'liquidity_sweep_pa' => 'Liquidity sweep / false break', 'trend_pullback' => 'Pullback theo xu hướng', 'range_sd_reversal' => 'Range trading tại vùng canh buy/sell', 'free_explore' => 'Tự do khám phá nhiều họ chiến lược', default => 'H4 Trend + H4 Zone + M15 Price Action'};
    }

    private function familyLabel(string $f): string
    {
        return match ($f) {'more_samples' => 'Nới mẫu nhưng giữ PA M15', 'quality' => 'Siết vùng H4 + PA M15', 'rr' => 'Tối ưu entry/SL/TP', 'stable' => 'Ổn định rule thắng', default => 'Bản chuẩn H4 -> M15 PA'};
    }

    private function hypothesis(string $f, string $symbol): string
    {
        return match ($f) {'more_samples' => $symbol . ': nới vùng/PA vừa đủ để tăng mẫu nhưng vẫn bám H4 -> M15.', 'quality' => $symbol . ': siết vùng H4 và Price Action M15 để giảm lệnh nhiễu.', 'rr' => $symbol . ': ép entry sát vùng H4 để cải thiện RR.', 'stable' => $symbol . ': giữ phần đang tốt và giảm nhiễu để kiểm tra độ ổn định.', default => $symbol . ': đo bản chuẩn H4 trend + H4 zone + M15 Price Action.'};
    }

    private function best(mixed $best, array $cycle): array
    {
        return !is_array($best) || (float)$cycle['score'] > (float)($best['score'] ?? -999999) ? $cycle : $best;
    }

    private function sourceText(array $session, array $cycle): string
    {
        $s = $cycle['backtestAgent']['summary'] ?? [];
        return 'AI Lab two-agent loop' . "\n" . 'Symbol: ' . (string)($session['symbol'] ?? '') . "\n" . 'Method: ' . (string)($session['methodProfileLabel'] ?? '') . "\n" . 'Cycle: ' . (string)($cycle['cycle'] ?? '') . "\n" . 'Verdict: ' . (string)($cycle['reviewAgent']['verdict'] ?? '') . "\n" . 'Backtest: ' . (int)($s['totalTrades'] ?? 0) . ' trades, WR ' . (float)($s['winRate'] ?? 0) . '%, RR ' . (float)($s['averageRR'] ?? 0) . ', PF ' . (float)($s['profitFactor'] ?? 0);
    }

    private function load(): array
    {
        $items = $this->store->readJson(self::FILE, []);
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    private function save(array $items): void
    {
        usort($items, fn($a, $b) => strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? '')));
        $this->store->writeJson(self::FILE, array_slice(array_values($items), 0, 100));
    }

    private function indexOf(array $items, string $id): int
    {
        foreach ($items as $i => $item) if ((string)($item['id'] ?? '') === $id) return $i;
        return -1;
    }

    private function get(array $data, array $path): mixed
    {
        $cursor = $data;
        foreach ($path as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) return null;
            $cursor = $cursor[$part];
        }
        return $cursor;
    }

    private function set(array &$data, array $path, mixed $value): void
    {
        $cursor =& $data;
        $last = array_pop($path);
        foreach ($path as $part) {
            if (!isset($cursor[$part]) || !is_array($cursor[$part])) $cursor[$part] = [];
            $cursor =& $cursor[$part];
        }
        $cursor[$last] = $value;
    }
}