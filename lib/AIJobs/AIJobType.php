<?php

declare(strict_types=1);

final class AIJobType
{
    public const GENERATE_STRATEGY_IDEA = 'GenerateStrategyIdea';
    public const IMPROVE_STRATEGY = 'ImproveStrategy';
    public const ANALYZE_BACKTEST = 'AnalyzeBacktest';
    public const EVALUATE_EXPERIMENT = 'EvaluateExperiment';

    private const ALIASES = [
        'generatestrategyidea' => self::GENERATE_STRATEGY_IDEA,
        'generate_strategy_idea' => self::GENERATE_STRATEGY_IDEA,
        'improvestrategy' => self::IMPROVE_STRATEGY,
        'improve_strategy' => self::IMPROVE_STRATEGY,
        'analyzebacktest' => self::ANALYZE_BACKTEST,
        'analyze_backtest' => self::ANALYZE_BACKTEST,
        'evaluateexperiment' => self::EVALUATE_EXPERIMENT,
        'evaluate_experiment' => self::EVALUATE_EXPERIMENT,
    ];

    public static function all(): array
    {
        return [
            self::GENERATE_STRATEGY_IDEA,
            self::IMPROVE_STRATEGY,
            self::ANALYZE_BACKTEST,
            self::EVALUATE_EXPERIMENT,
        ];
    }

    public static function normalize(string $type): string
    {
        $type = trim($type);
        if (in_array($type, self::all(), true)) {
            return $type;
        }

        $key = strtolower($type);
        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        throw new InvalidArgumentException('Unsupported AI job type: ' . $type);
    }
}
