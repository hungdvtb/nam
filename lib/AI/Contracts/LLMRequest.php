<?php

declare(strict_types=1);

final class LLMRequest
{
    private string $requestId;
    private string $taskType;
    private string $agentRole;
    private string $promptTemplateKey;
    private array $variables;
    private ?string $outputFormat;
    private ?string $conversationId;
    private ?string $preferredProvider;
    private ?string $modelAlias;
    private ?int $promptTemplateVersion;
    private array $metadata;

    public function __construct(
        string $requestId,
        string $taskType,
        string $agentRole,
        string $promptTemplateKey,
        array $variables = [],
        ?string $outputFormat = null,
        ?string $conversationId = null,
        ?string $preferredProvider = null,
        ?string $modelAlias = null,
        ?int $promptTemplateVersion = null,
        array $metadata = []
    ) {
        $this->requestId = trim($requestId) !== '' ? trim($requestId) : self::newId();
        $this->taskType = trim($taskType);
        $this->agentRole = trim($agentRole);
        $this->promptTemplateKey = trim($promptTemplateKey);
        $this->variables = $variables;
        $this->outputFormat = $this->nullableString($outputFormat);
        $this->conversationId = $this->nullableString($conversationId);
        $this->preferredProvider = $this->nullableString($preferredProvider);
        $this->modelAlias = $this->nullableString($modelAlias);
        $this->promptTemplateVersion = $promptTemplateVersion !== null && $promptTemplateVersion > 0 ? $promptTemplateVersion : null;
        $this->metadata = $metadata;
    }

    public static function fromArray(array $payload): self
    {
        $request = self::fromArrayWithoutValidation($payload);
        $request->assertValid();

        return $request;
    }

    public static function fromArrayWithoutValidation(array $payload): self
    {
        $version = self::intValue($payload, ['prompt_template_version', 'promptTemplateVersion', 'version']);

        return new self(
            self::stringValue($payload, ['request_id', 'requestId']) ?: self::newId(),
            self::stringValue($payload, ['task_type', 'taskType']) ?: 'unknown',
            self::stringValue($payload, ['agent_role', 'agentRole']) ?: 'unknown',
            self::stringValue($payload, ['prompt_template_key', 'promptTemplateKey', 'template_key', 'templateKey']) ?: 'unknown',
            self::arrayValue($payload, ['variables', 'prompt_variables', 'promptVariables']),
            self::stringValue($payload, ['output_format', 'outputFormat', 'response_format', 'responseFormat']),
            self::stringValue($payload, ['conversation_id', 'conversationId']),
            self::stringValue($payload, ['preferred_provider', 'preferredProvider', 'provider']),
            self::stringValue($payload, ['model_alias', 'modelAlias', 'preferred_model', 'preferredModel']),
            $version,
            self::arrayValue($payload, ['metadata', 'meta'])
        );
    }

    public static function newId(): string
    {
        return 'llm_req_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }

    public function assertValid(): void
    {
        if ($this->taskType === '' || $this->taskType === 'unknown') {
            throw new InvalidArgumentException('LLM request task_type is required.');
        }

        if ($this->agentRole === '' || $this->agentRole === 'unknown') {
            throw new InvalidArgumentException('LLM request agent_role is required.');
        }

        if ($this->promptTemplateKey === '' || $this->promptTemplateKey === 'unknown') {
            throw new InvalidArgumentException('LLM request prompt_template_key is required.');
        }
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function taskType(): string
    {
        return $this->taskType;
    }

    public function agentRole(): string
    {
        return $this->agentRole;
    }

    public function promptTemplateKey(): string
    {
        return $this->promptTemplateKey;
    }

    public function variables(): array
    {
        return $this->variables;
    }

    public function outputFormat(): ?string
    {
        return $this->outputFormat;
    }

    public function conversationId(): ?string
    {
        return $this->conversationId;
    }

    public function preferredProvider(): ?string
    {
        return $this->preferredProvider;
    }

    public function modelAlias(): ?string
    {
        return $this->modelAlias;
    }

    public function promptTemplateVersion(): ?int
    {
        return $this->promptTemplateVersion;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'task_type' => $this->taskType,
            'agent_role' => $this->agentRole,
            'prompt_template_key' => $this->promptTemplateKey,
            'variables' => $this->variables,
            'output_format' => $this->outputFormat,
            'conversation_id' => $this->conversationId,
            'preferred_provider' => $this->preferredProvider,
            'model_alias' => $this->modelAlias,
            'prompt_template_version' => $this->promptTemplateVersion,
            'metadata' => $this->metadata,
        ];
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private static function stringValue(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_scalar($source[$key])) {
                $value = trim((string)$source[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private static function intValue(array $source, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_scalar($source[$key]) && (int)$source[$key] > 0) {
                return (int)$source[$key];
            }
        }

        return null;
    }

    private static function arrayValue(array $source, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_array($source[$key])) {
                return $source[$key];
            }
        }

        return [];
    }
}
