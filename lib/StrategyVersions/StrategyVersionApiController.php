<?php

declare(strict_types=1);

require_once __DIR__ . '/../StrategyVersionService.php';

final class StrategyVersionApiController
{
    public function __construct(private StrategyVersionService $versions)
    {
    }

    public function handle(string $action, array $payload): array
    {
        return match ($action) {
            'strategy_v2_list' => $this->list($payload),
            'strategy_v2_find' => $this->find($payload),
            'strategy_v2_create' => $this->create($payload),
            'strategy_v2_clone' => $this->clone($payload),
            'strategy_v2_update' => $this->update($payload),
            'strategy_v2_archive' => $this->archive($payload),
            'strategy_v2_approve' => $this->approve($payload),
            'strategy_v2_latest' => $this->latest($payload),
            'strategy_v2_production' => $this->production($payload),
            default => throw new InvalidArgumentException('Unsupported strategy V2 API action: ' . $action),
        };
    }

    private function list(array $payload): array
    {
        return $this->payload(
            limit: $this->limit($payload),
            filters: $this->filters($payload, ['status', 'parent_version_id', 'created_by', 'approved'])
        );
    }

    private function find(array $payload): array
    {
        $id = $this->id($payload);
        $version = $this->versions->findVersion($id);
        if ($version === null) {
            throw new InvalidArgumentException('Strategy V2 version not found: ' . $id);
        }

        return $this->payload(version: $version);
    }

    private function create(array $payload): array
    {
        $version = $this->versions->createVersion($this->versionPayload($this->entityPayload($payload)));

        return $this->payload(version: $version);
    }

    private function clone(array $payload): array
    {
        $input = $this->entityPayload($payload);
        $sourceId = $this->id($input, [
            'source_strategy_version_id',
            'sourceStrategyVersionId',
            'source_version_id',
            'sourceVersionId',
        ]);
        $version = $this->versions->cloneVersion($sourceId, $this->versionPayload($input));

        return $this->payload(version: $version);
    }

    private function update(array $payload): array
    {
        $input = $this->entityPayload($payload);
        $id = $this->id($input);
        $changes = $this->versionPayload($input, false);
        unset($changes['id']);
        if ($changes === []) {
            throw new InvalidArgumentException('No strategy V2 fields were provided for update.');
        }

        $version = $this->versions->updateVersion($id, $changes);

        return $this->payload(version: $version);
    }

    private function archive(array $payload): array
    {
        return $this->payload(version: $this->versions->archiveVersion($this->id($payload)));
    }

    private function approve(array $payload): array
    {
        $version = $this->versions->approveVersion(
            $this->id($payload),
            $this->boolValue($payload['production'] ?? $payload['promote'] ?? false),
            trim((string)($payload['approved_by'] ?? $payload['approvedBy'] ?? 'user')) ?: 'user'
        );

        return $this->payload(version: $version);
    }

    private function latest(array $payload): array
    {
        return $this->payload(
            version: $this->versions->getLatestVersion(),
            limit: $this->limit($payload)
        );
    }

    private function production(array $payload): array
    {
        $version = $this->hasId($payload)
            ? $this->versions->approveVersion($this->id($payload), true, trim((string)($payload['approved_by'] ?? $payload['approvedBy'] ?? 'user')) ?: 'user')
            : $this->versions->getProductionVersion();

        return $this->payload(version: $version, limit: $this->limit($payload));
    }

    private function payload(?array $version = null, int $limit = 100, array $filters = []): array
    {
        return [
            'ok' => true,
            'strategy_version' => $version,
            'version' => $version,
            'strategy_versions' => $this->versions->listVersions($limit, $filters),
            'latest_strategy_version' => $this->versions->getLatestVersion(),
            'production_strategy_version' => $this->versions->getProductionVersion(),
        ];
    }

    private function entityPayload(array $payload): array
    {
        if (isset($payload['version']) && is_array($payload['version'])) {
            return array_merge($payload, $payload['version']);
        }

        return $payload;
    }

    private function versionPayload(array $payload, bool $forCreate = true): array
    {
        $fields = [
            'id',
            'version_code',
            'name',
            'description',
            'parent_version_id',
            'status',
            'created_by',
            'created_at',
            'updated_at',
            'win_rate',
            'average_rr',
            'profit_factor',
            'max_drawdown',
            'total_trades',
            'backtest_start',
            'backtest_end',
            'approved',
            'approved_at',
        ];

        $result = $this->only($payload, $fields);
        $aliases = [
            'versionCode' => 'version_code',
            'parentVersionId' => 'parent_version_id',
            'createdBy' => 'created_by',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
            'winRate' => 'win_rate',
            'averageRR' => 'average_rr',
            'profitFactor' => 'profit_factor',
            'maxDrawdown' => 'max_drawdown',
            'totalTrades' => 'total_trades',
            'backtestStart' => 'backtest_start',
            'backtestEnd' => 'backtest_end',
            'approvedAt' => 'approved_at',
        ];
        foreach ($aliases as $source => $target) {
            if (array_key_exists($source, $payload) && !array_key_exists($target, $result)) {
                $result[$target] = $payload[$source];
            }
        }

        foreach ([
            ['rules_json', 'rulesJson', 'rules'],
            ['settings_json', 'settingsJson', 'settings'],
            ['performance_json', 'performanceJson', 'performance', 'backtestSummary'],
        ] as $keys) {
            if ($this->hasAny($payload, $keys)) {
                $result[$keys[0]] = $this->arrayField($payload, $keys, []);
            } elseif ($forCreate) {
                $result[$keys[0]] = [];
            }
        }

        foreach (['version_code', 'name', 'description', 'created_by', 'status'] as $field) {
            if (!$forCreate && isset($result[$field]) && trim((string)$result[$field]) === '') {
                unset($result[$field]);
            }
        }

        return $result;
    }

    private function filters(array $payload, array $fields): array
    {
        $filters = [];
        foreach ($fields as $field) {
            $value = $payload[$field] ?? $payload[$this->camel($field)] ?? null;
            if ($value === null || trim((string)$value) === '') {
                continue;
            }
            $filters[$field] = $field === 'approved' ? $this->boolValue($value) : (string)$value;
        }

        return $filters;
    }

    private function only(array $payload, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $payload)) {
                $result[$field] = $payload[$field];
            }
        }

        return $result;
    }

    private function arrayField(array $payload, array $keys, array $fallback): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = $payload[$key];
            if (is_array($value)) {
                return $value;
            }
            if (is_string($value) && trim($value) !== '') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
            if ($value === null || $value === '') {
                return $fallback;
            }

            throw new InvalidArgumentException($key . ' must be an array or JSON object.');
        }

        return $fallback;
    }

    private function id(array $payload, array $keys = ['strategy_version_id', 'strategyVersionId', 'id']): string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if ($value !== null && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        throw new InvalidArgumentException('Strategy V2 version id is required.');
    }

    private function hasId(array $payload): bool
    {
        foreach (['strategy_version_id', 'strategyVersionId', 'id'] as $key) {
            if (isset($payload[$key]) && trim((string)$payload[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    private function hasAny(array $payload, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }

    private function limit(array $payload): int
    {
        return max(1, min(500, (int)($payload['limit'] ?? 100)));
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'passed', 'approved', 'production'], true);
    }

    private function camel(string $field): string
    {
        return preg_replace_callback('/_([a-z])/', fn(array $matches): string => strtoupper($matches[1]), $field) ?? $field;
    }
}
