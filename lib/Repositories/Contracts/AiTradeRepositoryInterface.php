<?php

declare(strict_types=1);

interface AiTradeRepositoryInterface
{
    public function storageName(): string;

    public function health(): array;
}
