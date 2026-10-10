<?php

declare(strict_types=1);

$index = file_get_contents(__DIR__ . '/../index.php');
$app = file_get_contents(__DIR__ . '/../assets/app.js');

assertContains('data-tab="experiments"', $index, 'Experiment navigation tab should exist');
assertContains('Thí nghiệm AI', $index, 'Experiment UI should use Vietnamese navigation label');
assertContains('id="tab-experiments"', $index, 'Experiment tab panel should exist');
assertContains('id="experimentCreateForm"', $index, 'Experiment create form should exist');
assertContains('id="experimentList"', $index, 'Experiment list container should exist');
assertContains('id="experimentItems"', $index, 'Experiment items container should exist');
assertContains('id="experimentBestItem"', $index, 'Experiment best item container should exist');

foreach ([
    'experiment_list',
    'experiment_find',
    'experiment_create',
    'experiment_start',
    'experiment_pause',
    'experiment_complete',
    'experiment_archive',
    'experiment_list_items',
    'experiment_best_item',
] as $action) {
    assertContains($action, $app, "App should call {$action}");
}

assertContains('renderExperiments', $app, 'App should render Experiment Manager UI');
assertContains('selectedExperimentId', $app, 'App state should track the selected experiment');

echo 'Experiment UI contract smoke tests passed.' . PHP_EOL;

function assertContains(string $needle, string|false $haystack, string $message): void
{
    if (!is_string($haystack) || !str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}
