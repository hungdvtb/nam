<?php

declare(strict_types=1);

require_once __DIR__ . '/LLMRequest.php';
require_once __DIR__ . '/UsageRecord.php';

final class LLMResponse
{
    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromProviderResult(
        LLMRequest $request,
        array $renderedPrompt,
        array $routeInfo,
        array $providerResponse,
        array $parseResult,
        UsageRecord $usage,
        string $outputFormat,
        string $parserMode
    ): self {
        $providerOk = ($providerResponse['ok'] ?? false) === true;
        $parseOk = ($parseResult['ok'] ?? false) === true;
        $error = null;

        if (!$providerOk) {
            $providerError = is_array($providerResponse['error'] ?? null) ? $providerResponse['error'] : [];
            $error = [
                'category' => (string)($providerError['category'] ?? 'provider_error'),
                'message' => (string)($providerError['message'] ?? 'LLM provider returned an error.'),
            ];
        } elseif (!$parseOk) {
            $parserError = is_array($parseResult['error'] ?? null) ? $parseResult['error'] : [];
            $error = [
                'category' => (string)($parserError['category'] ?? 'invalid_response'),
                'message' => (string)($parserError['message'] ?? 'LLM response could not be parsed.'),
            ];
        }

        return new self(self::basePayload(
            request: $request,
            renderedPrompt: $renderedPrompt,
            routeInfo: $routeInfo,
            usage: $usage,
            outputFormat: $outputFormat,
            parserMode: $parserMode,
            overrides: [
                'ok' => $providerOk && $parseOk,
                'provider' => $providerResponse['provider'] ?? $routeInfo['provider'] ?? null,
                'provider_response_id' => $providerResponse['provider_response_id'] ?? $providerResponse['providerResponseId'] ?? null,
                'model_alias' => $providerResponse['model_alias'] ?? $routeInfo['model_alias'] ?? null,
                'model_name' => $providerResponse['model_name'] ?? $routeInfo['model_name'] ?? null,
                'finish_reason' => (string)($providerResponse['finish_reason'] ?? 'unknown'),
                'text' => (string)($providerResponse['text'] ?? ''),
                'parsed' => $providerOk && $parseOk ? ($parseResult['parsed'] ?? null) : null,
                'validation_status' => (string)($parseResult['validation_status'] ?? ($providerOk && $parseOk ? 'valid' : 'failed')),
                'warnings' => is_array($parseResult['warnings'] ?? null) ? $parseResult['warnings'] : [],
                'cost' => is_array($providerResponse['cost'] ?? null) ? $providerResponse['cost'] : self::zeroCost(),
                'error' => $error,
                'metadata' => [
                    'route' => $routeInfo,
                    'provider_metadata' => is_array($providerResponse['metadata'] ?? null) ? $providerResponse['metadata'] : [],
                    'parser' => [
                        'mode' => $parserMode,
                        'validation_status' => (string)($parseResult['validation_status'] ?? ''),
                    ],
                ],
            ]
        ));
    }

    public static function failure(
        LLMRequest $request,
        string $category,
        string $message,
        UsageRecord $usage,
        array $renderedPrompt = [],
        array $routeInfo = [],
        ?string $outputFormat = null,
        ?string $parserMode = null
    ): self {
        $format = $outputFormat ?: 'plain_text';

        return new self(self::basePayload(
            request: $request,
            renderedPrompt: $renderedPrompt,
            routeInfo: $routeInfo,
            usage: $usage,
            outputFormat: $format,
            parserMode: $parserMode ?: $format,
            overrides: [
                'ok' => false,
                'provider' => $routeInfo['provider'] ?? null,
                'model_alias' => $routeInfo['model_alias'] ?? null,
                'model_name' => $routeInfo['model_name'] ?? null,
                'finish_reason' => 'error',
                'validation_status' => 'failed',
                'error' => [
                    'category' => $category,
                    'message' => $message,
                ],
                'cost' => self::zeroCost(),
            ]
        ));
    }

    public function ok(): bool
    {
        return (bool)($this->data['ok'] ?? false);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private static function basePayload(
        LLMRequest $request,
        array $renderedPrompt,
        array $routeInfo,
        UsageRecord $usage,
        string $outputFormat,
        string $parserMode,
        array $overrides = []
    ): array {
        $template = is_array($renderedPrompt['template'] ?? null) ? $renderedPrompt['template'] : [];

        return array_merge([
            'ok' => false,
            'request_id' => $request->requestId(),
            'conversation_id' => $request->conversationId(),
            'task_type' => $request->taskType(),
            'agent_role' => $request->agentRole(),
            'prompt_template_key' => $request->promptTemplateKey(),
            'prompt_template_version' => $template['version'] ?? $request->promptTemplateVersion(),
            'output_format' => $outputFormat,
            'parser_mode' => $parserMode,
            'provider' => $routeInfo['provider'] ?? null,
            'provider_response_id' => null,
            'model_alias' => $routeInfo['model_alias'] ?? null,
            'model_name' => $routeInfo['model_name'] ?? null,
            'finish_reason' => 'error',
            'text' => '',
            'parsed' => null,
            'validation_status' => 'failed',
            'warnings' => [],
            'usage' => $usage->toArray(),
            'cost' => self::zeroCost(),
            'prompt' => [
                'template_id' => $template['id'] ?? null,
                'template_key' => $template['template_key'] ?? $request->promptTemplateKey(),
                'version' => $template['version'] ?? $request->promptTemplateVersion(),
                'used_variables' => is_array($renderedPrompt['used_variables'] ?? null) ? $renderedPrompt['used_variables'] : [],
                'messages' => is_array($renderedPrompt['messages'] ?? null) ? $renderedPrompt['messages'] : [],
            ],
            'error' => null,
            'metadata' => [
                'route' => $routeInfo,
            ],
        ], $overrides);
    }

    private static function zeroCost(): array
    {
        return [
            'amount' => 0.0,
            'currency' => 'USD',
            'estimated' => false,
        ];
    }
}
