<?php

declare(strict_types=1);

final class StoragePaths
{
    public static function projectRoot(?string $projectRoot = null): string
    {
        return rtrim($projectRoot ?? dirname(__DIR__, 2), DIRECTORY_SEPARATOR);
    }

    public static function dataDir(?string $projectRoot = null): string
    {
        return self::projectRoot($projectRoot) . DIRECTORY_SEPARATOR . 'data';
    }

    public static function sqliteDatabase(?string $projectRoot = null): string
    {
        return self::dataDir($projectRoot) . DIRECTORY_SEPARATOR . 'ai_trade_v2.sqlite';
    }

    public static function migrationsDir(?string $projectRoot = null): string
    {
        return self::projectRoot($projectRoot) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    public static function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create directory: ' . $dir);
        }
    }
}
