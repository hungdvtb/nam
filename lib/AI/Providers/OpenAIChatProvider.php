<?php

declare(strict_types=1);

require_once __DIR__ . '/../Gateway/ProviderInterface.php';

final class OpenAIChatProvider implements ProviderInterface
{
    private const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(private array $config = [])
    {
    }

    public function key(): string
    {
        return 'openai_chat';
    }

    public function displayName(): string
    {
        return (string)($this->config['display_name'] ?? 'OpenAI Chat');
    }

    public function isEnabled(): bool
    {
        return (bool)($this->config['enabled'] ?? false);
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function capabilities(): array
    {
        return array_merge([
            'plain_text' => true,
            'json_object' => true,
            'json_array' => false,
            'schema_bound' => true,
            'markdown_report' => true,
            'code_review_report' => true,
            'streaming' => false,
            'network' => true,
            'usage' => true,
        ], is_array($this->config['capabilities'] ?? null) ? $this->config['capabilities'] : []);
    }

    public function modelAliases(): array
    {
        $aliases = is_array($this->config['model_aliases'] ?? null) ? $this->config['model_aliases'] : [];
        $default = $this->defaultModel();
        if ($default !== null && !isset($aliases['default'])) {
            $aliases['default'] = $default;
        }

        return array_filter($aliases, fn(mixed $model): bool => is_scalar($model) && trim((string)$model) !== '');
    }

    public function resolveModel(?string $modelAlias = null): ?string
    {
        $aliases = $this->modelAliases();
        $alias = $modelAlias ?: 'default';

        if (isset($aliases[$alias])) {
            return (string)$aliases[$alias];
        }

        return $this->defaultModel();
    }

    public function health(): array
    {
        if (!$this->isEnabled()) {
            return $this->healthPayload(false, 'disabled');
        }

        if ($this->apiKey() === null) {
            return $this->healthPayload(false, 'missing_api_key');
        }

        if ($this->defaultModel() === null && $this->modelAliases() === []) {
            return $this->healthPayload(false, 'missing_model');
        }

        return $this->healthPayload(true, 'ready');
    }

    public function complete(array $request): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResponse($request, 'configuration_error', 'OpenAI Chat provider is disabled.');
        }

        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            return $this->errorResponse($request, 'configuration_error', 'OpenAI Chat provider is missing OPENAI_API_KEY.');
        }

        $modelAlias = $this->stringValue($request, ['model_alias', 'modelAlias', 'preferred_model', 'preferredModel']) ?: 'default';
        $model = $this->resolveModel($modelAlias);
        if ($model === null) {
            return $this->errorResponse($request, 'configuration_error', 'OpenAI Chat provider is missing OPENAI_MODEL_DEFAULT or a configured model alias.');
        }

        $budget = new OpenAIChatBudget($this->config);
        $messages = $this->messages($request);
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        $temperature = $this->numericConfig('temperature');
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $maxTokens = $this->intConfig('max_completion_tokens') ?? $this->intConfig('max_tokens');
        if ($maxTokens !== null) {
            $payload['max_completion_tokens'] = $maxTokens;
        }

        $estimatedCallCost = $budget->estimatePreflightCostUsd($model, $messages, $maxTokens);
        try {
            $budget->assertCanCall($model, $estimatedCallCost);
        } catch (RuntimeException $error) {
            return $this->errorResponse($request, 'budget_exceeded', $error->getMessage(), $modelAlias, $model);
        }

        $format = $this->stringValue($request, ['response_format', 'responseFormat', 'output_format', 'outputFormat']) ?: 'plain_text';
        if (in_array($format, ['json_object', 'schema_bound'], true)) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->postJson($this->endpoint(), $payload, $apiKey, $this->timeoutSeconds());
        if (($response['ok'] ?? false) !== true) {
            return $this->errorResponse(
                $request,
                (string)($response['category'] ?? 'provider_error'),
                (string)($response['message'] ?? 'OpenAI Chat request failed.'),
                $modelAlias,
                $model,
                is_array($response['raw'] ?? null) ? $response['raw'] : []
            );
        }

        return $this->successResponse($request, $modelAlias, $model, $response['data'], $budget);
    }

    private function successResponse(array $request, string $modelAlias, string $model, array $data, OpenAIChatBudget $budget): array
    {
        $choice = is_array($data['choices'][0] ?? null) ? $data['choices'][0] : [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
        $text = (string)($message['content'] ?? '');
        $finishReason = (string)($choice['finish_reason'] ?? 'unknown');

        if ($finishReason === 'content_filter') {
            return $this->errorResponse($request, 'content_blocked', 'OpenAI content filter blocked the response.', $modelAlias, $model, $data);
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $promptDetails = is_array($usage['prompt_tokens_details'] ?? null) ? $usage['prompt_tokens_details'] : [];
        $completionDetails = is_array($usage['completion_tokens_details'] ?? null) ? $usage['completion_tokens_details'] : [];
        $normalizedUsage = [
            'input_tokens' => (int)($usage['prompt_tokens'] ?? 0),
            'output_tokens' => (int)($usage['completion_tokens'] ?? 0),
            'cached_tokens' => (int)($promptDetails['cached_tokens'] ?? 0),
            'reasoning_tokens' => (int)($completionDetails['reasoning_tokens'] ?? 0),
            'estimated' => false,
        ];
        $cost = $budget->recordCall($model, $normalizedUsage);

        return [
            'ok' => true,
            'provider' => $this->key(),
            'provider_response_id' => $data['id'] ?? null,
            'model_alias' => $modelAlias,
            'model_name' => (string)($data['model'] ?? $model),
            'finish_reason' => $finishReason,
            'text' => $text,
            'parsed' => null,
            'usage' => $normalizedUsage,
            'cost' => $cost,
            'error' => null,
            'metadata' => [
                'network' => true,
                'endpoint' => $this->endpoint(),
                'response_format' => $this->stringValue($request, ['response_format', 'responseFormat', 'output_format', 'outputFormat']) ?: 'plain_text',
                'budget' => $cost['budget'] ?? null,
            ],
            'raw' => [
                'id' => $data['id'] ?? null,
                'object' => $data['object'] ?? null,
                'created' => $data['created'] ?? null,
            ],
        ];
    }

    private function errorResponse(array $request, string $category, string $message, ?string $modelAlias = null, ?string $model = null, array $raw = []): array
    {
        return [
            'ok' => false,
            'provider' => $this->key(),
            'provider_response_id' => null,
            'model_alias' => $modelAlias,
            'model_name' => $model,
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
                'estimated' => true,
            ],
            'error' => [
                'category' => $category,
                'message' => $message,
            ],
            'metadata' => [
                'network' => true,
                'endpoint' => $this->endpoint(),
                'health' => $this->health(),
            ],
            'raw' => $this->redactedRaw($raw),
        ];
    }

    private function postJson(string $endpoint, array $payload, string $apiKey, int $timeoutSeconds): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return [
                'ok' => false,
                'category' => 'invalid_response',
                'message' => 'Unable to encode OpenAI request payload.',
            ];
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        $responseHeaders = [];
        $responseBody = null;

        if (function_exists('curl_init')) {
            $curl = curl_init($endpoint);
            if ($curl === false) {
                return [
                    'ok' => false,
                    'category' => 'network_error',
                    'message' => 'Unable to initialize OpenAI HTTP client.',
                ];
            }
            $curlOptions = [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            ];
            $caBundlePath = $this->caBundlePath();
            if ($caBundlePath !== null) {
                $curlOptions[CURLOPT_CAINFO] = $caBundlePath;
            }
            curl_setopt_array($curl, $curlOptions);
            $responseBody = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);
            curl_close($curl);

            if ($responseBody === false) {
                $isSslCertError = $curlErrno === 60 || str_contains(strtolower($curlError), 'certificate');
                return [
                    'ok' => false,
                    'category' => $curlErrno === 28 ? 'timeout' : ($isSslCertError ? 'ssl_certificate_error' : 'network_error'),
                    'message' => $isSslCertError
                        ? 'PHP/cURL chưa tìm được chứng chỉ CA để xác minh OpenAI. Hãy cấu hình OPENAI_CA_BUNDLE hoặc cài CA bundle cho PHP.'
                        : ($curlError !== '' ? $curlError : 'OpenAI network request failed.'),
                ];
            }

            return $this->decodeHttpResponse((string)$responseBody, $status);
        }

        $sslOptions = [];
        $caBundlePath = $this->caBundlePath();
        if ($caBundlePath !== null) {
            $sslOptions['cafile'] = $caBundlePath;
            $sslOptions['verify_peer'] = true;
            $sslOptions['verify_peer_name'] = true;
        }

        $contextOptions = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ];
        if ($sslOptions !== []) {
            $contextOptions['ssl'] = $sslOptions;
        }
        $context = stream_context_create($contextOptions);
        $responseBody = @file_get_contents($endpoint, false, $context);
        $responseHeaders = $http_response_header ?? [];
        if ($responseBody === false) {
            $lastError = error_get_last();
            $message = is_array($lastError) ? (string)($lastError['message'] ?? '') : '';

            return [
                'ok' => false,
                'category' => str_contains(strtolower($message), 'timed out') ? 'timeout' : 'network_error',
                'message' => $message !== '' ? $message : 'OpenAI network request failed.',
            ];
        }

        return $this->decodeHttpResponse((string)$responseBody, $this->statusCode($responseHeaders));
    }

    private function decodeHttpResponse(string $body, int $status): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'category' => 'invalid_response',
                'message' => 'OpenAI response was not valid JSON.',
                'raw' => ['status' => $status],
            ];
        }

        if ($status < 200 || $status >= 300) {
            $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];

            return [
                'ok' => false,
                'category' => $this->httpErrorCategory($status, (string)($error['type'] ?? ''), (string)($error['code'] ?? '')),
                'message' => (string)($error['message'] ?? 'OpenAI provider returned HTTP ' . $status . '.'),
                'raw' => ['status' => $status, 'error_type' => $error['type'] ?? null, 'error_code' => $error['code'] ?? null],
            ];
        }

        return [
            'ok' => true,
            'data' => $decoded,
        ];
    }

    private function httpErrorCategory(int $status, string $type, string $code): string
    {
        $value = strtolower($type . ' ' . $code);
        if ($status === 401 || str_contains($value, 'auth') || str_contains($value, 'api_key')) {
            return 'authentication_error';
        }
        if ($status === 429 || str_contains($value, 'rate_limit') || str_contains($value, 'quota')) {
            return 'rate_limit';
        }
        if ($status === 408 || $status === 504) {
            return 'timeout';
        }
        if ($status === 400 && str_contains($value, 'content')) {
            return 'content_blocked';
        }

        return 'provider_error';
    }

    private function messages(array $request): array
    {
        $messages = is_array($request['messages'] ?? null) ? $request['messages'] : [];
        $normalized = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $role = strtolower(trim((string)($message['role'] ?? 'user')));
            $content = $message['content'] ?? '';
            if (is_array($content)) {
                if ($role !== 'user') continue;
                $parts = [];
                foreach (array_slice($content, 0, 8) as $part) {
                    if (!is_array($part)) continue;
                    if (($part['type'] ?? '') === 'text') {
                        $text = trim((string)($part['text'] ?? ''));
                        if ($text !== '') $parts[] = ['type' => 'text', 'text' => function_exists('mb_substr') ? mb_substr($text, 0, 20000) : substr($text, 0, 20000)];
                    } elseif (($part['type'] ?? '') === 'image_url') {
                        $image = is_array($part['image_url'] ?? null) ? $part['image_url'] : [];
                        $url = (string)($image['url'] ?? '');
                        if (strlen($url) <= 3500000 && preg_match('#^data:image/jpeg;base64,[A-Za-z0-9+/]+={0,2}$#', $url) === 1) {
                            $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $url, 'detail' => 'low']];
                        }
                    }
                }
                if ($parts === []) continue;
                $content = $parts;
            } else {
                $content = trim((string)$content);
                if ($content === '') continue;
            }
            if ($role === 'developer') {
                $role = 'system';
                $content = 'Developer instructions:' . "\n" . $content;
            }
            if (!in_array($role, ['system', 'user', 'assistant'], true)) {
                $role = 'user';
            }
            $normalized[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $normalized !== [] ? $normalized : [
            [
                'role' => 'user',
                'content' => 'Respond to the AI Trade V2 prompt.',
            ],
        ];
    }

    private function healthPayload(bool $ok, string $status): array
    {
        return [
            'ok' => $ok,
            'provider' => $this->key(),
            'implemented' => true,
            'enabled' => $this->isEnabled(),
            'network' => true,
            'status' => $status,
            'api_key_configured' => $this->apiKey() !== null,
            'model_configured' => $this->defaultModel() !== null || $this->modelAliases() !== [],
            'endpoint' => $this->endpoint(),
        ];
    }

    private function endpoint(): string
    {
        return (string)($this->config['endpoint'] ?? self::DEFAULT_ENDPOINT);
    }

    private function apiKey(): ?string
    {
        $key = $this->stringConfig('api_key');
        if ($key !== null) {
            return $key;
        }

        return $this->envValue((string)($this->config['api_key_env'] ?? 'OPENAI_API_KEY'));
    }

    private function defaultModel(): ?string
    {
        $model = $this->stringConfig('default_model');
        if ($model !== null) {
            return $model;
        }

        return $this->envValue((string)($this->config['default_model_env'] ?? 'OPENAI_MODEL_DEFAULT'));
    }

    private function timeoutSeconds(): int
    {
        return max(1, min(120, (int)($this->config['timeout_seconds'] ?? 30)));
    }

    private function caBundlePath(): ?string
    {
        $projectRoot = dirname(__DIR__, 3);
        $candidates = [
            $this->stringConfig('ca_bundle_path'),
            $this->envValue('OPENAI_CA_BUNDLE'),
            $this->envValue('CURL_CA_BUNDLE'),
            $this->envValue('SSL_CERT_FILE'),
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            $projectRoot . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem',
            'C:\\xampp\\phpMyAdmin\\vendor\\composer\\ca-bundle\\res\\cacert.pem',
            'C:\\xampp\\perl\\vendor\\lib\\Mozilla\\CA\\cacert.pem',
            'C:\\xampp\\xammp2\\apps\\admin\\phpmyadmin\\vendor\\composer\\ca-bundle\\res\\cacert.pem',
        ];

        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }
            $path = trim((string)$candidate);
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string)$header, $matches)) {
                return (int)$matches[1];
            }
        }

        return 0;
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

    private function stringConfig(string $key): ?string
    {
        if (!isset($this->config[$key]) || !is_scalar($this->config[$key])) {
            return null;
        }

        $value = trim((string)$this->config[$key]);

        return $value === '' ? null : $value;
    }

    private function numericConfig(string $key): ?float
    {
        if (!isset($this->config[$key]) || !is_numeric($this->config[$key])) {
            return null;
        }

        return (float)$this->config[$key];
    }

    private function intConfig(string $key): ?int
    {
        if (!isset($this->config[$key]) || !is_numeric($this->config[$key])) {
            return null;
        }

        return (int)$this->config[$key];
    }

    private function envValue(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false || trim((string)$value) === '') {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function redactedRaw(array $raw): array
    {
        unset($raw['api_key'], $raw['authorization'], $raw['Authorization']);

        return $raw;
    }
}

