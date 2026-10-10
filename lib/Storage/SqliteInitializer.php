<?php

declare(strict_types=1);

final class SqliteInitializer
{
    private SqliteConnection $connection;
    private MigrationRunner $migrations;

    public function __construct(SqliteConnection $connection, MigrationRunner $migrations)
    {
        $this->connection = $connection;
        $this->migrations = $migrations;
    }

    public static function default(?string $projectRoot = null): self
    {
        return new self(
            new SqliteConnection(StoragePaths::sqliteDatabase($projectRoot)),
            new MigrationRunner(StoragePaths::migrationsDir($projectRoot))
        );
    }

    public function initialize(): array
    {
        $pdo = $this->connection->connect();
        $run = $this->migrations->run($pdo);

        return [
            'database_path' => $this->connection->path(),
            'migrations' => $run,
            'status' => $this->migrations->status($pdo),
        ];
    }
}
