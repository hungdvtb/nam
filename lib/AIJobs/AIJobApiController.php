<?php

declare(strict_types=1);

require_once __DIR__ . '/../AIJobService.php';
require_once __DIR__ . '/AIJobWorker.php';
require_once __DIR__ . '/AIJobStrategyDraftService.php';

final class AIJobApiController
{
    private AIJobWorker $worker;

    public function __construct(
        private AIJobService $jobs,
        ?AIJobWorker $worker = null,
        private ?AIJobStrategyDraftService $strategyDrafts = null
    ) {
        $this->worker = $worker ?? new AIJobWorker($jobs);
    }

    public function handle(string $action, array $payload): array
    {
        return match ($action) {
            'ai_job_list' => $this->list($payload),
            'ai_job_find' => $this->find($payload),
            'ai_job_create' => $this->create($payload),
            'ai_job_cancel' => $this->cancel($payload),
            'ai_job_create_strategy_draft' => $this->createStrategyDraft($payload),
            'ai_worker_run_once' => $this->runWorkerOnce(),
            default => throw new InvalidArgumentException('Unsupported AI job API action: ' . $action),
        };
    }

    private function list(array $payload): array
    {
        return [
            'ok' => true,
            'ai_jobs' => $this->jobs->listJobs(
                $this->limit($payload),
                $this->filters($payload, ['status', 'job_type', 'strategy_version_id', 'experiment_id', 'created_by'])
            ),
        ];
    }

    private function find(array $payload): array
    {
        $id = $this->id($payload);
        $job = $this->jobs->findJob($id);
        if ($job === null) {
            throw new InvalidArgumentException('AI job not found: ' . $id);
        }

        return [
            'ok' => true,
            'ai_job' => $job,
        ];
    }

    private function create(array $payload): array
    {
        $input = $this->entityPayload($payload, 'job');
        $job = $this->jobs->createJob($this->jobPayload($input));

        return [
            'ok' => true,
            'ai_job' => $job,
            'ai_jobs' => $this->jobs->listJobs(),
        ];
    }

    private function cancel(array $payload): array
    {
        $job = $this->jobs->cancelJob($this->id($payload));

        return [
            'ok' => true,
            'ai_job' => $job,
            'ai_jobs' => $this->jobs->listJobs(),
        ];
    }

    private function runWorkerOnce(): array
    {
        $result = $this->worker->runOnce();

        return array_merge([
            'ok' => true,
            'ai_jobs' => $this->jobs->listJobs(),
        ], $result);
    }

    private function createStrategyDraft(array $payload): array
    {
        if ($this->strategyDrafts === null) {
            throw new RuntimeException('Strategy draft conversion is not available.');
        }

        $result = $this->strategyDrafts->createDraftFromJob($this->id($payload));

        return array_merge([
            'ok' => true,
            'ai_jobs' => $this->jobs->listJobs(),
        ], $result);
    }

    private function jobPayload(array $input): array
    {
        $payload = $this->only($input, [
            'id',
            'job_type',
            'status',
            'strategy_version_id',
            'experiment_id',
            'created_by',
            'notes',
        ]);
        $aliases = [
            'jobType' => 'job_type',
            'strategyVersionId' => 'strategy_version_id',
            'experimentId' => 'experiment_id',
            'createdBy' => 'created_by',
        ];
        foreach ($aliases as $source => $target) {
            if (isset($input[$source]) && !isset($payload[$target])) {
                $payload[$target] = $input[$source];
            }
        }

        if ($this->hasAny($input, ['input_json', 'inputJson', 'input'])) {
            $payload['input_json'] = $this->arrayField($input, ['input_json', 'inputJson', 'input'], []);
        }
        if ($this->hasAny($input, ['output_json', 'outputJson', 'output'])) {
            $payload['output_json'] = $this->arrayField($input, ['output_json', 'outputJson', 'output'], []);
        }

        return $payload;
    }

    private function filters(array $payload, array $fields): array
    {
        $filters = [];
        foreach ($fields as $field) {
            $value = $payload[$field] ?? $payload[$this->camel($field)] ?? null;
            if ($value !== null && trim((string)$value) !== '') {
                $filters[$field] = (string)$value;
            }
        }

        return $filters;
    }

    private function only(array $payload, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $value = $payload[$field] ?? $payload[$this->camel($field)] ?? null;
            if ($value !== null) {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    private function entityPayload(array $payload, string $key): array
    {
        if (isset($payload[$key]) && is_array($payload[$key])) {
            return array_merge($payload, $payload[$key]);
        }

        return $payload;
    }

    private function arrayField(array $payload, array $keys, array $fallback): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = $payload[$key];
            if (is_array($value)) {
                return $value;
            }
            if (is_string($value) && trim($value) !== '') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
            if ($value === null || $value === '') {
                return $fallback;
            }

            throw new InvalidArgumentException($key . ' must be an array or JSON object.');
        }

        return $fallback;
    }

    private function id(array $payload): string
    {
        $id = trim((string)($payload['id'] ?? $payload['ai_job_id'] ?? $payload['aiJobId'] ?? ''));
        if ($id === '') {
            throw new InvalidArgumentException('AI job id is required.');
        }

        return $id;
    }

    private function hasAny(array $payload, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }

    private function limit(array $payload): int
    {
        return max(1, min(500, (int)($payload['limit'] ?? 100)));
    }

    private function camel(string $field): string
    {
        return preg_replace_callback('/_([a-z])/', fn(array $matches): string => strtoupper($matches[1]), $field) ?? $field;
    }
}