final class OpenAIChatBudget
{
    private const USAGE_DIR = 'data/ai';

    public function __construct(private array $config = [], private ?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?: dirname(__DIR__, 3);
    }

    public function status(?string $model = null): array
    {
        $usage = $this->usageToday();
        $budget = $this->dailyBudgetUsd();
        $spent = (float)($usage['spent_usd'] ?? 0.0);
        $maxCalls = $this->maxCallsPerDay();
        $calls = (int)($usage['calls'] ?? 0);
        $allowed = $budget > 0 && $spent < $budget;
        if ($maxCalls > 0 && $calls >= $maxCalls) {
            $allowed = false;
        }

        return [
            'date' => $this->today(),
            'budget_usd' => $budget,
            'spent_usd' => round($spent, 8),
            'remaining_usd' => round(max(0.0, $budget - $spent), 8),
            'calls' => $calls,
            'max_calls' => $maxCalls,
            'allowed' => $allowed,
            'block_reason' => $this->blockReason($budget, $spent, $maxCalls, $calls),
            'model' => $model ?: '',
            'price' => $this->priceForModel($model ?: ''),
            'estimated' => true,
        ];
    }

    public function assertCanCall(?string $model = null, float $estimatedCallCostUsd = 0.0): void
    {
        $status = $this->status($model);
        if (($status['allowed'] ?? false) !== true) {
            throw new RuntimeException((string)($status['block_reason'] ?? 'OpenAI daily budget is exhausted.'));
        }

        $remaining = (float)($status['remaining_usd'] ?? 0.0);
        if ($estimatedCallCostUsd > 0 && $estimatedCallCostUsd > $remaining) {
            throw new RuntimeException(sprintf(
                'Call OpenAI này ước tính tốn khoảng $%.6f, vượt ngân sách còn lại hôm nay ($%.6f). Giảm output token, đổi model rẻ hơn hoặc tăng ngân sách/ngày.',
                $estimatedCallCostUsd,
                $remaining
            ));
        }
    }

