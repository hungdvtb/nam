<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$outputPath = (string)($argv[1] ?? '');
if ($outputPath === '') {
    fwrite(STDERR, "Usage: php export_pa_training_sync.php <output.json>\n");
    exit(2);
}

$paDir = dirname(__DIR__) . '/data/pa_training';
$readJson = static function (string $name, array $fallback) use ($paDir): array {
    $path = $paDir . '/' . $name;
    if (!is_file($path)) return $fallback;
    $raw = file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) throw new RuntimeException('Dữ liệu PA local không hợp lệ: ' . $name);
    return $decoded;
};

$payload = [
    'format' => 'namtrade_pa_training_v1',
    'exported_at' => gmdate('c'),
    'memory' => $readJson('memory.json', ['zone_reviews' => [], 'setup_reviews' => [], 'lessons' => []]),
    'manual_zones' => $readJson('manual_zones.json', []),
    'chat_history' => $readJson('chat_history.json', []),
];
$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false || file_put_contents($outputPath, $json, LOCK_EX) === false) {
    fwrite(STDERR, "Could not write PA sync bundle.\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Prepared PA data: %d zone reviews, %d setup reviews, %d lessons, %d manual zones, %d chat messages.\n",
    count($payload['memory']['zone_reviews'] ?? []),
    count($payload['memory']['setup_reviews'] ?? []),
    count($payload['memory']['lessons'] ?? []),
    count($payload['manual_zones']),
    count($payload['chat_history'])
));
