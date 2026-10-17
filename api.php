<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/JsonStore.php';
require_once __DIR__ . '/lib/Mt5Bridge.php';
require_once __DIR__ . '/lib/PaReviewService.php';
require_once __DIR__ . '/lib/PaAdaptiveCaseSelector.php';
require_once __DIR__ . '/lib/AI/Gateway/ProviderFactory.php';
require_once __DIR__ . '/lib/AI/Gateway/OpenAIProviderStatus.php';
require_once __DIR__ . '/lib/AI/Gateway/OllamaProviderStatus.php';

if (!defined('AI_TRADE_API_LIBRARY_ONLY')) {
header('Content-Type: application/json; charset=utf-8');

$defaults = require __DIR__ . '/config/defaults.php';
$store = new JsonStore(__DIR__ . '/data', $defaults);
$action = trim((string)($_GET['action'] ?? ''));

try {
    if ($action === '') {
        throw new InvalidArgumentException('Missing API action.');
    }
    $payload = apiPayload();
    if ($action === 'market_data') {
        streamMarketDataResponse($payload);
    } else {
        apiJson(apiHandle($action, $payload, $store, $defaults));
    }
} catch (Throwable $e) {
    http_response_code(400);
    apiJson(['ok' => false, 'error' => $e->getMessage()]);
}
}

function apiPayload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if (trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new InvalidArgumentException('Invalid JSON payload.');
    return $data;
}

function apiJson(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function apiHandle(string $action, array $payload, JsonStore $store, array $defaults): array
{
    return match ($action) {
        'bootstrap' => bootstrapPayload($store, $defaults),
        'market_files' => ['ok' => true, 'market_files' => marketFiles()],
        'market_data' => marketData($payload),
        'risk_preview' => riskPreview($payload),
        'save_accounts' => saveAccounts($store, $payload),
        'save_strategy' => saveStrategy($store, $payload),
        'mt5_status' => mt5StatusAction($payload),
        'mt5_fetch' => mt5FetchAction($payload),
        'mt5_fetch_status' => mt5FetchStatusAction($payload),
        'pa_training_zone_candidates' => paTrainingZoneCandidatesAction($store, $payload),
        'pa_training_trade_cases' => paTrainingTradeCases($store, $payload),
        'pa_training_feedback_save' => paTrainingFeedbackSave($store, $payload),
        'pa_training_saved_cases' => paTrainingSavedCases($store),
        'pa_training_saved_case_update' => paTrainingSavedCaseMutate($store, 'update', $payload),
        'pa_training_saved_case_delete' => paTrainingSavedCaseMutate($store, 'delete', $payload),
        'pa_training_saved_case_restore' => paTrainingSavedCaseMutate($store, 'restore', $payload),
        'pa_training_ai_chat' => paTrainingAiChat($store, $payload),
        'pa_training_chat_history' => paTrainingChatHistoryAction($store, $payload),
        'pa_training_workspace_state' => paTrainingWorkspaceState($store, $payload),
        'pa_training_memory' => ['ok' => true, 'pa_training_memory' => paTrainingMemory($store)],
        'pa_training_lesson_save' => savePaTrainingLesson($store, $payload),
        'pa_training_manual_zone_save' => paTrainingManualZoneSave($store, $payload),
        'openai_provider_status', 'ollama_provider_status', 'openai_local_settings_status' => openAiLocalSettingsStatus(),
        'openai_local_settings_save' => openAiLocalSettingsSave($payload),
        'openai_local_settings_delete_key' => openAiLocalSettingsDeleteKey(),
        'openai_billing_admin_key_save' => openAiAdminKeySave($payload),
        'openai_billing_admin_key_delete' => openAiAdminKeyDelete(),
        'openai_billing_sync' => openAiLocalSettingsStatus(),
        'llm_gateway_test_call' => llmGatewayTestCall($payload),
        'review_case' => reviewCase($store, $payload),
        'score_case' => ['ok' => true, 'score' => ['total' => 0, 'decision' => 'needs_review'], 'case' => $payload['case'] ?? $payload],
        'save_candidate_cases' => saveCandidateCases($store, $payload),
        'import_memory' => importMemory($store, $payload),
        'evaluate_memory' => ['ok' => true, 'stats' => $store->stats(), 'memory_report' => $store->stats()],
        'run_backtest' => ['ok' => true, 'run' => ['id' => 'run_' . time(), 'createdAt' => gmdate('c'), 'trades' => []], 'trades' => []],
        default => genericApiPayload($action, $payload, $store),
    };
}

function mt5StatusAction(array $payload): array
{
    $terminalPath = trim((string)($payload['terminal_path'] ?? ''));
    $mt5 = (new Mt5Bridge(__DIR__))->status($terminalPath !== '' ? $terminalPath : null);
    return ['ok' => (bool)($mt5['ok'] ?? false), 'mt5' => $mt5];
}

function mt5FetchAction(array $payload): array
{
    $symbols = $payload['symbols'] ?? [];
    $timeframes = $payload['timeframes'] ?? [];
    if (!is_array($symbols) || !is_array($timeframes)) {
        throw new InvalidArgumentException('Danh sách cặp tiền hoặc khung thời gian không hợp lệ.');
    }
    $terminalPath = trim((string)($payload['terminal_path'] ?? ''));
    $barsByTimeframe = is_array($payload['bars_by_timeframe'] ?? null) ? $payload['bars_by_timeframe'] : [];
    $jobId = bin2hex(random_bytes(12));
    $started = (new Mt5Bridge(__DIR__))->startFetch(
        $symbols,
        $timeframes,
        (int)($payload['bars'] ?? 100000),
        $jobId,
        $terminalPath !== '' ? $terminalPath : null,
        $barsByTimeframe
    );
    if (!($started['ok'] ?? false)) {
        return ['ok' => false, 'mt5' => $started, 'written' => [], 'market_files' => marketFiles()];
    }
    return ['ok' => true, 'job_id' => $jobId, 'progress' => $started['progress'] ?? []];
}

function mt5FetchStatusAction(array $payload): array
{
    $jobId = (string)($payload['job_id'] ?? '');
    $progress = (new Mt5Bridge(__DIR__))->fetchProgress($jobId);
    if ($progress === null) {
        return ['ok' => false, 'error' => 'Không tìm thấy trạng thái lượt tải MT5 này.'];
    }
    if (($progress['status'] ?? '') === 'starting' && time() - (strtotime((string)($progress['started_at'] ?? 'now')) ?: time()) > 45) {
        return ['ok' => false, 'error' => 'Tiến trình tải MT5 không khởi chạy được. Hãy kiểm tra Python và package MetaTrader5 rồi thử lại.'];
    }
    if (($progress['status'] ?? '') === 'failed') {
        $message = (string)($progress['message'] ?? 'MT5 không tải được dữ liệu.');
        $detail = (string)($progress['error'] ?? '');
        $partialSummary = 'Đã xử lý ' . (int)($progress['completed'] ?? 0) . '/' . (int)($progress['total'] ?? 0)
            . ' cặp-khung, đã ghi ' . count(is_array($progress['written'] ?? null) ? $progress['written'] : []) . ' tệp.';
        return ['ok' => false, 'progress' => $progress, 'mt5' => [
            'ok' => false,
            'error' => $message,
            'detail' => $detail,
            'mt5_error' => $progress['mt5_error'] ?? null,
        ], 'error' => $detail !== '' ? $message . ' ' . $detail . ' ' . $partialSummary : $message . ' ' . $partialSummary];
    }
    if (($progress['status'] ?? '') !== 'complete') {
        return ['ok' => true, 'progress' => $progress];
    }

    $written = is_array($progress['written'] ?? null) ? $progress['written'] : [];
    foreach ($written as &$item) {
        if (is_array($item) && isset($item['file'])) $item['file'] = basename(str_replace('\\', '/', (string)$item['file']));
    }
    unset($item);
    return [
        'ok' => true,
        'complete' => true,
        'progress' => $progress,
        'mt5' => ['ok' => true, 'errors' => $progress['errors'] ?? []],
        'written' => $written,
        'market_files' => marketFiles(),
    ];
}

function bootstrapPayload(JsonStore $store, array $defaults): array
{
    return [
        'ok' => true,
        'accounts' => $store->readJson('accounts.json', $defaults['accounts'] ?? []),
        'strategy' => $store->readJson('strategy.json', $defaults['strategy'] ?? []),
        'strategy_summary' => $store->readJson('strategy_summary.json', $defaults['strategy_summary'] ?? []),
        'candidate_cases' => $store->readJson('candidate_cases.json', []),
        'market_files' => marketFiles(),
        'recent_reviews' => $store->readJsonl('cases/reviewed_cases.jsonl', 25),
        'stats' => $store->stats(),
        'strategy_versions' => $store->readJson('strategy_versions.json', []),
        'strategy_tree' => null,
        'backtest_runs' => $store->readJson('backtests/backtest_runs.json', []),
        'experiments' => $store->readJson('experiments.json', []),
        'tradebrain_profile' => $store->readJson('tradebrain/tradebrain_profile.json', null),
        'tradebrain_memory' => $store->readJson('tradebrain/tradebrain_memory.json', []),
        'ai_decisions' => $store->readJson('tradebrain/ai_decisions.json', []),
        'strategy_upgrade_proposals' => $store->readJson('tradebrain/strategy_upgrade_proposals.json', []),
        'strategy_evaluations' => $store->readJson('tradebrain/strategy_evaluations.json', []),
        'ai_usage' => $store->readJson('tradebrain/ai_usage.json', null),
        'auto_research_runs' => $store->readJson('research/auto_research_runs.json', []),
        'research_lab_sessions' => $store->readJson('research_lab/sessions.json', []),
        'openai_local_settings' => openAiLocalSettingsStatus(),
        'manual_review_labels' => [],
        'manual_reviews' => $store->readJson('manual_reviews.json', []),
        'zone_manual_reviews' => $store->readJson('zone_manual_reviews.json', []),
        'pa_training_memory' => paTrainingMemory($store),
        'pa_training_workspace' => $store->readJson('pa_training/workspace_state.json', []),
        'calibrated_presets' => $store->readJson('calibrated_presets.json', []),
    ];
}

function marketFiles(): array
{
    $items = [];
    foreach (glob(__DIR__ . '/data/market_data/*.json') ?: [] as $path) {
        $name = basename($path);
        $parts = explode('_', preg_replace('/\.json$/i', '', $name) ?: '');
        $item = ['file' => $name, 'symbol' => strtoupper($parts[0] ?? ''), 'timeframe' => strtoupper($parts[1] ?? ''), 'bars' => 0, 'fetched_at' => ''];
        $items[] = marketFileMetadata($path, $item);
    }
    usort($items, static fn(array $a, array $b): int => [$a['symbol'], $a['timeframe']] <=> [$b['symbol'], $b['timeframe']]);
    return $items;
}

function marketFileMetadata(string $path, array $fallback): array
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) return $fallback;

    $header = fread($handle, 8192);
    if (is_string($header)) {
        foreach (['symbol', 'timeframe', 'fetched_at'] as $field) {
            if (preg_match('/"' . preg_quote($field, '/') . '"\s*:\s*"([^"\\\\]*)"/', $header, $matches)) {
                $fallback[$field] = $field === 'fetched_at' ? $matches[1] : strtoupper($matches[1]);
            }
        }
        if (preg_match('/"bars"\s*:\s*\[/s', $header, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1] + strlen($matches[0][0]);
            if (fseek($handle, $offset) === 0) {
                $fallback['bars'] = countMarketFileBars($handle);
            }
        }
    }
    fclose($handle);
    return $fallback;
}

function countMarketFileBars($handle): int
{
    $start = ftell($handle);
    $probe = fread($handle, 16);
    if (is_string($probe) && str_starts_with($probe, '{"time":')) {
        fseek($handle, $start);
        $count = 0;
        $carry = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 65536);
            if ($chunk === false || $chunk === '') break;
            $scan = $carry . $chunk;
            $count += substr_count($scan, '{"time":');
            $carry = substr($scan, -7);
        }
        return $count;
    }
    fseek($handle, $start);

    $count = 0;
    $objectDepth = 0;
    $inString = false;
    $escaped = false;
    while (!feof($handle)) {
        $chunk = fread($handle, 65536);
        if ($chunk === false || $chunk === '') break;
        $length = strlen($chunk);
        for ($i = 0; $i < $length; $i++) {
            $char = $chunk[$i];
            if ($inString) {
                if ($escaped) $escaped = false;
                elseif ($char === '\\') $escaped = true;
                elseif ($char === '"') $inString = false;
                continue;
            }
            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                if ($objectDepth === 0) $count++;
                $objectDepth++;
            } elseif ($char === '}' && $objectDepth > 0) {
                $objectDepth--;
            } elseif ($char === ']' && $objectDepth === 0) {
                return $count;
            }
        }
    }
    return $count;
}

function marketData(array $payload): array
{
    $path = marketDataFilePath($payload);
    $file = basename($path);
    if (!is_file($path)) throw new InvalidArgumentException('Không tìm th?y d? li?u chart: ' . $file);
    $data = json_decode(file_get_contents($path) ?: '', true);
    if (!is_array($data)) throw new RuntimeException('File d? li?u chart l?i: ' . $file);
    return ['ok' => true, 'market_data' => normalizeMarketData($data)];
}

function marketDataFilePath(array $payload): string
{
    $file = str_replace(['..', '/', '\\'], '', (string)($payload['file'] ?? ''));
    if ($file === '') {
        $file = paTrainingSymbol((string)($payload['symbol'] ?? '')) . '_' . strtoupper((string)($payload['timeframe'] ?? 'H4')) . '.json';
    }
    return __DIR__ . '/data/market_data/' . $file;
}

