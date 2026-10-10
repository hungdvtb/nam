<?php

declare(strict_types=1);

$index = file_get_contents(__DIR__ . '/../index.php');
$app = file_get_contents(__DIR__ . '/../assets/app.js');

assertContains('data-tab="kpi-profiles"', $index, 'KPI profile navigation tab should exist');
assertContains('Cấu hình KPI', $index, 'KPI profile UI should use Vietnamese label');
assertContains('id="tab-kpi-profiles"', $index, 'KPI profile tab panel should exist');
assertContains('id="kpiProfileList"', $index, 'KPI profile list container should exist');
assertContains('id="kpiProfileForm"', $index, 'KPI profile form should exist');
assertContains('id="kpiProfileDefault"', $index, 'KPI default summary container should exist');

foreach ([
    'kpi_profile_list',
    'kpi_profile_create',
    'kpi_profile_update',
    'kpi_profile_set_default',
    'kpi_profile_delete',
] as $action) {
    assertContains($action, $app, "App should call {$action}");
}

assertContains('renderKPIProfiles', $app, 'App should render KPI profiles UI');
assertContains('selectedKPIProfileId', $app, 'App state should track selected KPI profile');

echo 'KPI profile UI contract smoke tests passed.' . PHP_EOL;

function assertContains(string $needle, string|false $haystack, string $message): void
{
    if (!is_string($haystack) || !str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}
