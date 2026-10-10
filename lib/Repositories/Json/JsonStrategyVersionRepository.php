<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/StrategyVersionRepositoryInterface.php';
require_once __DIR__ . '/../../Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/../../StrategyVersions/StrategyVersionStatus.php';
require_once __DIR__ . '/../../StrategyVersions/StrategyVersionRecord.php';

final class JsonStrategyVersionRepository implements StrategyVersionRepositoryInterface
{
    public function __construct(private JsonCompatibilityAdapter $adapter)
    {
    }

    public function storageName(): string
    {
        return 'legacy_json_strategy_versions';
    }

    public function health(): array
    {
        return [
            'storage' => $this->storageName(),
            'dataset' => 'strategy_versions',
            'readable' => true,
        ];
    }

    public function create(array $version): array
    {
        $version = StrategyVersionRecord::normalize($version);
        StrategyVersionRecord::validate($version);

        if ($this->versionCodeExists($version['version_code'])) {
            throw new InvalidArgumentException('Strategy version code already exists: ' . $version['version_code']);
        }

        $versions = $this->storedVersions();
        array_unshift($versions, $version);
        $this->writeVersions($versions);

        return $this->find((string)$version['id']) ?? $version;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        $versions = array_map(
            fn(array $version): array => StrategyVersionRecord::normalize($version),
            $this->storedVersions()
        );

        $versions = array_values(array_filter($versions, fn(array $version): bool => $this->matchesFilters($version, $filters)));
        usort($versions, fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));

        if ($limit > 0) {
            $versions = array_slice($versions, 0, $limit);
        }

        return $versions;
    }

    public function find(string $id): ?array
    {
        foreach ($this->list(0) as $version) {
            if ((string)$version['id'] === $id) {
                return $version;
            }
        }

        return null;
    }

    public function findByVersionCode(string $versionCode): ?array
    {
        foreach ($this->list(0) as $version) {
            if ((string)$version['version_code'] === $versionCode) {
                return $version;
            }
        }

        return null;
    }

    public function save(array $version): void
    {
        $version = StrategyVersionRecord::normalize($version);
        StrategyVersionRecord::validate($version);

        if ($this->versionCodeExists($version['version_code'], $version['id'])) {
            throw new InvalidArgumentException('Strategy version code already exists: ' . $version['version_code']);
        }

        $versions = $this->storedVersions();
        $found = false;
        foreach ($versions as $index => $existing) {
            if ((string)($existing['id'] ?? '') === (string)$version['id']) {
                $history = is_array($existing['history'] ?? null) ? $existing['history'] : [];
                $version['history'] = array_values($version['history'] ?: $history);
                $versions[$index] = $version;
                $found = true;
                break;
            }
        }

        if (!$found) {
            array_unshift($versions, $version);
        }

        $this->writeVersions($versions);
    }

    public function update(string $id, array $changes): array
    {
        $version = $this->find($id);
        if ($version === null) {
            throw new InvalidArgumentException('Strategy version not found: ' . $id);
        }

        unset($changes['id']);
        $updated = array_merge($version, $changes, ['updated_at' => $changes['updated_at'] ?? gmdate('c')]);
        $this->save($updated);

        return $this->find($id) ?? StrategyVersionRecord::normalize($updated);
    }

    public function latest(): ?array
    {
        return $this->list(1)[0] ?? null;
    }

    public function production(): ?array
    {
        return $this->list(1, ['status' => StrategyVersionStatus::PRODUCTION])[0] ?? null;
    }

    public function versionCodeExists(string $versionCode, ?string $excludeId = null): bool
    {
        foreach ($this->list(0) as $version) {
            if ((string)$version['version_code'] !== $versionCode) {
                continue;
            }
            if ($excludeId !== null && (string)$version['id'] === $excludeId) {
                continue;
            }
            return true;
        }

        return false;
    }

    public function appendEvent(string $versionId, string $eventType, array $payload): void
    {
        $versions = $this->storedVersions();
        $found = false;

        foreach ($versions as $index => $version) {
            if ((string)($version['id'] ?? '') !== $versionId) {
                continue;
            }

            $history = is_array($version['history'] ?? null) ? $version['history'] : [];
            $history[] = StrategyVersionRecord::historyEvent($versionId, $eventType, $payload);
            $version['history'] = $history;
            $version['updated_at'] = $version['updated_at'] ?? gmdate('c');
            $versions[$index] = $version;
            $found = true;
            break;
        }

        if (!$found) {
            throw new InvalidArgumentException('Strategy version not found: ' . $versionId);
        }

        $this->writeVersions($versions);
    }

    public function events(string $versionId): array
    {
        $version = $this->find($versionId);
        if ($version === null) {
            return [];
        }

        return is_array($version['history'] ?? null) ? $version['history'] : [];
    }

    private function storedVersions(): array
    {
        $versions = $this->adapter->read('strategy_versions', []);
        return is_array($versions) ? array_values(array_filter($versions, 'is_array')) : [];
    }

    private function writeVersions(array $versions): void
    {
        $normalized = array_map(
            fn(array $version): array => StrategyVersionRecord::normalize($version),
            $versions
        );
        $this->adapter->write('strategy_versions', array_values($normalized));
    }

    private function matchesFilters(array $version, array $filters): bool
    {
        if (isset($filters['status']) && $version['status'] !== StrategyVersionStatus::normalize((string)$filters['status'])) {
            return false;
        }

        if (array_key_exists('parent_version_id', $filters)
            && (string)($version['parent_version_id'] ?? '') !== (string)($filters['parent_version_id'] ?? '')
        ) {
            return false;
        }

        if (isset($filters['created_by']) && (string)$version['created_by'] !== (string)$filters['created_by']) {
            return false;
        }

        if (array_key_exists('approved', $filters) && (bool)$version['approved'] !== (bool)$filters['approved']) {
            return false;
        }

        return true;
    }
}
