<?php

declare(strict_types=1);

final class StrategyVersionStatus
{
    public const DRAFT = 'Draft';
    public const TESTING = 'Testing';
    public const PASSED = 'Passed';
    public const REJECTED = 'Rejected';
    public const PRODUCTION = 'Production';
    public const ARCHIVED = 'Archived';

    private const ALIASES = [
        'draft' => self::DRAFT,
        'testing' => self::TESTING,
        'backtested' => self::TESTING,
        'validation_passed' => self::PASSED,
        'final_test_passed' => self::PASSED,
        'paper_trading' => self::TESTING,
        'passed' => self::PASSED,
        'approved' => self::PASSED,
        'production' => self::PRODUCTION,
        'live' => self::PRODUCTION,
        'rejected' => self::REJECTED,
        'archived' => self::ARCHIVED,
        'retired' => self::ARCHIVED,
    ];

    private const LEGACY = [
        self::DRAFT => 'draft',
        self::TESTING => 'backtested',
        self::PASSED => 'approved',
        self::REJECTED => 'rejected',
        self::PRODUCTION => 'live',
        self::ARCHIVED => 'retired',
    ];

    public static function all(): array
    {
        return [
            self::DRAFT,
            self::TESTING,
            self::PASSED,
            self::REJECTED,
            self::PRODUCTION,
            self::ARCHIVED,
        ];
    }

    public static function normalize(string $status): string
    {
        $status = trim($status);
        if ($status === '') {
            return self::DRAFT;
        }

        if (in_array($status, self::all(), true)) {
            return $status;
        }

        $key = strtolower($status);
        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        throw new InvalidArgumentException('Unsupported strategy version status: ' . $status);
    }

    public static function toLegacy(string $status): string
    {
        $normalized = self::normalize($status);
        return self::LEGACY[$normalized];
    }
}
