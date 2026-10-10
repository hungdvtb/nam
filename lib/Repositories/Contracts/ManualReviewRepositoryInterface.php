<?php

declare(strict_types=1);

interface ManualReviewRepositoryInterface extends AiTradeRepositoryInterface
{
    public function list(int $limit = 500, array $filters = []): array;

    public function save(array $review): array;

    public function forTarget(string $targetType, string $targetId): array;

    public function labels(): array;
}