function iterateMarketDataBars(string $path): Generator
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) throw new RuntimeException('Không thể mở file dữ liệu chart.');
    try {
        $buffer = '';
        while (!preg_match('/"bars"\s*:\s*\[/', $buffer, $matches, PREG_OFFSET_CAPTURE)) {
            if (strlen($buffer) > 1048576) throw new RuntimeException('Header dữ liệu chart vượt quá giới hạn.');
            $chunk = fread($handle, 8192);
            if ($chunk === false || $chunk === '') throw new RuntimeException('File dữ liệu chart thiếu mảng bars.');
            $buffer .= $chunk;
        }
        $marker = $matches[0][0];
        $offset = $matches[0][1] + strlen($marker);
        $buffer = substr($buffer, $offset);
        $offset = 0;
        $depth = 0;
        $inString = false;
        $escaped = false;
        $value = '';
        while (true) {
            if ($offset >= strlen($buffer)) {
                $buffer = fread($handle, 65536);
                $offset = 0;
                if ($buffer === false || $buffer === '') throw new RuntimeException('File dữ liệu chart bị ngắt giữa mảng bars.');
            }
            $char = $buffer[$offset++];
            if ($value === '') {
                if ($char === ']') return;
                if ($char === ',' || ctype_space($char)) continue;
                if ($char !== '{') throw new RuntimeException('Nến chart không đúng định dạng JSON object.');
                $value = '{';
                $depth = 1;
                $inString = false;
                $escaped = false;
                continue;
            }

            $value .= $char;
            if ($inString) {
                if ($escaped) $escaped = false;
                elseif ($char === '\\') $escaped = true;
                elseif ($char === '"') $inString = false;
                continue;
            }
            if ($char === '"') $inString = true;
            elseif ($char === '{' || $char === '[') $depth++;
            elseif ($char === '}' || $char === ']') {
                $depth--;
                if ($depth === 0) {
                    $bar = json_decode($value, true);
                    if (is_array($bar)) yield $bar;
                    $value = '';
                }
            }
        }
    } finally {
        fclose($handle);
    }
}

function paTrainingReadCompactM15Bars(string $path): array
{
    $bars = [];
    foreach (iterateMarketDataBars($path) as $bar) {
        $timestamp = paTrainingBarTimestamp($bar);
        if ($timestamp <= 0) continue;
        $bars[] = pack('qdddd', $timestamp, (float)($bar['open'] ?? 0), (float)($bar['high'] ?? 0), (float)($bar['low'] ?? 0), (float)($bar['close'] ?? 0));
    }
    return $bars;
}

function paTrainingM15BarAt(array $bars, int $index): array
{
    $bar = $bars[$index] ?? [];
    if (!is_string($bar) || strlen($bar) !== 40) return is_array($bar) ? $bar : [];
    $values = unpack('qtimestamp/dopen/dhigh/dlow/dclose', $bar);
    if (!is_array($values)) return [];
    $values['time'] = gmdate('c', (int)$values['timestamp']);
    $values['time_iso'] = $values['time'];
    return $values;
}

function paTrainingM15BarTimestamp(array|string $bar): int
{
    if (is_string($bar) && strlen($bar) === 40) {
        $timestamp = unpack('qtimestamp', substr($bar, 0, 8));
        return is_array($timestamp) ? (int)$timestamp['timestamp'] : 0;
    }
    return is_array($bar) ? paTrainingBarTimestamp($bar) : 0;
}

function streamMarketDataResponse(array $payload): void
{
    $path = marketDataFilePath($payload);
    $file = basename($path);
    if (!is_file($path)) throw new InvalidArgumentException('Không tìm th?y d? li?u chart: ' . $file);
    $handle = @fopen($path, 'rb');
    if ($handle === false) throw new RuntimeException('Không đọc được dữ liệu chart: ' . $file);
    try {
        streamNormalizedMarketDataJson($handle);
    } finally {
        fclose($handle);
    }
}

function streamNormalizedMarketDataJson($handle, bool $flushOutput = true): void
{
    $buffer = '';
    $headerEnd = false;
    while (!$headerEnd) {
        if (preg_match('/"bars"\s*:\s*\[/', $buffer, $matches, PREG_OFFSET_CAPTURE)) {
            $markerOffset = $matches[0][1];
            $arrayOffset = $markerOffset + strlen($matches[0][0]);
            $metadata = json_decode(substr($buffer, 0, $markerOffset) . '"bars":[]}', true);
            if (!is_array($metadata)) throw new RuntimeException('File dữ liệu chart lỗi định dạng header.');
            unset($metadata['bars']);
            $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if (!is_string($metadataJson) || $metadataJson === '') throw new RuntimeException('Không thể chuẩn hóa header dữ liệu chart.');
            $buffer = substr($buffer, $arrayOffset);
            if ($flushOutput) {
                while (ob_get_level() > 0) @ob_end_flush();
            }
            echo '{"ok":true,"market_data":' . substr($metadataJson, 0, -1) . ',"bars":[';
            $headerEnd = true;
            continue;
        }
        if (strlen($buffer) > 1048576) throw new RuntimeException('Header dữ liệu chart vượt quá giới hạn.');
        $chunk = fread($handle, 8192);
        if ($chunk === false || $chunk === '') throw new RuntimeException('File dữ liệu chart thiếu mảng bars.');
        $buffer .= $chunk;
    }

    $offset = 0;
    $depth = 0;
    $inString = false;
    $escaped = false;
    $value = '';
    $index = 0;
    $first = true;
    $arrayClosed = false;
    while (!$arrayClosed) {
        if ($offset >= strlen($buffer)) {
            $buffer = fread($handle, 65536);
            $offset = 0;
            if ($buffer === false || $buffer === '') throw new RuntimeException('File dữ liệu chart bị ngắt giữa mảng bars.');
        }
        $char = $buffer[$offset++];
        if ($value === '') {
            if ($char === ']') {
                $arrayClosed = true;
                continue;
            }
            if ($char === ',' || ctype_space($char)) continue;
            if ($char !== '{') throw new RuntimeException('Nến chart không đúng định dạng JSON object.');
            $value = '{';
            $depth = 1;
            $inString = false;
            $escaped = false;
            continue;
        }

        $value .= $char;
        if ($inString) {
            if ($escaped) $escaped = false;
            elseif ($char === '\\') $escaped = true;
            elseif ($char === '"') $inString = false;
            continue;
        }
        if ($char === '"') {
            $inString = true;
        } elseif ($char === '{' || $char === '[') {
            $depth++;
        } elseif ($char === '}' || $char === ']') {
            $depth--;
            if ($depth === 0) {
                $bar = json_decode($value, true);
                if (is_array($bar)) {
                    $normalized = [
                        'index' => $index,
                        'time' => $bar['time_iso'] ?? $bar['time'] ?? '',
                        'timestamp' => $bar['time'] ?? null,
                        'open' => (float)($bar['open'] ?? 0),
                        'high' => (float)($bar['high'] ?? 0),
                        'low' => (float)($bar['low'] ?? 0),
                        'close' => (float)($bar['close'] ?? 0),
                        'volume' => (float)($bar['tick_volume'] ?? $bar['volume'] ?? 0),
                        'tickVolume' => (float)($bar['tick_volume'] ?? $bar['volume'] ?? 0),
                        'spread' => $bar['spread'] ?? null,
                    ];
                    $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
                    if (is_string($encoded)) {
                        echo ($first ? '' : ',') . $encoded;
                        $first = false;
                    }
                }
                $index++;
                $value = '';
                if ($flushOutput && $index % 256 === 0) flush();
            }
        }
    }
    echo ']}}';
    if ($flushOutput) flush();
}

function normalizeMarketData(array $data): array
{
    $bars = [];
    foreach (($data['bars'] ?? []) as $i => $bar) {
        if (!is_array($bar)) continue;
        $bars[] = [
            'index' => $i,
            'time' => $bar['time_iso'] ?? $bar['time'] ?? '',
            'timestamp' => $bar['time'] ?? null,
            'open' => (float)($bar['open'] ?? 0),
            'high' => (float)($bar['high'] ?? 0),
            'low' => (float)($bar['low'] ?? 0),
            'close' => (float)($bar['close'] ?? 0),
            'volume' => (float)($bar['tick_volume'] ?? $bar['volume'] ?? 0),
            'tickVolume' => (float)($bar['tick_volume'] ?? $bar['volume'] ?? 0),
            'spread' => $bar['spread'] ?? null,
        ];
    }
    $data['bars'] = $bars;
    return $data;
}

function riskPreview(array $payload): array
{
    $balance = (float)($payload['balance'] ?? $payload['initial_balance'] ?? 10000);
    $riskPct = (float)($payload['risk_per_trade_pct'] ?? $payload['riskPct'] ?? 1);
    $riskUsd = $balance * $riskPct / 100;
    return ['ok' => true, 'risk' => ['balance' => $balance, 'risk_pct' => $riskPct, 'risk_usd' => $riskUsd], 'preview' => ['risk_usd' => $riskUsd]];
}

function saveAccounts(JsonStore $store, array $payload): array
{
    $data = is_array($payload['accounts'] ?? null) ? $payload['accounts'] : $payload;
    $store->writeJson('accounts.json', $data);
    return ['ok' => true, 'accounts' => $data];
}

function saveStrategy(JsonStore $store, array $payload): array
{
    $data = is_array($payload['strategy'] ?? null) ? $payload['strategy'] : $payload;
    $store->writeJson('strategy.json', $data);
    return ['ok' => true, 'strategy' => $data];
}

function paTrainingSymbol(string $symbol): string { return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $symbol) ?: ''); }
function paTrainingLower(string $value): string { return function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value)); }
function paTrainingContainsAny(string $text, array $needles): bool { foreach ($needles as $needle) if ($needle !== '' && str_contains($text, paTrainingLower((string)$needle))) return true; return false; }
function paTrainingLimitText(string $text, int $limit = 1200): string { return function_exists('mb_substr') ? mb_substr(trim($text), 0, $limit, 'UTF-8') : substr(trim($text), 0, $limit); }

function paTrainingMemory(JsonStore $store, bool $includeDeleted = false): array
{
    $m = $store->readJson('pa_training/memory.json', []);
    $memory = [
        'zone_reviews' => array_values(is_array($m['zone_reviews'] ?? null) ? $m['zone_reviews'] : []),
        'setup_reviews' => array_values(is_array($m['setup_reviews'] ?? null) ? $m['setup_reviews'] : []),
        'lessons' => array_values(is_array($m['lessons'] ?? null) ? $m['lessons'] : []),
    ];
    if (!$includeDeleted) foreach ($memory as $key => $items) $memory[$key] = array_values(array_filter($items, static fn(array $item): bool => empty($item['deletedAt'])));
    return $memory;
}

function paTrainingSavedCases(JsonStore $store): array
{
    $memory = paTrainingMemory($store, true);
    return ['ok' => true, 'saved_cases' => $memory['setup_reviews'], 'pa_training_memory' => paTrainingMemory($store)];
}

function paTrainingSavedCaseMutate(JsonStore $store, string $action, array $payload): array
{
    $memory = PaReviewService::mutate(paTrainingMemory($store, true), $action, $payload);
    paTrainingSaveMemory($store, $memory);
    return paTrainingSavedCases($store);
}

function paTrainingSaveMemory(JsonStore $store, array $m): void
{
    $store->writeJson('pa_training/memory.json', [
        'zone_reviews' => array_values($m['zone_reviews'] ?? []),
        'setup_reviews' => array_values($m['setup_reviews'] ?? []),
        'lessons' => array_values($m['lessons'] ?? []),
    ]);
}

function paTrainingManualZones(JsonStore $store): array
{
    $zones = $store->readJson('pa_training/manual_zones.json', []);
    return array_values(is_array($zones) ? $zones : []);
}

function paTrainingManualZoneSave(JsonStore $store, array $payload): array
{
    $zone = is_array($payload['zone'] ?? null) ? $payload['zone'] : $payload;
    $zone['id'] = (string)($zone['id'] ?? ('manual_zone_' . time() . '_' . bin2hex(random_bytes(3))));
    $zone['manual'] = true;
    $zone['source'] = 'manual';
    $zone['createdAt'] = $zone['createdAt'] ?? gmdate('c');
    $zones = paTrainingManualZones($store);
    $found = false;
    foreach ($zones as $i => $item) {
        if (($item['id'] ?? '') === $zone['id']) {
            $zones[$i] = array_merge($item, $zone);
            $found = true;
        }
    }
    if (!$found) $zones[] = $zone;
    $store->writeJson('pa_training/manual_zones.json', $zones);
    return ['ok' => true, 'zone' => $zone, 'manual_zones' => $zones];
}

function paTrainingZoneCandidatesAction(JsonStore $store, array $payload): array
{
    $symbol = paTrainingSymbol((string)($payload['symbol'] ?? 'AUDJPY'));
    $tf = strtoupper((string)($payload['timeframe'] ?? 'H4'));
    return ['ok' => true, 'zone_candidates' => paTrainingZoneCandidates($store, $symbol, $tf), 'pa_training_memory' => paTrainingMemory($store)];
}

function paTrainingBarTimestamp(array $bar): int
{
    $timestamp = $bar['timestamp'] ?? $bar['time'] ?? $bar['time_iso'] ?? null;
    if (is_numeric($timestamp)) return (int)$timestamp;
    $parsed = strtotime((string)$timestamp);
    return $parsed === false ? 0 : $parsed;
}

function paTrainingM15Window(JsonStore $store, string $symbol): ?array
{
    static $windowCache = [];
    if (array_key_exists($symbol, $windowCache)) return $windowCache[$symbol];
    $path = __DIR__ . '/data/market_data/' . $symbol . '_M15.json';
    if (!is_file($path)) return $windowCache[$symbol] = null;
    $start = PHP_INT_MAX;
    $end = 0;
    foreach (iterateMarketDataBars($path) as $bar) {
        $time = paTrainingBarTimestamp($bar);
        if ($time <= 0) continue;
        $start = min($start, $time);
        $end = max($end, $time);
    }
    return $windowCache[$symbol] = $end > 0 ? ['start' => $start, 'end' => $end] : null;
}

