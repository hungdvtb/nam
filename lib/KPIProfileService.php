<?php

declare(strict_types=1);

require_once __DIR__ . '/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/Repositories/Contracts/KPIProfileRepositoryInterface.php';
require_once __DIR__ . '/KPIProfiles/KPIProfileRecord.php';

final class KPIProfileService
{
    public function __construct(private KPIProfileRepositoryInterface $profiles)
    {
    }

    public function createProfile(array $payload): array
    {
        $now = gmdate('c');
        $profile = KPIProfileRecord::normalize(array_merge([
            'id' => $this->newId(),
            'created_at' => $now,
            'updated_at' => $now,
        ], $payload));
        KPIProfileRecord::validate($profile);

        $shouldBeDefault = (bool)$profile['is_default'] || $this->getDefaultProfile() === null;
        $profile['is_default'] = false;
        $saved = $this->profiles->save($profile);

        return $shouldBeDefault ? $this->profiles->setDefault((string)$saved['id']) : $saved;
    }

    public function updateProfile(string $id, array $changes): array
    {
        $current = $this->findProfile($id);
        if ($current === null) {
            throw new InvalidArgumentException('KPI profile not found: ' . $id);
        }

        unset($changes['id'], $changes['created_at'], $changes['createdAt']);
        $makeDefault = array_key_exists('is_default', $changes) || array_key_exists('isDefault', $changes)
            ? KPIProfileRecord::normalize(array_merge($current, $changes))['is_default']
            : false;

        $updated = KPIProfileRecord::normalize(array_merge($current, $changes, ['updated_at' => gmdate('c')]));
        if (!$makeDefault && $current['is_default']) {
            $updated['is_default'] = true;
        }
        KPIProfileRecord::validate($updated);

        $saved = $this->profiles->save($updated);

        return $makeDefault ? $this->profiles->setDefault((string)$saved['id']) : $saved;
    }

    public function setDefaultProfile(string $id): array
    {
        return $this->profiles->setDefault($id);
    }

    public function deleteProfile(string $id): bool
    {
        return $this->profiles->delete($id);
    }

    public function listProfiles(int $limit = 100): array
    {
        return $this->profiles->list($limit);
    }

    public function findProfile(string $id): ?array
    {
        return $this->profiles->find($id);
    }

    public function getDefaultProfile(): ?array
    {
        return $this->profiles->default();
    }

    public function defaultKpiTargets(): ?array
    {
        $profile = $this->getDefaultProfile();

        return $profile === null ? null : KPIProfileRecord::toKpiTargets($profile);
    }

    private function newId(): string
    {
        return 'kpi_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
}
