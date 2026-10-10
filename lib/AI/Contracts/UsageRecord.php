<?php

declare(strict_types=1);

require_once __DIR__ . '/LLMRequest.php';

final class UsageRecord
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = [
            'usage_id' => (string)($data['usage_id'] ?? self::newId()),
            'conversation_id' => $data['conversation_id'] ?? null,
            'request_id' => (string)($data['request_id'] ?? ''),
            'provider' => $data['provider'] ?? null,
            'upstream_provider' => $data['upstream_provider'] ?? null,
            'model_alias' => $data['model_alias'] ?? null,
            'model_name' => $data['model_name'] ?? null,
            'input_tokens' => max(0, (int)($data['input_tokens'] ?? 0)),
            'output_tokens' => max(0, (int)($data['output_tokens'] ?? 0)),
            'cached_tokens' => max(0, (int)($data['cached_tokens'] ?? 0)),
            'reasoning_tokens' => max(0, (int)($data['reasoning_tokens'] ?? 0)),
            'estimated' => (bool)($data['estimated'] ?? true),
            'started_at' => (string)($data['started_at'] ?? gmdate('c')),
            'finished_at' => (string)($data['finished_at'] ?? gmdate('c')),
            'latency_ms' => max(0, (int)($data['latency_ms'] ?? 0)),
        ];
    }

    public static function fromProviderUsage(
        LLMRequest $request,
        array $routeInfo,
        array $providerResponse,
        string $startedAt,
        string $finishedAt,
        int $latencyMs
    ): self {
        $usage = is_array($providerResponse['usage'] ?? null) ? $providerResponse['usage'] : [];

        return new self([
            'conversation_id' => $request->conversationId(),
            'request_id' => $request->requestId(),
            'provider' => $providerResponse['provider'] ?? $routeInfo['provider'] ?? null,
            'upstream_provider' => $providerResponse['upstream_provider'] ?? $providerResponse['upstreamProvider'] ?? null,
            'model_alias' => $providerResponse['model_alias'] ?? $routeInfo['model_alias'] ?? null,
            'model_name' => $providerResponse['model_name'] ?? $routeInfo['model_name'] ?? null,
            'input_tokens' => $usage['input_tokens'] ?? $usage['inputTokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? $usage['outputTokens'] ?? 0,
            'cached_tokens' => $usage['cached_tokens'] ?? $usage['cachedTokens'] ?? 0,
            'reasoning_tokens' => $usage['reasoning_tokens'] ?? $usage['reasoningTokens'] ?? 0,
            'estimated' => $usage['estimated'] ?? true,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'latency_ms' => $latencyMs,
        ]);
    }

    public static function empty(
        LLMRequest $request,
        string $startedAt,
        string $finishedAt,
        int $latencyMs,
        array $routeInfo = []
    ): self {
        return new self([
            'conversation_id' => $request->conversationId(),
            'request_id' => $request->requestId(),
            'provider' => $routeInfo['provider'] ?? null,
            'model_alias' => $routeInfo['model_alias'] ?? null,
            'model_name' => $routeInfo['model_name'] ?? null,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'latency_ms' => $latencyMs,
            'estimated' => true,
        ]);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private static function newId(): string
    {
        return 'usage_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
}
