<?php

declare(strict_types=1);

final class JsonCompatibilityAdapter
{
    private JsonStore $store;
    private array $datasetFiles;
    private array $logFiles;

    public function __construct(JsonStore $store, ?array $datasetFiles = null, ?array $logFiles = null)
    {
        $this->store = $store;
        $this->datasetFiles = $datasetFiles ?? self::defaultDatasetFiles();
        $this->logFiles = $logFiles ?? self::defaultLogFiles();
    }

    public static function defaultDatasetFiles(): array
    {
        return [
            'accounts' => 'accounts.json',
            'strategy' => 'strategy.json',
            'strategy_summary' => 'strategy_summary.json',
            'candidate_cases' => 'candidate_cases.json',
            'strategy_versions' => 'strategy_versions.json',
            'backtest_runs' => 'backtests/backtest_runs.json',
            'backtest_trades' => 'backtests/backtest_trades.json',
            'backtest_results' => 'backtests/backtest_results.json',
            'experiments' => 'experiments.json',
            'experiment_items' => 'experiment_items.json',
            'evaluation_results' => 'evaluation_results.json',
            'kpi_profiles' => 'kpi_profiles.json',
            'manual_reviews' => 'manual_reviews.json',
            'zone_manual_reviews' => 'zone_manual_reviews.json',
            'calibrated_presets' => 'calibrated_presets.json',
            'tradebrain_profile' => 'tradebrain/tradebrain_profile.json',
            'tradebrain_memory' => 'tradebrain/tradebrain_memory.json',
            'ai_decisions' => 'tradebrain/ai_decisions.json',
            'strategy_upgrade_proposals' => 'tradebrain/strategy_upgrade_proposals.json',
            'strategy_evaluations' => 'tradebrain/strategy_evaluations.json',
            'ai_usage' => 'tradebrain/ai_usage.json',
        ];
    }

    public static function defaultLogFiles(): array
    {
        return [
            'reviewed_cases' => 'cases/reviewed_cases.jsonl',
        ];
    }

    public function read(string $datasetName, mixed $fallback = []): mixed
    {
        return $this->store->readJson($this->datasetFile($datasetName), $fallback);
    }

    public function write(string $datasetName, mixed $payload): void
    {
        $this->store->writeJson($this->datasetFile($datasetName), $payload);
    }

    public function appendLog(string $logName, array $record): void
    {
        $this->store->appendJsonl($this->logFile($logName), $record);
    }

    public function readLog(string $logName, int $limit = 50): array
    {
        return $this->store->readJsonl($this->logFile($logName), $limit);
    }

    public function exportSnapshot(): array
    {
        return $this->store->exportSnapshot();
    }

    public function hasDataset(string $datasetName): bool
    {
        return array_key_exists($datasetName, $this->datasetFiles);
    }

    public function hasLog(string $logName): bool
    {
        return array_key_exists($logName, $this->logFiles);
    }

    public function datasets(): array
    {
        return $this->datasetFiles;
    }

    public function logs(): array
    {
        return $this->logFiles;
    }

    private function datasetFile(string $datasetName): string
    {
        if (!$this->hasDataset($datasetName)) {
            throw new InvalidArgumentException('Unknown legacy JSON dataset: ' . $datasetName);
        }

        return $this->datasetFiles[$datasetName];
    }

    private function logFile(string $logName): string
    {
        if (!$this->hasLog($logName)) {
            throw new InvalidArgumentException('Unknown legacy JSONL log: ' . $logName);
        }

        return $this->logFiles[$logName];
    }
}
