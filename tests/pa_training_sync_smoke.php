<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/PaTrainingSync.php';

$root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pa_training_sync_' . bin2hex(random_bytes(4));
$paDir = $root . '/pa_training';
$backupRoot = $root . '/backups';
mkdir($paDir, 0775, true);

$existing = [
    'zone_reviews' => [['id' => 'zone_old', 'createdAt' => '2026-01-01T00:00:00Z']],
    'setup_reviews' => [['id' => 'case_shared', 'createdAt' => '2026-01-01T00:00:00Z', 'note' => 'server old']],
    'lessons' => [['id' => 'lesson_server', 'createdAt' => '2026-01-02T00:00:00Z']],
    'future_field' => ['keep' => true],
];
file_put_contents($paDir . '/memory.json', json_encode($existing));
file_put_contents($paDir . '/chat_history.json', json_encode([['id' => 'msg_server', 'createdAt' => '2026-01-01T00:00:00Z']]));

$bundle = [
    'format' => 'namtrade_pa_training_v1',
    'memory' => [
        'zone_reviews' => [['id' => 'zone_local', 'createdAt' => '2026-01-03T00:00:00Z']],
        'setup_reviews' => [
            ['id' => 'case_shared', 'createdAt' => '2026-01-04T00:00:00Z', 'note' => 'local newer'],
            ['id' => 'case_local', 'createdAt' => '2026-01-03T00:00:00Z'],
        ],
        'lessons' => [['id' => 'lesson_local', 'createdAt' => '2026-01-03T00:00:00Z']],
    ],
    'manual_zones' => [['id' => 'manual_local', 'createdAt' => '2026-01-03T00:00:00Z']],
    'chat_history' => [['id' => 'msg_local', 'createdAt' => '2026-01-03T00:00:00Z']],
];

try {
    $first = PaTrainingSync::merge($root, $bundle, $backupRoot);
    if ($first['added']['setup_reviews'] !== 1 || $first['updated']['setup_reviews'] !== 1) {
        throw new RuntimeException('Sync must append new reviews and update only newer copies.');
    }
    if ($first['added']['zone_reviews'] !== 1 || $first['added']['lessons'] !== 1) {
        throw new RuntimeException('Sync did not append local PA memory.');
    }
    if (empty($first['backup'])) throw new RuntimeException('Sync did not create a pre-merge backup.');

    $merged = json_decode((string)file_get_contents($paDir . '/memory.json'), true);
    if (($merged['future_field']['keep'] ?? false) !== true) throw new RuntimeException('Unknown server memory fields were lost.');
    $shared = array_values(array_filter($merged['setup_reviews'], static fn(array $item): bool => $item['id'] === 'case_shared'))[0] ?? [];
    if (($shared['note'] ?? '') !== 'local newer') throw new RuntimeException('The newer copy of an existing record was not retained.');

    $second = PaTrainingSync::merge($root, $bundle, $backupRoot);
    if (array_sum($second['added']) + array_sum($second['updated']) !== 0) {
        throw new RuntimeException('Repeated sync must be idempotent.');
    }
} finally {
    foreach (glob($backupRoot . '/*/*') ?: [] as $path) @unlink($path);
    foreach (glob($backupRoot . '/*') ?: [] as $path) @rmdir($path);
    foreach (glob($paDir . '/*') ?: [] as $path) @unlink($path);
    @rmdir($paDir);
    @rmdir($backupRoot);
    @rmdir($root);
}

$rollbackRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pa_training_sync_rollback_' . bin2hex(random_bytes(4));
$rollbackPaDir = $rollbackRoot . '/pa_training';
mkdir($rollbackPaDir, 0775, true);
$originalMemory = json_encode(['zone_reviews' => [], 'setup_reviews' => [], 'lessons' => []]);
file_put_contents($rollbackPaDir . '/memory.json', $originalMemory);
mkdir($rollbackPaDir . '/manual_zones.json');

try {
    try {
        PaTrainingSync::merge($rollbackRoot, $bundle, $rollbackRoot . '/backups');
        throw new RuntimeException('Sync should fail when a destination path cannot be replaced.');
    } catch (RuntimeException $error) {
        if (!str_contains($error->getMessage(), 'đã khôi phục')) throw $error;
    }
    if (file_get_contents($rollbackPaDir . '/memory.json') !== $originalMemory) {
        throw new RuntimeException('A partial sync failure did not restore the original memory file.');
    }
} finally {
    foreach (glob($rollbackPaDir . '/*.tmp') ?: [] as $path) @unlink($path);
    @rmdir($rollbackPaDir . '/manual_zones.json');
    foreach (glob($rollbackRoot . '/backups/*/*') ?: [] as $path) @unlink($path);
    foreach (glob($rollbackRoot . '/backups/*') ?: [] as $path) @rmdir($path);
    @rmdir($rollbackRoot . '/backups');
    foreach (glob($rollbackPaDir . '/*') ?: [] as $path) if (is_file($path)) @unlink($path);
    @rmdir($rollbackPaDir);
    @rmdir($rollbackRoot);
}

echo "pa_training_sync_smoke passed\n";
