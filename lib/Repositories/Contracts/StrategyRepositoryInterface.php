<?php

declare(strict_types=1);

interface StrategyRepositoryInterface extends AiTradeRepositoryInterface
{
    public function current(): array;

    public function saveCurrent(array $strategy): void;

    public function summary(): array;

    public function saveSummary(array $summary): void;
}
