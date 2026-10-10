<?php

declare(strict_types=1);

require_once __DIR__ . '/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/Repositories/Contracts/EvaluationResultRepositoryInterface.php';
require_once __DIR__ . '/Evaluations/EvaluationStatus.php';
require_once __DIR__ . '/Evaluations/EvaluationResultRecord.php';
require_once __DIR__ . '/KPIProfileService.php';

final class EvaluationService
{
    public const DEFAULT_KPI = [
        'win_rate_min' => 70.0,
        'average_rr_min' => 2.0,
        'profit_factor_min' => 2.0,
        'max_drawdown_max' => 10.0,
        'total_trades_min' => 300,
    ];

    public function __construct(
        private EvaluationResultRepositoryInterface $evaluations,
        private ?KPIProfileService $kpiProfiles = null
    )
    {
    }

    public function evaluateBacktestResult(array $backtestResult, array $kpi = [], array $context = []): array
    {
        $kpi = array_merge($this->defaultKpi(), $kpi);
        $metrics = $this->metricsFromBacktestResult($backtestResult);
        $components = $this->scoreComponents($metrics, $kpi);
        $score = round(array_sum($components), 2);
        $reasons = $this->reasons($metrics, $kpi);
        $recommendations = $this->recommendations($reasons);
        $passed = count(array_filter($reasons, fn(array $reason): bool => !($reason['passed'] ?? false))) === 0;
        $status = $passed ? EvaluationStatus::PASSED : ($score >= 70 ? EvaluationStatus::WARNING : EvaluationStatus::FAILED);

        return EvaluationResultRecord::normalize([
            'id' => $context['id'] ?? $this->newId(),
            'strategy_version_id' => $context['strategy_version_id'] ?? $context['strategyVersionId'] ?? $backtestResult['strategy_version_id'] ?? $backtestResult['strategyVersionId'] ?? null,
            'backtest_result_id' => $context['backtest_result_id'] ?? $context['backtestResultId'] ?? $backtestResult['id'] ?? $backtestResult['backtestRunId'] ?? null,
            'experiment_id' => $context['experiment_id'] ?? $context['experimentId'] ?? null,
            'status' => $status,
            'score' => $score,
            'passed' => $passed,
            'kpi_json' => $kpi,
            'metrics_json' => $metrics,
            'reasons_json' => $reasons,
            'recommendations_json' => $recommendations,
            'created_at' => $context['created_at'] ?? $context['createdAt'] ?? gmdate('c'),
            'created_by' => $context['created_by'] ?? $context['createdBy'] ?? 'system',
            'notes' => $context['notes'] ?? '',
        ]);
    }

    public function saveEvaluation(array $evaluation): array
    {
        if (trim((string)($evaluation['id'] ?? '')) === '') {
            $evaluation['id'] = $this->newId();
        }

        $normalized = EvaluationResultRecord::normalize($evaluation);
        EvaluationResultRecord::validate($normalized);

        return $this->evaluations->save($normalized);
    }

    public function findEvaluation(string $id): ?array
    {
        return $this->evaluations->find($id);
    }

    public function listEvaluations(int $limit = 100, array $filters = []): array
    {
        return $this->evaluations->list($limit, $filters);
    }

    public function listByStrategyVersion(string $strategyVersionId, int $limit = 100): array
    {
        return $this->evaluations->listByStrategyVersion($strategyVersionId, $limit);
    }

    public function listByExperiment(string $experimentId, int $limit = 100): array
    {
        return $this->evaluations->listByExperiment($experimentId, $limit);
    }

    public function getLatestEvaluation(?string $strategyVersionId = null, ?string $experimentId = null): ?array
    {
        return $this->evaluations->latest($this->filters($strategyVersionId, $experimentId));
    }

    public function getBestEvaluation(?string $strategyVersionId = null, ?string $experimentId = null): ?array
    {
        return $this->evaluations->best($this->filters($strategyVersionId, $experimentId));
    }

    private function metricsFromBacktestResult(array $backtestResult): array
    {
        $metrics = is_array($backtestResult['metrics_json'] ?? null)
            ? $backtestResult['metrics_json']
            : (is_array($backtestResult['metricsJson'] ?? null) ? $backtestResult['metricsJson'] : []);
        $summary = is_array($backtestResult['summary'] ?? null) ? $backtestResult['summary'] : [];
        $source = array_merge($metrics, $summary, $backtestResult);

        return [
            'win_rate' => $this->floatMetric($source, ['win_rate', 'winRate']),
            'average_rr' => $this->floatMetric($source, ['average_rr', 'averageRR']),
            'profit_factor' => $this->floatMetric($source, ['profit_factor', 'profitFactor']),
            'max_drawdown' => $this->floatMetric($source, ['max_drawdown', 'maxDrawdown', 'maxDrawdownPercent']),
            'total_trades' => (int)$this->floatMetric($source, ['total_trades', 'totalTrades']),
        ];
    }

