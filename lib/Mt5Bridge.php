<?php

final class Mt5Bridge
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = rtrim($projectDir, DIRECTORY_SEPARATOR);
    }

    public function status(?string $terminalPath = null): array
    {
        return $this->runBridge(['status'], $terminalPath);
    }

    public function fetch(array $symbols, array $timeframes, int $bars, ?string $terminalPath = null, array $barsByTimeframe = []): array
    {
        $symbols = array_values(array_filter(array_map(fn($s) => strtoupper(trim((string)$s)), $symbols)));
        $timeframes = array_values(array_filter(array_map(fn($t) => strtoupper(trim((string)$t)), $timeframes)));
        if (!$symbols) {
            throw new InvalidArgumentException('Bạn chưa chọn cặp tiền cần lấy dữ liệu');
        }
        if (!$timeframes) {
            throw new InvalidArgumentException('Bạn chưa chọn khung thời gian cần lấy dữ liệu');
        }
        $maxBars = 1000000;
        $bars = max(50, min($maxBars, $bars));
        $cleanBarsByTimeframe = [];
        foreach ($barsByTimeframe as $timeframe => $count) {
            $timeframe = strtoupper(trim((string)$timeframe));
            if ($timeframe === '') {
                continue;
            }
            $cleanBarsByTimeframe[$timeframe] = max(50, min($maxBars, (int)$count));
        }

        $args = [
            'fetch',
            '--symbols', implode(',', $symbols),
            '--timeframes', implode(',', $timeframes),
            '--bars', (string)$bars,
        ];
        if ($cleanBarsByTimeframe) {
            $pairs = [];
            foreach ($cleanBarsByTimeframe as $timeframe => $count) {
                $pairs[] = $timeframe . ':' . $count;
            }
            $args[] = '--bars-by-timeframe';
            $args[] = implode(',', $pairs);
        }
        $args[] = '--output-dir';
        $args[] = $this->projectDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'market_data';

        return $this->runBridge($args, $terminalPath, 600);
    }

    public function saveMarketData(array $bridgePayload, JsonStore $store): array
    {
        $written = [];
        $data = $bridgePayload['data'] ?? [];
        if (!is_array($data)) {
            return $written;
        }

        foreach ($data as $symbol => $byTimeframe) {
            if (!is_array($byTimeframe)) {
                continue;
            }
            foreach ($byTimeframe as $timeframe => $bars) {
                if (!is_array($bars)) {
                    continue;
                }
                $file = 'market_data/' . $this->safeName($symbol) . '_' . $this->safeName($timeframe) . '.json';
                $incomingCount = count($bars);
                $existing = $store->readJson($file, null);
                $existingBars = is_array($existing) && is_array($existing['bars'] ?? null) ? count($existing['bars']) : 0;
                if ($existingBars > $incomingCount) {
                    $written[] = [
                        'symbol' => strtoupper((string)$symbol),
                        'timeframe' => strtoupper((string)$timeframe),
                        'bars' => $existingBars,
                        'fetched_bars' => $incomingCount,
                        'file' => $file,
                        'kept_existing' => true,
                    ];
                    continue;
                }

                $payload = [
                    'symbol' => strtoupper((string)$symbol),
                    'timeframe' => strtoupper((string)$timeframe),
                    'source' => 'MT5',
                    'fetched_at' => $bridgePayload['fetched_at'] ?? gmdate('c'),
                    'bars' => $bars,
                ];
                $store->writeJson($file, $payload);
                $written[] = [
                    'symbol' => $payload['symbol'],
                    'timeframe' => $payload['timeframe'],
                    'bars' => $incomingCount,
                    'fetched_bars' => $incomingCount,
                    'file' => $file,
                    'kept_existing' => false,
                ];
            }
        }

        return $written;
    }

    public function marketFiles(JsonStore $store): array
    {
        $dir = $this->projectDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'market_data';
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $items = [];
        foreach ($files as $path) {
            $summary = $this->marketFileSummary($path);
            if ($summary === null) {
                continue;
            }
            $items[] = [
                'symbol' => $summary['symbol'],
                'timeframe' => $summary['timeframe'],
                'source' => $summary['source'],
                'fetched_at' => $summary['fetched_at'],
                'bars' => $summary['bars'],
                'file' => 'market_data/' . basename($path),
            ];
        }

        usort($items, fn($a, $b) => [$a['symbol'], $a['timeframe']] <=> [$b['symbol'], $b['timeframe']]);
        return $items;
    }

    private function marketFileSummary(string $path): ?array
    {
        $head = file_get_contents($path, false, null, 0, 16384);
        if ($head === false || trim($head) === '') {
            return null;
        }

        $symbol = $this->jsonStringField($head, 'symbol');
        $timeframe = $this->jsonStringField($head, 'timeframe');
        if ($symbol === '' || $timeframe === '') {
            return null;
        }

        return [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'source' => $this->jsonStringField($head, 'source'),
            'fetched_at' => $this->jsonStringField($head, 'fetched_at'),
            'bars' => $this->countBarRows($path),
        ];
    }

    private function jsonStringField(string $jsonHead, string $field): string
    {
        $pattern = '/"' . preg_quote($field, '/') . '"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/';
        if (!preg_match($pattern, $jsonHead, $match)) {
            return '';
        }

        $decoded = json_decode('"' . $match[1] . '"', true);
        return is_string($decoded) ? $decoded : '';
    }

    private function countBarRows(string $path): int
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        $count = 0;
        $needle = '"time":';
        $tail = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 1048576);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $scan = $tail . $chunk;
            $count += substr_count($scan, $needle) - substr_count($tail, $needle);
            $tail = substr($scan, -strlen($needle) + 1);
        }
        fclose($handle);

        return $count;
    }

    private function runBridge(array $args, ?string $terminalPath = null, int $timeoutSeconds = 120): array
    {
        $python = $this->findPython();
        if ($python === null) {
            return [
                'ok' => false,
                'error' => 'Không tìm thấy Python trên máy.',
                'install_hint' => 'Cài Python 64-bit rồi cài package: pip install MetaTrader5',
            ];
        }

        $script = $this->projectDir . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'mt5_bridge.py';
        $command = array_merge($python, [$script], $args);
        if ($terminalPath !== null && trim($terminalPath) !== '') {
            $command[] = '--terminal';
            $command[] = $terminalPath;
        }

        $result = $this->runCommand($command, $timeoutSeconds);
        $decoded = json_decode($result['stdout'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $decoded['_process'] = [
                'exit_code' => $result['exit_code'],
                'stderr' => trim($result['stderr']),
                'python' => implode(' ', $python),
            ];
            return $decoded;
        }

        return [
            'ok' => false,
            'error' => 'Bridge MT5 không trả JSON hợp lệ',
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'exit_code' => $result['exit_code'],
            'python' => implode(' ', $python),
        ];
    }

    private function findPython(): ?array
    {
        $env = getenv('AI_TRADE_PYTHON');
        $candidates = [];
        if ($env) {
            $candidates[] = [$env];
        }

        $local = [
            $this->projectDir . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            'C:\\Python314\\python.exe',
            'C:\\Python313\\python.exe',
            'C:\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
            getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA') . '\\Programs\\Python\\Python314\\python.exe' : '',
            getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA') . '\\Programs\\Python\\Python313\\python.exe' : '',
            getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA') . '\\Programs\\Python\\Python312\\python.exe' : '',
            getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA') . '\\Programs\\Python\\Python311\\python.exe' : '',
        ];
        foreach ($local as $path) {
            if ($path && is_file($path)) {
                $candidates[] = [$path];
            }
        }

        $candidates[] = ['python'];
        $candidates[] = ['py', '-3'];

        foreach ($candidates as $candidate) {
            $result = $this->runCommand(array_merge($candidate, ['--version']), 10);
            if ($result['exit_code'] === 0 && (str_contains($result['stdout'], 'Python') || str_contains($result['stderr'], 'Python'))) {
                return $candidate;
            }
        }

        return null;
    }

    private function runCommand(array $tokens, int $timeoutSeconds): array
    {
        $command = implode(' ', array_map([$this, 'quote'], $tokens));
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->projectDir);
        if (!is_resource($process)) {
            return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'Không chạy được command'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $started = time();
        while (true) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (time() - $started > $timeoutSeconds) {
                proc_terminate($process);
                $stderr .= "\nCommand quá thời gian chờ";
                break;
            }
            usleep(50000);
        }

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exit_code' => $exitCode,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
        ];
    }

    private function quote(string $token): string
    {
        if (preg_match('/^[A-Za-z0-9_:\\\\\/\\.\\-]+$/', $token)) {
            return $token;
        }
        return '"' . str_replace('"', '\"', $token) . '"';
    }

    private function safeName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\\-]/', '_', strtoupper($value));
    }
}
