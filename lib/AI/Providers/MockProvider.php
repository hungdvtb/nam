<?php

declare(strict_types=1);

require_once __DIR__ . '/../Gateway/ProviderInterface.php';

final class MockProvider implements ProviderInterface
{
    public function __construct(private array $config = [])
    {
    }

    public function key(): string
    {
        return 'mock';
    }

    public function displayName(): string
    {
        return (string)($this->config['display_name'] ?? 'Mock Provider');
    }

    public function isEnabled(): bool
    {
        return (bool)($this->config['enabled'] ?? true);
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function capabilities(): array
    {
        return $this->config['capabilities'] ?? [
            'plain_text' => true,
            'json_object' => true,
            'json_array' => true,
            'schema_bound' => true,
            'markdown_report' => true,
            'code_review_report' => true,
            'streaming' => false,
            'network' => false,
        ];
    }

    public function modelAliases(): array
    {
        return $this->config['model_aliases'] ?? [
            'default' => 'mock-deterministic-v1',
        ];
    }

    public function resolveModel(?string $modelAlias = null): ?string
    {
        $aliases = $this->modelAliases();
        $alias = $modelAlias ?: 'default';

        return $aliases[$alias] ?? (string)($this->config['default_model'] ?? 'mock-deterministic-v1');
    }

    public function health(): array
    {
        return [
            'ok' => $this->isEnabled(),
            'provider' => $this->key(),
            'implemented' => true,
            'network' => false,
            'status' => $this->isEnabled() ? 'ready' : 'disabled',
        ];
    }

    public function complete(array $request): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResponse($request, 'configuration_error', 'Mock provider is disabled.');
        }

        $forcedError = $this->forcedError();
        if ($forcedError !== null) {
            return $this->errorResponse(
                $request,
                (string)$forcedError['category'],
                (string)$forcedError['message']
            );
        }

