<?php

declare(strict_types=1);

final class MigrationRunner
{
    private string $migrationsDir;

    public function __construct(string $migrationsDir)
    {
        $this->migrationsDir = rtrim($migrationsDir, DIRECTORY_SEPARATOR);
    }

    public function run(PDO $pdo): array
    {
        $this->ensureLedger($pdo);

        $appliedVersions = $this->appliedVersions($pdo);
        $result = [
            'applied' => [],
            'skipped' => [],
        ];

        foreach ($this->discoverMigrations() as $migration) {
            $version = $migration->version();
            if (isset($appliedVersions[$version])) {
                $result['skipped'][] = $version;
                continue;
            }

            $pdo->beginTransaction();
            try {
                $migration->up($pdo);
                $statement = $pdo->prepare(
                    'INSERT INTO schema_migrations (version, description, applied_at) VALUES (:version, :description, :applied_at)'
                );
                $statement->execute([
                    ':version' => $version,
                    ':description' => $migration->description(),
                    ':applied_at' => gmdate('c'),
                ]);
                $pdo->commit();
            } catch (Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }

            $result['applied'][] = $version;
        }

        return $result;
    }

    public function status(PDO $pdo): array
    {
        $this->ensureLedger($pdo);
        $appliedVersions = $this->appliedVersions($pdo);
        $migrations = $this->discoverMigrations();

        $pending = [];
        foreach ($migrations as $migration) {
            if (!isset($appliedVersions[$migration->version()])) {
                $pending[] = [
                    'version' => $migration->version(),
                    'description' => $migration->description(),
                ];
            }
        }

        return [
            'applied' => array_values($appliedVersions),
            'pending' => $pending,
        ];
    }

    private function ensureLedger(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                description TEXT NOT NULL,
                applied_at TEXT NOT NULL
            )'
        );
    }

    private function appliedVersions(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT version, description, applied_at FROM schema_migrations ORDER BY version')->fetchAll();
        $versions = [];

        foreach ($rows as $row) {
            $versions[$row['version']] = [
                'version' => $row['version'],
                'description' => $row['description'],
                'applied_at' => $row['applied_at'],
            ];
        }

        return $versions;
    }

    private function discoverMigrations(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = glob($this->migrationsDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            $migration = require $file;
            if (!$migration instanceof StorageMigrationInterface) {
                throw new RuntimeException('Migration file must return StorageMigrationInterface: ' . $file);
            }

            $migrations[] = $migration;
        }

        return $migrations;
    }
}