    private function defaultKpi(): array
    {
        if ($this->kpiProfiles === null) {
            return self::DEFAULT_KPI;
        }

        try {
            return $this->kpiProfiles->defaultKpiTargets() ?? self::DEFAULT_KPI;
        } catch (Throwable) {
            return self::DEFAULT_KPI;
        }
    }

    private function scoreComponents(array $metrics, array $kpi): array
    {
        $drawdown = (float)$metrics['max_drawdown'];
        $drawdownTarget = max(0.0001, (float)$kpi['max_drawdown_max']);

        return [
            'win_rate' => round(min(1.0, max(0.0, (float)$metrics['win_rate'] / max(0.0001, (float)$kpi['win_rate_min']))) * 20, 4),
            'average_rr' => round(min(1.0, max(0.0, (float)$metrics['average_rr'] / max(0.0001, (float)$kpi['average_rr_min']))) * 20, 4),
            'profit_factor' => round(min(1.0, max(0.0, (float)$metrics['profit_factor'] / max(0.0001, (float)$kpi['profit_factor_min']))) * 20, 4),
            'max_drawdown' => round(min(1.0, max(0.0, (($drawdownTarget * 2) - $drawdown) / $drawdownTarget)) * 20, 4),
            'total_trades' => round(min(1.0, max(0.0, (float)$metrics['total_trades'] / max(1.0, (float)$kpi['total_trades_min']))) * 20, 4),
        ];
    }

    private function reasons(array $metrics, array $kpi): array
    {
        return [
            $this->reason('win_rate', 'Win rate', (float)$metrics['win_rate'], '>=', (float)$kpi['win_rate_min']),
            $this->reason('average_rr', 'Average RR', (float)$metrics['average_rr'], '>=', (float)$kpi['average_rr_min']),
            $this->reason('profit_factor', 'Profit factor', (float)$metrics['profit_factor'], '>=', (float)$kpi['profit_factor_min']),
            $this->reason('max_drawdown', 'Max drawdown', (float)$metrics['max_drawdown'], '<=', (float)$kpi['max_drawdown_max']),
            $this->reason('total_trades', 'Total trades', (float)$metrics['total_trades'], '>=', (float)$kpi['total_trades_min']),
        ];
    }

    private function reason(string $metric, string $label, float $actual, string $operator, float $target): array
    {
        $passed = $operator === '<=' ? $actual <= $target : $actual >= $target;

        return [
            'metric' => $metric,
            'label' => $label,
            'actual' => $actual,
            'operator' => $operator,
            'target' => $target,
            'passed' => $passed,
            'message' => $passed
                ? $label . ' passed KPI.'
                : $label . ' did not meet KPI.',
        ];
    }

    private function recommendations(array $reasons): array
    {
        $recommendations = [];
        foreach ($reasons as $reason) {
            if ($reason['passed'] ?? false) {
                continue;
            }

            $recommendations[] = match ($reason['metric']) {
                'win_rate' => 'Improve setup quality or add filters to raise win rate.',
                'average_rr' => 'Improve reward-to-risk quality by tightening entries, improving targets, or rejecting low-RR setups.',
                'profit_factor' => 'Improve profit factor by reducing loss frequency, loss size, or low-quality trades.',
                'max_drawdown' => 'Reduce drawdown with stricter risk, fewer correlated trades, or stronger invalidation rules.',
                'total_trades' => 'Run a larger sample before trusting this strategy evaluation.',
                default => 'Review this KPI before promotion.',
            };
        }

        return array_values(array_unique($recommendations));
    }

    private function filters(?string $strategyVersionId, ?string $experimentId): array
    {
        $filters = [];
        if ($strategyVersionId !== null && $strategyVersionId !== '') {
            $filters['strategy_version_id'] = $strategyVersionId;
        }
        if ($experimentId !== null && $experimentId !== '') {
            $filters['experiment_id'] = $experimentId;
        }

        return $filters;
    }

    private function floatMetric(array $source, array $keys): float
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_numeric($source[$key])) {
                return (float)$source[$key];
            }
        }

        return 0.0;
    }

    private function newId(): string
    {
        return 'eval_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
}