    public function estimatePreflightCostUsd(string $model, array $messages, ?int $maxOutputTokens = null): float
    {
        $inputTokens = 24;
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $content = $message['content'] ?? '';
            if (is_array($content)) {
                $text = implode(' ', array_map(static fn(array $part): string => (string)($part['text'] ?? ''), array_filter($content, 'is_array')));
                $chars = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
                $hasImage = count(array_filter($content, static fn($part): bool => is_array($part) && ($part['type'] ?? '') === 'image_url')) > 0;
                $inputTokens += (int)ceil(max(0, $chars) / 3) + ($hasImage ? 900 : 0) + 8;
            } else {
                $content = (string)$content;
                $chars = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
                $inputTokens += (int)ceil(max(0, $chars) / 3) + 8;
            }
        }

        $outputTokens = max(64, (int)($maxOutputTokens ?? $this->configValue('max_completion_tokens') ?? 1200));
        $estimate = $this->estimateCostUsd($model, $inputTokens, $outputTokens);

        return round($estimate * $this->preflightMultiplier(), 8);
    }

    public function recordCall(string $model, array $usage): array
    {
        $input = max(0, (int)($usage['input_tokens'] ?? 0));
        $output = max(0, (int)($usage['output_tokens'] ?? 0));
        $cost = $this->estimateCostUsd($model, $input, $output);
        $record = $this->usageToday();
        $record['date'] = $this->today();
        $record['budget_usd'] = $this->dailyBudgetUsd();
        $record['spent_usd'] = round((float)($record['spent_usd'] ?? 0.0) + $cost, 8);
        $record['calls'] = (int)($record['calls'] ?? 0) + 1;
        $record['input_tokens'] = (int)($record['input_tokens'] ?? 0) + $input;
        $record['output_tokens'] = (int)($record['output_tokens'] ?? 0) + $output;
        $record['updated_at'] = gmdate('c');
        $record['last_model'] = $model;
        $record['last_cost_usd'] = round($cost, 8);
        $this->writeUsage($record);

        return [
            'amount' => round($cost, 8),
            'currency' => 'USD',
            'estimated' => true,
            'budget' => $this->status($model),
        ];
    }

    public function dailyBudgetUsd(): float
    {
        $value = $this->configValue('daily_budget_usd')
            ?? $this->envValue('OPENAI_DAILY_BUDGET_USD')
            ?? $this->envValue('AI_DAILY_BUDGET_USD')
            ?? '1';

        return round(max(0.0, (float)$value), 4);
    }

    public function maxCallsPerDay(): int
    {
        $value = $this->configValue('max_calls_per_day')
            ?? $this->envValue('OPENAI_MAX_CALLS_PER_DAY')
            ?? $this->envValue('AI_MAX_CALLS_PER_DAY')
            ?? '0';

        return max(0, (int)$value);
    }

    public function estimateCostUsd(string $model, int $inputTokens, int $outputTokens): float
    {
        $price = $this->priceForModel($model);

        return (($inputTokens / 1000000) * (float)$price['input_per_1m'])
            + (($outputTokens / 1000000) * (float)$price['output_per_1m']);
    }

    public function priceForModel(string $model): array
    {
        $model = strtolower(trim($model));
        $configuredInput = $this->envValue('OPENAI_PRICE_INPUT_PER_1M');
        $configuredOutput = $this->envValue('OPENAI_PRICE_OUTPUT_PER_1M');
        if ($configuredInput !== null && $configuredOutput !== null) {
            return ['input_per_1m' => (float)$configuredInput, 'output_per_1m' => (float)$configuredOutput, 'source' => 'env_override'];
        }

        $table = [
            'gpt-6-luna' => [0.10, 0.50],
            'gpt-6-sol' => [2.00, 10.00],
            'gpt-6-astra' => [10.00, 50.00],
            'gpt-5.6-luna' => [0.20, 1.20],
            'gpt-5.6-terra' => [2.00, 12.00],
            'gpt-5.6-sol' => [4.00, 20.00],
            'gpt-5.3-codex' => [1.75, 14.00],
            'gpt-5-chat-latest' => [1.25, 10.00],
            'chat-latest' => [5.00, 30.00],
        ];
        foreach ($table as $prefix => [$input, $output]) {
            if ($model === $prefix || str_starts_with($model, $prefix)) {
                return ['input_per_1m' => $input, 'output_per_1m' => $output, 'source' => 'official_standard_estimate_2026_09_26'];
            }
        }

        return ['input_per_1m' => 10.00, 'output_per_1m' => 50.00, 'source' => 'conservative_unknown_model_fallback'];
    }

    private function preflightMultiplier(): float
    {
        $value = $this->configValue('preflight_cost_multiplier')
            ?? $this->envValue('OPENAI_COST_SAFETY_MULTIPLIER')
            ?? '1.25';

        return max(1.0, min(5.0, (float)$value));
    }

    private function blockReason(float $budget, float $spent, int $maxCalls, int $calls): string
    {
        if ($budget <= 0) return 'Ngân sách OpenAI/ngày đang bằng 0 USD nên hệ thống không gọi API.';
        if ($spent >= $budget) return 'Đã chạm giới hạn chi phí OpenAI trong ngày.';
        if ($maxCalls > 0 && $calls >= $maxCalls) return 'Đã chạm giới hạn số lần gọi OpenAI trong ngày.';
        return '';
    }

    private function usageToday(): array
    {
        $path = $this->usagePath();
        if (!is_file($path)) return $this->emptyUsage();
        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded) || (string)($decoded['date'] ?? '') !== $this->today()) return $this->emptyUsage();
        return array_merge($this->emptyUsage(), $decoded);
    }

    private function emptyUsage(): array
    {
        return ['date' => $this->today(), 'budget_usd' => $this->dailyBudgetUsd(), 'spent_usd' => 0.0, 'calls' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'updated_at' => null];
    }

    private function writeUsage(array $usage): void
    {
        $path = $this->usagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $json = json_encode($usage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) throw new RuntimeException('Không thể ghi usage OpenAI.');
        file_put_contents($path, $json . PHP_EOL, LOCK_EX);
    }

    private function usagePath(): string
    {
        $projectRoot = rtrim((string)$this->projectRoot, DIRECTORY_SEPARATOR);
        $dir = $projectRoot . DIRECTORY_SEPARATOR . self::USAGE_DIR;
        if ((!is_dir($dir) && !@mkdir($dir, 0775, true)) || !is_writable($dir)) {
            $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_trade_openai_usage_' . substr(sha1($projectRoot), 0, 10);
        }

        return $dir . DIRECTORY_SEPARATOR . 'openai_usage_' . $this->today() . '.json';
    }

    private function today(): string
    {
        return gmdate('Y-m-d');
    }

    private function configValue(string $key): ?string
    {
        if (!isset($this->config[$key]) || !is_scalar($this->config[$key])) return null;
        $value = trim((string)$this->config[$key]);
        return $value === '' ? null : $value;
    }

    private function envValue(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false || trim((string)$value) === '') $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        if (!is_scalar($value)) return null;
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}