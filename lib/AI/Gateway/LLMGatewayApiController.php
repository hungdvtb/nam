<?php

declare(strict_types=1);

require_once __DIR__ . '/LLMGateway.php';

final class LLMGatewayApiController
{
    public function __construct(private LLMGateway $gateway)
    {
    }

    public function handle(string $action, array $payload): array
    {
        return match ($action) {
            'llm_gateway_test_call' => $this->testCall($payload),
            default => throw new InvalidArgumentException('Unsupported LLM Gateway API action: ' . $action),
        };
    }

    private function testCall(array $payload): array
    {
        $templateKey = $this->stringValue($payload, ['prompt_template_key', 'promptTemplateKey', 'template_key', 'templateKey']);
        if ($templateKey === '') {
            throw new InvalidArgumentException('Prompt template key is required.');
        }

        $variables = $this->variables($payload);
        $provider = $this->provider($payload);
        $response = $this->gateway->call([
            'task_type' => $this->stringValue($payload, ['task_type', 'taskType']) ?: $this->taskTypeFromTemplateKey($templateKey),
            'agent_role' => $this->stringValue($payload, ['agent_role', 'agentRole']) ?: 'PromptAI',
            'prompt_template_key' => $templateKey,
            'variables' => $variables,
            'output_format' => $this->stringValue($payload, ['output_format', 'outputFormat', 'response_format', 'responseFormat']),
            'preferred_provider' => $provider,
            'metadata' => [
                'api_action' => 'llm_gateway_test_call',
                'ui_test' => true,
            ],
        ])->toArray();
        $response = $this->normalizeProviderSetupError($response, $provider);

        return [
            'ok' => true,
            'llm_response' => $response,
            'response' => $response,
        ];
    }

    private function variables(array $payload): array
    {
        foreach (['variables', 'prompt_variables', 'promptVariables', 'sample_variables', 'sampleVariables'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return [];
    }

    private function provider(array $payload): string
    {
        $provider = strtolower($this->stringValue($payload, ['preferred_provider', 'preferredProvider', 'provider']));

        return in_array($provider, ['mock', 'openai_chat', 'ollama'], true) ? $provider : 'mock';
    }

    private function taskTypeFromTemplateKey(string $templateKey): string
    {
        return [
            'strategy.idea.generate' => 'strategy_proposal',
            'strategy.version.improve' => 'strategy_proposal',
            'backtest.summary' => 'backtest_summary',
            'experiment.compare' => 'optimizer_compare',
        ][$templateKey] ?? preg_replace('/[^a-z0-9]+/', '_', strtolower($templateKey)) ?: 'prompt_test';
    }

    private function stringValue(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_scalar($source[$key])) {
                $value = trim((string)$source[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function normalizeProviderSetupError(array $response, string $provider): array
    {
        if (($response['ok'] ?? false) === true) {
            return $response;
        }

        if (!is_array($response['error'] ?? null) || ($response['error']['category'] ?? '') !== 'configuration_error') {
            return $response;
        }

        if ($provider === 'openai_chat') {
            $response['error']['message'] = 'OpenAI Chat provider is not configured or is disabled. Enable openai_chat in config and set OPENAI_API_KEY plus OPENAI_MODEL_DEFAULT on the server.';
        }

        if ($provider === 'ollama') {
            $response['error']['message'] = 'Ollama Local provider is disabled or not configured. Enable ollama in config/local.php, start Ollama with `ollama serve`, and pull the configured model.';
        }

        return $response;
    }
}
