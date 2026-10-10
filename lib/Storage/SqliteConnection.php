<?php

declare(strict_types=1);

final class SqliteConnection
{
    private string $databasePath;

    public function __construct(string $databasePath)
    {
        $this->databasePath = $databasePath;
    }

    public function path(): string
    {
        return $this->databasePath;
    }

    public function connect(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('The pdo_sqlite extension is required for AI Trade V2 storage.');
        }

        StoragePaths::ensureDirectory(dirname($this->databasePath));

        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }
}
