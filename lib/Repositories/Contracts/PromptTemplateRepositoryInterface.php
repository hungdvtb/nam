<?php

declare(strict_types=1);

interface PromptTemplateRepositoryInterface extends AiTradeRepositoryInterface
{
    public function save(array $template): array;

    public function find(string $id): ?array;

    public function findByKeyVersion(string $templateKey, int $version): ?array;

    public function active(string $templateKey): ?array;

    public function list(int $limit = 100, array $filters = []): array;

    public function activate(string $id): array;

    public function archive(string $id): array;
}

