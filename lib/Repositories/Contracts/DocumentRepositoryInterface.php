<?php

declare(strict_types=1);

interface DocumentRepositoryInterface extends AiTradeRepositoryInterface
{
    public function read(string $documentName, mixed $fallback = []): mixed;

    public function write(string $documentName, mixed $payload): void;

    public function exists(string $documentName): bool;

    public function list(string $prefix = ''): array;
}