function paTrainingAlignBarsToM15(JsonStore $store, string $symbol, string $timeframe, array $bars): array
{
    if (strtoupper($timeframe) === 'M15') return array_values($bars);
    $window = paTrainingM15Window($store, $symbol);
    if (!$window) return array_values($bars);
    $durationSeconds = match (strtoupper($timeframe)) {
        'M1' => 60, 'M5' => 300, 'M15' => 900, 'M30' => 1800,
        'H1' => 3600, 'H4' => 14400, 'D1' => 86400,
        default => 0,
    };
    $aligned = [];
    foreach ($bars as $bar) {
        if (!is_array($bar)) continue;
        $time = paTrainingBarTimestamp($bar);
        $barEnd = $time + max(1, $durationSeconds) - 1;
        if ($barEnd < $window['start'] || $time > $window['end']) continue;
        $bar['index'] = count($aligned);
        $aligned[] = $bar;
    }
    return $aligned;
}

function paTrainingZoneCandidates(JsonStore $store, string $symbol, string $timeframe = 'H4'): array
{
    $zones = [];
    $path = __DIR__ . '/data/market_data/' . $symbol . '_' . $timeframe . '.json';
    if (is_file($path)) {
        $data = normalizeMarketData(json_decode(file_get_contents($path) ?: '', true) ?: []);
        $bars = is_array($data['bars'] ?? null) ? $data['bars'] : [];
        $bars = paTrainingAlignBarsToM15($store, $symbol, $timeframe, $bars);
        $count = count($bars);
        $averageRange = static function (int $index) use ($bars, $count): float {
            $from = max(0, $index - 24);
            $to = min($count - 1, $index - 1);
            $sum = 0.0;
            $n = 0;
            for ($j = $from; $j <= $to; $j++) {
                $range = abs((float)($bars[$j]['high'] ?? 0) - (float)($bars[$j]['low'] ?? 0));
                if ($range > 0) {
                    $sum += $range;
                    $n++;
                }
            }
            return $n ? $sum / $n : 0.0;
        };
        $firstTouch = static function (int $start, float $low, float $high) use ($bars, $count): ?int {
            for ($j = max(0, $start); $j < $count; $j++) {
                $barLow = (float)($bars[$j]['low'] ?? 0);
                $barHigh = (float)($bars[$j]['high'] ?? 0);
                if ($barLow <= $high && $barHigh >= $low) return $j;
            }
            return null;
        };
        $lastAccepted = -9999;
        for ($i = 8; $i < $count - 8 && count($zones) < 1000; $i++) {
            if ($i - $lastAccepted < 8) continue;
            $bar = $bars[$i];
            $open = (float)($bar['open'] ?? 0);
            $high = (float)($bar['high'] ?? 0);
            $low = (float)($bar['low'] ?? 0);
            $close = (float)($bar['close'] ?? 0);
            if (!is_finite($open) || !is_finite($high) || !is_finite($low) || !is_finite($close) || $high <= $low) continue;
            $avg = max($averageRange($i), abs($high - $low), 0.00001);
            $isPivotLow = true;
            $isPivotHigh = true;
            for ($j = $i - 4; $j <= $i + 4; $j++) {
                if ($j === $i || !isset($bars[$j])) continue;
                if ((float)($bars[$j]['low'] ?? INF) < $low) $isPivotLow = false;
                if ((float)($bars[$j]['high'] ?? -INF) > $high) $isPivotHigh = false;
            }
            $futureHigh = $high;
            $futureLow = $low;
            $futureClose = $close;
            for ($j = $i + 1; $j <= min($count - 1, $i + 8); $j++) {
                $futureHigh = max($futureHigh, (float)($bars[$j]['high'] ?? $futureHigh));
                $futureLow = min($futureLow, (float)($bars[$j]['low'] ?? $futureLow));
                $futureClose = (float)($bars[$j]['close'] ?? $futureClose);
            }
            $type = '';
            $zoneLow = 0.0;
            $zoneHigh = 0.0;
            $reason = '';
            $impulseR = 0.0;
            $pad = max(abs($high - $low) * 0.10, $avg * 0.04);
            if ($isPivotLow && max($futureHigh - $high, $futureClose - $close) >= $avg * 0.85) {
                $type = 'demand';
                $zoneLow = $low - $pad;
                $zoneHigh = min($high, max($open, $close) + $pad);
                $impulseR = max($futureHigh - $high, $futureClose - $close) / $avg;
                $reason = 'Vùng canh buy sinh ra từ đáy phản ứng, sau đó giá bật lên đủ mạnh rồi quay lại kiểm tra vùng.';
            } elseif ($isPivotHigh && max($low - $futureLow, $close - $futureClose) >= $avg * 0.85) {
                $type = 'supply';
                $zoneLow = max($low, min($open, $close) - $pad);
                $zoneHigh = $high + $pad;
                $impulseR = max($low - $futureLow, $close - $futureClose) / $avg;
                $reason = 'Vùng canh sell sinh ra từ đỉnh phản ứng, sau đó giá rơi đủ mạnh rồi quay lại kiểm tra vùng.';
            }
            if ($type === '' || $zoneHigh <= $zoneLow) continue;
            $touchIndex = $firstTouch($i + 9, $zoneLow, $zoneHigh);
            if ($touchIndex === null) continue;
            $touchBar = $bars[$touchIndex] ?? $bar;
            $waitBars = max(1, $touchIndex - $i);
            $score = min(99, max(55, (int)round(58 + $impulseR * 12 + min(18, $waitBars / 3))));
            $zones[] = [
                'id' => "zone_{$symbol}_{$timeframe}_{$i}_{$touchIndex}",
                'symbol' => $symbol,
                'timeframe' => $timeframe,
                'type' => $type,
                'low' => $zoneLow,
                'high' => $zoneHigh,
                'originIndex' => $i,
                'originCandleNumber' => $i + 1,
                'originCandleTime' => (string)($bar['time'] ?? ''),
                'firstTouchIndex' => $touchIndex,
                'firstTouchCandleNumber' => $touchIndex + 1,
                'firstTouchTime' => (string)($touchBar['time'] ?? $bar['time'] ?? ''),
                'createdAt' => gmdate('c'),
                'status' => 'active',
                'strengthScore' => $score,
                'touches' => [['index' => $touchIndex, 'time' => (string)($touchBar['time'] ?? ''), 'price' => ($zoneLow + $zoneHigh) / 2]],
                'source' => 'auto_price_action_scan',
                'reason' => $reason,
            ];
            $lastAccepted = $i;
        }
        if (count($zones) < 20 && $count > 20) {
            $step = max(12, (int)floor(max(1, $count) / 120));
            for ($i = $step; $i < $count - 6 && count($zones) < 80; $i += $step) {
                $bar = $bars[$i];
                $range = max(0.00001, (float)$bar['high'] - (float)$bar['low']);
                $type = (float)$bar['close'] >= (float)$bar['open'] ? 'demand' : 'supply';
                $pad = $range * 0.16;
                $touchIndex = min($count - 1, $i + 3);
                $zones[] = [
                    'id' => "zone_{$symbol}_{$timeframe}_fallback_{$i}",
                    'symbol' => $symbol,
                    'timeframe' => $timeframe,
                    'type' => $type,
                    'low' => (float)$bar['low'] - $pad,
                    'high' => (float)$bar['high'] + $pad,
                    'originIndex' => $i,
                    'originCandleNumber' => $i + 1,
                    'originCandleTime' => (string)($bar['time'] ?? ''),
                    'firstTouchIndex' => $touchIndex,
                    'firstTouchCandleNumber' => $touchIndex + 1,
                    'firstTouchTime' => (string)($bars[$touchIndex]['time'] ?? $bar['time'] ?? ''),
                    'createdAt' => gmdate('c'),
                    'status' => 'active',
                    'strengthScore' => 58,
                    'touches' => [],
                    'source' => 'auto_price_action_fallback',
                    'reason' => 'Ca dự phòng để vẫn có dữ liệu dạy khi chưa tìm đủ vùng phản ứng rõ.',
                ];
            }
        }
    }
    $m15Window = strtoupper($timeframe) === 'M15' ? null : paTrainingM15Window($store, $symbol);
    foreach (paTrainingManualZones($store) as $zone) {
        if (paTrainingSymbol((string)($zone['symbol'] ?? '')) !== $symbol) continue;
        if ($m15Window) {
            $zoneTime = strtotime((string)($zone['originCandleTime'] ?? $zone['firstTouchTime'] ?? $zone['createdAt'] ?? ''));
            if ($zoneTime !== false && ($zoneTime < $m15Window['start'] || $zoneTime > $m15Window['end'])) continue;
        }
        $zones[] = $zone;
    }
    $zoneSortIndex = static function (array $zone): int {
        if (isset($zone['firstTouchIndex'])) return (int)$zone['firstTouchIndex'];
        if (isset($zone['originIndex'])) return (int)$zone['originIndex'];
        return PHP_INT_MAX;
    };
    usort($zones, static fn(array $a, array $b): int => $zoneSortIndex($a) <=> $zoneSortIndex($b));
    return $zones;
}

function paTrainingTradeCases(JsonStore $store, array $payload): array
{
    $symbol = paTrainingSymbol((string)($payload['symbol'] ?? 'AUDJPY'));
    $tf = strtoupper((string)($payload['timeframe'] ?? 'H4')) ?: 'H4';
    $limit = max(1, min(500, (int)($payload['limit'] ?? 180)));
    $zones = paTrainingZoneCandidates($store, $symbol, $tf);
    $bars = [];
    $m15Bars = [];
    $strategy = $store->readJson('strategy.json', []);
    $riskReward = is_array($strategy['risk_reward'] ?? null) ? $strategy['risk_reward'] : [];
    // The teaching-case scanner's hard floor is 2R; strategy default_rr controls
    // the preferred target (3R by default), even if an older saved strategy has
    // a stale min_rr value.
    $minimumRr = 2.0;
    $preferredRr = max($minimumRr, (float)($riskReward['default_rr'] ?? 3.0));
    $path = __DIR__ . '/data/market_data/' . $symbol . '_' . $tf . '.json';
    if (is_file($path)) {
        $data = normalizeMarketData(json_decode(file_get_contents($path) ?: '', true) ?: []);
        $bars = is_array($data['bars'] ?? null) ? $data['bars'] : [];
        $bars = paTrainingAlignBarsToM15($store, $symbol, $tf, $bars);
    }
    $m15Path = __DIR__ . '/data/market_data/' . $symbol . '_M15.json';
    if (is_file($m15Path)) {
        $m15Bars = paTrainingReadCompactM15Bars($m15Path);
    }
    $cases = [];
    foreach ($zones as $i => $zone) {
        $case = paTrainingBuildTeachingCase($zone, $bars, $i, $m15Bars, $minimumRr, $preferredRr);
        if ($case) $cases[] = $case;
    }
    $filter = (string)($payload['filter'] ?? 'active');
    $cases = array_values(array_filter($cases, static function (array $case) use ($filter): bool {
        $zone = is_array($case['zone'] ?? null) ? $case['zone'] : [];
        $status = (string)($zone['status'] ?? 'active');
        return match ($filter) {
            'all' => true,
            'active' => !in_array($status, ['broken', 'weak'], true),
            'fresh', 'touched', 'weak', 'broken' => $status === $filter,
            'manual' => !empty($zone['manual']) || ($zone['source'] ?? '') === 'manual',
            default => true,
        };
    }));
    $memory = paTrainingMemory($store);
    $selection = PaAdaptiveCaseSelector::select(
        $cases,
        $memory,
        $limit,
        is_array($payload['excluded_case_ids'] ?? null) ? array_slice($payload['excluded_case_ids'], 0, 500) : [],
        (string)($payload['preferred_case_id'] ?? '')
    );
    $cases = $selection['cases'];
    return [
        'ok' => true,
        'zone_candidates' => $zones,
        'trade_cases' => $cases,
        'selected_case_id' => $cases[0]['id'] ?? '',
        'case_summary' => ['h4_bars' => count($bars), 'm15_bars' => count($m15Bars), 'cases' => count($cases), 'available' => $selection['available_count'], 'reviewed' => $selection['reviewed_count']],
        'pa_training_memory' => $memory,
    ];
}

function paTrainingM15IndexAtOrAfter(array $bars, int $time): int
{
    $low = 0;
    $high = count($bars);
    while ($low < $high) {
        $middle = intdiv($low + $high, 2);
        if (paTrainingM15BarTimestamp($bars[$middle]) < $time) $low = $middle + 1;
        else $high = $middle;
    }
    return $low;
}

function paTrainingTriggeredEntry(array $zone, array $m15Bars, int $touchTime, string $direction, float $minimumRr = 2.0, float $preferredRr = 3.0): ?array
{
    $count = count($m15Bars);
    if ($count < 3 || $touchTime <= 0) return null;
    $zoneLow = (float)($zone['low'] ?? 0);
    $zoneHigh = (float)($zone['high'] ?? 0);
    $zoneMid = ($zoneLow + $zoneHigh) / 2;
    $zoneHeight = $zoneHigh - $zoneLow;
    if ($zoneHeight <= 0) return null;
    $start = paTrainingM15IndexAtOrAfter($m15Bars, $touchTime);
    // The retest must turn into an M15 signal within two H4 candles.
    $lastSignalTime = $touchTime + 8 * 3600;
    for ($i = $start; $i < $count - 1 && paTrainingM15BarTimestamp($m15Bars[$i]) < $lastSignalTime; $i++) {
        $bar = paTrainingM15BarAt($m15Bars, $i);
        $open = (float)($bar['open'] ?? NAN);
        $close = (float)($bar['close'] ?? NAN);
        $high = (float)($bar['high'] ?? NAN);
        $low = (float)($bar['low'] ?? NAN);
        if (!is_finite($open) || !is_finite($close) || !is_finite($high) || !is_finite($low)) continue;
        $touchesZone = $low <= $zoneHigh && $high >= $zoneLow;
        $confirms = $direction === 'short'
            ? $close < $open && $close <= $zoneMid
            : $close > $open && $close >= $zoneMid;
        if (!$touchesZone || !$confirms) continue;
        $entryBar = paTrainingM15BarAt($m15Bars, $i + 1);
        $entryPrice = (float)($entryBar['open'] ?? NAN);
        if (!is_finite($entryPrice) || $entryPrice <= 0) continue;
        // Skip a gap that has already moved away from the setup before entry.
        if ($entryPrice < $zoneLow - $zoneHeight * 0.2 || $entryPrice > $zoneHigh + $zoneHeight * 0.2) continue;
        $buffer = max($zoneHeight * 0.1, abs($entryPrice) * 0.0001);
        $sl = $direction === 'short' ? max($zoneHigh, $high) + $buffer : min($zoneLow, $low) - $buffer;
        $risk = abs($entryPrice - $sl);
        if ($risk <= 0 || $risk > $zoneHeight * 3) continue;
        $targetR = max($minimumRr, $preferredRr);
        $target = $direction === 'short' ? $entryPrice - $risk * $targetR : $entryPrice + $risk * $targetR;
        return [
            'signalIndex' => $i,
            'entryIndex' => $i + 1,
            'entryPrice' => $entryPrice,
            'sl' => $sl,
            'tp1' => $target,
            'tp2' => $target,
            'rr' => $targetR,
            'risk' => $risk,
        ];
    }
    return null;
}

