<?php

final class JsonStore
{
    private string $baseDir;
    private array $defaults;

    public function __construct(string $baseDir, array $defaults)
    {
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $this->defaults = $defaults;
        $this->ensureStructure();
    }

    public function ensureStructure(): void
    {
        foreach ([
            $this->baseDir,
            $this->baseDir . '/backtests',
            $this->baseDir . '/cases',
            $this->baseDir . '/exports',
            $this->baseDir . '/market_data',
            $this->baseDir . '/tradebrain',
        ] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        $this->seedJson('accounts.json', $this->defaults['accounts']);
        $this->seedJson('strategy.json', $this->defaults['strategy']);
        $this->seedJson('strategy_summary.json', $this->defaults['strategy_summary']);
        $this->seedJson('candidate_cases.json', $this->defaults['candidate_cases']);
    }

    public function readJson(string $file, mixed $fallback = []): mixed
    {
        $path = $this->path($file);
        if (!is_file($path)) {
            return $fallback;
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return $fallback;
        }

        $decoded = json_decode($content, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
    }

    public function writeJson(string $file, mixed $data): void
    {
        $path = $this->path($file);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Không thể tạo thư mục lưu dữ liệu: ' . $dir);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Không thể mã hóa JSON cho ' . $file);
        }

        $tmp = $path . '.tmp';
        $written = @file_put_contents($tmp, $json . PHP_EOL, LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('Không thể ghi file dữ liệu tạm: ' . $tmp);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Không thể cập nhật file dữ liệu: ' . $path);
        }
    }

    public function appendJsonl(string $file, array $record): void
    {
        $path = $this->path($file);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Không thể tạo thư mục lưu dữ liệu: ' . $dir);
        }

        $record['stored_at'] = gmdate('c');
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Không thể mã hóa bản ghi JSONL cho ' . $file);
        }

        $written = @file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('Không thể ghi file dữ liệu: ' . $path);
        }
    }

    public function readJsonl(string $file, int $limit = 50): array
    {
        $path = $this->path($file);
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $lines = array_slice($lines, max(0, count($lines) - $limit));
        $records = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return array_reverse($records);
    }

    public function stats(): array
    {
        $records = $this->readJsonl('cases/reviewed_cases.jsonl', 100000);
        $stats = [
            'total_reviewed' => count($records),
            'approved' => 0,
            'rejected' => 0,
            'edited' => 0,
            'by_symbol' => [],
            'by_setup' => [],
        ];

        foreach ($records as $record) {
            $decision = $record['your_decision'] ?? 'unknown';
            if (isset($stats[$decision])) {
                $stats[$decision]++;
            }

            $symbol = $record['case']['symbol'] ?? 'UNKNOWN';
            $setup = $record['case']['setup_type'] ?? 'unknown';
            $stats['by_symbol'][$symbol] = ($stats['by_symbol'][$symbol] ?? 0) + 1;
            $stats['by_setup'][$setup] = ($stats['by_setup'][$setup] ?? 0) + 1;
        }

        ksort($stats['by_symbol']);
        ksort($stats['by_setup']);
        return $stats;
    }

    public function exportSnapshot(): array
    {
        return [
            'exported_at' => gmdate('c'),
            'format' => 'ai_trade_memory_v1',
            'accounts' => $this->readJson('accounts.json', []),
            'strategy' => $this->readJson('strategy.json', []),
            'strategy_summary' => $this->readJson('strategy_summary.json', []),
            'candidate_cases' => $this->readJson('candidate_cases.json', []),
            'reviewed_cases' => $this->readJsonl('cases/reviewed_cases.jsonl', 100000),
            'strategy_versions' => $this->readJson('strategy_versions.json', []),
            'backtest_runs' => $this->readJson('backtests/backtest_runs.json', []),
            'simulated_trades' => $this->readJson('backtests/backtest_trades.json', []),
            'manual_reviews' => $this->readJson('manual_reviews.json', []),
            'zone_manual_reviews' => $this->readJson('zone_manual_reviews.json', []),
            'calibrated_presets' => $this->readJson('calibrated_presets.json', []),
            'tradebrain_profile' => $this->readJson('tradebrain/tradebrain_profile.json', []),
            'tradebrain_memory' => $this->readJson('tradebrain/tradebrain_memory.json', []),
            'ai_decisions' => $this->readJson('tradebrain/ai_decisions.json', []),
            'strategy_upgrade_proposals' => $this->readJson('tradebrain/strategy_upgrade_proposals.json', []),
            'strategy_evaluations' => $this->readJson('tradebrain/strategy_evaluations.json', []),
            'ai_usage' => $this->readJson('tradebrain/ai_usage.json', []),
            'pa_training_memory' => $this->readJson('pa_training/memory.json', ['zone_reviews' => [], 'setup_reviews' => []]),
            'pa_training_manual_zones' => $this->readJson('pa_training/manual_zones.json', []),
            'pa_training_chat_history' => $this->readJson('pa_training/chat_history.json', []),
            'stats' => $this->stats(),
        ];
    }

    private function seedJson(string $file, mixed $data): void
    {
        $path = $this->path($file);
        if (!is_file($path)) {
            $this->writeJson($file, $data);
        }
    }

    private function path(string $file): string
    {
        $file = str_replace(['..', '\\'], ['', '/'], $file);
        return $this->baseDir . '/' . ltrim($file, '/');
    }
}

