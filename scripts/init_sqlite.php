<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Storage/StoragePaths.php';
require_once __DIR__ . '/../lib/Storage/SqliteConnection.php';
require_once __DIR__ . '/../lib/Storage/StorageMigrationInterface.php';
require_once __DIR__ . '/../lib/Storage/MigrationRunner.php';
require_once __DIR__ . '/../lib/Storage/SqliteInitializer.php';

$projectRoot = dirname(__DIR__);
$databasePath = $argv[1] ?? StoragePaths::sqliteDatabase($projectRoot);

try {
    $initializer = new SqliteInitializer(
        new SqliteConnection($databasePath),
        new MigrationRunner(StoragePaths::migrationsDir($projectRoot))
    );

    echo json_encode($initializer->initialize(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'SQLite initialization failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
