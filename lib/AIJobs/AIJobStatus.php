<?php

declare(strict_types=1);

final class AIJobStatus
{
    public const PENDING = 'Pending';
    public const RUNNING = 'Running';
    public const COMPLETED = 'Completed';
    public const FAILED = 'Failed';
    public const CANCELLED = 'Cancelled';

    private const ALIASES = [
        'pending' => self::PENDING,
        'running' => self::RUNNING,
        'completed' => self::COMPLETED,
        'complete' => self::COMPLETED,
        'failed' => self::FAILED,
        'fail' => self::FAILED,
        'cancelled' => self::CANCELLED,
        'canceled' => self::CANCELLED,
        'cancel' => self::CANCELLED,
    ];

    public static function all(): array
    {
        return [
            self::PENDING,
            self::RUNNING,
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
        ];
    }

    public static function normalize(string $status): string
    {
        $status = trim($status);
        if ($status === '') {
            return self::PENDING;
        }

        if (in_array($status, self::all(), true)) {
            return $status;
        }

        $key = strtolower($status);
        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        throw new InvalidArgumentException('Unsupported AI job status: ' . $status);
    }
}
