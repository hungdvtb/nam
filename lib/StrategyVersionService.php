<?php

declare(strict_types=1);

require_once __DIR__ . '/StrategyRuleParser.php';
require_once __DIR__ . '/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/Repositories/Contracts/StrategyVersionRepositoryInterface.php';
require_once __DIR__ . '/Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/StrategyVersions/StrategyVersionStatus.php';
require_once __DIR__ . '/StrategyVersions/StrategyVersionRecord.php';
require_once __DIR__ . '/Repositories/Json/JsonStrategyVersionRepository.php';

final class StrategyVersionService
{
    private const FILE = 'strategy_versions.json';
    private const STATUSES = [
        'draft',
        'backtested',
        'validation_passed',
        'final_test_passed',
        'paper_trading',
        'approved',
        'live',
        'rejected',
        'retired',
    ];

    private StrategyVersionRepositoryInterface $versionRepository;

    public function __construct(private JsonStore $store, ?StrategyVersionRepositoryInterface $versionRepository = null)
    {
        $this->versionRepository = $versionRepository ?? new JsonStrategyVersionRepository(
            new JsonCompatibilityAdapter($store)
        );
    }

    public function createVersion(array $payload = []): array
    {
        $now = gmdate('c');
        $versionCode = trim((string)($payload['version_code'] ?? $payload['versionCode'] ?? ''));
        if ($versionCode === '') {
            $versionCode = $this->newVersionCode();
        }

        if ($this->versionRepository->versionCodeExists($versionCode)) {
            throw new InvalidArgumentException('Strategy version code already exists: ' . $versionCode);
        }

        $rules = $payload['rules_json'] ?? $payload['rulesJson'] ?? [];
        $settings = $payload['settings_json'] ?? $payload['settingsJson'] ?? [];
        $performance = $payload['performance_json'] ?? $payload['performanceJson'] ?? $payload['backtestSummary'] ?? [];

        $version = StrategyVersionRecord::normalize([
            'id' => $payload['id'] ?? $this->newId('strategy_v2'),
            'version_code' => $versionCode,
            'name' => $payload['name'] ?? ('Strategy ' . $versionCode),
            'description' => $payload['description'] ?? $payload['notes'] ?? '',
            'parent_version_id' => $payload['parent_version_id'] ?? $payload['parentVersionId'] ?? null,
            'status' => $payload['status'] ?? StrategyVersionStatus::DRAFT,
            'created_by' => $payload['created_by'] ?? $payload['createdBy'] ?? 'user',
            'created_at' => $payload['created_at'] ?? $payload['createdAt'] ?? $now,
            'updated_at' => $payload['updated_at'] ?? $payload['updatedAt'] ?? $now,
            'performance_json' => is_array($performance) ? $performance : [],
            'rules_json' => is_array($rules) ? $this->normalizeRules($rules) : [],
            'settings_json' => is_array($settings) ? $settings : [],
            'win_rate' => $payload['win_rate'] ?? $payload['winRate'] ?? null,
            'average_rr' => $payload['average_rr'] ?? $payload['averageRR'] ?? null,
            'profit_factor' => $payload['profit_factor'] ?? $payload['profitFactor'] ?? null,
            'max_drawdown' => $payload['max_drawdown'] ?? $payload['maxDrawdown'] ?? null,
            'total_trades' => $payload['total_trades'] ?? $payload['totalTrades'] ?? null,
            'backtest_start' => $payload['backtest_start'] ?? $payload['backtestStart'] ?? null,
            'backtest_end' => $payload['backtest_end'] ?? $payload['backtestEnd'] ?? null,
            'approved' => $payload['approved'] ?? false,
            'approved_at' => $payload['approved_at'] ?? $payload['approvedAt'] ?? null,
        ]);

        $created = $this->versionRepository->create($version);
        $this->versionRepository->appendEvent((string)$created['id'], 'strategy_version.created', [
            'version_code' => $created['version_code'],
            'created_by' => $created['created_by'],
            'status' => $created['status'],
        ]);

        return $this->versionWithHistory((string)$created['id']);
    }

