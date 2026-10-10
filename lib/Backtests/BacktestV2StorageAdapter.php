<?php

declare(strict_types=1);

require_once __DIR__ . '/BacktestResultMapper.php';
require_once __DIR__ . '/../Storage/StoragePaths.php';
require_once __DIR__ . '/../Storage/SqliteConnection.php';
require_once __DIR__ . '/../BacktestResultService.php';
require_once __DIR__ . '/../EvaluationService.php';
require_once __DIR__ . '/../ExperimentService.php';
require_once __DIR__ . '/../KPIProfileService.php';
require_once __DIR__ . '/../Experiments/ExperimentAutoAttachService.php';
require_once __DIR__ . '/../Repositories/Sqlite/SQLiteBacktestResultRepository.php';
require_once __DIR__ . '/../Repositories/Sqlite/SQLiteEvaluationResultRepository.php';
require_once __DIR__ . '/../Repositories/Sqlite/SQLiteExperimentRepository.php';
require_once __DIR__ . '/../Repositories/Sqlite/SQLiteKPIProfileRepository.php';

final class BacktestV2StorageAdapter
{
    public function __construct(
        private BacktestResultMapper $mapper,
        private BacktestResultService $backtestResults,
        private EvaluationService $evaluations,
        private bool $enabled = true,
        private ?ExperimentAutoAttachService $experimentAutoAttach = null
    ) {
    }

    public static function sqliteDefault(?string $projectRoot = null, bool $enabled = true, bool $experimentAutoAttachEnabled = true): self
    {
        $pdo = (new SqliteConnection(StoragePaths::sqliteDatabase($projectRoot)))->connect();

        return new self(
            new BacktestResultMapper(),
            new BacktestResultService(new SQLiteBacktestResultRepository($pdo)),
            new EvaluationService(
                new SQLiteEvaluationResultRepository($pdo),
                new KPIProfileService(new SQLiteKPIProfileRepository($pdo))
            ),
            $enabled,
            new ExperimentAutoAttachService(
                new ExperimentService(new SQLiteExperimentRepository($pdo)),
                $experimentAutoAttachEnabled
            )
        );
    }

    public function storeAfterLegacyBacktest(array $engineOutput, array $strategyVersion = [], array $input = []): array
    {
        if (!$this->enabled) {
            return [
                'enabled' => false,
                'ok' => true,
                'backtest_saved' => false,
                'evaluation_saved' => false,
                'experiment_attached' => false,
                'experiment_attach' => ['enabled' => false, 'attached' => false, 'errors' => []],
                'errors' => [],
            ];
        }

        try {
            $payload = $this->mapper->map($engineOutput, $strategyVersion, $input);
            $savedBacktest = $this->backtestResults->saveResult($payload);
            $savedEvaluation = $this->saveEvaluation($savedBacktest);
            $experimentAttach = $this->attachToExperiment($savedBacktest, $savedEvaluation);

            return [
                'enabled' => true,
                'ok' => true,
                'backtest_saved' => true,
                'evaluation_saved' => true,
                'experiment_attached' => (bool)($experimentAttach['attached'] ?? false),
                'experiment_attach' => $experimentAttach,
                'backtest_result_id' => $savedBacktest['id'] ?? null,
                'evaluation_result_id' => $savedEvaluation['id'] ?? null,
                'errors' => [],
            ];
        } catch (Throwable $error) {
            return [
                'enabled' => true,
                'ok' => false,
                'backtest_saved' => false,
                'evaluation_saved' => false,
                'experiment_attached' => false,
                'experiment_attach' => ['enabled' => false, 'attached' => false, 'errors' => []],
                'errors' => [$error->getMessage()],
            ];
        }
    }

    private function saveEvaluation(array $savedBacktest): array
    {
        $id = (string)($savedBacktest['id'] ?? '');
        $evaluation = $this->evaluations->evaluateBacktestResult($savedBacktest, [], [
            'id' => $this->evaluationId($id),
            'strategy_version_id' => $savedBacktest['strategy_version_id'] ?? null,
            'backtest_result_id' => $id,
            'created_at' => $savedBacktest['created_at'] ?? gmdate('c'),
            'created_by' => 'backtest-v2-adapter',
            'notes' => 'Automatic KPI evaluation generated after legacy backtest persistence.',
        ]);

        return $this->evaluations->saveEvaluation($evaluation);
    }

    private function attachToExperiment(array $savedBacktest, array $savedEvaluation): array
    {
        if ($this->experimentAutoAttach === null) {
            return [
                'enabled' => false,
                'attached' => false,
                'experiment_id' => null,
                'experiment_item_id' => null,
                'reason' => 'not_configured',
                'errors' => [],
            ];
        }

        try {
            return $this->experimentAutoAttach->attachIfRunning($savedBacktest, $savedEvaluation) + ['errors' => []];
        } catch (Throwable $error) {
            return [
                'enabled' => true,
                'attached' => false,
                'experiment_id' => null,
                'experiment_item_id' => null,
                'reason' => 'attach_failed',
                'errors' => [$error->getMessage()],
            ];
        }
    }

    private function evaluationId(string $backtestResultId): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $backtestResultId);
        $clean = trim((string)$clean, '_-');

        return 'eval_' . ($clean !== '' ? $clean : bin2hex(random_bytes(4)));
    }
}
