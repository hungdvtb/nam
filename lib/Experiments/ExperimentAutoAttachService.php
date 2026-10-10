<?php

declare(strict_types=1);

require_once __DIR__ . '/../ExperimentService.php';
require_once __DIR__ . '/ExperimentStatus.php';
require_once __DIR__ . '/ExperimentItemStatus.php';
require_once __DIR__ . '/../Evaluations/EvaluationStatus.php';

final class ExperimentAutoAttachService
{
    public const ITEM_TYPE_BACKTEST_RESULT = 'BacktestResult';

    public function __construct(private ExperimentService $experiments, private bool $enabled = true)
    {
    }

    public function attachIfRunning(array $backtestResult, array $evaluationResult): array
    {
        if (!$this->enabled) {
            return [
                'enabled' => false,
                'attached' => false,
                'experiment_id' => null,
                'experiment_item_id' => null,
                'reason' => 'disabled',
            ];
        }

        $experiment = $this->activeExperiment();
        if ($experiment === null) {
            return [
                'enabled' => true,
                'attached' => false,
                'experiment_id' => null,
                'experiment_item_id' => null,
                'reason' => 'no_running_experiment',
            ];
        }

        $item = $this->upsertItem($experiment, $backtestResult, $evaluationResult);

        return [
            'enabled' => true,
            'attached' => true,
            'experiment_id' => $experiment['id'],
            'experiment_item_id' => $item['id'],
            'reason' => 'attached',
        ];
    }

    private function activeExperiment(): ?array
    {
        return $this->experiments->listExperiments(1, ['status' => ExperimentStatus::RUNNING])[0] ?? null;
    }

    private function upsertItem(array $experiment, array $backtestResult, array $evaluationResult): array
    {
        $experimentId = (string)$experiment['id'];
        $backtestResultId = (string)($backtestResult['id'] ?? $evaluationResult['backtest_result_id'] ?? '');
        $existing = $this->experiments->listItems($experimentId, 1, ['backtest_result_id' => $backtestResultId])[0] ?? null;
        $payload = $this->itemPayload($experimentId, $backtestResult, $evaluationResult, $existing['id'] ?? null);

        if ($existing !== null) {
            return $this->experiments->updateItem((string)$existing['id'], $payload);
        }

        return $this->experiments->addItem($experimentId, $payload);
    }

    private function itemPayload(string $experimentId, array $backtestResult, array $evaluationResult, ?string $existingId): array
    {
        $backtestResultId = (string)($backtestResult['id'] ?? $evaluationResult['backtest_result_id'] ?? '');
        $strategyVersionId = $backtestResult['strategy_version_id'] ?? $evaluationResult['strategy_version_id'] ?? null;

        return [
            'id' => $existingId ?? $this->itemId($experimentId, $backtestResultId),
            'experiment_id' => $experimentId,
            'strategy_version_id' => $strategyVersionId,
            'backtest_result_id' => $backtestResultId,
            'item_type' => self::ITEM_TYPE_BACKTEST_RESULT,
            'status' => $this->itemStatus((string)($evaluationResult['status'] ?? '')),
            'score' => isset($evaluationResult['score']) && is_numeric($evaluationResult['score']) ? (float)$evaluationResult['score'] : null,
            'metrics_json' => [
                'evaluation_id' => $evaluationResult['id'] ?? null,
                'evaluation_status' => $evaluationResult['status'] ?? null,
                'passed' => (bool)($evaluationResult['passed'] ?? false),
                'score' => isset($evaluationResult['score']) && is_numeric($evaluationResult['score']) ? (float)$evaluationResult['score'] : null,
                'kpi' => is_array($evaluationResult['kpi_json'] ?? null) ? $evaluationResult['kpi_json'] : [],
                'metrics' => is_array($evaluationResult['metrics_json'] ?? null) ? $evaluationResult['metrics_json'] : [],
                'reasons' => is_array($evaluationResult['reasons_json'] ?? null) ? $evaluationResult['reasons_json'] : [],
                'recommendations' => is_array($evaluationResult['recommendations_json'] ?? null) ? $evaluationResult['recommendations_json'] : [],
            ],
            'created_at' => $evaluationResult['created_at'] ?? $backtestResult['created_at'] ?? gmdate('c'),
            'notes' => 'Automatically attached from V2 backtest storage.',
        ];
    }

    private function itemStatus(string $evaluationStatus): string
    {
        return match (EvaluationStatus::normalize($evaluationStatus)) {
            EvaluationStatus::PASSED => ExperimentItemStatus::PASSED,
            EvaluationStatus::WARNING => ExperimentItemStatus::TESTING,
            EvaluationStatus::FAILED => ExperimentItemStatus::FAILED,
        };
    }

    private function itemId(string $experimentId, string $backtestResultId): string
    {
        $seed = $experimentId . '_' . $backtestResultId;
        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $seed);
        $clean = trim((string)$clean, '_-');

        return 'exp_item_' . ($clean !== '' ? $clean : bin2hex(random_bytes(4)));
    }
}
