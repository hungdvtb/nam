<?php

declare(strict_types=1);

require_once __DIR__ . '/../AIJobService.php';
require_once __DIR__ . '/../AI/Gateway/LLMGateway.php';
require_once __DIR__ . '/../Repositories/Sqlite/SQLitePromptTemplateRepository.php';
require_once __DIR__ . '/../Storage/StorageMigrationInterface.php';

final class AIJobWorker
{
    public function __construct(
        private AIJobService $jobs,
        private ?LLMGateway $gateway = null
    ) {
        $this->gateway = $this->gateway ?? self::defaultGateway();
    }

    public function runOnce(): array
    {
        $pending = $this->jobs->listJobs(1, ['status' => AIJobStatus::PENDING]);
        if ($pending === []) {
            return [
                'processed' => false,
                'message' => 'No pending AI jobs.',
                'ai_job' => null,
            ];
        }

        $job = $pending[0];
        return $this->process($job);
    }

    public function runJob(string $jobId): array
    {
        $job = $this->jobs->findJob($jobId);
        if ($job === null) {
            throw new InvalidArgumentException('AI job not found: ' . $jobId);
        }

        return $this->process($job);
    }

    private function process(array $job): array
    {
        try {
            $running = $this->jobs->startJob((string)$job['id']);
            $output = $this->gatewayOutput($running);
            $completed = $this->jobs->completeJob((string)$running['id'], $output);

            return [
                'processed' => true,
                'job_id' => $completed['id'],
                'status' => $completed['status'],
                'ai_job' => $completed,
            ];
        } catch (Throwable $error) {
            $failed = $this->jobs->failJob((string)$job['id'], $error->getMessage());

            return [
                'processed' => true,
                'job_id' => $failed['id'],
                'status' => $failed['status'],
                'error_message' => $failed['error_message'],
                'ai_job' => $failed,
            ];
        }
    }

    private function gatewayOutput(array $job): array
    {
        $jobType = AIJobType::normalize((string)($job['job_type'] ?? ''));
        $spec = $this->gatewaySpec($jobType);
        $response = $this->gateway->call([
            'task_type' => $spec['task_type'],
            'agent_role' => $spec['agent_role'],
            'prompt_template_key' => $spec['prompt_template_key'],
            'variables' => is_array($job['input_json'] ?? null) ? $job['input_json'] : [],
            'output_format' => $spec['output_format'],
            'preferred_provider' => 'mock',
            'metadata' => [
                'source' => 'ai_job_worker',
                'ai_job_id' => $job['id'] ?? null,
                'ai_job_type' => $jobType,
                'strategy_version_id' => $job['strategy_version_id'] ?? null,
                'experiment_id' => $job['experiment_id'] ?? null,
            ],
        ])->toArray();

        if (($response['ok'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];
            $category = (string)($error['category'] ?? 'llm_gateway_error');
            $message = (string)($error['message'] ?? 'LLM Gateway failed.');
            throw new RuntimeException('LLM Gateway failed for ' . $jobType . ': ' . $category . ' - ' . $message);
        }

        return [
            'prompt_template_key' => $spec['prompt_template_key'],
            'provider' => $response['provider'] ?? null,
            'model' => $response['model_name'] ?? null,
            'model_name' => $response['model_name'] ?? null,
            'model_alias' => $response['model_alias'] ?? null,
            'usage' => is_array($response['usage'] ?? null) ? $response['usage'] : [],
            'parsed_output' => $response['parsed'] ?? null,
            'llm_response' => $response,
        ];
    }

    private function gatewaySpec(string $jobType): array
    {
        return match ($jobType) {
            AIJobType::GENERATE_STRATEGY_IDEA => [
                'task_type' => 'strategy_proposal',
                'agent_role' => 'StrategyAI',
                'prompt_template_key' => 'strategy.idea.generate',
                'output_format' => 'json_object',
            ],
            AIJobType::IMPROVE_STRATEGY => [
                'task_type' => 'strategy_proposal',
                'agent_role' => 'StrategyAI',
                'prompt_template_key' => 'strategy.version.improve',
                'output_format' => 'json_object',
            ],
            AIJobType::ANALYZE_BACKTEST => [
                'task_type' => 'backtest_summary',
                'agent_role' => 'AnalysisAI',
                'prompt_template_key' => 'backtest.summary',
                'output_format' => 'markdown_report',
            ],
            AIJobType::EVALUATE_EXPERIMENT => [
                'task_type' => 'optimizer_compare',
                'agent_role' => 'OptimizerAI',
                'prompt_template_key' => 'experiment.compare',
                'output_format' => 'json_object',
            ],
            default => throw new RuntimeException('Unsupported AI job type: ' . $jobType),
        };
    }

    private static function defaultGateway(): LLMGateway
    {
        $projectRoot = dirname(__DIR__, 2);
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        foreach (['001_storage_foundation.php', '015_prompt_templates.php'] as $file) {
            $migration = require $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . $file;
            $migration->up($pdo);
        }

        $prompts = new PromptManager(new SQLitePromptTemplateRepository($pdo));
        $prompts->seedDefaultTemplates();

        return new LLMGateway(
            $prompts,
            ProviderRouter::fromConfigFile($projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ai_providers.php'),
            new ResponseParser()
        );
    }
}