    public function cloneVersion(string $sourceVersionId, array $overrides = []): array
    {
        $source = $this->versionRepository->find($sourceVersionId);
        if ($source === null) {
            throw new InvalidArgumentException('Strategy version not found: ' . $sourceVersionId);
        }

        $version = $this->createVersion(array_merge([
            'name' => ($source['name'] ?? 'Strategy version') . ' clone',
            'description' => $source['description'] ?? '',
            'parent_version_id' => $sourceVersionId,
            'status' => StrategyVersionStatus::DRAFT,
            'created_by' => 'user',
            'rules_json' => $source['rules_json'] ?? [],
            'settings_json' => $source['settings_json'] ?? [],
            'performance_json' => [],
            'approved' => false,
            'approved_at' => null,
        ], $overrides, [
            'id' => $overrides['id'] ?? $this->newId('strategy_v2'),
            'version_code' => $overrides['version_code'] ?? $overrides['versionCode'] ?? $this->newVersionCode(),
        ]));

        $this->versionRepository->appendEvent((string)$version['id'], 'strategy_version.cloned', [
            'source_version_id' => $sourceVersionId,
        ]);

        return $this->versionWithHistory((string)$version['id']);
    }

    public function updateVersion(string $id, array $changes): array
    {
        $current = $this->versionRepository->find($id);
        if ($current === null) {
            throw new InvalidArgumentException('Strategy version not found: ' . $id);
        }

        $versionCode = $changes['version_code'] ?? $changes['versionCode'] ?? null;
        if ($versionCode !== null && $this->versionRepository->versionCodeExists((string)$versionCode, $id)) {
            throw new InvalidArgumentException('Strategy version code already exists: ' . $versionCode);
        }

        if (isset($changes['rules_json']) && is_array($changes['rules_json'])) {
            $changes['rules_json'] = $this->normalizeRules($changes['rules_json']);
        }
        if (isset($changes['rulesJson']) && is_array($changes['rulesJson'])) {
            $changes['rules_json'] = $this->normalizeRules($changes['rulesJson']);
            unset($changes['rulesJson']);
        }
        if (isset($changes['status'])) {
            $changes['status'] = StrategyVersionStatus::normalize((string)$changes['status']);
        }

        $changes['updated_at'] = gmdate('c');
        $updated = $this->versionRepository->update($id, $changes);
        $this->versionRepository->appendEvent($id, 'strategy_version.updated', [
            'changed_fields' => array_values(array_diff(array_keys($changes), ['updated_at'])),
        ]);

        return $this->versionWithHistory((string)$updated['id']);
    }

    public function archiveVersion(string $id): array
    {
        $updated = $this->versionRepository->update($id, [
            'status' => StrategyVersionStatus::ARCHIVED,
            'approved' => false,
            'approved_at' => null,
            'updated_at' => gmdate('c'),
        ]);
        $this->versionRepository->appendEvent($id, 'strategy_version.archived', []);

        return $this->versionWithHistory((string)$updated['id']);
    }

    public function approveVersion(string $id, bool $production = false, string $approvedBy = 'user'): array
    {
        $current = $this->versionRepository->find($id);
        if ($current === null) {
            throw new InvalidArgumentException('Strategy version not found: ' . $id);
        }

        if ($production) {
            $existingProduction = $this->versionRepository->production();
            if ($existingProduction !== null && (string)$existingProduction['id'] !== $id) {
                $this->versionRepository->update((string)$existingProduction['id'], [
                    'status' => StrategyVersionStatus::PASSED,
                    'updated_at' => gmdate('c'),
                ]);
                $this->versionRepository->appendEvent((string)$existingProduction['id'], 'strategy_version.production_replaced', [
                    'replaced_by_version_id' => $id,
                ]);
            }
        }

        $approvedAt = gmdate('c');
        $updated = $this->versionRepository->update($id, [
            'status' => $production ? StrategyVersionStatus::PRODUCTION : StrategyVersionStatus::PASSED,
            'approved' => true,
            'approved_at' => $approvedAt,
            'updated_at' => $approvedAt,
        ]);
        $this->versionRepository->appendEvent($id, $production ? 'strategy_version.promoted_to_production' : 'strategy_version.approved', [
            'approved_by' => $approvedBy,
            'approved_at' => $approvedAt,
        ]);

        return $this->versionWithHistory((string)$updated['id']);
    }

