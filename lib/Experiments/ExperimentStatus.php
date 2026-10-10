<?php

declare(strict_types=1);

final class ExperimentStatus
{
    public const DRAFT = 'Draft';
    public const RUNNING = 'Running';
    public const PAUSED = 'Paused';
    public const COMPLETED = 'Completed';
    public const FAILED = 'Failed';
    public const ARCHIVED = 'Archived';

    public static function all(): array
    {
        return [
            self::DRAFT,
            self::RUNNING,
            self::PAUSED,
            self::COMPLETED,
            self::FAILED,
            self::ARCHIVED,
        ];
    }

    public static function normalize(string $status): string
    {
        $status = trim($status);
        if ($status === '') {
            return self::DRAFT;
        }

        foreach (self::all() as $allowed) {
            if (strcasecmp($allowed, $status) === 0) {
                return $allowed;
            }
        }

        throw new InvalidArgumentException('Unsupported experiment status: ' . $status);
    }
}
