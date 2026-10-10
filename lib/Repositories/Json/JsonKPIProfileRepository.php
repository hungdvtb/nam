<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/KPIProfileRepositoryInterface.php';
require_once __DIR__ . '/../../Storage/JsonCompatibilityAdapter.php';
require_once __DIR__ . '/../../KPIProfiles/KPIProfileRecord.php';

final class JsonKPIProfileRepository implements KPIProfileRepositoryInterface
{
    public function __construct(private JsonCompatibilityAdapter $adapter)
    {
    }

    public function storageName(): string
    {
        return 'legacy_json_kpi_profiles';
    }

    public function health(): array
    {
        return [
            'storage' => $this->storageName(),
            'dataset' => 'kpi_profiles',
            'readable' => true,
        ];
    }

    public function save(array $profile): array
    {
        $profile = KPIProfileRecord::normalize($profile);
        KPIProfileRecord::validate($profile);
        $profiles = $this->storedProfiles();
        $found = false;

        foreach ($profiles as $index => $existing) {
            if ((string)($existing['id'] ?? '') === (string)$profile['id']) {
                $profiles[$index] = $profile;
                $found = true;
                break;
            }
        }

        if (!$found) {
            array_unshift($profiles, $profile);
        }

        $this->writeProfiles($profiles);

        return $this->find((string)$profile['id']) ?? $profile;
    }

    public function find(string $id): ?array
    {
        foreach ($this->list(0) as $profile) {
            if ((string)$profile['id'] === $id) {
                return $profile;
            }
        }

        return null;
    }

    public function list(int $limit = 100): array
    {
        $profiles = array_map(
            fn(array $profile): array => KPIProfileRecord::normalize($profile),
            $this->storedProfiles()
        );
        usort($profiles, function (array $a, array $b): int {
            if ((bool)$a['is_default'] !== (bool)$b['is_default']) {
                return (bool)$a['is_default'] ? -1 : 1;
            }

            return strcmp((string)$b['updated_at'], (string)$a['updated_at']);
        });

        return $limit > 0 ? array_slice($profiles, 0, $limit) : $profiles;
    }

    public function default(): ?array
    {
        foreach ($this->list(0) as $profile) {
            if ($profile['is_default']) {
                return $profile;
            }
        }

        return null;
    }

    public function setDefault(string $id): array
    {
        $profiles = $this->storedProfiles();
        $selected = null;
        foreach ($profiles as $index => $profile) {
            $profile = KPIProfileRecord::normalize($profile);
            $profile['is_default'] = (string)$profile['id'] === $id;
            $profile['updated_at'] = $profile['is_default'] ? gmdate('c') : $profile['updated_at'];
            $profiles[$index] = $profile;
            if ($profile['is_default']) {
                $selected = $profile;
            }
        }

        if ($selected === null) {
            throw new InvalidArgumentException('KPI profile not found: ' . $id);
        }

        $this->writeProfiles($profiles);

        return $this->find($id) ?? $selected;
    }

    public function delete(string $id): bool
    {
        $profiles = $this->list(0);
        $wasDefault = false;
        $remaining = [];
        $deleted = false;

        foreach ($profiles as $profile) {
            if ((string)$profile['id'] === $id) {
                $wasDefault = (bool)$profile['is_default'];
                $deleted = true;
                continue;
            }
            $remaining[] = $profile;
        }

        if ($deleted && $wasDefault && $remaining !== [] && !array_filter($remaining, fn(array $profile): bool => (bool)$profile['is_default'])) {
            $remaining[0]['is_default'] = true;
            $remaining[0]['updated_at'] = gmdate('c');
        }

        $this->writeProfiles($remaining);

        return $deleted;
    }

    private function storedProfiles(): array
    {
        $profiles = $this->adapter->read('kpi_profiles', []);
        return is_array($profiles) ? array_values(array_filter($profiles, 'is_array')) : [];
    }

    private function writeProfiles(array $profiles): void
    {
        $this->adapter->write('kpi_profiles', array_values(array_map(
            fn(array $profile): array => KPIProfileRecord::normalize($profile),
            $profiles
        )));
    }
}
