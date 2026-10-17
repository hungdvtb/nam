<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/lib/PaTrainingSync.php';

$inputPath = (string)($argv[1] ?? '');
$realPath = $inputPath !== '' ? realpath($inputPath) : false;
$tempRoot = realpath(sys_get_temp_dir());
if ($realPath === false || $tempRoot === false || !str_starts_with($realPath, $tempRoot . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
    fwrite(STDERR, "Expected a sync JSON file in the system temp directory.\n");
    exit(2);
}
if ((filesize($realPath) ?: 0) > 100 * 1024 * 1024) {
    fwrite(STDERR, "PA sync bundle exceeds the 100 MB safety limit.\n");
    exit(2);
}

$raw = file_get_contents($realPath);
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    fwrite(STDERR, "PA sync bundle is not valid JSON.\n");
    exit(2);
}

try {
    $result = PaTrainingSync::merge(dirname(__DIR__) . '/data', $payload);
    fwrite(STDOUT, json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