    public function getLatestVersion(): ?array
    {
        $latest = $this->versionRepository->latest();
        return $latest === null ? null : $this->versionWithHistory((string)$latest['id']);
    }

    public function getProductionVersion(): ?array
    {
        $production = $this->versionRepository->production();
        return $production === null ? null : $this->versionWithHistory((string)$production['id']);
    }

    public function listVersions(int $limit = 100, array $filters = []): array
    {
        return array_map(
            fn(array $version): array => $this->versionWithHistory((string)$version['id']),
            $this->versionRepository->list($limit, $filters)
        );
    }

    public function findVersion(string $id): ?array
    {
        return $this->versionRepository->find($id) === null ? null : $this->versionWithHistory($id);
    }

    public function list(): array
    {
        $versions = $this->store->readJson(self::FILE, []);
        if (!is_array($versions)) {
            return [];
        }

        $versions = array_map(fn($version) => is_array($version) ? $this->normalizeVersion($version) : $version, $versions);
        usort($versions, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        return array_values(array_filter($versions, 'is_array'));
    }

    public function find(string $id): ?array
    {
        foreach ($this->list() as $version) {
            if (($version['id'] ?? '') === $id) {
                return $version;
            }
        }
        return null;
    }

    public function createFromCurrentStrategy(array $strategy, array $strategySummary, array $accounts = []): array
    {
        $versions = $this->list();
        $baseName = (string)($strategy['name'] ?? 'Strategy');
        $version = $this->normalizeVersion([
            'id' => $this->newId('strategy_v1'),
            'name' => 'v1 - ' . $baseName,
            'parentVersionId' => null,
            'createdAt' => gmdate('c'),
            'createdBy' => 'user',
            'status' => 'draft',
            'notes' => 'Cloned from the current manual strategy.',
            'rulesJson' => $this->rulesFromStrategy($strategy, $accounts),
            'sourceStrategyText' => $this->sourceText($strategySummary, $strategy),
            'backtestSummary' => null,
        ]);

        array_unshift($versions, $version);
        $this->saveAll($versions);
        return $version;
    }

    public function createRootFromRules(?string $name, string $createdBy = 'ai', string $notes = '', array $rulesJson = [], string $sourceStrategyText = ''): array
    {
        if (!in_array($createdBy, ['user', 'ai', 'system'], true)) {
            throw new InvalidArgumentException('Nguồn tạo phiên bản chiến lược không hợp lệ.');
        }

        $version = $this->normalizeVersion([
            'id' => $this->newId('strategy_v'),
            'name' => trim((string)($name ?? '')) ?: 'AI generated strategy',
            'parentVersionId' => null,
            'createdAt' => gmdate('c'),
            'createdBy' => $createdBy,
            'status' => 'draft',
            'notes' => $notes,
            'rulesJson' => $this->normalizeRules($rulesJson),
            'sourceStrategyText' => $sourceStrategyText,
            'backtestSummary' => null,
        ]);

        $versions = $this->list();
        array_unshift($versions, $version);
        $this->saveAll($versions);
        return $version;
    }

    public function createFromParent(string $parentVersionId, ?string $name = null, string $createdBy = 'user', string $notes = '', ?array $rulesJson = null): array
    {
        $parent = $this->find($parentVersionId);
        if ($parent === null) {
            throw new InvalidArgumentException('Không tìm thấy chiến lược gốc.');
        }

        if (!in_array($createdBy, ['user', 'ai'], true)) {
            throw new InvalidArgumentException('Nguồn tạo phiên bản chiến lược không hợp lệ.');
        }

        $version = $this->normalizeVersion([
            'id' => $this->newId('strategy_v'),
            'name' => trim((string)($name ?? '')) ?: (($parent['name'] ?? 'Strategy') . ' copy'),
            'parentVersionId' => $parentVersionId,
            'createdAt' => gmdate('c'),
            'createdBy' => $createdBy,
            'status' => 'draft',
            'notes' => $notes !== '' ? $notes : 'Created from ' . ($parent['name'] ?? $parentVersionId) . '.',
            'rulesJson' => $this->normalizeRules($rulesJson ?? ($parent['rulesJson'] ?? [])),
            'sourceStrategyText' => $parent['sourceStrategyText'] ?? '',
            'backtestSummary' => null,
        ]);

        $versions = $this->list();
        array_unshift($versions, $version);
        $this->saveAll($versions);
        return $version;
    }

    public function saveVersion(array $version): array
    {
        $id = (string)($version['id'] ?? '');
        if ($id === '') {
            throw new InvalidArgumentException('Thiếu mã phiên bản chiến lược.');
        }

        $version = $this->normalizeVersion($version);
        $versions = $this->list();
        $found = false;
        foreach ($versions as $index => $item) {
            if (($item['id'] ?? '') === $id) {
                $versions[$index] = $version;
                $found = true;
                break;
            }
        }
        if (!$found) {
            array_unshift($versions, $version);
        }
        $this->saveAll($versions);
        return $version;
    }

    public function attachBacktestSummary(string $versionId, array $summary): ?array
    {
        $version = $this->find($versionId);
        if ($version === null) {
            return null;
        }
        $version['backtestSummary'] = $summary;
        $version['status'] = $version['status'] === 'draft' ? 'backtested' : $version['status'];
        return $this->saveVersion($version);
    }

    public function attachSplitSummary(string $versionId, string $split, array $summary): ?array
    {
        $version = $this->find($versionId);
        if ($version === null) {
            return null;
        }

        if ($split === 'validation') {
            $version['validationSummary'] = $summary;
            if (!in_array($version['status'], ['rejected', 'retired', 'approved', 'live'], true)) {
                $version['status'] = 'validation_passed';
            }
        } elseif ($split === 'final') {
            $version['finalTestSummary'] = $summary;
            if (!in_array($version['status'], ['rejected', 'retired', 'approved', 'live'], true)) {
                $version['status'] = 'final_test_passed';
            }
        } else {
            $version['backtestSummary'] = $summary;
            if ($version['status'] === 'draft') {
                $version['status'] = 'backtested';
            }
        }

        return $this->saveVersion($version);
    }

    public function updateStatus(string $versionId, string $status, string $approvalNotes = ''): array
    {
        $version = $this->find($versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Không tìm thấy phiên bản chiến lược.');
        }

        $status = $this->normalizeStatus($status);
        $version['status'] = $status;
        $version['approvalNotes'] = $approvalNotes;
        $version['updatedAt'] = gmdate('c');
        return $this->saveVersion($version);
    }

    public function tree(): array
    {
        $versions = $this->list();
        $children = [];
        foreach ($versions as $version) {
            $parent = (string)($version['parentVersionId'] ?? '');
            $children[$parent][] = [
                'id' => $version['id'],
                'name' => $version['name'],
                'status' => $version['status'],
                'createdBy' => $version['createdBy'],
                'createdAt' => $version['createdAt'],
                'parentVersionId' => $version['parentVersionId'],
            ];
        }

        return [
            'roots' => $children[''] ?? [],
            'childrenByParentId' => $children,
        ];
    }

    public function diff(string $fromVersionId, string $toVersionId): array
    {
        $from = $this->find($fromVersionId);
        $to = $this->find($toVersionId);
        if ($from === null || $to === null) {
            throw new InvalidArgumentException('Không thể so sánh vì thiếu phiên bản chiến lược.');
        }

        return $this->diffArrays($from['rulesJson'] ?? [], $to['rulesJson'] ?? []);
    }

    public function applyChanges(array $rules, array $changes): array
    {
        $updated = $this->normalizeRules($rules);
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $field = (string)($change['field'] ?? '');
            if ($field === '') {
                continue;
            }
            $this->setPath($updated, explode('.', $field), $change['newValue'] ?? null);
        }
        return $this->normalizeRules($updated);
    }