        $modelAlias = $this->stringValue($request, ['model_alias', 'modelAlias', 'preferred_model', 'preferredModel']) ?: 'default';
        $model = $this->resolveModel($modelAlias);
        $format = $this->stringValue($request, ['response_format', 'responseFormat', 'output_format', 'outputFormat']) ?: 'plain_text';
        $taskType = $this->stringValue($request, ['task_type', 'taskType']) ?: 'generic';
        $agentRole = $this->stringValue($request, ['agent_role', 'agentRole']) ?: 'unknown';
        $promptTemplateKey = $this->stringValue($request, ['prompt_template_key', 'promptTemplateKey', 'template_key', 'templateKey']) ?: '';
        $messages = $this->messages($request);
        $promptVariables = $this->arrayValue($request, ['prompt_variables', 'promptVariables', 'variables']);
        $requestHash = $this->requestHash($request);
        $parsed = $this->buildParsedPayload($format, $taskType, $agentRole, $promptTemplateKey, $requestHash, $promptVariables, $messages);
        $text = $this->renderText($format, $parsed);
        $inputTokens = $this->estimateTokens(json_encode($this->normalizedForHash($request), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        $outputTokens = $this->estimateTokens($text);

        return [
            'ok' => true,
            'provider' => $this->key(),
            'provider_response_id' => 'mock_' . substr($requestHash, 0, 16),
            'model_alias' => $modelAlias,
            'model_name' => $model,
            'finish_reason' => 'stop',
            'text' => $text,
            'parsed' => $parsed,
            'usage' => [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cached_tokens' => 0,
                'reasoning_tokens' => 0,
                'estimated' => true,
            ],
            'cost' => [
                'amount' => 0.0,
                'currency' => 'USD',
                'estimated' => false,
            ],
            'error' => null,
            'metadata' => [
                'deterministic' => true,
                'network' => false,
                'request_hash' => $requestHash,
                'task_type' => $taskType,
                'agent_role' => $agentRole,
                'response_format' => $format,
            ],
            'raw' => [
                'mock' => true,
            ],
        ];
    }

    private function errorResponse(array $request, string $category, string $message): array
    {
        $requestHash = $this->requestHash($request);

        return [
            'ok' => false,
            'provider' => $this->key(),
            'provider_response_id' => 'mock_error_' . substr($requestHash, 0, 16),
            'model_alias' => null,
            'model_name' => null,
            'finish_reason' => 'error',
            'text' => '',
            'parsed' => null,
            'usage' => [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cached_tokens' => 0,
                'reasoning_tokens' => 0,
                'estimated' => true,
            ],
            'cost' => [
                'amount' => 0.0,
                'currency' => 'USD',
                'estimated' => false,
            ],
            'error' => [
                'category' => $category,
                'message' => $message,
            ],
            'metadata' => [
                'deterministic' => true,
                'network' => false,
                'request_hash' => $requestHash,
            ],
            'raw' => [
                'mock' => true,
            ],
        ];
    }

    private function forcedError(): ?array
    {
        $forced = $this->config['force_error'] ?? false;
        if ($forced === false || $forced === null) {
            return null;
        }

        if (is_array($forced)) {
            return [
                'category' => (string)($forced['category'] ?? 'provider_error'),
                'message' => (string)($forced['message'] ?? 'Mock provider forced error.'),
            ];
        }

        return [
            'category' => 'provider_error',
            'message' => 'Mock provider forced error.',
        ];
    }

    private function buildParsedPayload(string $format, string $taskType, string $agentRole, string $promptTemplateKey, string $requestHash, array $promptVariables, array $messages): array
    {
        $summary = $this->summary($promptVariables, $messages);
        $base = [
            'provider' => 'mock',
            'task_type' => $taskType,
            'agent_role' => $agentRole,
            'prompt_template_key' => $promptTemplateKey,
            'request_hash' => $requestHash,
            'summary' => $summary,
            'proposal_only' => true,
        ];

        $jobPayload = $this->promptTemplatePayload($promptTemplateKey, $promptVariables);
        if ($jobPayload !== null) {
            return $base + $jobPayload;
        }

        if ($format === 'json_array') {
            return [
                [
                    'rank' => 1,
                    'title' => 'Deterministic mock candidate',
                    'reason' => $summary,
                    'request_hash' => $requestHash,
                ],
            ];
        }

        if ($format === 'code_review_report') {
            return $base + [
                'findings' => [],
                'verdict' => 'mock_no_findings',
            ];
        }

        if ($format === 'schema_bound') {
            return $base + [
                'schema_status' => 'valid_mock',
                'changes' => [],
                'risk_level' => 'mock_low',
            ];
        }

        return $base + [
            'message' => 'Mock provider deterministic response.',
        ];
    }

    private function promptTemplatePayload(string $promptTemplateKey, array $promptVariables): ?array
    {
        return match ($promptTemplateKey) {
            'strategy.idea.generate' => [
                'strategy_idea' => 'Trade only clean H4 supply/demand reactions with M15 confirmation.',
                'rules_json' => $this->mockStrategyRules(),
                'explanation' => 'Mock idea only: it summarizes a conservative zone-reaction strategy without changing live rules.',
            ],
            'strategy.version.improve' => [
                'improvement_suggestion' => 'Tighten entry confirmation around weak zones and review low-quality limit fills.',
                'changed_rules_json' => [
                    'h4Rules' => [
                        'minZoneStrength' => 70,
                    ],
                    'm15Rules' => [
                        'requireStructureBreak' => true,
                        'avoidDirtySideway' => true,
                    ],
                ],
                'reason' => 'Mock suggestion only: reduce exposure to unclear H4 context while preserving the existing backtest engine.',
            ],
            'backtest.summary' => [
                'analysis' => 'Backtest has usable structure but needs review for sample size and clustered losses.',
                'strengths' => [
                    'Clear rule-based entries are available for review.',
                    'Result payload can be linked back to strategy and experiment records.',
                ],
                'weaknesses' => [
                    'Small samples may overstate win rate.',
                    'Loss streaks need manual inspection before promotion.',
                ],
                'recommendations' => [
                    'Compare train, validation, and final splits.',
                    'Tag weak-zone losses before proposing rule changes.',
                ],
            ],
            'experiment.compare' => [
                'experiment_summary' => 'Experiment candidates are ready for a manual comparison pass.',
                'best_candidate' => [
                    'strategy_version_id' => $this->scalarOrNull($promptVariables['strategy_version_id'] ?? $promptVariables['strategyVersionId'] ?? null),
                    'experiment_id' => $this->scalarOrNull($promptVariables['experiment_id'] ?? $promptVariables['experimentId'] ?? null),
                    'selection_reason' => 'Mock selection only: choose the candidate with steadier validation behavior.',
                ],
                'next_steps' => [
                    'Review candidate metrics.',
                    'Run an additional backtest if sample size is weak.',
                    'Approve only after manual validation.',
                ],
            ],
            default => null,
        };
    }

    private function mockStrategyRules(): array
    {
        return [
            'symbols' => ['EURUSD'],
            'contextTimeframe' => 'H4',
            'entryTimeframe' => 'M15',
            'entryMode' => 'LIMIT',
            'directionAllowed' => 'BUY_SELL',
            'minRR' => 3,
            'maxRiskPercent' => 3,
            'riskPercent' => 1,
            'setupTypes' => ['mock_h4_zone_m15_confirmation'],
            'h4Rules' => [
                'detectTrend' => true,
                'detectSupplyDemandZones' => true,
                'minZoneStrength' => 70,
            ],
            'm15Rules' => [
                'requireStructureBreak' => true,
                'requireReactionAtZone' => true,
                'avoidDirtySideway' => true,
            ],
            'filters' => [
                'avoidNewsWindow' => true,
                'avoidWeekend' => true,
            ],
        ];
    }

    private function scalarOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function renderText(string $format, array $parsed): string
    {
        if (in_array($format, ['json_object', 'json_array', 'schema_bound'], true)) {
            return json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        if ($format === 'markdown_report') {
            return '# Mock LLM Report' . "\n\n"
                . '- Provider: mock' . "\n"
                . '- Task: ' . (string)($parsed['task_type'] ?? 'generic') . "\n"
                . '- Summary: ' . (string)($parsed['summary'] ?? 'No prompt content.') . "\n"
                . '- Safety: proposal only';
        }

        if ($format === 'code_review_report') {
            return 'Mock code review report: no findings. Request hash ' . (string)($parsed['request_hash'] ?? '');
        }

        return 'Mock provider deterministic response for '
            . (string)($parsed['task_type'] ?? 'generic')
            . ' by '
            . (string)($parsed['agent_role'] ?? 'unknown')
            . '. Summary: '
            . (string)($parsed['summary'] ?? 'No prompt content.')
            . ' Request hash: '
            . (string)($parsed['request_hash'] ?? '');
    }

    private function messages(array $request): array
    {
        $messages = $request['messages'] ?? [];
        if (!is_array($messages)) {
            return [];
        }

        return $messages;
    }

    private function summary(array $promptVariables, array $messages): string
    {
        $parts = [];
        foreach ($messages as $message) {
            if (is_array($message) && isset($message['content'])) {
                $parts[] = trim((string)$message['content']);
            } elseif (is_string($message)) {
                $parts[] = trim($message);
            }
        }

        foreach ($promptVariables as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = (string)$key . '=' . (string)$value;
            }
        }

        $summary = trim(implode(' ', array_filter($parts)));
        if ($summary === '') {
            return 'No prompt content.';
        }

        $summary = preg_replace('/\s+/', ' ', $summary) ?: $summary;

        return strlen($summary) > 160 ? substr($summary, 0, 157) . '...' : $summary;
    }

    private function requestHash(array $request): string
    {
        return hash('sha256', json_encode($this->normalizedForHash($request), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function normalizedForHash(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizedForHash($item);
            }
        }

        return $value;
    }

    private function estimateTokens(string $text): int
    {
        $length = strlen(trim($text));
        if ($length === 0) {
            return 0;
        }

        return max(1, (int)ceil($length / 4));
    }

    private function stringValue(array $source, array $keys): ?string
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

    private function arrayValue(array $source, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_array($source[$key])) {
                return $source[$key];
            }
        }

        return [];
    }
}
