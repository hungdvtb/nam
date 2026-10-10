<?php

declare(strict_types=1);

require_once __DIR__ . '/AIJobService.php';
require_once __DIR__ . '/ExperimentService.php';
require_once __DIR__ . '/StrategyVersionService.php';
require_once __DIR__ . '/AIJobs/AIJobWorker.php';
require_once __DIR__ . '/AIJobs/AIJobStrategyDraftService.php';
require_once __DIR__ . '/AIJobs/AIJobStatus.php';
require_once __DIR__ . '/AIJobs/AIJobType.php';
require_once __DIR__ . '/Experiments/ExperimentItemStatus.php';
require_once __DIR__ . '/Experiments/ExperimentStatus.php';

final class ResearchMockRunOnceService
{
    public function __construct(
        private ExperimentService $experiments,
        private AIJobService $jobs,
        private AIJobWorker $worker,
        private AIJobStrategyDraftService $strategyDrafts,
        private StrategyVersionService $versions
    ) {
    }

    public function runOnce(array $payload = []): array
    {
        [$experiment, $experimentCreated] = $this->runningExperiment($payload);

        $job = $this->jobs->createJob([
            'job_type' => AIJobType::GENERATE_STRATEGY_IDEA,
            'experiment_id' => $experiment['id'],
            'created_by' => 'research_mock',
            'notes' => 'One-click mock research flow. No real AI API, optimizer, or trading execution.',
            'input_json' => [
                'research_goal' => 'Generate one mock Strategy V2 draft for manual research review.',
                'strategy_context' => [
                    'experiment_id' => $experiment['id'],
                    'mode' => 'mock',
                    'objective' => 'Create a deterministic proposal-only Strategy V2 draft.',
                ],
                'memory_lessons' => [
                    'No real AI API calls.',
                    'No optimizer run.',
                    'No auto trading or live trading action.',
                    'No backtest calculation change.',
                ],
                'action' => 'research_mock_run_once',
                'mode' => 'mock',
                'experiment_id' => $experiment['id'],
                'goal' => 'Generate one mock Strategy V2 draft for manual research review.',
                'safety' => $this->safetyFlags(),
            ],
        ]);

        $workerResult = $this->worker->runJob((string)$job['id']);
        $completedJob = $workerResult['ai_job'] ?? null;
        if (!is_array($completedJob) || ($completedJob['status'] ?? '') !== AIJobStatus::COMPLETED) {
            throw new RuntimeException('Mock research AI job did not complete.');
        }

        $draftResult = $this->strategyDrafts->createDraftFromJob((string)$completedJob['id']);
        $updatedJob = $draftResult['ai_job'];
        $strategyVersion = $draftResult['strategy_version'];

        $experimentItem = $this->experiments->addItem((string)$experiment['id'], [
            'strategy_version_id' => $strategyVersion['id'],
            'item_type' => 'StrategyV2Draft',
            'status' => ExperimentItemStatus::PENDING,
            'metrics_json' => [
                'mode' => 'mock',
                'ai_job_id' => $updatedJob['id'],
                'job_type' => $updatedJob['job_type'],
                'strategy_version_id' => $strategyVersion['id'],
                'safety' => $this->safetyFlags(),
            ],
            'notes' => 'Linked Strategy V2 draft from one-click mock research flow.',
        ]);

        return [
            'ok' => true,
            'experiment' => $experiment,
            'experiment_created' => $experimentCreated,
            'ai_job' => $updatedJob,
            'strategy_version' => $strategyVersion,
            'version' => $strategyVersion,
            'experiment_item' => $experimentItem,
            'worker_result' => $workerResult,
            'ai_jobs' => $this->jobs->listJobs(),
            'experiments' => $this->experiments->listExperiments(),
            'strategy_versions' => $draftResult['strategy_versions'] ?? $this->versions->listVersions(),
            'latest_strategy_version' => $draftResult['latest_strategy_version'] ?? $this->versions->getLatestVersion(),
            'production_strategy_version' => $draftResult['production_strategy_version'] ?? $this->versions->getProductionVersion(),
            'safety' => $this->safetyFlags(),
        ];
    }

    private function runningExperiment(array $payload): array
    {
        $running = $this->experiments->listExperiments(1, ['status' => ExperimentStatus::RUNNING]);
        if ($running !== []) {
            return [$running[0], false];
        }

        $created = $this->experiments->createExperiment([
            'name' => trim((string)($payload['name'] ?? 'Mock research run')) ?: 'Mock research run',
            'description' => 'One-click mock research flow for generating a Strategy V2 draft.',
            'goal_json' => [
                'objective' => 'Create a mock Strategy V2 draft from a GenerateStrategyIdea job.',
                'mode' => 'mock',
                'safety' => $this->safetyFlags(),
            ],
            'created_by' => 'research_mock',
            'notes' => 'Created automatically by research_mock_run_once.',
        ]);

        return [$this->experiments->startExperiment((string)$created['id']), true];
    }

    private function safetyFlags(): array
    {
        return [
            'real_ai_api' => false,
            'optimizer' => false,
            'auto_trading' => false,
            'live_trading' => false,
            'trading_logic_changed' => false,
            'backtest_calculation_changed' => false,
        ];
    }
}
