<?php

declare(strict_types=1);

interface AccountRepositoryInterface extends AiTradeRepositoryInterface
{
    public function all(): array;

    public function find(string $id): ?array;

    public function save(array $account): void;

    public function replaceAll(array $accounts): void;
}
