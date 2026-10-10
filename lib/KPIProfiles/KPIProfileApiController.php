<?php

declare(strict_types=1);

require_once __DIR__ . '/../KPIProfileService.php';

final class KPIProfileApiController
{
    public function __construct(private KPIProfileService $profiles)
    {
    }

    public function handle(string $action, array $payload): array
    {
        return match ($action) {
            'kpi_profile_list' => $this->list($payload),
            'kpi_profile_create' => $this->create($payload),
            'kpi_profile_update' => $this->update($payload),
            'kpi_profile_set_default' => $this->setDefault($payload),
            'kpi_profile_delete' => $this->delete($payload),
            default => throw new InvalidArgumentException('Unsupported KPI profile API action: ' . $action),
        };
    }

    private function list(array $payload): array
    {
        return $this->payload($this->limit($payload));
    }

    private function create(array $payload): array
    {
        $profile = $this->profiles->createProfile($this->profilePayload($payload));

        return $this->payload(profile: $profile);
    }

    private function update(array $payload): array
    {
        $profile = $this->profiles->updateProfile($this->id($payload), $this->profilePayload($payload));

        return $this->payload(profile: $profile);
    }

    private function setDefault(array $payload): array
    {
        $profile = $this->profiles->setDefaultProfile($this->id($payload));

        return $this->payload(profile: $profile);
    }

    private function delete(array $payload): array
    {
        $deleted = $this->profiles->deleteProfile($this->id($payload));

        return $this->payload(deleted: $deleted);
    }

    private function payload(int $limit = 100, ?array $profile = null, bool $deleted = false): array
    {
        return [
            'ok' => true,
            'profile' => $profile,
            'profiles' => $this->profiles->listProfiles($limit),
            'default_profile' => $this->profiles->getDefaultProfile(),
            'deleted' => $deleted,
        ];
    }

    private function profilePayload(array $payload): array
    {
        if (isset($payload['profile']) && is_array($payload['profile'])) {
            return array_merge($payload, $payload['profile']);
        }

        return $payload;
    }

    private function id(array $payload): string
    {
        $id = trim((string)($payload['id'] ?? $payload['profile_id'] ?? $payload['profileId'] ?? ''));
        if ($id === '') {
            throw new InvalidArgumentException('KPI profile id is required.');
        }

        return $id;
    }

    private function limit(array $payload): int
    {
        return max(1, min(500, (int)($payload['limit'] ?? 100)));
    }
}