    public function rulesFromStrategy(array $strategy, array $accounts = []): array
    {
        $account = is_array($accounts[0] ?? null) ? $accounts[0] : [];
        $entries = [];
        foreach (($strategy['entries'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['enabled'] ?? true)) {
                $entries[] = (string)($entry['id'] ?? $entry['name'] ?? 'setup');
            }
        }

        $rules = [
            'symbols' => array_values(array_filter(array_map('strval', $strategy['symbols'] ?? []))),
            'contextTimeframe' => (string)($strategy['timeframes']['bias'] ?? 'H4'),
            'entryTimeframe' => (string)($strategy['timeframes']['entry'] ?? 'M15'),
            'entryMode' => 'LIMIT',
            'directionAllowed' => 'BUY_SELL',
            'minRR' => (float)($strategy['risk_reward']['min_rr'] ?? 3),
            'maxRiskPercent' => 3,
            'riskPercent' => (float)($account['risk_per_trade_pct'] ?? 1),
            'maxOpenTrades' => (int)($account['max_open_trades'] ?? 1),
            'setupTypes' => $entries ?: ['h4_trend_m15_structure'],
            'aiScoreMin' => (float)($strategy['trend']['min_confidence'] ?? 65),
            'rrMax' => max((float)($strategy['risk_reward']['default_rr'] ?? 3), (float)($strategy['risk_reward']['min_rr'] ?? 3)),
            'h4Rules' => [
                'detectTrend' => true,
                'trendMethod' => 'market_structure',
                'detectSupplyDemandZones' => true,
                'minZoneStrength' => 70,
                'allowCounterTrendOnlyIfZoneStrong' => true,
                'counterTrendZoneStrengthMin' => 85,
            ],
            'm15Rules' => [
                'requireStructureBreak' => true,
                'requireQmlOrFakeBreak' => false,
                'requireReactionAtZone' => true,
                'avoidDirtySideway' => true,
            ],
            'entryRules' => [
                'entryType' => 'LIMIT',
                'entryAt' => 'zone_extreme_or_50_percent',
                'stopLossMode' => 'behind_zone_or_swing',
                'takeProfitMode' => 'rr_or_opposite_zone',
                'minDistanceToTPR' => (float)($strategy['risk_reward']['min_rr'] ?? 3),
            ],
            'managementRules' => [
                'moveSlToBEAfterR' => (float)($strategy['management']['be_trigger_r_min'] ?? 1.8),
                'partialCloseEnabled' => (bool)($strategy['management']['partial_close'] ?? false),
                'cancelLimitAfterCandles' => 3,
            ],
            'filters' => [
                'maxSpread' => 30,
                'avoidWeekend' => true,
                'avoidNewsWindow' => (bool)($strategy['filters']['news_filter_enabled'] ?? false),
            ],
            'exitRules' => [
                'stopLossMode' => 'behind_zone_or_swing',
                'takeProfitMode' => 'fixed_rr',
                'partialCloseRule' => (bool)($strategy['management']['partial_close'] ?? false) ? 'strategy_partial_close' : 'none',
                'emergencyExitRule' => 'opposite_m15_structure_or_time_stop',
            ],
        ];

        return $this->normalizeRules($rules);
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

    public function normalizeRules(array $rules): array
    {
        return StrategyRuleParser::normalize($rules);
    }

    private function sourceText(array $summary, array $strategy): string
    {
        $lines = [];
        $lines[] = (string)($summary['title'] ?? $strategy['name'] ?? 'Strategy');
        foreach (($summary['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $lines[] = '';
            $lines[] = (string)($section['title'] ?? '');
            foreach (($section['items'] ?? []) as $item) {
                $lines[] = '- ' . (string)$item;
            }
        }
        return trim(implode("\n", $lines));
    }

    private function saveAll(array $versions): void
    {
        $this->store->writeJson(self::FILE, array_values(array_map(fn($version) => $this->normalizeVersion($version), $versions)));
    }

    private function newId(string $prefix): string
    {
        return $prefix . '_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }

    private function newVersionCode(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = 'SV-' . gmdate('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            if (!$this->versionRepository->versionCodeExists($code)) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique strategy version code.');
    }

    private function versionWithHistory(string $id): array
    {
        $version = $this->versionRepository->find($id);
        if ($version === null) {
            throw new InvalidArgumentException('Strategy version not found: ' . $id);
        }

        $version['history'] = $this->versionRepository->events($id);
        return $version;
    }

    private function setPath(array &$target, array $parts, mixed $value): void
    {
        $key = array_shift($parts);
        if ($key === null || $key === '') {
            return;
        }
        if (!$parts) {
            $target[$key] = $value;
            return;
        }
        if (!isset($target[$key]) || !is_array($target[$key])) {
            $target[$key] = [];
        }
        $this->setPath($target[$key], $parts, $value);
    }

    private function normalizeVersion(array $version): array
    {
        $version['id'] = (string)($version['id'] ?? $this->newId('strategy_v'));
        $version['name'] = (string)($version['name'] ?? 'Strategy version');
        $version['parentVersionId'] = ($version['parentVersionId'] ?? $version['parent_version_id'] ?? null) === ''
            ? null
            : ($version['parentVersionId'] ?? $version['parent_version_id'] ?? null);
        $version['status'] = $this->normalizeStatus((string)($version['status'] ?? 'draft'));
        $version['createdBy'] = in_array(($version['createdBy'] ?? $version['created_by'] ?? ''), ['user', 'ai', 'system'], true)
            ? ($version['createdBy'] ?? $version['created_by'])
            : 'user';
        $version['createdAt'] = (string)($version['createdAt'] ?? $version['created_at'] ?? gmdate('c'));
        $version['updatedAt'] = (string)($version['updatedAt'] ?? $version['updated_at'] ?? $version['createdAt']);
        $version['sourceStrategyText'] = (string)($version['sourceStrategyText'] ?? '');
        $rules = $version['rulesJson'] ?? $version['rules_json'] ?? [];
        $version['rulesJson'] = $this->normalizeRules(is_array($rules) ? $rules : []);
        $version['notes'] = (string)($version['notes'] ?? '');
        $performance = $version['backtestSummary'] ?? $version['performance_json'] ?? null;
        $version['backtestSummary'] = is_array($performance) ? $performance : null;
        $version['validationSummary'] = is_array($version['validationSummary'] ?? null) ? $version['validationSummary'] : null;
        $version['finalTestSummary'] = is_array($version['finalTestSummary'] ?? null) ? $version['finalTestSummary'] : null;
        $version['approvalNotes'] = (string)($version['approvalNotes'] ?? '');
        return $version;
    }

    private function normalizeStatus(string $status): string
    {
        if (in_array($status, self::STATUSES, true)) {
            return $status;
        }

        try {
            return StrategyVersionStatus::toLegacy($status);
        } catch (InvalidArgumentException) {
            return 'draft';
        }
    }

    private function diffArrays(array $from, array $to, string $prefix = ''): array
    {
        $changes = [];
        $keys = array_unique(array_merge(array_keys($from), array_keys($to)));
        sort($keys);

        foreach ($keys as $key) {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            $oldExists = array_key_exists($key, $from);
            $newExists = array_key_exists($key, $to);
            $oldValue = $oldExists ? $from[$key] : null;
            $newValue = $newExists ? $to[$key] : null;

            if (is_array($oldValue) && is_array($newValue) && !$this->isList($oldValue) && !$this->isList($newValue)) {
                $changes = array_merge($changes, $this->diffArrays($oldValue, $newValue, $path));
                continue;
            }

            if (!$oldExists || !$newExists || json_encode($oldValue) !== json_encode($newValue)) {
                $changes[] = [
                    'field' => $path,
                    'oldValue' => $oldExists ? $oldValue : null,
                    'newValue' => $newExists ? $newValue : null,
                ];
            }
        }

        return $changes;
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}
