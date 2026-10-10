<?php

declare(strict_types=1);

$index = file_get_contents(__DIR__ . '/../index.php');
$app = file_get_contents(__DIR__ . '/../assets/app.js');

assertContains('data-tab="ai-orchestrator"', $index, 'AI Orchestrator navigation tab should exist');
assertContains('AI Orchestrator', $index, 'AI Orchestrator UI should use the requested label');
assertContains('id="tab-ai-orchestrator"', $index, 'AI Orchestrator tab panel should exist');
assertContains('id="aiJobCreateForm"', $index, 'AI job create form should exist');
assertContains('id="aiJobList"', $index, 'AI job list container should exist');
assertContains('id="aiJobDetail"', $index, 'AI job detail container should exist');
assertContains('id="cancelAIJobBtn"', $index, 'AI job cancel button should exist');
assertContains('id="runAIWorkerOnceBtn"', $index, 'AI worker run-once button should exist');
assertContains('Chạy worker thử', $index, 'AI worker button should use the requested Vietnamese label');
assertContains('id="runResearchMockOnceBtn"', $index, 'Research mock run button should exist');
assertContains('Chạy nghiên cứu thử', $index, 'Research mock button should use the requested Vietnamese label');
assertContains('id="createAIJobStrategyDraftBtn"', $index, 'AI job strategy draft button should exist');
assertContains('Tạo bản nháp chiến lược', $index, 'AI job strategy draft button should use the requested Vietnamese label');

foreach ([
    'ai_job_list',
    'ai_job_find',
    'ai_job_create',
    'ai_job_cancel',
    'ai_job_create_strategy_draft',
    'ai_worker_run_once',
    'research_mock_run_once',
] as $action) {
    assertContains($action, $app, "App should call {$action}");
}

assertContains('renderAIJobs', $app, 'App should render AI Orchestrator UI');
assertContains('createAIJob', $app, 'App should create manual/fake AI jobs');
assertContains('cancelAIJob', $app, 'App should cancel pending AI jobs');
assertContains('runAIWorkerOnce', $app, 'App should run the mock AI worker once');
assertContains('runResearchMockOnce', $app, 'App should run the one-click mock research flow');
assertContains('createStrategyDraftFromAIJob', $app, 'App should create Strategy V2 drafts from AI jobs');
assertContains('canCreateStrategyDraftFromAIJob', $app, 'App should gate Strategy V2 draft creation by job output');
assertContains('selectedAIJobId', $app, 'App state should track the selected AI job');
assertContains('researchMockRunning', $app, 'App state should track the one-click mock research flow');

echo 'AI job UI contract smoke tests passed.' . PHP_EOL;

function assertContains(string $needle, string|false $haystack, string $message): void
{
    if (!is_string($haystack) || !str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}
