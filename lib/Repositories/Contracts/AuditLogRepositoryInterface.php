<?php

declare(strict_types=1);

interface AuditLogRepositoryInterface extends AiTradeRepositoryInterface
{
    public function record(string $eventType, string $aggregateType, ?string $aggregateId, array $payload = []): void;

    public function recent(int $limit = 100, array $filters = []): array;
}