function paTrainingResolvedExit(array $bars, int $entryIndex, string $direction, float $sl, float $tp1, float $tp2): ?array
{
    $tp1Taken = false;
    $last = min(count($bars) - 1, $entryIndex + 512);
    for ($i = $entryIndex; $i <= $last; $i++) {
        $bar = paTrainingM15BarAt($bars, $i);
        $open = (float)($bar['open'] ?? NAN);
        $high = (float)($bar['high'] ?? NAN);
        $low = (float)($bar['low'] ?? NAN);
        if (!is_finite($open) || !is_finite($high) || !is_finite($low)) continue;
        if ($direction === 'short') {
            if ($open >= $sl) return ['index' => $i, 'price' => $open, 'type' => $tp1Taken ? 'TP1_SL' : 'SL', 'gap' => true];
            if ($open <= $tp2) return ['index' => $i, 'price' => $tp2, 'type' => 'TP2'];
            if ($open <= $tp1) $tp1Taken = true;
            $hitSl = $high >= $sl;
            $hitTp2 = $low <= $tp2;
            $hitTp1 = $low <= $tp1;
        } else {
            if ($open <= $sl) return ['index' => $i, 'price' => $open, 'type' => $tp1Taken ? 'TP1_SL' : 'SL', 'gap' => true];
            if ($open >= $tp2) return ['index' => $i, 'price' => $tp2, 'type' => 'TP2'];
            if ($open >= $tp1) $tp1Taken = true;
            $hitSl = $low <= $sl;
            $hitTp2 = $high >= $tp2;
            $hitTp1 = $high >= $tp1;
        }
        // OHLC has no intrabar order; count a candle touching both as SL.
        if ($hitSl) return ['index' => $i, 'price' => $sl, 'type' => $tp1Taken ? 'TP1_SL' : 'SL', 'sameBarAmbiguous' => !$tp1Taken && ($hitTp1 || $hitTp2)];
        if ($hitTp2) return ['index' => $i, 'price' => $tp2, 'type' => 'TP2'];
        if ($hitTp1) $tp1Taken = true;
    }
    return null;
}

function paTrainingBuildTeachingCase(array $zone, array $bars, int $index, array $m15Bars = [], float $minimumRr = 2.0, float $preferredRr = 3.0): ?array
{
    $symbol = paTrainingSymbol((string)($zone['symbol'] ?? ''));
    if ($symbol === '' || !$m15Bars || ($zone['source'] ?? '') !== 'auto_price_action_scan') return null;
    $direction = ($zone['type'] ?? '') === 'supply' ? 'short' : 'long';
    $barCount = count($bars);
    if ($barCount === 0 || !isset($zone['firstTouchIndex'])) return null;
    $anchorIndex = min(max(0, (int)$zone['firstTouchIndex']), $barCount - 1);
    $originIndex = min(max(0, (int)($zone['originIndex'] ?? max(0, $anchorIndex - 2))), max(0, $barCount - 1));
    $anchorBar = $bars[$anchorIndex] ?? [];
    $originBar = $bars[$originIndex] ?? $anchorBar;
    $anchorTime = (string)($zone['firstTouchTime'] ?? $anchorBar['time'] ?? $zone['originCandleTime'] ?? '');
    $low = (float)($zone['low'] ?? ($originBar['low'] ?? 0));
    $high = (float)($zone['high'] ?? ($originBar['high'] ?? 0));
    if (!is_finite($low) || !is_finite($high) || $high <= $low) return null;
    $touchTime = paTrainingBarTimestamp($anchorBar);
    $signal = paTrainingTriggeredEntry($zone, $m15Bars, $touchTime, $direction, $minimumRr, $preferredRr);
    if (!$signal) return null;
    $entryIndex = $signal['entryIndex'];
    $entry = $signal['entryPrice'];
    $sl = $signal['sl'];
    $tp1 = $signal['tp1'];
    $tp2 = $signal['tp2'];
    $risk = $signal['risk'];
    $exit = paTrainingResolvedExit($m15Bars, $entryIndex, $direction, $sl, $tp1, $tp2);
    if (!$exit) return null;
    $exitIndex = $exit['index'];
    $exitPrice = $exit['price'];
    $netR = ($direction === 'short' ? $entry - $exitPrice : $exitPrice - $entry) / $risk;
    $zoneLabel = $direction === 'short' ? 'Vùng canh sell' : 'Vùng canh buy';
    $sideLabel = $direction === 'short' ? 'Bán' : 'Mua';
    $rangeText = number_format($low, 5, '.', '') . ' - ' . number_format($high, 5, '.', '');
    $entryBar = paTrainingM15BarAt($m15Bars, $entryIndex);
    $signalBar = paTrainingM15BarAt($m15Bars, $signal['signalIndex']);
    $exitBar = paTrainingM15BarAt($m15Bars, $exitIndex);
    $touchCount = 0;
    $wasTouching = false;
    for ($j = paTrainingM15IndexAtOrAfter($m15Bars, $touchTime); $j <= $signal['signalIndex']; $j++) {
        $candidateBar = paTrainingM15BarAt($m15Bars, $j);
        $touching = (float)($candidateBar['low'] ?? INF) <= $high && (float)($candidateBar['high'] ?? -INF) >= $low;
        if ($touching && !$wasTouching) $touchCount++;
        $wasTouching = $touching;
    }
    $signalRange = max(0.00000001, (float)($signalBar['high'] ?? 0) - (float)($signalBar['low'] ?? 0));
    $signalBodyFraction = min(1.0, abs((float)($signalBar['close'] ?? 0) - (float)($signalBar['open'] ?? 0)) / $signalRange);
    return [
        'id' => 'case_' . (string)($zone['id'] ?? $index) . '_' . paTrainingBarTimestamp($entryBar),
        'isTeachingCase' => true,
        'caseStatus' => 'closed',
        'resultStatus' => 'closed',
        'symbol' => $symbol,
        'timeframe' => 'M15',
        'contextTimeframe' => (string)($zone['timeframe'] ?? 'H4'),
        'direction' => $direction,
        'anchorTime' => $anchorTime,
        'anchorIndex' => $anchorIndex,
        'signalIndex' => $signal['signalIndex'],
        'signalTime' => (string)($signalBar['time'] ?? ''),
        'zoneTouchCount' => $touchCount,
        'signalBodyFraction' => round($signalBodyFraction, 4),
        'entryIndex' => $entryIndex,
        'entryCandleNumber' => $entryIndex + 1,
        'entryTime' => (string)($entryBar['time'] ?? ''),
        'entryPrice' => $entry,
        'initialStop' => $sl,
        'targetPrice' => $tp1,
        'targetPrice2' => null,
        'exitIndex' => $exitIndex,
        'exitCandleNumber' => $exitIndex + 1,
        'exitTime' => (string)($exitBar['time'] ?? ''),
        'exitPrice' => $exitPrice,
        'exitType' => $exit['type'],
        'netR' => round($netR, 2),
        'riskR' => $risk,
        'rr' => round((float)$signal['rr'], 2),
        'rrTarget2' => null,
        'holdingBars' => max(0, $exitIndex - $entryIndex),
        'sameBarAmbiguous' => (bool)($exit['sameBarAmbiguous'] ?? false),
        'zoneId' => (string)($zone['id'] ?? ''),
        'zoneType' => (string)($zone['type'] ?? ''),
        'zoneLow' => $low,
        'zoneHigh' => $high,
        'zoneOriginIndex' => $originIndex,
        'zoneOriginCandleNumber' => $originIndex + 1,
        'zone' => array_merge($zone, [
            'originIndex' => $originIndex,
            'originCandleNumber' => $originIndex + 1,
            'firstTouchIndex' => $anchorIndex,
            'firstTouchCandleNumber' => $anchorIndex + 1,
        ]),
        'reason' => $zoneLabel . ': giá quay lại vùng ' . $rangeText . ', M15 có nến xác nhận. ' . $sideLabel . ' ở giá mở cửa nến kế tiếp, kết quả theo các nến M15 sau lệnh.',
        'zoneReason' => (string)($zone['reason'] ?? ($zoneLabel . ' lấy từ vùng phản ứng H4.')),
        'entryReason' => 'Nến M15 #' . ($signal['signalIndex'] + 1) . ' chạm vùng ' . $rangeText . ' và đóng ' . ($direction === 'short' ? 'giảm dưới giữa vùng' : 'tăng trên giữa vùng') . '. Vào ' . strtolower($sideLabel) . ' tại giá mở cửa nến M15 kế tiếp (#' . ($entryIndex + 1) . ').',
        'riskReason' => 'Chỉ giữ setup có RR tối thiểu ' . number_format($minimumRr, 1) . 'R; TP mặc định tại ' . number_format((float)$signal['rr'], 1) . 'R tính từ giá vào thực tế.',
        'exitReason' => paTrainingTeachingExitReason($exit['type'], (float)$netR) . (!empty($exit['sameBarAmbiguous']) ? ' Nến này chạm cả SL và TP; tính SL vì không có dữ liệu thứ tự giá trong nến.' : ''),
        'annotations' => [
            'zoneLabel' => strtoupper($zoneLabel),
            'entryLabel' => '', 'slLabel' => '', 'tpLabel' => '', 'exitLabel' => '',
        ],
    ];
}

function paTrainingTeachingExitReason(string $type, float $netR): string
{
    if ($type === 'TP' || $type === 'TP1') return 'Sau khi vào lệnh, nến M15 chạm TP1; kết quả mô phỏng là ' . number_format($netR, 2, '.', '') . 'R.';
    if ($type === 'TP2') return 'Sau khi vào lệnh, chốt một nửa ở TP1 và phần còn lại ở TP2; kết quả mô phỏng là ' . number_format($netR, 2, '.', '') . 'R.';
    if ($type === 'TP1_SL') return 'Chốt một nửa ở TP1, phần còn lại chạm SL; kết quả mô phỏng là 0R.';
    if ($type === 'SL') return 'Sau khi vào lệnh, nến M15 chạm SL trước TP; kết quả mô phỏng là ' . number_format($netR, 2, '.', '') . 'R.';
    return 'Chưa có kết quả SL/TP xác nhận từ nến M15.';
}
function paTrainingFeedbackSave(JsonStore $store, array $payload): array
{
    $kind = (string)($payload['kind'] ?? 'setup');
    $snapshot = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : [];
    if (!empty($snapshot['trade']['corrected'])) $snapshot['trade'] = PaReviewService::correctedTrade($snapshot['trade'], []);
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    if (strlen($json) > 500000) {
        $snapshot = [
            'preview' => true,
            'truncated' => true,
            'original_size' => strlen($json),
            'summary' => [
                'originalTrade' => $snapshot['originalTrade'] ?? null,
                'zone' => $snapshot['zone'] ?? null,
                'trade' => $snapshot['trade'] ?? null,
                'session' => is_array($snapshot['session'] ?? null) ? array_diff_key($snapshot['session'], ['chart' => true]) : null,
            ],
        ];
    }
    $review = [
        'id' => 'pa_review_' . time() . '_' . bin2hex(random_bytes(3)),
        'kind' => $kind,
        'targetId' => (string)($payload['targetId'] ?? ''),
        'symbol' => paTrainingSymbol((string)($payload['symbol'] ?? '')),
        'timeframe' => strtoupper((string)($payload['timeframe'] ?? 'H4')),
        'decision' => (string)($payload['decision'] ?? ''),
        'label' => (string)($payload['label'] ?? $payload['decision'] ?? ''),
        'note' => paTrainingLimitText((string)($payload['note'] ?? ''), 3000),
        'snapshot' => $snapshot,
        'createdAt' => gmdate('c'),
    ];
    $m = paTrainingMemory($store, true);
    $reviewId = (string)($payload['reviewId'] ?? '');
    if ($reviewId !== '') {
        $existing = null;
        foreach ($m['setup_reviews'] as $item) if (($item['id'] ?? '') === $reviewId && empty($item['deletedAt'])) { $existing = $item; break; }
        if (!$existing) throw new InvalidArgumentException('Ca đã lưu không còn tồn tại. Hãy mở lại danh sách ca.');
        if (($payload['expectedUpdatedAt'] ?? '') !== ($existing['updatedAt'] ?? $existing['createdAt'])) throw new RuntimeException('Ca đã được sửa ở cửa sổ khác. Hãy tải lại trước khi lưu.');
        $review['id'] = $reviewId;
        $review['createdAt'] = $existing['createdAt'];
        $review['updatedAt'] = (new DateTimeImmutable())->format('Y-m-d\TH:i:s.uP');
        $m['setup_reviews'] = array_values(array_filter($m['setup_reviews'], static fn(array $item): bool => $item['id'] !== $reviewId));
        $m['lessons'] = array_values(array_filter($m['lessons'], static fn(array $item): bool => ($item['sourceReviewId'] ?? '') !== $reviewId));
    }
    if ($kind === 'zone') array_unshift($m['zone_reviews'], $review); else array_unshift($m['setup_reviews'], $review);
    $lesson = paTrainingLessonFromFeedback($review);
    if ($lesson) array_unshift($m['lessons'], $lesson);
    $m['zone_reviews'] = array_slice($m['zone_reviews'], 0, 4000);
    $m['setup_reviews'] = array_slice($m['setup_reviews'], 0, 4000);
    $m['lessons'] = array_slice($m['lessons'], 0, 2000);
    paTrainingSaveMemory($store, $m);
    return ['ok' => true, 'review' => $review, 'pa_training_memory' => paTrainingMemory($store)];
}

