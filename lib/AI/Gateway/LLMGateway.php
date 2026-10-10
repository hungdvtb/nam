<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/LLMRequest.php';
require_once __DIR__ . '/../Contracts/LLMResponse.php';
require_once __DIR__ . '/../Contracts/UsageRecord.php';
require_once __DIR__ . '/../Prompts/PromptManager.php';
require_once __DIR__ . '/ProviderRouter.php';
require_once __DIR__ . '/ResponseParser.php';

final class LLMGateway
{
    public function __construct(
        private PromptManager $prompts,
        private ProviderRouter $router,
        private ?ResponseParser $parser = null
    ) {
        $this->parser = $this->parser ?? new ResponseParser();
    }

    public function call(LLMRequest|array $request): LLMResponse
    {
        $startedAt = gmdate('c');
        $startNanos = hrtime(true);
        $rendered = [];
        $routeInfo = [];
        $outputFormat = 'plain_text';
        $parserMode = 'plain_text';

        try {
            $llmRequest = is_array($request) ? LLMRequest::fromArray($request) : $request;
            $llmRequest->assertValid();
        } catch (Throwable $error) {
            $llmRequest = is_array($request) ? LLMRequest::fromArrayWithoutValidation($request) : $request;
            $finishedAt = gmdate('c');

            return LLMResponse::failure(
                $llmRequest,
                'invalid_request',
                $error->getMessage(),
                UsageRecord::empty($llmRequest, $startedAt, $finishedAt, $this->latencyMs($startNanos))
            );
        }

        try {
            $rendered = $this->prompts->render(
                $llmRequest->promptTemplateKey(),
                $llmRequest->variables(),
                $llmRequest->promptTemplateVersion()
            );
            $outputFormat = $llmRequest->outputFormat() ?: (string)($rendered['output_format'] ?? 'plain_text');
            $parserMode = (string)($rendered['parser_mode'] ?? $outputFormat);
            $providerPayload = $this->providerPayload($llmRequest, $rendered, $outputFormat, $parserMode);
            $routeInfo = $this->router->routeInfo($providerPayload);
            $providerPayload['provider'] = $routeInfo['provider'];
            $providerPayload['model_alias'] = $routeInfo['model_alias'];
            $providerPayload['model_name'] = $routeInfo['model_name'];

            $provider = $this->router->route($providerPayload);
            $providerResponse = $provider->complete($providerPayload);
            $finishedAt = gmdate('c');
            $usage = UsageRecord::fromProviderUsage(
                $llmRequest,
                $routeInfo,
                $providerResponse,
                $startedAt,
                $finishedAt,
                $this->latencyMs($startNanos)
            );
            $parseResult = ($providerResponse['ok'] ?? false) === true
                ? $this->parser->parse($providerResponse, $parserMode)
                : [
                    'ok' => false,
                    'mode' => $parserMode,
                    'parsed' => null,
                    'validation_status' => 'skipped_provider_error',
                    'warnings' => [],
                    'error' => null,
                ];

            return LLMResponse::fromProviderResult(
                $llmRequest,
                $rendered,
                $routeInfo,
                $providerResponse,
                $parseResult,
                $usage,
                $outputFormat,
                $parserMode
            );
        } catch (Throwable $error) {
            $finishedAt = gmdate('c');

            return LLMResponse::failure(
                $llmRequest,
                $this->errorCategory($error),
                $error->getMessage(),
                UsageRecord::empty($llmRequest, $startedAt, $finishedAt, $this->latencyMs($startNanos), $routeInfo),
                $rendered,
                $routeInfo,
                $outputFormat,
                $parserMode
            );
        }
    }

    private function providerPayload(LLMRequest $request, array $rendered, string $outputFormat, string $parserMode): array
    {
        $template = is_array($rendered['template'] ?? null) ? $rendered['template'] : [];
        $payload = [
            'request_id' => $request->requestId(),
            'conversation_id' => $request->conversationId(),
            'task_type' => $request->taskType(),
            'agent_role' => $request->agentRole(),
            'prompt_template_key' => $request->promptTemplateKey(),
            'prompt_template_version' => $template['version'] ?? $request->promptTemplateVersion(),
            'messages' => is_array($rendered['messages'] ?? null) ? $rendered['messages'] : [],
            'variables' => $request->variables(),
            'prompt_variables' => $request->variables(),
            'output_format' => $outputFormat,
            'response_format' => $outputFormat,
            'parser_mode' => $parserMode,
            'metadata' => $request->metadata(),
        ];

        if ($request->preferredProvider() !== null) {
            $payload['provider'] = $request->preferredProvider();
            $payload['preferred_provider'] = $request->preferredProvider();
        }

        if ($request->modelAlias() !== null) {
            $payload['model_alias'] = $request->modelAlias();
            $payload['preferred_model'] = $request->modelAlias();
        }

        return $payload;
    }

    private function errorCategory(Throwable $error): string
    {
        $message = strtolower($error->getMessage());

        if (str_contains($message, 'missing required prompt variable')) {
            return 'invalid_request';
        }

        if (str_contains($message, 'prompt template not found') || str_contains($message, 'provider')) {
            return 'configuration_error';
        }

        return $error instanceof InvalidArgumentException ? 'invalid_request' : 'provider_error';
    }

    private function latencyMs(int $startNanos): int
    {
        return max(0, (int)round((hrtime(true) - $startNanos) / 1_000_000));
    }
}
