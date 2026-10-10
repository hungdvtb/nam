<?php

declare(strict_types=1);

interface ArtifactRepositoryInterface extends AiTradeRepositoryInterface
{
    public function saveMetadata(string $id, string $artifactType, string $relativePath, array $metadata = []): void;

    public function find(string $id): ?array;

    public function list(int $limit = 100, array $filters = []): array;
}
