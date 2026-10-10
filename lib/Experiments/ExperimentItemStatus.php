<?php

declare(strict_types=1);

final class ExperimentItemStatus
{
    public const PENDING = 'Pending';
    public const TESTING = 'Testing';
    public const PASSED = 'Passed';
    public const FAILED = 'Failed';
    public const REJECTED = 'Rejected';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::TESTING,
            self::PASSED,
            self::FAILED,
            self::REJECTED,
        ];
    }

    public static function normalize(string $status): string
    {
        $status = trim($status);
        if ($status === '') {
            return self::PENDING;
        }

        foreach (self::all() as $allowed) {
            if (strcasecmp($allowed, $status) === 0) {
                return $allowed;
            }
        }

        throw new InvalidArgumentException('Unsupported experiment item status: ' . $status);
    }
}
