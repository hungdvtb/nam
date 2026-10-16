<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/JsonStore.php';
require_once __DIR__ . '/lib/AI/Gateway/ProviderFactory.php';
require_once __DIR__ . '/lib/AI/Gateway/OpenAIProviderStatus.php';
require_once __DIR__ . '/lib/AI/Gateway/OllamaProviderStatus.php';

header('Content-Type: application/json; charset=utf-8');

$defaults = require __DIR__ . '/config/defaults.php';
$store = new JsonStore(__DIR__ . '/data', $defaults);
$action = trim((string)($_GET['action'] ?? ''));

try {
    if ($action === '') {
        throw new InvalidArgumentException('Missing API action.');
    }
    $payload = apiPayload();
    apiJson(apiHandle($action, $payload, $store, $defaults));
} catch (Throwable $e) {
    http_response_code(400);
    apiJson(['ok' => false, 'error' => $e->getMessage()]);
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
        'mt5_status' => ['ok' => true, 'mt5' => ['ok' => false, 'error' => 'MT5 bridge chua ch?y trong b?n API khôi ph?c. D? li?u da luu v?n dùng du?c.']],
        'mt5_fetch' => ['ok' => true, 'written' => [], 'market_files' => marketFiles()],
        'pa_training_zone_candidates' => paTrainingZoneCandidatesAction($store, $payload),
        'pa_training_trade_cases' => paTrainingTradeCases($store, $payload),
        'pa_training_feedback_save' => paTrainingFeedbackSave($store, $payload),
        'pa_training_ai_chat' => paTrainingAiChat($store, $payload),
        'pa_training_chat_history' => paTrainingChatHistoryAction($store, $payload),
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
        $data = json_decode(file_get_contents($path) ?: '', true);
        if (is_array($data)) {
            $item['symbol'] = strtoupper((string)($data['symbol'] ?? $item['symbol']));
            $item['timeframe'] = strtoupper((string)($data['timeframe'] ?? $item['timeframe']));
            $item['bars'] = is_array($data['bars'] ?? null) ? count($data['bars']) : 0;
            $item['fetched_at'] = (string)($data['fetched_at'] ?? '');
        }
        $items[] = $item;
    }
    usort($items, static fn(array $a, array $b): int => [$a['symbol'], $a['timeframe']] <=> [$b['symbol'], $b['timeframe']]);
    return $items;
}