function paTrainingLessonFromFeedback(array $review): ?array
{
    $note = trim((string)($review['note'] ?? ''));
    if ($note === '') return null;
    return [
        'id' => 'pa_lesson_' . time() . '_' . bin2hex(random_bytes(3)),
        'sourceReviewId' => $review['id'] ?? '',
        'kind' => ($review['kind'] ?? '') === 'zone' ? 'zone' : 'trade_case',
        'title' => (string)($review['label'] ?? 'Bài h?c PA'),
        'content' => $note,
        'symbol' => (string)($review['symbol'] ?? ''),
        'timeframe' => (string)($review['timeframe'] ?? ''),
        'tags' => ['feedback', (string)($review['decision'] ?? '')],
        'confidence' => 4,
        'createdAt' => gmdate('c'),
    ];
}

function savePaTrainingLesson(JsonStore $store, array $payload): array
{
    $text = paTrainingLimitText((string)($payload['sourceText'] ?? $payload['content'] ?? ''), 4000);
    if ($text === '') throw new InvalidArgumentException('Chua có n?i dung d? luu thành bài h?c.');
    $lesson = paTrainingDraftLesson($text, is_array($payload['zone'] ?? null) ? $payload['zone'] : null, is_array($payload['trade'] ?? null) ? $payload['trade'] : null, is_array($payload['context'] ?? null) ? $payload['context'] : [], 'chat_lesson', '', '', 'Bài h?c t? chat');
    $m = paTrainingStoreLessons($store, paTrainingMemory($store, true), [$lesson]);
    return ['ok' => true, 'lesson' => $lesson, 'pa_training_memory' => paTrainingMemory($store)];
}

function paTrainingDraftLesson(string $text, ?array $zone, ?array $trade, array $context, string $kind, string $title = '', string $tags = '', string $fallbackTitle = 'Bài h?c PA'): array
{
    $symbol = paTrainingSymbol((string)($context['symbol'] ?? $trade['symbol'] ?? $zone['symbol'] ?? ''));
    $tf = strtoupper((string)($context['timeframe'] ?? $trade['timeframe'] ?? $zone['timeframe'] ?? 'H4'));
    return ['id' => 'pa_lesson_' . time() . '_' . bin2hex(random_bytes(3)), 'kind' => $kind, 'title' => $title ?: $fallbackTitle, 'content' => $text, 'symbol' => $symbol, 'timeframe' => $tf, 'tags' => array_values(array_filter([$kind, $symbol ? 'symbol:' . strtolower($symbol) : '', $tf ? 'tf:' . strtolower($tf) : '', $tags])), 'confidence' => 4, 'createdAt' => gmdate('c')];
}

function paTrainingStoreLessons(JsonStore $store, array $m, array $lessons): array
{
    // Chat receives active memory only. Reload the complete store before writing
    // so adding a lesson cannot discard records retained in the trash.
    $m = paTrainingMemory($store, true);
    foreach ($lessons as $lesson) if (is_array($lesson)) array_unshift($m['lessons'], $lesson);
    $m['lessons'] = array_slice(array_values($m['lessons']), 0, 2000);
    paTrainingSaveMemory($store, $m);
    return paTrainingMemory($store);
}

function paTrainingLessonEstimatedTokens(string $text): int
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') return 0;
    preg_match_all('/[\p{L}\p{N}]+|[^\s\p{L}\p{N}]/u', $text, $units);
    $compact = preg_replace('/\s+/u', '', $text) ?? $text;
    $characters = function_exists('mb_strlen') ? mb_strlen($compact, 'UTF-8') : strlen($compact);
    return max(count($units[0] ?? []), (int)ceil($characters / 4));
}

function paTrainingLessonSearchTerms(string $text): array
{
    $stopWords = array_fill_keys(explode(' ', 'ai app ban biet cac cai ca chart cho chon chi dang day de den duoc gi giup hay hien khi khong la lenh moi mot muc nay neu noi o phan price action setup tai theo the thi toi trong tu va voi xem'), true);
    preg_match_all('/[\p{L}\p{N}]{2,}/u', paTrainingLower($text), $matches);
    return array_values(array_unique(array_filter($matches[0] ?? [], static fn(string $term): bool => !isset($stopWords[$term]))));
}

function paTrainingRelevantLessons(array $memory, array $context, ?array $zone, ?array $trade, string $query = '', int $tokenBudget = 3200): array
{
    $lessons = is_array($memory['lessons'] ?? null) ? $memory['lessons'] : [];
    $symbol = paTrainingSymbol((string)($context['symbol'] ?? $trade['symbol'] ?? $zone['symbol'] ?? ''));
    $tf = strtoupper((string)($context['timeframe'] ?? $trade['timeframe'] ?? $zone['timeframe'] ?? ''));
    $zone = is_array($zone) ? $zone : [];
    $trade = is_array($trade) ? $trade : [];
    $contextText = implode(' ', array_map('strval', [
        $query,
        $context['direction'] ?? '', $context['reason'] ?? '',
        $zone['type'] ?? '', $zone['status'] ?? '', $zone['originType'] ?? '',
        $trade['direction'] ?? '', $trade['exitType'] ?? '', $trade['correctionNote'] ?? '', $trade['reason'] ?? '',
    ]));
    $queryTerms = paTrainingLessonSearchTerms($contextText);

    // Merge exact repeated notes so repeated feedback is visible as stronger evidence, not duplicate prompt text.
    $groups = [];
    foreach ($lessons as $lesson) {
        if (!is_array($lesson)) continue;
        $content = trim((string)($lesson['content'] ?? $lesson['summary'] ?? ''));
        $normalized = paTrainingLower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $content) ?? $content);
        if ($normalized === '') continue;
        $lessonSymbol = paTrainingSymbol((string)($lesson['symbol'] ?? ''));
        $lessonTf = strtoupper((string)($lesson['timeframe'] ?? ''));
        $kind = (string)($lesson['kind'] ?? '');
        $tags = is_array($lesson['tags'] ?? null) ? array_map('strval', $lesson['tags']) : [];
        sort($tags);
        $key = hash('sha256', implode("\n", [$lessonSymbol, $lessonTf, $kind, (string)($lesson['title'] ?? ''), implode(',', $tags), $normalized]));
        if (!isset($groups[$key])) {
            $groups[$key] = $lesson;
            $groups[$key]['support_count'] = max(1, (int)($lesson['support_count'] ?? 1));
        } else {
            $groups[$key]['support_count'] += max(1, (int)($lesson['support_count'] ?? 1));
            $groups[$key]['confidence'] = max((int)($groups[$key]['confidence'] ?? 1), (int)($lesson['confidence'] ?? 1));
            if (strcmp((string)($lesson['createdAt'] ?? ''), (string)($groups[$key]['createdAt'] ?? '')) > 0) {
                foreach (['id', 'title', 'createdAt'] as $field) if (isset($lesson[$field])) $groups[$key][$field] = $lesson[$field];
            }
        }
    }

    $ranked = [];
    foreach ($groups as $lesson) {
        $lessonSymbol = paTrainingSymbol((string)($lesson['symbol'] ?? ''));
        $lessonTf = strtoupper((string)($lesson['timeframe'] ?? ''));
        $tags = is_array($lesson['tags'] ?? null) ? $lesson['tags'] : [];
        $searchable = implode(' ', array_filter([
            (string)($lesson['title'] ?? ''), (string)($lesson['content'] ?? ''),
            (string)($lesson['summary'] ?? ''), (string)($lesson['appliesWhen'] ?? ''),
            implode(' ', array_map('strval', $tags)), (string)($lesson['kind'] ?? ''),
        ]));
        $searchableLower = paTrainingLower($searchable);
        $score = max(0, min(5, (int)($lesson['confidence'] ?? 1)));
        if ($symbol && $lessonSymbol === $symbol) $score += 16;
        if ($tf && $lessonTf === $tf) $score += 8;
        $kind = (string)($lesson['kind'] ?? '');
        if (($trade && $kind === 'trade_case') || ($zone && $kind === 'zone')) $score += 3;
        foreach ($queryTerms as $term) {
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u', $searchableLower)) $score += 3;
        }
        $score += min(6, (int)floor(log(max(1, (int)($lesson['support_count'] ?? 1)), 2) * 2));
        $lesson['_score'] = $score;
        $ranked[] = $lesson;
    }
    usort($ranked, static fn(array $a, array $b): int => ((int)($b['_score'] ?? 0) <=> (int)($a['_score'] ?? 0)) ?: strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));

    $budget = max(128, min(6000, $tokenBudget));
    $selected = [];
    $usedTokens = 0;
    foreach ($ranked as $lesson) {
        $item = array_intersect_key($lesson, array_flip(['title', 'content', 'summary', 'appliesWhen', 'kind', 'symbol', 'timeframe', 'tags', 'confidence', 'support_count', 'createdAt']));
        $item['_score'] = (int)($lesson['_score'] ?? 0);
        $tokens = paTrainingLessonEstimatedTokens(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        if ($tokens > 0 && $usedTokens + $tokens > $budget) continue;
        $selected[] = $item;
        $usedTokens += $tokens;
    }
    return $selected;
}

function paTrainingChatScope(array $context): array
{
    $caseId = (string)($context['selectedTeachingCaseId'] ?? $context['selectedTradeId'] ?? '');
    return ['symbol' => paTrainingSymbol((string)($context['symbol'] ?? '')), 'timeframe' => strtoupper((string)($context['timeframe'] ?? 'H4')), 'caseId' => $caseId, 'zoneId' => (string)($context['selectedZoneId'] ?? '')];
}

function paTrainingChatMessageId(): string { return 'pa_msg_' . time() . '_' . bin2hex(random_bytes(4)); }

function paTrainingChatHistoryAction(JsonStore $store, array $payload): array
{
    $scope = paTrainingChatScope(is_array($payload['context'] ?? null) ? $payload['context'] : $payload);
    $messages = paTrainingChatMessagesForScope($store->readJson('pa_training/chat_history.json', []), $scope);
    return ['ok' => true, 'messages' => array_slice($messages, -120), 'scope' => $scope, 'pa_training_memory' => paTrainingMemory($store)];
}

function paTrainingWorkspaceState(JsonStore $store, array $payload): array
{
    $current = $store->readJson('pa_training/workspace_state.json', []);
    if (!is_array($current)) $current = [];
    if (!array_key_exists('state', $payload)) return ['ok' => true, 'workspace' => $current];
    $items = is_array($payload['state']) ? $payload['state'] : [];
    foreach ($items as $key => $item) {
        if (!is_array($item)) continue;
        $symbol = paTrainingSymbol((string)($item['symbol'] ?? ''));
        $timeframe = strtoupper((string)($item['timeframe'] ?? 'H4'));
        if ($symbol === '' || !preg_match('/^[A-Z0-9]+$/', $symbol) || !preg_match('/^[A-Z0-9]+$/', $timeframe)) continue;
        $replayIndex = $item['replayIndex'] ?? null;
        $chartTimeframe = strtoupper((string)($item['chartTimeframe'] ?? $timeframe));
        $allowedTimeframes = ['M1', 'M5', 'M15', 'M30', 'H1', 'H4', 'D1', 'W1', 'MN1'];
        if (!in_array($chartTimeframe, $allowedTimeframes, true)) $chartTimeframe = $timeframe;
        $skipped = is_array($item['skippedCaseIds'] ?? null) ? array_slice($item['skippedCaseIds'], 0, 500) : [];
        $skipped = array_values(array_filter(array_map('strval', $skipped), static fn(string $id): bool => preg_match('/^case_[A-Za-z0-9_-]{1,155}$/', $id) === 1));
        $workspace = [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'selectedCaseId' => paTrainingLimitText((string)($item['selectedCaseId'] ?? ''), 160),
            'skippedCaseIds' => $skipped,
            'replayIndex' => is_numeric($replayIndex) ? max(0, (int)$replayIndex) : null,
            'replayEnabled' => ($item['replayEnabled'] ?? true) !== false,
            'chartTimeframe' => $chartTimeframe,
            'updatedAt' => (string)($item['updatedAt'] ?? gmdate('c')),
        ];
        foreach (['barsOnScreen' => [12, 100000], 'rightOffset' => [-100000, 100000]] as $field => [$min, $max]) {
            if (isset($item[$field]) && is_numeric($item[$field]) && is_finite((float)$item[$field])) {
                $workspace[$field] = max($min, min($max, (int)$item[$field]));
            }
        }
        foreach (['viewportRightTime', 'priceScale', 'priceOffset'] as $field) {
            $value = $item[$field] ?? null;
            if ($value === null) {
                $workspace[$field] = null;
            } elseif (is_numeric($value) && is_finite((float)$value)) {
                $number = (float)$value;
                $workspace[$field] = $field === 'priceScale' ? max(0.2, min(20.0, $number)) : max(-1000000000000.0, min(1000000000000.0, $number));
            }
        }
        $current[$symbol . '_' . $timeframe] = $workspace;
    }
    $store->writeJson('pa_training/workspace_state.json', $current);
    return ['ok' => true, 'workspace' => $current];
}

function paTrainingAppendChatMessages(JsonStore $store, array $new): array
{
    $messages = $store->readJson('pa_training/chat_history.json', []);
    if (!is_array($messages)) $messages = [];
    foreach ($new as $message) if (is_array($message)) $messages[] = $message;
    $messages = array_slice($messages, -10000);
    $store->writeJson('pa_training/chat_history.json', $messages);
    return $messages;
}

