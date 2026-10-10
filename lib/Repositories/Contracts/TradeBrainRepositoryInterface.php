<?php

declare(strict_types=1);

interface TradeBrainRepositoryInterface extends AiTradeRepositoryInterface
{
    public function profile(): array;

    public function saveProfile(array $profile): void;

    public function memory(int $limit = 200, array $filters = []): array;

    public function appendMemory(array $memory): void;

    public function proposals(int $limit = 100, array $filters = []): array;

    public function saveProposal(array $proposal): void;

    public function decisions(int $limit = 100, array $filters = []): array;

    public function saveDecision(array $decision): void;
}
