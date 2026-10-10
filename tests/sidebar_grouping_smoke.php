<?php

declare(strict_types=1);

$index = file_get_contents(__DIR__ . '/../index.php');

foreach ([
    '1. Mục tiêu',
    '2. Dữ liệu',
    '3. Chiến lược',
    '4. Backtest',
    '5. AI Lab',
    '6. Tối ưu nâng cao',
    '7. Dạy & bộ nhớ AI',
    '8. Nâng cao',
] as $groupTitle) {
    assertContains($groupTitle, $index, "Sidebar should include group {$groupTitle}");
}

foreach ([
    'class="nav-section-toggle"',
    'class="nav-section-arrow"',
    'class="nav-section-body"',
    'aria-expanded="false"',
    'hidden>',
    'id="nav-section-advanced"',
] as $markup) {
    assertContains($markup, $index, "Sidebar should include collapsible markup {$markup}");
}

foreach ([
    'dashboard',
    'accounts',
    'kpi-profiles',
    'mt5',
    'backtest',
    'backtest-v2',
    'compare',
    'strategy',
    'strategy-versions',
    'research-lab',
    'playbook',
    'calibration',
    'ai-orchestrator',
    'prompt-ai',
    'experiments',
    'evolution',
    'trainer',
    'tradebrain-memory',
    'memory',
] as $tab) {
    assertContains('data-tab="' . $tab . '"', $index, "Sidebar should keep {$tab} tab");
}

echo 'Sidebar grouping smoke tests passed.' . PHP_EOL;

function assertContains(string $needle, string|false $haystack, string $message): void
{
    if (!is_string($haystack) || !str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}
