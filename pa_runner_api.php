<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/PaRunnerCoordinator.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function runnerReply(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function runnerSetting(string $name): string
{
    $value = trim((string)(getenv($name) ?: ''));
    if ($value !== '') {
        return $value;
    }
    $path = __DIR__ . '/.env.local';
    if (!is_readable($path)) {
        return '';
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^\s*' . preg_quote($name, '/') . '\s*=\s*(.*?)\s*$/', $line, $matches)) {
            return trim($matches[1], " \t\r\n\"'");
        }
    }
    return '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    runnerReply(405, ['ok' => false, 'error' => 'POST required.']);
}

$secret = runnerSetting('PA_RUNNER_TOKEN');
if (strlen($secret) < 32) {
    runnerReply(503, ['ok' => false, 'error' => 'Runner is not configured.']);
}
$authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer (\S+)$/', $authorization, $matches) || !hash_equals($secret, $matches[1])) {
    runnerReply(401, ['ok' => false, 'error' => 'Unauthorized.']);
}

try {
    $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Invalid JSON payload.');
    }
    $stateDir = runnerSetting('PA_RUNNER_STATE_DIR');
    $coordinator = new PaRunnerCoordinator($stateDir !== '' ? $stateDir : __DIR__ . '/data/pa_runner');
    $host = (string)($payload['host'] ?? '');
    $instance = (string)($payload['instance'] ?? '');
    $epoch = (int)($payload['epoch'] ?? 0);
    $response = match ((string)($payload['action'] ?? '')) {
        'heartbeat' => $coordinator->heartbeat($host, $instance, (string)($payload['account'] ?? '')),
        'release' => $coordinator->release($host, $instance, $epoch),
        'claim_closed_bar' => $coordinator->claimClosedBar($host, $instance, $epoch, (string)($payload['symbol'] ?? ''), (int)($payload['bar_open'] ?? 0)),
        'status' => $coordinator->status(),
        default => throw new InvalidArgumentException('Unknown runner action.'),
    };
    runnerReply(200, $response);
} catch (JsonException | InvalidArgumentException $error) {
    runnerReply(400, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('PA runner API: ' . $error->getMessage());
    runnerReply(409, ['ok' => false, 'error' => 'Runner state unavailable or account mismatch.']);
}
