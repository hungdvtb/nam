<?php

declare(strict_types=1);

interface MarketDataRepositoryInterface extends AiTradeRepositoryInterface
{
    public function files(): array;

    public function payload(string $symbol, string $timeframe): ?array;

    public function savePayload(string $symbol, string $timeframe, array $payload): void;

    public function bars(string $symbol, string $timeframe, ?string $from = null, ?string $to = null): array;
}