function paTrainingChatMessagesForScope(array $messages, array $scope): array
{
    $filtered = array_values(array_filter($messages, static function (array $m) use ($scope): bool {
        $symbol = paTrainingSymbol((string)($m['symbol'] ?? $m['context']['symbol'] ?? ''));
        return ($scope['symbol'] ?? '') === '' || $symbol === '' || $symbol === $scope['symbol'];
    }));
    usort($filtered, static fn(array $a, array $b): int => strcmp((string)($a['createdAt'] ?? ''), (string)($b['createdAt'] ?? '')));
    return $filtered;
}

function paTrainingAiChat(JsonStore $store, array $payload): array
{
    $message = trim(paTrainingLimitText((string)($payload['message'] ?? ''), 2000));
    if ($message === '') throw new InvalidArgumentException('B?n chua nh?p n?i dung d? h?i AI.');
    $history = is_array($payload['history'] ?? null) ? $payload['history'] : [];
    $zone = is_array($payload['zone'] ?? null) ? $payload['zone'] : null;
    $trade = is_array($payload['trade'] ?? null) ? $payload['trade'] : null;
    $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
    $chartAttachment = is_array($payload['chart_attachment'] ?? null) ? $payload['chart_attachment'] : [];
    $memory = paTrainingMemory($store);
    $wantsChartDrawing = paTrainingIsChartDrawingRequest($message);
    $actions = paTrainingChatActions($message, $context, $history);
    $lessons = paTrainingRelevantLessons($memory, $context, $zone, $trade, $message);
    $ai = paTrainingOpenAICoachReply($message, $zone, $trade, $actions, $memory, $context, $history, $lessons, $chartAttachment, $wantsChartDrawing);
    if ($wantsChartDrawing && empty(array_filter($actions, static fn(array $action): bool => ($action['type'] ?? '') === 'draw_chart_annotation'))) {
        $actions = array_merge($actions, paTrainingNormalizeAiChartActions($ai['chart_actions'] ?? [], $context, $chartAttachment));
    }
    $provider = is_array($ai['provider'] ?? null) ? $ai['provider'] : ['used' => 'openai_chat', 'status' => 'openai_failed', 'error' => 'OpenAI không tr? l?i du?c.'];
    $reply = ($ai['ok'] ?? false) === true ? (string)$ai['reply'] : paTrainingProviderErrorReply($provider, $actions);
    if (paTrainingShouldSaveChatLesson($message, $actions)) {
        $memory = paTrainingStoreLessons($store, $memory, [paTrainingDraftLesson($message, $zone, $trade, $context, 'chat_lesson', '', '', 'Bài h?c t? chat')]);
        $lessons = paTrainingRelevantLessons($memory, $context, $zone, $trade, $message);
    }
    $scope = paTrainingChatScope($context);
    $zoneId = is_array($zone) ? (string)($zone['id'] ?? $zone['zoneId'] ?? '') : '';
    $tradeId = is_array($trade) ? (string)($trade['id'] ?? '') : '';
    $messages = paTrainingAppendChatMessages($store, [
        ['id' => paTrainingChatMessageId(), 'role' => 'user', 'content' => $message, 'createdAt' => gmdate('c'), 'symbol' => $scope['symbol'], 'timeframe' => $scope['timeframe'], 'zoneId' => $zoneId, 'tradeId' => $tradeId, 'context' => $context],
        ['id' => paTrainingChatMessageId(), 'role' => 'assistant', 'content' => $reply, 'createdAt' => gmdate('c'), 'symbol' => $scope['symbol'], 'timeframe' => $scope['timeframe'], 'zoneId' => $zoneId, 'tradeId' => $tradeId, 'provider' => (string)($provider['used'] ?? 'openai_chat'), 'context' => $context],
    ]);
    return ['ok' => true, 'reply' => $reply, 'actions' => $actions, 'scope' => $scope, 'messages' => array_slice(paTrainingChatMessagesForScope($messages, $scope), -120), 'provider' => $provider, 'relevant_lessons' => $lessons, 'pa_training_memory' => $memory];
}

function paTrainingOpenAICoachReply(string $message, ?array $zone, ?array $trade, array $actions, array $memory, array $context = [], array $history = [], array $relevantLessons = [], array $chartAttachment = [], bool $wantsChartDrawing = false): array
{
    try {
        $provider = ProviderFactory::fromConfigFile()->create('openai_chat');
        $health = $provider->health();
        if (($health['ok'] ?? false) !== true) {
            return ['ok' => false, 'provider' => ['used' => 'openai_chat', 'status' => (string)($health['status'] ?? 'not_ready'), 'error' => paTrainingProviderStatusText((string)($health['status'] ?? 'not_ready'))]];
        }
        $messages = [[
            'role' => 'system',
            'content' => "B?n là AI Coach trong app d?y Price Action Forex H4/M15. Tr? l?i ti?ng Vi?t ng?n, th?c t?. Khi có ảnh chart và hình vẽ, hãy phân tích đúng các vùng/đường được đánh dấu; dữ liệu tọa độ trong phần đính kèm mô tả chúng. Phân biệt nhận xét kỹ thuật với khuyến nghị chắc chắn; nêu điều kiện và rủi ro. N?u user ra l?nh thao tác, xác nh?n thao tác app s? ch?y b?ng action n?i b?. Không h?a d?t l?nh th?t. Use relevant_lessons as prior examples: support_count indicates repeated observations; favor rules supported by multiple context-matched examples, do not treat a single lesson as universal, and call out conflicting lessons when relevant.",
        ]];
        $messages[] = ['role' => 'system', 'content' => $wantsChartDrawing
            ? 'This user requested a chart drawing. Return exactly one JSON object with keys reply (Vietnamese string) and chart_actions (array). Each action must be {"type":"draw_chart_annotation","drawing":{...}}. Use only supported drawing types listed in the user data. Every point must use an existing global barIndex from chart_geometry.visible_bars and a real positive price from those candles/chart/context. Never invent bar indexes outside chart_geometry.bar_count. For zones use type rect, two points at the price boundaries, and zoneType supply or demand only when justified. For lines use two points; for hline use price; for vline use barIndex; for text/markers use point; position uses direction, entry, stop, target, startBar, endBar. You may return multiple drawings when requested. Prefer the selected trade/zone values when the user asks to draw that setup. If the chart/context cannot support a precise drawing, return chart_actions:[] and ask one short clarifying question in reply. Do not say you drew anything unless chart_actions contains it. If app_actions_detected already contains draw_chart_annotation, return chart_actions:[] to avoid duplicate drawings.'
            : 'Only say an app action completed when app_actions_detected contains the matching action. If none is detected, ask for clarification and do not claim that you are opening or changing a chart.'];
        foreach (array_slice($history, -12) as $item) {
            if (!is_array($item)) continue;
            $role = in_array(($item['role'] ?? ''), ['user', 'assistant'], true) ? (string)$item['role'] : 'user';
            $content = trim((string)($item['content'] ?? ''));
            if ($content !== '') $messages[] = ['role' => $role, 'content' => paTrainingLimitText($content, 1200)];
        }
        $userContent = json_encode([
            'user_message' => $message,
            'chart_context' => $context,
            'chart_annotations' => [
                'symbol' => (string)($chartAttachment['symbol'] ?? ''),
                'timeframe' => (string)($chartAttachment['timeframe'] ?? ''),
                'drawings' => is_array($chartAttachment['drawings'] ?? null) ? array_slice($chartAttachment['drawings'], 0, 12) : [],
                'chart_geometry' => [
                    'bar_count' => (int)($chartAttachment['barCount'] ?? 0),
                    'visible_first' => (int)($chartAttachment['visibleFirst'] ?? 0),
                    'visible_last_exclusive' => (int)($chartAttachment['visibleLastExclusive'] ?? 0),
                    'price_low' => $chartAttachment['priceLow'] ?? null,
                    'price_high' => $chartAttachment['priceHigh'] ?? null,
                    'visible_bars' => is_array($chartAttachment['visibleBars'] ?? null) ? array_slice($chartAttachment['visibleBars'], 0, 120) : [],
                ],
                'note' => 'Ảnh chart (nếu có) thể hiện biểu đồ hiện tại cùng các hình vẽ được gửi để hỏi.',
            ],
            'supported_chart_drawing_types' => $wantsChartDrawing ? paTrainingSupportedChartDrawingTypes() : [],
            'selected_zone' => $zone,
            'selected_trade_case' => $trade,
            'app_actions_detected' => $actions,
            'relevant_lessons' => $relevantLessons,
            'memory_counts' => ['zone_reviews' => count($memory['zone_reviews'] ?? []), 'setup_reviews' => count($memory['setup_reviews'] ?? []), 'lessons' => count($memory['lessons'] ?? [])],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $message;
        $imageDataUrl = (string)($chartAttachment['imageDataUrl'] ?? '');
        if ($imageDataUrl !== '' && strlen($imageDataUrl) <= 3500000 && preg_match('#^data:image/jpeg;base64,[A-Za-z0-9+/]+={0,2}$#', $imageDataUrl) === 1) {
            $messages[] = ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $userContent],
                ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl, 'detail' => 'low']],
            ]];
        } else {
            $messages[] = ['role' => 'user', 'content' => $userContent];
        }
        $response = $provider->complete(['messages' => $messages, 'model_alias' => 'default', 'response_format' => $wantsChartDrawing ? 'json_object' : 'plain_text']);
        if (($response['ok'] ?? false) !== true) {
            return ['ok' => false, 'provider' => ['used' => 'openai_chat', 'status' => (string)($response['error']['category'] ?? 'openai_failed'), 'error' => (string)($response['error']['message'] ?? 'OpenAI không tr? l?i du?c.')]];
        }
        $replyText = trim((string)($response['text'] ?? ''));
        $chartActions = [];
        if ($wantsChartDrawing) {
            $structured = json_decode($replyText, true);
            if (is_array($structured)) {
                $replyText = trim((string)($structured['reply'] ?? ''));
                $chartActions = is_array($structured['chart_actions'] ?? null) ? $structured['chart_actions'] : [];
            } else {
                $replyText = '';
            }
            if ($replyText === '') $replyText = $chartActions ? 'Mình đang đánh dấu các hình được chọn lên biểu đồ.' : 'Mình chưa xác định được tọa độ đủ chắc chắn để vẽ. Hãy chỉ rõ vùng/giá hoặc loại hình cần đánh dấu.';
        }
        return ['ok' => true, 'reply' => $replyText, 'chart_actions' => $chartActions, 'provider' => ['used' => 'openai_chat', 'status' => 'ok', 'model' => (string)($response['model_name'] ?? '')]];
    } catch (Throwable $e) {
        return ['ok' => false, 'provider' => ['used' => 'openai_chat', 'status' => 'exception', 'error' => $e->getMessage()]];
    }
}

function paTrainingProviderStatusText(string $status): string
{
    return match ($status) {
        'disabled' => 'OpenAI Chat provider dang t?t. B?t và luu API token ? m?c K?t n?i OpenAI.',
        'missing_api_key' => 'OpenAI thi?u API key. Hay luu token API ? m?c K?t n?i OpenAI.',
        'missing_model' => 'OpenAI thi?u model m?c d?nh. Hay ch?n/luu model ? m?c K?t n?i OpenAI.',
        default => 'OpenAI chua s?n sàng: ' . $status,
    };
}

function paTrainingProviderErrorReply(array $providerMeta, array $actions): string
{
    $lines = [];
    foreach ($actions as $action) {
        $type = (string)($action['type'] ?? '');
        if ($type === 'set_indicator') $lines[] = 'App v?n da x? ly thao tác bi?u d?: ' . (($action['enabled'] ?? true) === false ? 't?t ' : 'b?t ') . (string)($action['label'] ?? 'ch? báo') . '.';
        elseif ($type === 'open_chart') $lines[] = 'App v?n da x? ly thao tác m? chart ' . (string)($action['timeframe'] ?? '') . '.';
        elseif ($type === 'open_symbol_chart') $lines[] = 'App v?n da x? ly thao tác m? ' . (string)($action['symbol'] ?? '') . ' ' . (string)($action['timeframe'] ?? '') . '.';
        elseif ($type === 'save_current_session') $lines[] = 'App s? luu phiên hi?n t?i n?u dang có ca trade du?c ch?n.';
        elseif ($type === 'load_trade_cases') $lines[] = 'App s? t?i/t?o hàng d?i ca d?y theo yêu c?u.';
        elseif ($type === 'start_trade_cases') $lines[] = 'App s? b?t d?u ca d?y d?u tiên trong hàng d?i.';
        elseif ($type === 'chart_boundary') $lines[] = 'App s? nh?y t?i ' . (($action['where'] ?? '') === 'start' ? 'd?u' : 'cu?i') . ' bi?u d?.';
        elseif ($type === 'fit_chart') $lines[] = 'App s? dua chart v? ch? d? v?a khung.';
    }
    $error = trim((string)($providerMeta['error'] ?? 'OpenAI chua ph?n h?i du?c.'));
    return trim('Chua g?i du?c OpenAI th?t nên mình không gi? v? tr? l?i nhu ChatGPT. ' . $error . ($lines ? ' ' . implode(' ', $lines) : ''));
}

function paTrainingShouldSaveChatLesson(string $message, array $actions): bool
{
    foreach ($actions as $action) if (($action['type'] ?? '') === 'save_chat_lesson') return true;
    return paTrainingContainsAny(paTrainingLower($message), ['ghi nh?', 'ghi nho', 'luu bài h?c', 'luu bai hoc', 'hay h?c', 'hay hoc']);
}

