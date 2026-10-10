<?php

declare(strict_types=1);

require_once __DIR__ . '/../ExperimentService.php';
require_once __DIR__ . '/ExperimentStatus.php';
require_once __DIR__ . '/ExperimentItemStatus.php';

final class ExperimentApiController
{
    public function __construct(private ExperimentService $experiments)
    {
    }

    public function handle(string $action, array $payload): array
    {
        return match ($action) {
            'experiment_list' => $this->list($payload),
            'experiment_find' => $this->find($payload),
            'experiment_create' => $this->create($payload),
            'experiment_update' => $this->update($payload),
            'experiment_start' => $this->start($payload),
            'experiment_pause' => $this->pause($payload),
            'experiment_complete' => $this->complete($payload),
            'experiment_archive' => $this->archive($payload),
            'experiment_add_item' => $this->addItem($payload),
            'experiment_list_items' => $this->listItems($payload),
            'experiment_best_item' => $this->bestItem($payload),
            default => throw new InvalidArgumentException('Unsupported experiment API action: ' . $action),
        };
    }

    private function list(array $payload): array
    {
        return [
            'ok' => true,
            'experiments' => $this->experiments->listExperiments(
                $this->limit($payload),
                $this->filters($payload, ['status', 'created_by'])
            ),
        ];
    }

    private function find(array $payload): array
    {
        $id = $this->experimentId($payload);
        $experiment = $this->experiments->findExperiment($id);
        if ($experiment === null) {
            throw new InvalidArgumentException('Experiment not found: ' . $id);
        }

        return [
            'ok' => true,
            'experiment' => $experiment,
        ];
    }

    private function create(array $payload): array
    {
        $input = $this->entityPayload($payload, 'experiment');
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Experiment name is required.');
        }

        $experiment = $this->experiments->createExperiment($this->experimentCreatePayload($input));

        return [
            'ok' => true,
            'experiment' => $experiment,
            'experiments' => $this->experiments->listExperiments(),
        ];
    }

    private function update(array $payload): array
    {
        $id = $this->experimentId($payload);
        $changes = $this->experimentUpdatePayload($this->entityPayload($payload, 'experiment'));
        if ($changes === []) {
            throw new InvalidArgumentException('No experiment fields were provided for update.');
        }

        $experiment = $this->experiments->updateExperiment($id, $changes);

        return [
            'ok' => true,
            'experiment' => $experiment,
            'experiments' => $this->experiments->listExperiments(),
        ];
    }

    private function start(array $payload): array
    {
        $experiment = $this->experiments->startExperiment($this->experimentId($payload));

        return [
            'ok' => true,
            'experiment' => $experiment,
        ];
    }

    private function pause(array $payload): array
    {
        $experiment = $this->experiments->pauseExperiment($this->experimentId($payload));

        return [
            'ok' => true,
            'experiment' => $experiment,
        ];
    }

    private function complete(array $payload): array
    {
        $experiment = $this->experiments->completeExperiment(
            $this->experimentId($payload),
            $this->arrayField($payload, ['summary_json', 'summaryJson', 'summary'], [])
        );

        return [
            'ok' => true,
            'experiment' => $experiment,
        ];
    }

    private function archive(array $payload): array
    {
        $experiment = $this->experiments->archiveExperiment($this->experimentId($payload));

        return [
            'ok' => true,
            'experiment' => $experiment,
        ];
    }

    private function addItem(array $payload): array
    {
        $experimentId = $this->experimentId($payload);
        $input = $this->entityPayload($payload, 'item');
        $itemPayload = $this->itemPayload($input);
        if (!isset($itemPayload['strategy_version_id']) && !isset($itemPayload['backtest_result_id']) && !isset($itemPayload['item_type'])) {
            throw new InvalidArgumentException('Experiment item requires a strategy version, backtest result, or item type.');
        }

        $item = $this->experiments->addItem($experimentId, $itemPayload);

        return [
            'ok' => true,
            'item' => $item,
            'items' => $this->experiments->listItems($experimentId),
        ];
    }

    private function listItems(array $payload): array
    {
        $experimentId = $this->experimentId($payload);

        return [
            'ok' => true,
            'items' => $this->experiments->listItems(
                $experimentId,
                $this->limit($payload),
                $this->filters($payload, ['status', 'item_type', 'strategy_version_id', 'backtest_result_id'])
            ),
        ];
    }

    private function bestItem(array $payload): array
    {
        return [
            'ok' => true,
            'item' => $this->experiments->getBestItem($this->experimentId($payload)),
        ];
    }

    private function experimentCreatePayload(array $input): array
    {
        $payload = $this->only($input, ['id', 'name', 'description', 'created_by', 'notes']);
        $payload['goal_json'] = $this->arrayField($input, ['goal_json', 'goalJson', 'goal'], []);

        return $payload;
    }

    private function experimentUpdatePayload(array $input): array
    {
        $payload = $this->only($input, [
            'name',
            'description',
            'status',
            'created_by',
            'started_at',
            'completed_at',
            'best_strategy_version_id',
            'best_backtest_result_id',
            'notes',
        ]);

        if ($this->hasAny($input, ['goal_json', 'goalJson', 'goal'])) {
            $payload['goal_json'] = $this->arrayField($input, ['goal_json', 'goalJson', 'goal'], []);
        }
        if ($this->hasAny($input, ['summary_json', 'summaryJson', 'summary'])) {
            $payload['summary_json'] = $this->arrayField($input, ['summary_json', 'summaryJson', 'summary'], []);
        }

        return $payload;
    }

    private function itemPayload(array $input): array
    {
        $payload = $this->only($input, [
            'id',
            'strategy_version_id',
            'backtest_result_id',
            'item_type',
            'status',
            'score',
            'created_at',
            'notes',
        ]);
        $aliases = [
            'strategyVersionId' => 'strategy_version_id',
            'backtestResultId' => 'backtest_result_id',
            'itemType' => 'item_type',
            'createdAt' => 'created_at',
        ];
        foreach ($aliases as $source => $target) {
            if (isset($input[$source]) && !isset($payload[$target])) {
                $payload[$target] = $input[$source];
            }
        }
        if ($this->hasAny($input, ['metrics_json', 'metricsJson', 'metrics'])) {
            $payload['metrics_json'] = $this->arrayField($input, ['metrics_json', 'metricsJson', 'metrics'], []);
        }

        return $payload;
    }

    private function entityPayload(array $payload, string $key): array
    {
        if (isset($payload[$key]) && is_array($payload[$key])) {
            return array_merge($payload, $payload[$key]);
        }

        return $payload;
    }

    private function experimentId(array $payload): string
    {
        $id = trim((string)($payload['experiment_id'] ?? $payload['experimentId'] ?? $payload['id'] ?? ''));
        if ($id === '') {
            throw new InvalidArgumentException('Experiment id is required.');
        }

        return $id;
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

            throw new InvalidArgumentException($key . ' must be an array or JSON object.');
        }

        return $fallback;
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
        return preg_replace_callback('/_([a-z])/', fn(array $match): string => strtoupper($match[1]), $field) ?? $field;
    }
}
