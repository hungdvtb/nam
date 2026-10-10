<?php

declare(strict_types=1);

final class AiUsageTracker
{
    private const FILE = 'tradebrain/ai_usage.json';

    public function __construct(private JsonStore $store)
    {
    }

    public function canCall(int $estimatedTokens = 0, float $estimatedCostUsd = 0.0): array
    {
        $today = gmdate('Y-m-d');
        $usage = $this->today();
        $maxCalls = $this->intEnv('AI_MAX_CALLS_PER_DAY', 100);
        $dailyBudget = $this->floatEnv('AI_DAILY_BUDGET_USD', 0.0);
        $maxTokens = $this->intEnv('AI_MAX_TOKENS_PER_CALL', 8000);

        $allowed = true;
        $reason = '';
        if ((int)$usage['calls'] >= $maxCalls) {
            $allowed = false;
            $reason = 'AI_MAX_CALLS_PER_DAY exceeded.';
        }
        if ($maxTokens > 0 && $estimatedTokens > $maxTokens) {
            $allowed = false;
            $reason = 'AI_MAX_TOKENS_PER_CALL exceeded.';
        }
        if ($dailyBudget > 0 && ((float)$usage['estimatedCostUsd'] + $estimatedCostUsd) > $dailyBudget) {
            $allowed = false;
            $reason = 'AI_DAILY_BUDGET_USD exceeded.';
        }

        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'date' => $today,
            'usage' => $usage,
            'limits' => [
                'AI_DAILY_BUDGET_USD' => $dailyBudget,
                'AI_MAX_CALLS_PER_DAY' => $maxCalls,
                'AI_MAX_TOKENS_PER_CALL' => $maxTokens,
            ],
        ];
    }

    public function record(string $service, int $estimatedInputTokens = 0, int $estimatedOutputTokens = 0, float $estimatedCostUsd = 0.0, bool $mock = true): array
    {
        $all = $this->store->readJson(self::FILE, []);
        if (!is_array($all)) {
            $all = [];
        }

        $date = gmdate('Y-m-d');
        $all[$date] = $all[$date] ?? [
            'date' => $date,
            'calls' => 0,
            'estimatedInputTokens' => 0,
            'estimatedOutputTokens' => 0,
            'estimatedTokens' => 0,
            'estimatedCostUsd' => 0.0,
            'events' => [],
        ];

        $tokens = max(0, $estimatedInputTokens) + max(0, $estimatedOutputTokens);
        $all[$date]['calls']++;
        $all[$date]['estimatedInputTokens'] += max(0, $estimatedInputTokens);
        $all[$date]['estimatedOutputTokens'] += max(0, $estimatedOutputTokens);
        $all[$date]['estimatedTokens'] += $tokens;
        $all[$date]['estimatedCostUsd'] = round((float)$all[$date]['estimatedCostUsd'] + max(0, $estimatedCostUsd), 6);
        array_unshift($all[$date]['events'], [
            'service' => $service,
            'mock' => $mock,
            'estimatedTokens' => $tokens,
            'estimatedCostUsd' => max(0, $estimatedCostUsd),
            'createdAt' => gmdate('c'),
        ]);
        $all[$date]['events'] = array_slice($all[$date]['events'], 0, 100);

        $this->store->writeJson(self::FILE, $all);
        return $all[$date];
    }

    public function today(): array
    {
        $date = gmdate('Y-m-d');
        $all = $this->store->readJson(self::FILE, []);
        if (!is_array($all)) {
            $all = [];
        }

        return $all[$date] ?? [
            'date' => $date,
            'calls' => 0,
            'estimatedInputTokens' => 0,
            'estimatedOutputTokens' => 0,
            'estimatedTokens' => 0,
            'estimatedCostUsd' => 0.0,
            'events' => [],
        ];
    }

    private function intEnv(string $name, int $default): int
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }
        return max(0, (int)$value);
    }

    private function floatEnv(string $name, float $default): float
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }
        return max(0, (float)$value);
    }
}
