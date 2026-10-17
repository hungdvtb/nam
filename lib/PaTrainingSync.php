<?php

declare(strict_types=1);

final class PaTrainingSync
{
    private const MEMORY_LISTS = ['zone_reviews', 'setup_reviews', 'lessons'];

    public static function merge(string $dataDir, array $payload, ?string $backupRoot = null): array
    {
        if (($payload['format'] ?? '') !== 'namtrade_pa_training_v1') {
            throw new InvalidArgumentException('File đồng bộ PA không đúng định dạng.');
        }

        $paDir = rtrim($dataDir, DIRECTORY_SEPARATOR) . '/pa_training';
        if (!is_dir($paDir) && !@mkdir($paDir, 0775, true) && !is_dir($paDir)) {
            throw new RuntimeException('Không thể tạo thư mục bộ nhớ PA.');
        }

        $memoryPath = $paDir . '/memory.json';
        $manualZonesPath = $paDir . '/manual_zones.json';
        $chatHistoryPath = $paDir . '/chat_history.json';
        $currentMemory = self::readJson($memoryPath, []);
        $incomingMemory = is_array($payload['memory'] ?? null) ? $payload['memory'] : [];
        $mergedMemory = $currentMemory;
        $changes = ['added' => [], 'updated' => [], 'totals' => []];

        foreach (self::MEMORY_LISTS as $key) {
            [$mergedMemory[$key], $added, $updated] = self::mergeRecords(
                is_array($currentMemory[$key] ?? null) ? $currentMemory[$key] : [],
                is_array($incomingMemory[$key] ?? null) ? $incomingMemory[$key] : []
            );
            $changes['added'][$key] = $added;
            $changes['updated'][$key] = $updated;
            $changes['totals'][$key] = count($mergedMemory[$key]);
        }

        [$manualZones, $added, $updated] = self::mergeRecords(
            self::readJson($manualZonesPath, []),
            is_array($payload['manual_zones'] ?? null) ? $payload['manual_zones'] : []
        );
        $changes['added']['manual_zones'] = $added;
        $changes['updated']['manual_zones'] = $updated;
        $changes['totals']['manual_zones'] = count($manualZones);

        [$chatHistory, $added, $updated] = self::mergeRecords(
            self::readJson($chatHistoryPath, []),
            is_array($payload['chat_history'] ?? null) ? $payload['chat_history'] : []
        );
        $changes['added']['chat_history'] = $added;
        $changes['updated']['chat_history'] = $updated;
        $changes['totals']['chat_history'] = count($chatHistory);

        if (array_sum($changes['added']) + array_sum($changes['updated']) > 0) {
            $backupBase = $backupRoot ?? (getenv('PA_TRAINING_SYNC_BACKUP_DIR') ?: $paDir . '/.sync-backups');
            $backupDir = rtrim($backupBase, DIRECTORY_SEPARATOR) . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
            if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
                throw new RuntimeException('Không thể tạo bản sao lưu trước khi đồng bộ.');
            }
            $paths = [$memoryPath, $manualZonesPath, $chatHistoryPath];
            $originals = [];
            foreach ($paths as $path) {
                $originals[$path] = is_file($path) ? file_get_contents($path) : null;
                if ($originals[$path] === false) {
                    throw new RuntimeException('Không thể đọc dữ liệu PA để sao lưu.');
                }
                if (is_file($path) && !@copy($path, $backupDir . '/' . basename($path))) {
                    throw new RuntimeException('Không thể sao lưu dữ liệu PA hiện có.');
                }
            }
            try {
                self::writeJsonFiles([
                    $memoryPath => $mergedMemory,
                    $manualZonesPath => $manualZones,
                    $chatHistoryPath => $chatHistory,
                ]);
            } catch (Throwable $error) {
                $rollbackErrors = [];
                foreach ($originals as $path => $contents) {
                    if ($contents === null) {
                        if (is_file($path) && !@unlink($path)) $rollbackErrors[] = basename($path);
                        continue;
                    }
                    if (!self::restoreFile($path, $contents)) $rollbackErrors[] = basename($path);
                }
                $message = 'Đồng bộ thất bại; đã khôi phục dữ liệu PA trước đó.';
                if ($rollbackErrors) $message .= ' Khôi phục chưa hoàn tất: ' . implode(', ', $rollbackErrors) . '.';
                throw new RuntimeException($message, 0, $error);
            }
            $changes['backup'] = basename($backupDir);
        } else {
            $changes['backup'] = null;
        }

        return $changes;
    }

    private static function mergeRecords(array $existing, array $incoming): array
    {
        $records = [];
        foreach ($existing as $item) {
            if (!is_array($item)) continue;
            $records[self::recordKey($item)] = $item;
        }

        $added = 0;
        $updated = 0;
        foreach ($incoming as $item) {
            if (!is_array($item)) continue;
            $key = self::recordKey($item);
            if (!array_key_exists($key, $records)) {
                $records[$key] = $item;
                $added++;
                continue;
            }
            if (self::recordTimestamp($item) > self::recordTimestamp($records[$key])) {
                $records[$key] = $item;
                $updated++;
            }
        }

        $merged = array_values($records);
        usort($merged, static fn(array $a, array $b): int => self::recordTimestamp($b) <=> self::recordTimestamp($a));
        return [$merged, $added, $updated];
    }

    private static function recordKey(array $item): string
    {
        $id = trim((string)($item['id'] ?? ''));
        if ($id !== '') return 'id:' . $id;
        return 'hash:' . hash('sha256', json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    private static function recordTimestamp(array $item): float
    {
        $latest = 0.0;
        foreach (['updatedAt', 'deletedAt', 'createdAt', 'stored_at', 'timestamp'] as $field) {
            if (empty($item[$field])) continue;
            try {
                $value = (float)(new DateTimeImmutable((string)$item[$field]))->format('U.u');
                $latest = max($latest, $value);
            } catch (Throwable) {
                continue;
            }
        }
        return $latest;
    }

    private static function readJson(string $path, array $fallback): array
    {
        if (!is_file($path)) return $fallback;
        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) throw new RuntimeException('Dữ liệu PA hiện tại không phải JSON hợp lệ: ' . basename($path));
        return $decoded;
    }

    private static function writeJsonFiles(array $files): void
    {
        $temporaryPaths = [];
        try {
            foreach ($files as $path => $data) {
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($json === false) throw new RuntimeException('Không thể mã hóa dữ liệu PA để lưu.');
                $temporary = $path . '.sync-' . bin2hex(random_bytes(4)) . '.tmp';
                if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
                    throw new RuntimeException('Không thể chuẩn bị dữ liệu PA đã đồng bộ.');
                }
                $temporaryPaths[$path] = $temporary;
            }
            foreach ($temporaryPaths as $path => $temporary) {
                if (!@rename($temporary, $path)) throw new RuntimeException('Không thể cập nhật dữ liệu PA đã đồng bộ.');
                unset($temporaryPaths[$path]);
            }
        } finally {
            foreach ($temporaryPaths as $temporary) @unlink($temporary);
        }
    }

    private static function restoreFile(string $path, string $contents): bool
    {
        $temporary = $path . '.rollback-' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) return false;
        if (@rename($temporary, $path)) return true;
        @unlink($temporary);
        return false;
    }
}
