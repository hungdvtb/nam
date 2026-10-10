<?php

declare(strict_types=1);

require_once __DIR__ . '/../AIJobService.php';
require_once __DIR__ . '/../StrategyVersionService.php';

final class AIJobStrategyDraftService
{
    public function __construct(
        private AIJobService $jobs,
        private StrategyVersionService $versions
    ) {
    }

    public function createDraftFromJob(string $jobId): array
    {
        $job = $this->jobs->findJob($jobId);
        if ($job === null) {
            throw new InvalidArgumentException('AI job not found: ' . $jobId);
        }

        if (($job['status'] ?? '') !== AIJobStatus::COMPLETED) {
            throw new InvalidArgumentException('Only completed AI jobs can create strategy drafts.');
        }

        $jobType = AIJobType::normalize((string)($job['job_type'] ?? ''));
        if (!in_array($jobType, [AIJobType::GENERATE_STRATEGY_IDEA, AIJobType::IMPROVE_STRATEGY], true)) {
            throw new InvalidArgumentException('AI job output is not strategy-like.');
        }

        $output = is_array($job['output_json'] ?? null) ? $job['output_json'] : [];
        if ($output === []) {
            throw new InvalidArgumentException('AI job output_json is empty.');
        }

        $existingId = trim((string)($output['strategy_version_id'] ?? $output['strategyVersionId'] ?? ''));
        if ($existingId !== '') {
            $existing = $this->versions->findVersion($existingId);
            if ($existing !== null) {
                return $this->payload($job, $existing, false);
            }
        }

        $rules = $this->rulesFromOutput($jobType, $output);
        $description = $this->descriptionFromOutput($jobType, $output, (string)$job['id']);
        $version = $this->versions->createVersion([
            'name' => $this->draftName($jobType),
            'description' => $description,
            'status' => StrategyVersionStatus::DRAFT,
            'created_by' => 'ai',
            'rules_json' => $rules,
            'settings_json' => [
                'source' => 'ai_job',
                'source_ai_job_id' => $job['id'],
                'source_job_type' => $jobType,
                'source_experiment_id' => $job['experiment_id'] ?? null,
                'explanation' => $description,
            ],
            'performance_json' => [],
            'approved' => false,
            'approved_at' => null,
        ]);

        $output['strategy_version_id'] = $version['id'];
        $output['strategyVersionId'] = $version['id'];
        $output['strategy_draft_created_at'] = gmdate('c');
        $updatedJob = $this->jobs->updateJobOutput((string)$job['id'], $output);

        return $this->payload($updatedJob, $version, true);
    }

    private function payload(array $job, array $version, bool $created): array
    {
        return [
            'ai_job' => $job,
            'strategy_version' => $version,
            'version' => $version,
            'strategy_versions' => $this->versions->listVersions(),
            'latest_strategy_version' => $this->versions->getLatestVersion(),
            'production_strategy_version' => $this->versions->getProductionVersion(),
            'created' => $created,
        ];
    }

    private function rulesFromOutput(string $jobType, array $output): array
    {
        $rules = null;
        foreach ($this->outputPayloads($output) as $payload) {
            $rules = match ($jobType) {
                AIJobType::GENERATE_STRATEGY_IDEA => $payload['rules_json'] ?? $payload['rulesJson'] ?? $payload['rules'] ?? null,
                AIJobType::IMPROVE_STRATEGY => $payload['changed_rules_json'] ?? $payload['changedRulesJson'] ?? $payload['rules_json'] ?? $payload['rulesJson'] ?? null,
                default => null,
            };
            if (is_array($rules) && $rules !== []) {
                break;
            }
        }

        if (!is_array($rules) || $rules === []) {
            throw new InvalidArgumentException('AI job output does not contain strategy rules JSON.');
        }

        return $rules;
    }

    private function descriptionFromOutput(string $jobType, array $output, string $jobId): string
    {
        $primary = '';
        $secondary = '';
        foreach ($this->outputPayloads($output) as $payload) {
            if ($primary === '') {
                $primary = match ($jobType) {
                    AIJobType::GENERATE_STRATEGY_IDEA => $this->firstString($payload, ['explanation', 'description', 'mock_strategy_idea', 'strategy_idea']),
                    AIJobType::IMPROVE_STRATEGY => $this->firstString($payload, ['reason', 'explanation', 'mock_improvement_suggestion', 'improvement_suggestion']),
                    default => '',
                };
            }
            if ($secondary === '') {
                $secondary = match ($jobType) {
                    AIJobType::GENERATE_STRATEGY_IDEA => $this->firstString($payload, ['mock_strategy_idea', 'strategy_idea']),
                    AIJobType::IMPROVE_STRATEGY => $this->firstString($payload, ['mock_improvement_suggestion', 'improvement_suggestion']),
                    default => '',
                };
            }
        }

        $parts = array_values(array_unique(array_filter([$primary, $secondary], fn(string $part): bool => trim($part) !== '')));
        $parts[] = 'Source AI job: ' . $jobId;

        return implode("\n\n", $parts);
    }

    private function draftName(string $jobType): string
    {
        return match ($jobType) {
            AIJobType::GENERATE_STRATEGY_IDEA => 'AI Draft - Strategy Idea',
            AIJobType::IMPROVE_STRATEGY => 'AI Draft - Strategy Improvement',
            default => 'AI Draft',
        };
    }

    private function firstString(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && trim((string)$payload[$key]) !== '') {
                return trim((string)$payload[$key]);
            }
        }

        return '';
    }

    private function outputPayloads(array $output): array
    {
        $payloads = [$output];
        if (is_array($output['parsed_output'] ?? null)) {
            $payloads[] = $output['parsed_output'];
        }
        if (is_array($output['llm_response']['parsed'] ?? null)) {
            $payloads[] = $output['llm_response']['parsed'];
        }

        return $payloads;
    }
}