function marketData(array $payload): array
{
    $file = str_replace(['..', '/', '\\'], '', (string)($payload['file'] ?? ''));
    if ($file === '') {
        $file = paTrainingSymbol((string)($payload['symbol'] ?? '')) . '_' . strtoupper((string)($payload['timeframe'] ?? 'H4')) . '.json';
    }
    $path = __DIR__ . '/data/market_data/' . $file;
    if (!is_file($path)) throw new InvalidArgumentException('Không tìm th?y d? li?u chart: ' . $file);
    $data = json_decode(file_get_contents($path) ?: '', true);
    if (!is_array($data)) throw new RuntimeException('File d? li?u chart l?i: ' . $file);
    return ['ok' => true, 'market_data' => normalizeMarketData($data)];
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

function paTrainingMemory(JsonStore $store): array
{
    $m = $store->readJson('pa_training/memory.json', []);
    return [
        'zone_reviews' => array_values(is_array($m['zone_reviews'] ?? null) ? $m['zone_reviews'] : []),
        'setup_reviews' => array_values(is_array($m['setup_reviews'] ?? null) ? $m['setup_reviews'] : []),
        'lessons' => array_values(is_array($m['lessons'] ?? null) ? $m['lessons'] : []),
    ];
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

function paTrainingZoneCandidates(JsonStore $store, string $symbol, string $timeframe = 'H4'): array
{
    $zones = [];
    $path = __DIR__ . '/data/market_data/' . $symbol . '_' . $timeframe . '.json';
    if (is_file($path)) {
        $data = normalizeMarketData(json_decode(file_get_contents($path) ?: '', true) ?: []);
        $bars = is_array($data['bars'] ?? null) ? $data['bars'] : [];
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
        for ($i = 8; $i < $count - 8 && count($zones) < 220; $i++) {
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
    foreach (paTrainingManualZones($store) as $zone) {
        if (paTrainingSymbol((string)($zone['symbol'] ?? '')) === $symbol) $zones[] = $zone;
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
    $path = __DIR__ . '/data/market_data/' . $symbol . '_' . $tf . '.json';
    if (is_file($path)) {
        $data = normalizeMarketData(json_decode(file_get_contents($path) ?: '', true) ?: []);
        $bars = is_array($data['bars'] ?? null) ? $data['bars'] : [];
    }
    $cases = [];
    foreach (array_slice($zones, 0, $limit) as $i => $zone) {
        $case = paTrainingBuildTeachingCase($zone, $bars, $i);
        if ($case) $cases[] = $case;
    }
    usort($cases, static fn(array $a, array $b): int => ((int)($a['entryIndex'] ?? 0)) <=> ((int)($b['entryIndex'] ?? 0)));
    return [
        'ok' => true,
        'zone_candidates' => $zones,
        'trade_cases' => $cases,
        'selected_case_id' => $cases[0]['id'] ?? '',
        'case_summary' => ['h4_bars' => count($bars), 'cases' => count($cases)],
        'pa_training_memory' => paTrainingMemory($store),
    ];
}

function paTrainingBuildTeachingCase(array $zone, array $bars, int $index): ?array
{
    $symbol = paTrainingSymbol((string)($zone['symbol'] ?? ''));
    if ($symbol === '') return null;
    $direction = ($zone['type'] ?? '') === 'supply' ? 'short' : 'long';
    $barCount = count($bars);
    $anchorIndex = min(max(0, (int)($zone['firstTouchIndex'] ?? $index * 12 + 2)), max(0, $barCount - 1));
    $originIndex = min(max(0, (int)($zone['originIndex'] ?? max(0, $anchorIndex - 2))), max(0, $barCount - 1));
    $anchorBar = $bars[$anchorIndex] ?? [];
    $originBar = $bars[$originIndex] ?? $anchorBar;
    $anchorTime = (string)($zone['firstTouchTime'] ?? $anchorBar['time'] ?? $zone['originCandleTime'] ?? '');
    $low = (float)($zone['low'] ?? ($originBar['low'] ?? 0));
    $high = (float)($zone['high'] ?? ($originBar['high'] ?? 0));
    if (!is_finite($low) || !is_finite($high) || $high <= $low) return null;
    $entry = ($low + $high) / 2;
    $zoneHeight = max(abs($high - $low), max(abs($entry) * 0.00035, 0.00001));
    $sl = $direction === 'short' ? $high + $zoneHeight * 0.28 : $low - $zoneHeight * 0.28;
    $risk = max(abs($entry - $sl), max(abs($entry) * 0.0005, 0.00001));
    $rr = 2.5;
    $tp = $direction === 'short' ? $entry - $risk * $rr : $entry + $risk * $rr;
    $exit = paTrainingTeachingCaseExit($bars, $anchorIndex, $direction, $sl, $tp, $entry);
    $exitIndex = (int)($exit['index'] ?? $anchorIndex);
    $exitPrice = (float)($exit['price'] ?? $entry);
    $netR = $direction === 'short' ? ($entry - $exitPrice) / $risk : ($exitPrice - $entry) / $risk;
    $zoneLabel = $direction === 'short' ? 'Vùng canh sell' : 'Vùng canh buy';
    $sideLabel = $direction === 'short' ? 'Bán' : 'Mua';
    $rangeText = number_format($low, 5, '.', '') . ' - ' . number_format($high, 5, '.', '');
    return [
        'id' => 'case_' . (string)($zone['id'] ?? $index) . '_' . $anchorIndex,
        'isTeachingCase' => true,
        'caseStatus' => 'open',
        'symbol' => $symbol,
        'timeframe' => (string)($zone['timeframe'] ?? 'H4'),
        'direction' => $direction,
        'anchorTime' => $anchorTime,
        'anchorIndex' => $anchorIndex,
        'entryIndex' => $anchorIndex,
        'entryCandleNumber' => $anchorIndex + 1,
        'entryTime' => $anchorTime,
        'entryPrice' => $entry,
        'initialStop' => $sl,
        'targetPrice' => $tp,
        'exitIndex' => $exitIndex,
        'exitCandleNumber' => $exitIndex + 1,
        'exitTime' => (string)($bars[$exitIndex]['time'] ?? $anchorTime),
        'exitPrice' => $exitPrice,
        'exitType' => (string)($exit['type'] ?? 'time_stop'),
        'netR' => round($netR, 2),
        'riskR' => $risk,
        'rr' => $rr,
        'holdingBars' => max(0, $exitIndex - $anchorIndex),
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
        'reason' => $zoneLabel . ': giá quay lại vùng ' . $rangeText . '. ' . $sideLabel . ' tại giữa vùng, SL đặt ngoài vùng, TP ' . $rr . 'R để bạn chấm như đang backtest.',
        'zoneReason' => (string)($zone['reason'] ?? ($zoneLabel . ' lấy từ vùng phản ứng H4.')),
        'entryReason' => 'Nến #' . ($anchorIndex + 1) . ' quay lại vùng ' . $rangeText . ', app đặt entry giả lập ở giữa vùng để bạn chấm.',
        'riskReason' => 'SL nằm ngoài biên vùng; TP theo RR cố định để so sánh từng ca cho nhất quán.',
        'exitReason' => paTrainingTeachingExitReason((string)($exit['type'] ?? 'time_stop'), (float)$netR),
        'annotations' => [
            'zoneLabel' => strtoupper($zoneLabel),
            'entryLabel' => 'ENTRY',
            'slLabel' => 'SL',
            'tpLabel' => 'TP',
            'exitLabel' => strtoupper((string)($exit['type'] ?? 'time_stop')),
        ],
    ];
}

function paTrainingTeachingCaseExit(array $bars, int $entryIndex, string $direction, float $sl, float $tp, float $entry): array
{
    if (!count($bars)) return ['index' => 0, 'price' => $entry, 'type' => 'time_stop'];
    $last = min(count($bars) - 1, $entryIndex + 96);
    for ($i = $entryIndex + 1; $i <= $last; $i++) {
        $high = (float)($bars[$i]['high'] ?? 0);
        $low = (float)($bars[$i]['low'] ?? 0);
        if ($direction === 'long') {
            if ($low <= $sl) return ['index' => $i, 'price' => $sl, 'type' => 'SL'];
            if ($high >= $tp) return ['index' => $i, 'price' => $tp, 'type' => 'TP'];
        } else {
            if ($high >= $sl) return ['index' => $i, 'price' => $sl, 'type' => 'SL'];
            if ($low <= $tp) return ['index' => $i, 'price' => $tp, 'type' => 'TP'];
        }
    }
    $close = (float)($bars[$last]['close'] ?? $entry);
    return ['index' => $last, 'price' => $close, 'type' => 'time_stop'];
}

function paTrainingTeachingExitReason(string $type, float $netR): string
{
    if ($type === 'TP') return 'Giá chạm TP trước, ca đang thắng theo mô phỏng.';
    if ($type === 'SL') return 'Giá chạm SL trước, cần xem lại vùng, entry hoặc cách đặt SL.';
    return 'Hết đoạn quan sát mà chưa chạm TP/SL, app thoát theo thời gian để bạn chấm tiếp.';
}
function paTrainingFeedbackSave(JsonStore $store, array $payload): array
{
    $kind = (string)($payload['kind'] ?? 'setup');
    $snapshot = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : [];
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    if (strlen($json) > 500000) {
        $snapshot = [
            'preview' => true,
            'truncated' => true,
            'original_size' => strlen($json),
            'summary' => [
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
    $m = paTrainingMemory($store);
    if ($kind === 'zone') array_unshift($m['zone_reviews'], $review); else array_unshift($m['setup_reviews'], $review);
    $lesson = paTrainingLessonFromFeedback($review);
    if ($lesson) array_unshift($m['lessons'], $lesson);
    $m['zone_reviews'] = array_slice($m['zone_reviews'], 0, 2000);
    $m['setup_reviews'] = array_slice($m['setup_reviews'], 0, 2000);
    $m['lessons'] = array_slice($m['lessons'], 0, 1000);
    paTrainingSaveMemory($store, $m);
    return ['ok' => true, 'review' => $review, 'pa_training_memory' => $m];
}

function paTrainingLessonFromFeedback(array $review): ?array
{
    $note = trim((string)($review['note'] ?? ''));
    if ($note === '') return null;
    return [
        'id' => 'pa_lesson_' . time() . '_' . bin2hex(random_bytes(3)),
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
    $m = paTrainingStoreLessons($store, paTrainingMemory($store), [$lesson]);
    return ['ok' => true, 'lesson' => $lesson, 'pa_training_memory' => $m];
}

function paTrainingDraftLesson(string $text, ?array $zone, ?array $trade, array $context, string $kind, string $title = '', string $tags = '', string $fallbackTitle = 'Bài h?c PA'): array
{
    $symbol = paTrainingSymbol((string)($context['symbol'] ?? $trade['symbol'] ?? $zone['symbol'] ?? ''));
    $tf = strtoupper((string)($context['timeframe'] ?? $trade['timeframe'] ?? $zone['timeframe'] ?? 'H4'));
    return ['id' => 'pa_lesson_' . time() . '_' . bin2hex(random_bytes(3)), 'kind' => $kind, 'title' => $title ?: $fallbackTitle, 'content' => $text, 'symbol' => $symbol, 'timeframe' => $tf, 'tags' => array_values(array_filter([$kind, $symbol ? 'symbol:' . strtolower($symbol) : '', $tf ? 'tf:' . strtolower($tf) : '', $tags])), 'confidence' => 4, 'createdAt' => gmdate('c')];
}

function paTrainingStoreLessons(JsonStore $store, array $m, array $lessons): array
{
    foreach ($lessons as $lesson) if (is_array($lesson)) array_unshift($m['lessons'], $lesson);
    $m['lessons'] = array_slice(array_values($m['lessons']), 0, 1000);
    paTrainingSaveMemory($store, $m);
    return $m;
}

function paTrainingRelevantLessons(array $memory, array $context, ?array $zone, ?array $trade, int $limit = 8): array
{
    $lessons = is_array($memory['lessons'] ?? null) ? $memory['lessons'] : [];
    $symbol = paTrainingSymbol((string)($context['symbol'] ?? $trade['symbol'] ?? $zone['symbol'] ?? ''));
    $tf = strtoupper((string)($context['timeframe'] ?? $trade['timeframe'] ?? $zone['timeframe'] ?? ''));
    foreach ($lessons as &$lesson) {
        $score = (int)($lesson['confidence'] ?? 1);
        if ($symbol && paTrainingSymbol((string)($lesson['symbol'] ?? '')) === $symbol) $score += 8;
        if ($tf && strtoupper((string)($lesson['timeframe'] ?? '')) === $tf) $score += 4;
        $lesson['_score'] = $score;
    }
    unset($lesson);
    usort($lessons, static fn(array $a, array $b): int => ((int)($b['_score'] ?? 0)) <=> ((int)($a['_score'] ?? 0)));
    return array_slice($lessons, 0, $limit);
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

function paTrainingAppendChatMessages(JsonStore $store, array $new): array
{
    $messages = $store->readJson('pa_training/chat_history.json', []);
    if (!is_array($messages)) $messages = [];
    foreach ($new as $message) if (is_array($message)) $messages[] = $message;
    $messages = array_slice($messages, -5000);
    $store->writeJson('pa_training/chat_history.json', $messages);
    return $messages;
}

function paTrainingChatMessagesForScope(array $messages, array $scope): array
{
    $caseScopedMessages = ($scope['caseId'] ?? '') !== '' ? $messages : [];
    if (($scope['caseId'] ?? '') !== '') {
        return array_values(array_filter($caseScopedMessages, static function (array $m) use ($scope): bool {
            $id = (string)($m['tradeId'] ?? $m['context']['selectedTeachingCaseId'] ?? $m['context']['selectedTradeId'] ?? '');
            return $id === (string)$scope['caseId'];
        }));
    }
    return array_values(array_filter($messages, static function (array $m) use ($scope): bool {
        $symbol = paTrainingSymbol((string)($m['symbol'] ?? $m['context']['symbol'] ?? ''));
        $tf = strtoupper((string)($m['timeframe'] ?? $m['context']['timeframe'] ?? ''));
        return (($scope['symbol'] ?? '') === '' || $symbol === '' || $symbol === $scope['symbol']) && (($scope['timeframe'] ?? '') === '' || $tf === '' || $tf === $scope['timeframe']);
    }));
}

function paTrainingAiChat(JsonStore $store, array $payload): array
{
    $message = trim(paTrainingLimitText((string)($payload['message'] ?? ''), 2000));
    if ($message === '') throw new InvalidArgumentException('B?n chua nh?p n?i dung d? h?i AI.');
    $history = is_array($payload['history'] ?? null) ? $payload['history'] : [];
    $zone = is_array($payload['zone'] ?? null) ? $payload['zone'] : null;
    $trade = is_array($payload['trade'] ?? null) ? $payload['trade'] : null;
    $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
    $memory = paTrainingMemory($store);
    $actions = paTrainingChatActions($message, $context, $history);
    $lessons = paTrainingRelevantLessons($memory, $context, $zone, $trade, 8);
    $ai = paTrainingOpenAICoachReply($message, $zone, $trade, $actions, $memory, $context, $history, $lessons);
    $provider = is_array($ai['provider'] ?? null) ? $ai['provider'] : ['used' => 'openai_chat', 'status' => 'openai_failed', 'error' => 'OpenAI không tr? l?i du?c.'];
    $reply = ($ai['ok'] ?? false) === true ? (string)$ai['reply'] : paTrainingProviderErrorReply($provider, $actions);
    if (paTrainingShouldSaveChatLesson($message, $actions)) {
        $memory = paTrainingStoreLessons($store, $memory, [paTrainingDraftLesson($message, $zone, $trade, $context, 'chat_lesson', '', '', 'Bài h?c t? chat')]);
        $lessons = paTrainingRelevantLessons($memory, $context, $zone, $trade, 8);
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

function paTrainingOpenAICoachReply(string $message, ?array $zone, ?array $trade, array $actions, array $memory, array $context = [], array $history = [], array $relevantLessons = []): array
{
    try {
        $provider = ProviderFactory::fromConfigFile()->create('openai_chat');
        $health = $provider->health();
        if (($health['ok'] ?? false) !== true) {
            return ['ok' => false, 'provider' => ['used' => 'openai_chat', 'status' => (string)($health['status'] ?? 'not_ready'), 'error' => paTrainingProviderStatusText((string)($health['status'] ?? 'not_ready'))]];
        }
        $messages = [[
            'role' => 'system',
            'content' => "B?n là AI Coach trong app d?y Price Action Forex H4/M15. Tr? l?i ti?ng Vi?t ng?n, th?c t?. N?u user ra l?nh thao tác, xác nh?n thao tác app s? ch?y b?ng action n?i b?. Không h?a d?t l?nh th?t.",
        ]];
        foreach (array_slice($history, -12) as $item) {
            if (!is_array($item)) continue;
            $role = in_array(($item['role'] ?? ''), ['user', 'assistant'], true) ? (string)$item['role'] : 'user';
            $content = trim((string)($item['content'] ?? ''));
            if ($content !== '') $messages[] = ['role' => $role, 'content' => paTrainingLimitText($content, 1200)];
        }
        $messages[] = ['role' => 'user', 'content' => json_encode([
            'user_message' => $message,
            'chart_context' => $context,
            'selected_zone' => $zone,
            'selected_trade_case' => $trade,
            'app_actions_detected' => $actions,
            'relevant_lessons' => $relevantLessons,
            'memory_counts' => ['zone_reviews' => count($memory['zone_reviews'] ?? []), 'setup_reviews' => count($memory['setup_reviews'] ?? []), 'lessons' => count($memory['lessons'] ?? [])],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $message];
        $response = $provider->complete(['messages' => $messages, 'model_alias' => 'default', 'response_format' => 'plain_text']);
        if (($response['ok'] ?? false) !== true) {
            return ['ok' => false, 'provider' => ['used' => 'openai_chat', 'status' => (string)($response['error']['category'] ?? 'openai_failed'), 'error' => (string)($response['error']['message'] ?? 'OpenAI không tr? l?i du?c.')]];
        }
        return ['ok' => true, 'reply' => trim((string)($response['text'] ?? '')), 'provider' => ['used' => 'openai_chat', 'status' => 'ok', 'model' => (string)($response['model_name'] ?? '')]];
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
    if (!preg_match('/\b([A-Z]{3})[\s_\/.-]?([A-Z]{3})\b/i', $message, $m)) return '';
    $known = ['AUD','CAD','CHF','EUR','GBP','JPY','NZD','USD','XAU','XAG','BTC','ETH'];
    if (!in_array(strtoupper($m[1]), $known, true) || !in_array(strtoupper($m[2]), $known, true)) return '';
    return paTrainingSymbol($m[1] . $m[2]);
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
    $wantsOpen = paTrainingContainsAny($text, ['m?','mo','xem','chart','bi?u d?','bieu do','chuy?n','chuyen','d?i','doi','sang']);
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
    return openAiLocalSettingsStatus();
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
    file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
}

function writeOpenAiLocalConfig(bool $enabled, string $model, array $payload): void
{
    $config = ['ai_providers' => ['default_provider' => $enabled ? 'openai_chat' : 'mock', 'providers' => ['openai_chat' => ['enabled' => $enabled, 'default_model' => $model, 'model_aliases' => ['default' => $model], 'max_completion_tokens' => max(1, (int)($payload['maxCompletionTokens'] ?? 1200)), 'budget' => ['daily_budget_usd' => max(0.01, (float)($payload['dailyBudgetUsd'] ?? 1)), 'max_calls_per_day' => max(0, (int)($payload['maxCallsPerDay'] ?? 100))]]]]];
    file_put_contents(__DIR__ . '/config/local.php', "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
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