function paTrainingExtractSymbolFromText(string $message): string
{
    if (preg_match('/\b([A-Z]{3})[\s_\/.-]?([A-Z]{3})\b/i', $message, $m)) {
        $known = ['AUD','CAD','CHF','EUR','GBP','JPY','NZD','USD','XAU','XAG','BTC','ETH'];
        if (in_array(strtoupper($m[1]), $known, true) && in_array(strtoupper($m[2]), $known, true)) {
            return paTrainingSymbol($m[1] . $m[2]);
        }
    }

    $tokens = preg_split('/[^\p{L}\p{N}]+/u', paTrainingLower($message), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $aliases = [
        'au' => 'AUDUSD', 'âu' => 'AUDUSD',
        'gu' => 'GBPUSD',
        'eu' => 'EURUSD',
        'uj' => 'USDJPY',
        'uc' => 'USDCAD',
        'nu' => 'NZDUSD',
        'aj' => 'AUDJPY',
        'gj' => 'GBPJPY',
        'vàng' => 'XAUUSD', 'gold' => 'XAUUSD',
    ];
    foreach ($tokens as $token) {
        if (isset($aliases[$token])) return $aliases[$token];
    }
    return '';
}

function paTrainingExtractSymbolFromHistory(array $history): string
{
    for ($i = count($history) - 1; $i >= 0; $i--) {
        $symbol = paTrainingExtractSymbolFromText((string)($history[$i]['content'] ?? ''));
        if ($symbol !== '') return $symbol;
    }
    return '';
}

function paTrainingExtractTimeframeFromText(string $message): string
{
    $text = paTrainingLower($message);
    foreach (['M15','M30','M1','M5','H1','H4','D1'] as $tf) if (preg_match('/\b' . strtolower($tf) . '\b/u', $text) === 1) return $tf;
    return '';
}

function paTrainingChatActions(string $message, array $context = [], array $history = []): array
{
    $text = paTrainingLower($message);
    $actions = [];
    $isTradeCase = paTrainingContainsAny($text, ['ca trade','ca này','case này','l?nh này','setup','entry','vào l?nh','di?m vào','sl','tp','stop loss','take profit']);
    $wantsNext = paTrainingContainsAny($text, ['ti?p theo','vùng ti?p','sang vùng','ca ti?p','l?nh ti?p','trade ti?p','case ti?p','next']);
    $wantsPrevious = paTrainingContainsAny($text, ['quay l?i','vùng tru?c','ca tru?c','l?nh tru?c','previous']);
    $timeframeTarget = paTrainingExtractTimeframeFromText($message);
    $wantsOpen = paTrainingContainsAny($text, ['mở','mo','xem','chart','biểu đồ','bieu do','chuyển','chuyen','đổi','doi','sang']);
    $symbolTarget = paTrainingExtractSymbolFromText($message);
    if ($symbolTarget === '' && $wantsOpen && paTrainingContainsAny($text, ['dúng','dung','ok','?','uh','uhm','du?c','duoc','m? di','mo di'])) $symbolTarget = paTrainingExtractSymbolFromHistory($history);
    $wantsIndicator = paTrainingContainsAny($text, ['v?','ve','thêm','them','b?t','bat','t?t','tat','?n','an','hi?n th?','hien thi','cho vào','màu','mau','ma20','ema20','ma50','ema50','ma200','ema200','rsi','macd','bollinger','vwap']);
    $disableIndicator = paTrainingContainsAny($text, ['t?t','tat','?n','an','b? ma','bo ma','remove','hide']);
    $wantsSaveSession = paTrainingContainsAny($text, ['luu phiên','luu phien','luu ca','luu ca','luu l?nh','luu lenh','luu di','luu di','xong ca','xong l?nh','xong lenh','done','k?t thúc ca','ket thuc ca','ch?t ca','chot ca']);
    $wantsLessonSave = paTrainingContainsAny($text, ['luu bài h?c','luu bai hoc','luu thành bài h?c','luu thanh bai hoc','ghi nh? bài h?c','ghi nho bai hoc','hay h?c','hay hoc']);
    $wantsLoadCases = paTrainingContainsAny($text, ['t?i ca','tai ca','t?o ca','tao ca','l?y ca','lay ca','t?i vùng','tai vung','t?o hàng d?i','tao hang doi']);
    $wantsStartCases = paTrainingContainsAny($text, ['b?t d?u d?y','bat dau day','b?t d?u ca','bat dau ca','vào ca d?u','vao ca dau','start cases']);
    if ($symbolTarget !== '' && ($wantsOpen || $timeframeTarget !== '')) $actions[] = ['type' => 'open_symbol_chart', 'symbol' => $symbolTarget, 'timeframe' => $timeframeTarget];
    if ($wantsLoadCases) $actions[] = ['type' => 'load_trade_cases', 'symbol' => $symbolTarget, 'timeframe' => $timeframeTarget ?: 'H4'];
    if ($wantsStartCases) $actions[] = ['type' => 'start_trade_cases'];

    if ($isTradeCase) {
        $caseDecision = null; $caseLabel = '';
        if (paTrainingContainsAny($text, ['dúng','chu?n','h?p','d?p','good','ok ca','ca t?t','l?y ca','nên vào'])) { $caseDecision = 'good_case'; $caseLabel = 'Ca dúng cách trade'; }
        elseif (paTrainingContainsAny($text, ['b?','lo?i','skip','không trade','không vào','sai','chua ?n','không ?n','không ch?n'])) { $caseDecision = 'bad_case'; $caseLabel = 'B? ca này'; }
        elseif (paTrainingContainsAny($text, ['y?u','nhi?u','c?n s?a','vào s?m','vào mu?n','sl chua','tp chua','entry chua'])) { $caseDecision = 'weak_case'; $caseLabel = 'Ca y?u/c?n s?a'; }
        if ($caseDecision !== null) $actions[] = ['type' => 'save_case_feedback', 'decision' => $caseDecision, 'label' => $caseLabel, 'note' => paTrainingLimitText($message, 1200)];
    } else {
        $decision = null; $label = '';
        if (paTrainingContainsAny($text, ['da b? phá','bi phá','b? phá','break','broken','v? vùng'])) { $decision = 'broken_zone'; $label = 'Vùng da b? phá'; }
        elseif (paTrainingContainsAny($text, ['y?u','nhi?u','không s?ch','ch?m nhi?u','quá nhi?u l?n','không ro'])) { $decision = 'weak_zone'; $label = 'Vùng y?u/nhi?u'; }
        elseif (paTrainingContainsAny($text, ['b?','lo?i','skip','không trade','không vào','chua ?n','không ?n','không ch?n'])) { $decision = 'skip_zone'; $label = 'B? qua'; }
        elseif (paTrainingContainsAny($text, ['ch?n d? trade','ch?n vùng','duy?t','ok vùng','vùng d?p','có th? trade','uu tiên trade'])) { $decision = 'trade_zone'; $label = 'Ch?n d? trade'; }
        if ($decision !== null) $actions[] = ['type' => 'save_zone_feedback', 'decision' => $decision, 'label' => $label, 'note' => paTrainingLimitText($message, 1200)];
    }
    if ($wantsSaveSession) $actions[] = ['type' => 'save_current_session', 'note' => paTrainingLimitText($message, 1200)];
    if ($wantsLessonSave) $actions[] = ['type' => 'save_chat_lesson'];
    if ($symbolTarget === '' && $wantsOpen && $timeframeTarget !== '') $actions[] = ['type' => 'open_chart', 'timeframe' => $timeframeTarget];
    $drawingAction = paTrainingExplicitChartDrawingAction($message, $context);
    if ($drawingAction !== null) $actions[] = $drawingAction;
    $indicatorMap = [
        'ema20' => ['pattern' => '/\b(?:ema|ma)\s*20\b/u', 'label' => 'EMA 20'],
        'ema50' => ['pattern' => '/\b(?:ema|ma)\s*50\b/u', 'label' => 'EMA 50'],
        'ema200' => ['pattern' => '/\b(?:ema|ma)\s*200\b/u', 'label' => 'EMA 200'],
        'rsi' => ['pattern' => '/\brsi\b/u', 'label' => 'RSI'],
        'macd' => ['pattern' => '/\bmacd\b/u', 'label' => 'MACD'],
        'bollinger' => ['pattern' => '/\b(?:bollinger|bb)\b/u', 'label' => 'Bollinger Bands'],
        'vwap' => ['pattern' => '/\bvwap\b/u', 'label' => 'VWAP'],
    ];
    foreach ($indicatorMap as $indicator => $config) {
        if ($wantsIndicator && preg_match($config['pattern'], $text) === 1) {
            $color = null;
            if ($indicator === 'ema20' && paTrainingContainsAny($text, ['xanh lá','xanh la','xanh cây','xanh cay','green'])) $color = '#16a34a';
            if ($indicator === 'ema50' && paTrainingContainsAny($text, ['màu d?','mau do','d?','do','red'])) $color = '#ef4444';
            $actions[] = ['type' => 'set_indicator', 'indicator' => $indicator, 'label' => $config['label'], 'enabled' => !$disableIndicator, 'color' => $color];
        }
    }
    if (paTrainingContainsAny($text, ['v? d?u bi?u d?','ve dau bieu do','d?u bi?u d?','dau bieu do','v? d?u chart','ve dau chart'])) $actions[] = ['type' => 'chart_boundary', 'where' => 'start'];
    if (paTrainingContainsAny($text, ['v? cu?i bi?u d?','ve cuoi bieu do','cu?i bi?u d?','cuoi bieu do','v? cu?i chart','ve cuoi chart','n?n m?i nh?t','nen moi nhat'])) $actions[] = ['type' => 'chart_boundary', 'where' => 'end'];
    if (paTrainingContainsAny($text, ['v?a khung','vua khung','fit chart','xem toàn b?','xem toan bo','thu g?n chart','thu gon chart'])) $actions[] = ['type' => 'fit_chart'];
    if ($wantsPrevious) $actions[] = ['type' => $isTradeCase ? 'previous_trade' : 'previous_zone'];
    if ($wantsNext) $actions[] = ['type' => $isTradeCase ? 'next_trade' : 'next_zone'];
    return $actions;
}

function paTrainingIsChartDrawingRequest(string $message): bool
{
    $text = paTrainingLower($message);
    return paTrainingContainsAny($text, ['vẽ', 'draw ', 'đánh dấu', 'danh dau', 'khoanh', 'kẻ lên chart', 'ke len chart', 'tô vùng', 'to vung'])
        || preg_match('/(?<![\p{L}\p{N}])ve(?![\p{L}\p{N}])/u', $text) === 1;
}

function paTrainingSupportedChartDrawingTypes(): array
{
    return ['rect','rotated-rect','circle','ellipse','triangle-shape','fib-circle','fib-wedge','gann-square','gann-box','volume-profile','price-range','date-range','date-price-range','trendline','ray','extended-line','trend-angle','fib','fib-extension','fib-time-zone','fib-fan','gann-fan','forecast','bars-pattern','anchored-vwap','arc','curve','arrow','measure','parallel-channel','regression-trend','flat-top-bottom','disjoint-channel','fib-channel','polyline','path','brush','highlighter','xabcd','abcd','triangle-pattern','three-drives','head-shoulders','elliott-wave','cyclic-lines','pitchfork','inside-pitchfork','schiff-pitchfork','modified-schiff','hline','hray','vline','cross-line','dot','alert','price-label','pin','flag','icon','arrow-marker','up-arrow','down-arrow','text','note','price-note','callout','comment','table','position'];
}

function paTrainingExplicitChartDrawingAction(string $message, array $context): ?array
{
    if (!paTrainingIsChartDrawingRequest($message)) return null;
    $text = paTrainingLower($message);
    $number = '(\\d+(?:[.,]\\d+)?|\\.\\d+)';
    if (paTrainingContainsAny($text, ['vùng','vung','zone','khoanh','rectangle','hình chữ nhật','hinh chu nhat','box'])
        && preg_match('/(?<![\\d.])' . $number . '\\s*(?:-|–|—|đến|tới|to)\\s*' . $number . '(?![\\d.])/iu', $message, $range) === 1) {
        $first = (float)str_replace(',', '.', $range[1]);
        $second = (float)str_replace(',', '.', $range[2]);
        if ($first > 0 && $second > 0 && $first !== $second) {
            $zoneType = paTrainingContainsAny($text, ['cung','supply','sell','bán','ban']) ? 'supply' : (paTrainingContainsAny($text, ['cầu','cau','demand','buy','mua']) ? 'demand' : '');
            $label = $zoneType === 'supply' ? 'VÙNG CANH SELL' : ($zoneType === 'demand' ? 'VÙNG CANH BUY' : 'VÙNG ĐÁNH DẤU');
            return ['type' => 'draw_chart_annotation', 'symbol' => paTrainingSymbol((string)($context['symbol'] ?? '')), 'timeframe' => strtoupper((string)($context['timeframe'] ?? '')), 'drawing' => ['type' => 'rect', 'priceRange' => [min($first, $second), max($first, $second)], 'zoneType' => $zoneType ?: null, 'label' => $label, 'color' => $zoneType === 'supply' ? '#ef4444' : ($zoneType === 'demand' ? '#10b981' : '#2563eb'), 'opacity' => 14]];
        }
    }
    $isHorizontal = paTrainingContainsAny($text, ['đường ngang','duong ngang','hline','kháng cự','khang cu','hỗ trợ','ho tro','mức giá','muc gia','price line']);
    if ($isHorizontal && preg_match('/(?:tại|tai|mức(?: giá)?|gia|price|hỗ trợ|ho tro|kháng cự|khang cu)\\s*(?::|là|la|at)?\\s*' . $number . '(?![\\d.])/iu', $message, $match) === 1) {
        $price = (float)str_replace(',', '.', $match[1]);
        if ($price > 0) return ['type' => 'draw_chart_annotation', 'symbol' => paTrainingSymbol((string)($context['symbol'] ?? '')), 'timeframe' => strtoupper((string)($context['timeframe'] ?? '')), 'drawing' => ['type' => 'hline', 'price' => $price, 'label' => paTrainingLimitText($message, 120), 'color' => '#2563eb']];
    }
    return null;
}

function paTrainingNormalizeAiChartActions(array $actions, array $context, array $chartAttachment): array
{
    $types = array_flip(paTrainingSupportedChartDrawingTypes());
    $symbol = paTrainingSymbol((string)($context['symbol'] ?? $chartAttachment['symbol'] ?? ''));
    $timeframe = strtoupper((string)($context['timeframe'] ?? $chartAttachment['timeframe'] ?? ''));
    $barCount = max(0, (int)($chartAttachment['barCount'] ?? 0));
    $validPrice = static fn($value): bool => is_numeric($value) && is_finite((float)$value) && (float)$value > 0 && (float)$value < 1000000000;
    $normalized = [];
    foreach (array_slice($actions, 0, 8) as $item) {
        if (!is_array($item) || (string)($item['type'] ?? '') !== 'draw_chart_annotation') continue;
        $drawing = is_array($item['drawing'] ?? null) ? $item['drawing'] : [];
        $type = (string)($drawing['type'] ?? '');
        if (!isset($types[$type])) continue;
        $safe = ['type' => $type];
        $invalidBarReference = false;
        foreach (['color','label','text','zoneType','opacity','width','direction','entry','stop','target','price'] as $key) {
            if (!array_key_exists($key, $drawing)) continue;
            if (in_array($key, ['entry','stop','target','price'], true) && !$validPrice($drawing[$key])) continue;
            if ($key === 'color' && preg_match('/^#[0-9a-f]{6}$/i', (string)$drawing[$key]) !== 1) continue;
            if ($key === 'zoneType' && !in_array($drawing[$key], ['supply','demand',null], true)) continue;
            if (in_array($key, ['label','text'], true)) $safe[$key] = paTrainingLimitText(strip_tags((string)$drawing[$key]), 160);
            elseif ($key === 'opacity') $safe[$key] = max(0, min(60, (int)$drawing[$key]));
            elseif ($key === 'width') $safe[$key] = max(1, min(6, (int)$drawing[$key]));
            elseif ($key === 'direction' && in_array($drawing[$key], ['long','short'], true)) $safe[$key] = $drawing[$key];
            elseif ($key !== 'direction') $safe[$key] = $drawing[$key];
        }
        if (isset($drawing['priceRange']) && is_array($drawing['priceRange']) && count($drawing['priceRange']) >= 2 && $validPrice($drawing['priceRange'][0]) && $validPrice($drawing['priceRange'][1])) $safe['priceRange'] = array_values(array_map('floatval', array_slice($drawing['priceRange'], 0, 2)));
        foreach (['barIndex','startBar','endBar'] as $key) {
            if (!isset($drawing[$key])) continue;
            if (!is_numeric($drawing[$key]) || $barCount < 1 || (int)$drawing[$key] < 0 || (int)$drawing[$key] >= $barCount) {
                $invalidBarReference = true;
                continue;
            }
            $safe[$key] = (int)$drawing[$key];
        }
        $points = [];
        foreach (array_slice(is_array($drawing['points'] ?? null) ? $drawing['points'] : [], 0, 8) as $point) {
            if (!is_array($point) || !$validPrice($point['price'] ?? null) || !is_numeric($point['barIndex'] ?? null) || $barCount < 1 || (int)$point['barIndex'] < 0 || (int)$point['barIndex'] >= $barCount) {
                $invalidBarReference = true;
                continue;
            }
            $points[] = ['barIndex' => (int)$point['barIndex'], 'price' => (float)$point['price']];
        }
        if ($points) $safe['points'] = $points;
        if (isset($drawing['point'])) {
            $point = $drawing['point'];
            if (is_array($point) && $validPrice($point['price'] ?? null) && is_numeric($point['barIndex'] ?? null) && $barCount > 0 && (int)$point['barIndex'] >= 0 && (int)$point['barIndex'] < $barCount) $safe['point'] = ['barIndex' => (int)$point['barIndex'], 'price' => (float)$point['price']];
            else $invalidBarReference = true;
        }
        if ($invalidBarReference) continue;
        if ($type === 'position' && (!isset($safe['entry'], $safe['stop'], $safe['target']) || !isset($safe['direction']))) continue;
        if (in_array($type, ['hline','hray','alert'], true) && !isset($safe['price'])) continue;
        if ($type === 'vline' && !isset($safe['barIndex'])) continue;
        if ($type === 'cross-line' && (!isset($safe['barIndex']) || !isset($safe['price']))) continue;
        if (in_array($type, ['text','note','price-note','callout','comment','table','price-label','dot','pin','flag','icon','arrow-marker','up-arrow','down-arrow'], true) && !isset($safe['point'])) continue;
        if (in_array($type, ['rect','rotated-rect','circle','ellipse','triangle-shape','fib-circle','fib-wedge','gann-square','gann-box','volume-profile','price-range','date-range','date-price-range','trendline','ray','extended-line','trend-angle','fib','fib-extension','fib-time-zone','fib-fan','gann-fan','forecast','bars-pattern','anchored-vwap','arc','curve','arrow','measure','parallel-channel','regression-trend','flat-top-bottom','disjoint-channel','fib-channel'], true) && count($points) < 2) continue;
        if (in_array($type, ['polyline','path','brush','highlighter','xabcd','abcd','triangle-pattern','three-drives','head-shoulders','elliott-wave','cyclic-lines','pitchfork','inside-pitchfork','schiff-pitchfork','modified-schiff'], true) && count($points) < 2) continue;
        $normalized[] = ['type' => 'draw_chart_annotation', 'symbol' => $symbol, 'timeframe' => $timeframe, 'drawing' => $safe];
    }
    return $normalized;
}

function openAiLocalSettingsStatus(): array
{
    $status = OpenAIProviderStatus::fromConfigFile(__DIR__ . '/config/ai_providers.php');
    $local = is_file(__DIR__ . '/config/local.php') ? require __DIR__ . '/config/local.php' : [];
    $provider = is_array($local['ai_providers']['providers']['openai_chat'] ?? null) ? $local['ai_providers']['providers']['openai_chat'] : [];
    $budget = is_array($provider['budget'] ?? null) ? $provider['budget'] : [];
    $apiConfigured = ($status['configured'] ?? '') === 'configured';
    $model = getenv('OPENAI_MODEL_DEFAULT') ?: (string)($provider['default_model'] ?? 'gpt-6-luna');
    $ready = ($status['enabled_status'] ?? '') === 'enabled' && $apiConfigured && ($status['model'] ?? '') === 'set';
    $budgetUsd = (float)($budget['daily_budget_usd'] ?? 1);
    return [
        'ok' => true,
        'ready' => $ready,
        'enabled' => ($status['enabled_status'] ?? '') === 'enabled',
        'status' => $status,
        'api_key_configured' => $apiConfigured,
        'api_key_label' => maskedEnv('OPENAI_API_KEY'),
        'admin_api_key_configured' => (string)(getenv('OPENAI_ADMIN_API_KEY') ?: '') !== '',
        'admin_api_key_label' => maskedEnv('OPENAI_ADMIN_API_KEY'),
        'model' => $model ?: 'gpt-6-luna',
        'daily_budget_usd' => $budgetUsd,
        'max_completion_tokens' => (int)($provider['max_completion_tokens'] ?? 1200),
        'max_calls_per_day' => (int)($budget['max_calls_per_day'] ?? 100),
        'budget' => (new OpenAIChatBudget($budget))->status($model),
        'official_costs' => [],
    ];
}

function maskedEnv(string $key): string
{
    $value = (string)(getenv($key) ?: '');
    return $value === '' ? '' : '...' . substr($value, -4);
}

function openAiLocalSettingsSave(array $payload): array
{
    $enabled = ($payload['enabled'] ?? true) !== false;
    $model = trim((string)($payload['model'] ?? 'gpt-6-luna')) ?: 'gpt-6-luna';
    $apiKey = trim((string)($payload['apiKey'] ?? ''));
    if ($apiKey !== '') updateEnvLocal('OPENAI_API_KEY', $apiKey);
    updateEnvLocal('OPENAI_MODEL_DEFAULT', $model);
    writeOpenAiLocalConfig($enabled, $model, $payload);
    $status = openAiLocalSettingsStatus();
    if ($apiKey !== '' && ($status['api_key_configured'] ?? false) !== true) {
        throw new RuntimeException('Đã ghi cấu hình nhưng máy chủ chưa nạp được OpenAI API key. Kiểm tra quyền đọc .env.local và biến OPENAI_API_KEY trên hosting.');
    }
    return $status;
}

function openAiLocalSettingsDeleteKey(): array { updateEnvLocal('OPENAI_API_KEY', ''); return openAiLocalSettingsStatus(); }
function openAiAdminKeySave(array $payload): array { $key = trim((string)($payload['adminApiKey'] ?? $payload['admin_api_key'] ?? '')); if ($key === '') throw new InvalidArgumentException('B?n chua nh?p OpenAI Admin API key.'); updateEnvLocal('OPENAI_ADMIN_API_KEY', $key); return openAiLocalSettingsStatus(); }
function openAiAdminKeyDelete(): array { updateEnvLocal('OPENAI_ADMIN_API_KEY', ''); return openAiLocalSettingsStatus(); }

function updateEnvLocal(string $key, string $value): void
{
    $path = __DIR__ . '/.env.local';
    $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    if (!is_array($lines)) $lines = [];
    $found = false;
    foreach ($lines as &$line) {
        if (str_starts_with($line, $key . '=')) {
            $line = $key . '=' . $value;
            $found = true;
        }
    }
    unset($line);
    if (!$found) $lines[] = $key . '=' . $value;
    $written = @file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('Máy chủ không ghi được .env.local. Hãy cấp quyền ghi cho PHP hoặc cấu hình OPENAI_API_KEY trong biến môi trường của hosting.');
    }
    // Make the saved value visible to status checks and provider creation in this request.
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function writeOpenAiLocalConfig(bool $enabled, string $model, array $payload): void
{
    $config = ['ai_providers' => ['default_provider' => $enabled ? 'openai_chat' : 'mock', 'providers' => ['openai_chat' => ['enabled' => $enabled, 'default_model' => $model, 'model_aliases' => ['default' => $model], 'max_completion_tokens' => max(1, (int)($payload['maxCompletionTokens'] ?? 1200)), 'budget' => ['daily_budget_usd' => max(0.01, (float)($payload['dailyBudgetUsd'] ?? 1)), 'max_calls_per_day' => max(0, (int)($payload['maxCallsPerDay'] ?? 100))]]]]];
    $written = @file_put_contents(__DIR__ . '/config/local.php', "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('Máy chủ không ghi được config/local.php. Hãy cấp quyền ghi cho PHP trên hosting.');
    }
}

function llmGatewayTestCall(array $payload): array
{
    $provider = ProviderFactory::fromConfigFile()->create('openai_chat');
    $response = $provider->complete(['messages' => [['role' => 'system', 'content' => 'B?n là ki?m tra k?t n?i OpenAI cho app AI Trade.'], ['role' => 'user', 'content' => 'Tr? l?i ng?n xác nh?n k?t n?i.']], 'model_alias' => 'default', 'response_format' => 'plain_text']);
    return ['ok' => true, 'llm_response' => $response, 'response' => $response];
}

function reviewCase(JsonStore $store, array $payload): array
{
    $record = ['case' => $payload['case'] ?? [], 'your_decision' => $payload['decision'] ?? $payload['your_decision'] ?? '', 'your_note' => $payload['your_note'] ?? '', 'stored_at' => gmdate('c')];
    $store->appendJsonl('cases/reviewed_cases.jsonl', $record);
    return ['ok' => true, 'review' => $record, 'recent_reviews' => $store->readJsonl('cases/reviewed_cases.jsonl', 25), 'stats' => $store->stats()];
}

function saveCandidateCases(JsonStore $store, array $payload): array
{
    $cases = is_array($payload['candidate_cases'] ?? null) ? $payload['candidate_cases'] : (is_array($payload['cases'] ?? null) ? $payload['cases'] : []);
    $store->writeJson('candidate_cases.json', $cases);
    return ['ok' => true, 'candidate_cases' => $cases];
}

function importMemory(JsonStore $store, array $payload): array
{
    foreach (['accounts', 'strategy', 'candidate_cases'] as $key) if (array_key_exists($key, $payload)) $store->writeJson($key . '.json', $payload[$key]);
    return ['ok' => true, 'imported' => true, 'snapshot' => $store->exportSnapshot()];
}

function genericApiPayload(string $action, array $payload, JsonStore $store): array
{
    $lists = [
        'backtest_v2_list' => ['backtest_results' => []],
        'evaluation_list' => ['evaluations' => []],
        'experiment_list' => ['experiments' => []],
        'experiment_list_items' => ['items' => []],
        'kpi_profile_list' => ['profiles' => [], 'default_profile' => null],
        'ai_job_list' => ['ai_jobs' => []],
        'auto_research_runs' => ['auto_research_runs' => $store->readJson('research/auto_research_runs.json', [])],
        'research_lab_sessions' => ['sessions' => $store->readJson('research_lab/sessions.json', []), 'research_lab_sessions' => $store->readJson('research_lab/sessions.json', [])],
        'prompt_template_list' => ['prompt_templates' => []],
        'calibration_queue' => ['queue' => []],
        'calibration_report' => ['report' => []],
        'calibration_recommendations' => ['recommendations' => []],
        'strategy_versions' => ['strategy_versions' => $store->readJson('strategy_versions.json', [])],
        'strategy_v2_list' => ['strategy_versions' => $store->readJson('strategy_versions.json', [])],
    ];
    if (isset($lists[$action])) return array_merge(['ok' => true], $lists[$action]);
    $known = ['backtest_v2_find','evaluation_find','evaluation_latest','evaluation_best','kpi_profile_set_default','kpi_profile_delete','experiment_find','experiment_best_item','experiment_create','ai_job_find','ai_job_create','ai_job_cancel','ai_worker_run_once','research_mock_run_once','auto_research_run','research_lab_create','research_lab_run_cycle','prompt_template_activate','prompt_template_archive','generate_calibrated_preset','save_manual_review','save_zone_manual_review','tradebrain_evolve','strategy_v2_create','strategy_v2_update','strategy_v2_clone','strategy_v2_archive','strategy_version_status','create_strategy_v1','data_quality','ai_job_create_strategy_draft'];
    if (in_array($action, $known, true) || str_ends_with($action, '_find')) return ['ok' => true, 'action' => $action, 'payload' => $payload, 'item' => null, 'result' => null, 'evaluation' => null, 'experiment' => null, 'ai_job' => null];
    throw new InvalidArgumentException('Unsupported API action: ' . $action);
}



