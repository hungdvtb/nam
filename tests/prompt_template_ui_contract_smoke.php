<?php

declare(strict_types=1);

$index = file_get_contents(__DIR__ . '/../index.php');
$app = file_get_contents(__DIR__ . '/../assets/app.js');

assertContains('data-tab="prompt-ai"', $index, 'Prompt AI navigation tab should exist');
assertContains('Prompt AI', $index, 'Prompt AI page label should exist');
assertContains('id="tab-prompt-ai"', $index, 'Prompt AI tab panel should exist');
assertContains('id="promptTemplateList"', $index, 'Prompt template list should exist');
assertContains('id="promptTemplateForm"', $index, 'Prompt template form should exist');
assertContains('id="activatePromptTemplateBtn"', $index, 'Prompt template activate button should exist');
assertContains('id="archivePromptTemplateBtn"', $index, 'Prompt template archive button should exist');
assertContains('id="testPromptMockAiBtn"', $index, 'Prompt mock AI test button should exist');
assertContains('Test prompt bằng Mock AI', $index, 'Prompt mock AI test label should exist');
assertContains('id="promptGatewayVariablesInput"', $index, 'Prompt gateway sample variables input should exist');
assertContains('id="promptGatewayTestResult"', $index, 'Prompt gateway test result panel should exist');
assertContains('id="promptGatewayProviderSelect"', $index, 'Prompt gateway provider selector should exist');
assertContains('OpenAI Chat', $index, 'Prompt gateway provider selector should include OpenAI Chat');
assertContains('Ollama Local', $index, 'Prompt gateway provider selector should include Ollama Local');

foreach ([
    'prompt_template_list',
    'prompt_template_create',
    'prompt_template_update',
    'prompt_template_activate',
    'prompt_template_archive',
    'llm_gateway_test_call',
] as $action) {
    assertContains($action, $app, "App should call {$action}");
}

assertContains('renderPromptTemplates', $app, 'App should render Prompt AI UI');
assertContains('loadPromptTemplates', $app, 'App should load prompt templates');
assertContains('savePromptTemplate', $app, 'App should save prompt templates');
assertContains('testPromptWithMockAI', $app, 'App should test prompts through Mock AI');
assertContains('renderPromptGatewayTester', $app, 'App should render LLM Gateway test UI');
assertContains('promptGatewayProvider', $app, 'App state should track selected LLM provider');
assertContains('preferred_provider', $app, 'App should send selected LLM provider to API');
assertContains('Ollama Local', $app, 'App should label Ollama provider');
assertContains('selectedPromptTemplateId', $app, 'App state should track selected prompt template');

echo 'Prompt template UI contract smoke tests passed.' . PHP_EOL;

function assertContains(string $needle, string|false $haystack, string $message): void
{
    if (!is_string($haystack) || !str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}
