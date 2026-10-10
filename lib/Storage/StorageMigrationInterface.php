<?php

declare(strict_types=1);

interface StorageMigrationInterface
{
    public function version(): string;

    public function description(): string;

    public function up(PDO $pdo): void;
}
