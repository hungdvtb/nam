<?php

declare(strict_types=1);

require_once __DIR__ . '/Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/Repositories/Contracts/AIJobRepositoryInterface.php';
require_once __DIR__ . '/AIJobs/AIJobStatus.php';
require_once __DIR__ . '/AIJobs/AIJobType.php';
require_once __DIR__ . '/AIJobs/AIJobRecord.php';

final class AIJobService
{
    public function __construct(private AIJobRepositoryInterface $jobs)
    {
    }

    public function createJob(array $payload = []): array
    {
        $now = gmdate('c');
        $job = AIJobRecord::normalize(array_merge([
            'id' => $this->newId(),
            'status' => AIJobStatus::PENDING,
            'input_json' => [],
            'output_json' => [],
            'created_at' => $now,
            'created_by' => 'user',
        ], $payload, [
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ]));
        AIJobRecord::validate($job);

        return $this->jobs->create($job);
    }

    public function startJob(string $id): array
    {
        $job = $this->mustFindJob($id);
        $this->assertStatus($job, [AIJobStatus::PENDING], 'Only pending AI jobs can be started.');

        return $this->jobs->update($id, [
            'status' => AIJobStatus::RUNNING,
            'started_at' => gmdate('c'),
            'error_message' => null,
        ]);
    }

    public function completeJob(string $id, array $output = []): array
    {
        $job = $this->mustFindJob($id);
        $this->assertStatus($job, [AIJobStatus::RUNNING, AIJobStatus::PENDING], 'Only pending or running AI jobs can be completed.');
        $now = gmdate('c');

        return $this->jobs->update($id, [
            'status' => AIJobStatus::COMPLETED,
            'output_json' => $output,
            'error_message' => null,
            'started_at' => $job['started_at'] ?? $now,
            'completed_at' => $now,
        ]);
    }

    public function failJob(string $id, string $errorMessage): array
    {
        $job = $this->mustFindJob($id);
        $this->assertStatus($job, [AIJobStatus::RUNNING, AIJobStatus::PENDING], 'Only pending or running AI jobs can fail.');
        $now = gmdate('c');

        return $this->jobs->update($id, [
            'status' => AIJobStatus::FAILED,
            'error_message' => trim($errorMessage) !== '' ? $errorMessage : 'AI job failed.',
            'started_at' => $job['started_at'] ?? $now,
            'completed_at' => $now,
        ]);
    }

    public function cancelJob(string $id): array
    {
        $job = $this->mustFindJob($id);
        $this->assertStatus($job, [AIJobStatus::PENDING], 'Only pending AI jobs can be cancelled.');

        return $this->jobs->update($id, [
            'status' => AIJobStatus::CANCELLED,
            'completed_at' => gmdate('c'),
        ]);
    }

    public function updateJobOutput(string $id, array $output): array
    {
        $this->mustFindJob($id);

        return $this->jobs->update($id, [
            'output_json' => $output,
        ]);
    }

    public function findJob(string $id): ?array
    {
        return $this->jobs->find($id);
    }

    public function listJobs(int $limit = 100, array $filters = []): array
    {
        return $this->jobs->list($limit, $filters);
    }

    private function mustFindJob(string $id): array
    {
        $job = $this->findJob($id);
        if ($job === null) {
            throw new InvalidArgumentException('AI job not found: ' . $id);
        }

        return $job;
    }

    private function assertStatus(array $job, array $allowed, string $message): void
    {
        if (!in_array($job['status'] ?? '', $allowed, true)) {
            throw new InvalidArgumentException($message);
        }
    }

    private function newId(): string
    {
        return 'ai_job_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
}
