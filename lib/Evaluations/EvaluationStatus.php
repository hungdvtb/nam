<?php

declare(strict_types=1);

final class EvaluationStatus
{
    public const PASSED = 'Passed';
    public const FAILED = 'Failed';
    public const WARNING = 'Warning';

    public static function all(): array
    {
        return [
            self::PASSED,
            self::FAILED,
            self::WARNING,
        ];
    }

    public static function normalize(string $status): string
    {
        $status = trim($status);
        if ($status === '') {
            return self::FAILED;
        }

        foreach (self::all() as $allowed) {
            if (strcasecmp($allowed, $status) === 0) {
                return $allowed;
            }
        }

        throw new InvalidArgumentException('Unsupported evaluation status: ' . $status);
    }
}
