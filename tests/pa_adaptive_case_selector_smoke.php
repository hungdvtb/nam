<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/PaAdaptiveCaseSelector.php';
define('AI_TRADE_API_LIBRARY_ONLY', true);
require_once __DIR__ . '/../api.php';

function checkAdaptive(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

function adaptiveCase(string $id, int $day, string $direction, int $strength, int $touches): array
{
    return [
        'id' => $id,
        'isTeachingCase' => true,
        'symbol' => 'AUDUSD',
        'direction' => $direction,
        'entryTime' => sprintf('2026-01-%02dT10:00:00+00:00', $day),
        'entryPrice' => 1.1,
        'initialStop' => $direction === 'short' ? 1.102 : 1.098,
        'zoneLow' => 1.099,
        'zoneHigh' => 1.101,
        'zoneOriginIndex' => 1,
        'anchorIndex' => 9,
        'zoneTouchCount' => $touches,
        'signalBodyFraction' => $strength >= 80 ? 0.8 : 0.2,
        'rr' => 3.0,
        'zone' => ['strengthScore' => $strength, 'originIndex' => 1, 'firstTouchIndex' => 9],
        'exitType' => 'SL',
        'netR' => -1.0,
    ];
}

function adaptiveReview(array $case, string $decision, string $note = ''): array
{
    return [
        'id' => 'review_' . $case['id'],
        'targetId' => $case['id'],
        'symbol' => $case['symbol'],
        'decision' => $decision,
        'label' => $decision,
        'note' => $note,
        'snapshot' => ['originalTrade' => $case, 'trade' => $case, 'zone' => $case['zone']],
    ];
}

$c1 = adaptiveCase('c1', 1, 'long', 60, 4);
$c2 = adaptiveCase('c2', 2, 'long', 60, 4);
$c3 = adaptiveCase('c3', 3, 'short', 90, 1);
$c4 = adaptiveCase('c4', 4, 'short', 90, 1);
$c5 = adaptiveCase('c5', 5, 'long', 60, 4);
$cases = [$c1, $c2, $c3, $c4, $c5];

$initial = PaAdaptiveCaseSelector::select($cases, ['setup_reviews' => []], 1);
checkAdaptive($initial['cases'][0]['id'] === 'c1', 'first teaching case must be chronological');
$skipped = PaAdaptiveCaseSelector::select($cases, ['setup_reviews' => []], 1, ['c1', 'c2']);
checkAdaptive($skipped['cases'][0]['id'] === 'c3', 'skipped cases must not reappear in the active queue');
$restored = PaAdaptiveCaseSelector::select($cases, ['setup_reviews' => []], 1, [], 'c3');
checkAdaptive($restored['cases'][0]['id'] === 'c3', 'refresh must restore the selected teaching case');
$negative = adaptiveReview($c1, 'bad_case', 'Bỏ vùng chạm nhiều lần');
$afterFirst = PaAdaptiveCaseSelector::select($cases, ['setup_reviews' => [$negative]], 1);
checkAdaptive($afterFirst['cases'][0]['id'] === 'c3', 'negative feedback must change next-case selection');
checkAdaptive($afterFirst['cases'][0]['learning']['taughtCount'] === 1, 'prior feedback must reach the ranking model');
checkAdaptive(!in_array($afterFirst['cases'][0]['id'], ['c1', 'c2'], true), 'similar rejected case must not remain first');
$restoredReviewed = PaAdaptiveCaseSelector::select($cases, ['setup_reviews' => [$negative]], 1, [], 'c1');
checkAdaptive($restoredReviewed['cases'][0]['id'] === 'c1' && $restoredReviewed['cases'][0]['review']['id'] === 'review_c1', 'refresh must restore a reviewed case without losing its feedback');

$positive = adaptiveReview($c3, 'good_case', 'Chọn nến xác nhận mạnh');
$afterSecond = PaAdaptiveCaseSelector::select($cases, ['setup_reviews' => [$positive, $negative]], 1);
checkAdaptive($afterSecond['cases'][0]['id'] === 'c4', 'second feedback must affect the following recommendation');
checkAdaptive($afterSecond['cases'][0]['learning']['taughtCount'] === 2, 'both earlier reviews must be considered');
checkAdaptive(str_contains($afterSecond['cases'][0]['learning']['reason'], '2 ca'), 'recommendation must explain its evidence count');

$futureReview = adaptiveReview($c5, 'good_case');
$futureOnly = PaAdaptiveCaseSelector::select([$c1, $c2], ['setup_reviews' => [$futureReview]], 2);
checkAdaptive($futureOnly['cases'] === [], 'historical queue must not move backward after a later taught case');

$changedOutcome = $c4;
$changedOutcome['exitType'] = 'TP';
$changedOutcome['netR'] = 3.0;
checkAdaptive(PaAdaptiveCaseSelector::features($c4) === PaAdaptiveCaseSelector::features($changedOutcome), 'future outcome must not be a model feature');

$manyReviews = [];
for ($i = 1; $i <= 200; $i++) {
    $past = adaptiveCase('old_' . $i, 1, 'short', 90, 1);
    $past['entryTime'] = gmdate('c', strtotime('2025-01-01T10:00:00+00:00') + $i * 86400);
    $manyReviews[] = adaptiveReview($past, 'good_case');
}
$many = PaAdaptiveCaseSelector::select([$c4], ['setup_reviews' => $manyReviews], 1);
checkAdaptive($many['cases'][0]['learning']['taughtCount'] === 200, 'ranking must use all 200 prior teaching cases');

$temp = sys_get_temp_dir() . '/pa-adaptive-' . bin2hex(random_bytes(6));
$store = new JsonStore($temp, require __DIR__ . '/../config/defaults.php');
try {
    paTrainingFeedbackSave($store, [
        'kind' => 'setup', 'targetId' => $c1['id'], 'symbol' => 'AUDUSD', 'timeframe' => 'M15',
        'decision' => 'bad_case', 'label' => 'Bỏ ca này', 'note' => 'Bỏ vùng chạm nhiều lần',
        'snapshot' => ['originalTrade' => $c1, 'trade' => $c1, 'zone' => $c1['zone']],
    ]);
    $persisted = PaAdaptiveCaseSelector::select($cases, paTrainingMemory($store), 1);
    checkAdaptive($persisted['cases'][0]['id'] === 'c3', 'saved feedback must change the next recommendation');
    checkAdaptive(count(paTrainingMemory($store)['lessons']) === 1, 'review note must remain in teaching memory');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($temp);
}

echo "pa_adaptive_case_selector_smoke passed\n";
