<?php

declare(strict_types=1);

require_once __DIR__ . '/../Gateway/ProviderInterface.php';

final class OllamaProvider implements ProviderInterface
{
    private const DEFAULT_ENDPOINT = 'http://localhost:11434';
    private const DEFAULT_MODEL = 'llama3.1:8b';

    /** @var callable|null */
    private $transport;

    public function __construct(private array $config = [], ?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public function key(): string
    {
        return 'ollama';
    }

    public function displayName(): string
    {
        return (string)($this->config['display_name'] ?? 'Ollama Local');
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
            'json_array' => true,
            'schema_bound' => true,
            'markdown_report' => true,
            'code_review_report' => true,
            'streaming' => false,
            'local' => true,
            'cloud' => false,
            'network' => false,
            'usage' => true,
            'api_key_required' => false,
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

        if (!$this->isLocalEndpoint($this->endpoint())) {
            return $this->healthPayload(false, 'invalid_endpoint', [
                'message' => 'Ollama endpoint must be localhost, 127.0.0.1, or ::1.',
            ]);
        }

        $model = $this->defaultModel();
        if ($model === null && $this->modelAliases() === []) {
            return $this->healthPayload(false, 'missing_model');
        }
        $model = $model ?? $this->resolveModel('default');

        $probe = $this->requestJson('GET', $this->apiUrl('/api/tags'), null, $this->healthTimeoutSeconds());
        if (($probe['ok'] ?? false) !== true) {
            return $this->healthPayload(false, 'ollama_unreachable', [
                'message' => $this->unreachableMessage((string)($probe['message'] ?? 'Ollama is not reachable.')),
            ]);
        }

        $models = $this->modelNames(is_array($probe['data'] ?? null) ? $probe['data'] : []);
        if ($model !== null && $models !== [] && !in_array($model, $models, true)) {
            return $this->healthPayload(false, 'model_not_found', [
                'known_models_count' => count($models),
            ]);
        }

        return $this->healthPayload(true, 'ready', [
            'known_models_count' => count($models),
        ]);
    }

    public function complete(array $request): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResponse($request, 'configuration_error', 'Ollama provider is disabled.');
        }

        if (!$this->isLocalEndpoint($this->endpoint())) {
            return $this->errorResponse($request, 'configuration_error', 'Ollama endpoint must be local. Use localhost, 127.0.0.1, or ::1.');
        }

        $modelAlias = $this->stringValue($request, ['model_alias', 'modelAlias', 'preferred_model', 'preferredModel']) ?: 'default';
        $model = $this->resolveModel($modelAlias);
        if ($model === null) {
            return $this->errorResponse($request, 'configuration_error', 'Ollama provider is missing a default model.');
        }

        $payload = [
            'model' => $model,
            'messages' => $this->messages($request),
            'stream' => false,
        ];

        $format = $this->stringValue($request, ['response_format', 'responseFormat', 'output_format', 'outputFormat']) ?: 'plain_text';
        if (in_array($format, ['json_object', 'json_array', 'schema_bound'], true)) {
            $payload['format'] = 'json';
        }

        $options = $this->options();
        if ($options !== []) {
            $payload['options'] = $options;
        }

        $response = $this->requestJson('POST', $this->apiUrl('/api/chat'), $payload, $this->timeoutSeconds());
        if (($response['ok'] ?? false) !== true) {
            return $this->errorResponse(
                $request,
                (string)($response['category'] ?? 'provider_error'),
                $this->providerErrorMessage((string)($response['message'] ?? 'Ollama request failed.'), $model),
                $modelAlias,
                $model,
                is_array($response['raw'] ?? null) ? $response['raw'] : []
            );
        }

        return $this->successResponse($request, $modelAlias, $model, is_array($response['data'] ?? null) ? $response['data'] : []);
    }

    private function successResponse(array $request, string $modelAlias, string $model, array $data): array
    {
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];
        $text = (string)($message['content'] ?? $data['response'] ?? '');
        $finishReason = (string)($data['done_reason'] ?? (($data['done'] ?? false) ? 'stop' : 'unknown'));
        $inputTokens = (int)($data['prompt_eval_count'] ?? 0);
        $outputTokens = (int)($data['eval_count'] ?? 0);

        if ($inputTokens === 0) {
            $inputTokens = $this->estimateTokens(json_encode($this->messages($request), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        }
        if ($outputTokens === 0) {
            $outputTokens = $this->estimateTokens($text);
        }

        return [
            'ok' => true,
            'provider' => $this->key(),
            'provider_response_id' => 'ollama_' . substr(hash('sha256', ($data['created_at'] ?? '') . $model . $text), 0, 16),
            'model_alias' => $modelAlias,
            'model_name' => (string)($data['model'] ?? $model),
            'finish_reason' => $finishReason,
            'text' => $text,
            'parsed' => null,
            'usage' => [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cached_tokens' => 0,
                'reasoning_tokens' => 0,
                'estimated' => !isset($data['prompt_eval_count'], $data['eval_count']),
            ],
            'cost' => [
                'amount' => 0.0,
                'currency' => 'USD',
                'estimated' => false,
            ],
            'error' => null,
            'metadata' => [
                'local' => true,
                'cloud' => false,
                'network' => false,
                'endpoint' => $this->endpoint(),
                'response_format' => $this->stringValue($request, ['response_format', 'responseFormat', 'output_format', 'outputFormat']) ?: 'plain_text',
                'total_duration_ns' => isset($data['total_duration']) ? (int)$data['total_duration'] : null,
                'load_duration_ns' => isset($data['load_duration']) ? (int)$data['load_duration'] : null,
            ],
            'raw' => [
                'created_at' => $data['created_at'] ?? null,
                'done' => $data['done'] ?? null,
                'done_reason' => $data['done_reason'] ?? null,
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
                'estimated' => false,
            ],
            'error' => [
                'category' => $category,
                'message' => $message,
            ],
            'metadata' => [
                'local' => true,
                'cloud' => false,
                'network' => false,
                'endpoint' => $this->endpoint(),
                'health' => $category === 'configuration_error' ? null : $this->health(),
            ],
            'raw' => $raw,
        ];
    }

    private function requestJson(string $method, string $url, ?array $payload, int $timeoutSeconds): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($method, $url, $payload, $timeoutSeconds);
        }

        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload !== null && $body === false) {
            return [
                'ok' => false,
                'category' => 'invalid_request',
                'message' => 'Unable to encode Ollama request payload.',
            ];
        }

        $headers = ['Content-Type: application/json'];

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                return [
                    'ok' => false,
                    'category' => 'network_error',
                    'message' => 'Unable to initialize Ollama HTTP client.',
                ];
            }

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSeconds),
            ];
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $body ?? '{}';
            }
            curl_setopt_array($curl, $options);
            $responseBody = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);
            curl_close($curl);

            if ($responseBody === false) {
                return [
                    'ok' => false,
                    'category' => $curlErrno === 28 ? 'timeout' : 'network_error',
                    'message' => $curlError !== '' ? $curlError : 'Ollama local request failed.',
                ];
            }

            return $this->decodeHttpResponse((string)$responseBody, $status);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $method === 'POST' ? ($body ?? '{}') : '',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        if ($responseBody === false) {
            $lastError = error_get_last();
            $message = is_array($lastError) ? (string)($lastError['message'] ?? '') : '';

            return [
                'ok' => false,
                'category' => str_contains(strtolower($message), 'timed out') ? 'timeout' : 'network_error',
                'message' => $message !== '' ? $message : 'Ollama local request failed.',
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
                'message' => 'Ollama response was not valid JSON.',
                'raw' => ['status' => $status],
            ];
        }

        if ($status < 200 || $status >= 300) {
            $message = (string)($decoded['error'] ?? 'Ollama returned HTTP ' . $status . '.');

            return [
                'ok' => false,
                'category' => $this->httpErrorCategory($status, $message),
                'message' => $message,
                'raw' => ['status' => $status],
            ];
        }

        return [
            'ok' => true,
            'data' => $decoded,
            'status' => $status,
        ];
    }

    private function httpErrorCategory(int $status, string $message): string
    {
        $lower = strtolower($message);
        if ($status === 408 || $status === 504) {
            return 'timeout';
        }
        if ($status === 404 || str_contains($lower, 'model') || str_contains($lower, 'pull')) {
            return 'configuration_error';
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
            $content = trim((string)($message['content'] ?? ''));
            if ($content === '') {
                continue;
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

    private function options(): array
    {
        $options = [];
        foreach ([
            'temperature' => 'temperature',
            'top_p' => 'top_p',
            'top_k' => 'top_k',
            'repeat_penalty' => 'repeat_penalty',
            'num_predict' => 'num_predict',
            'max_tokens' => 'num_predict',
            'max_completion_tokens' => 'num_predict',
        ] as $configKey => $optionKey) {
            if (isset($this->config[$configKey]) && is_numeric($this->config[$configKey])) {
                $options[$optionKey] = $configKey === 'temperature' || $configKey === 'top_p' || $configKey === 'repeat_penalty'
                    ? (float)$this->config[$configKey]
                    : (int)$this->config[$configKey];
            }
        }

        return $options;
    }

    private function modelNames(array $data): array
    {
        $models = is_array($data['models'] ?? null) ? $data['models'] : [];
        $names = [];
        foreach ($models as $model) {
            if (!is_array($model)) {
                continue;
            }
            foreach (['name', 'model'] as $key) {
                $value = $this->stringValue($model, [$key]);
                if ($value !== null) {
                    $names[] = $value;
                }
            }
        }

        return array_values(array_unique($names));
    }

    private function healthPayload(bool $ok, string $status, array $extra = []): array
    {
        return array_merge([
            'ok' => $ok,
            'provider' => $this->key(),
            'implemented' => true,
            'enabled' => $this->isEnabled(),
            'local' => true,
            'cloud' => false,
            'network' => false,
            'status' => $status,
            'api_key_required' => false,
            'api_key_configured' => true,
            'model_configured' => $this->defaultModel() !== null || $this->modelAliases() !== [],
            'endpoint_configured' => trim($this->endpoint()) !== '',
            'endpoint' => $this->endpoint(),
        ], $extra);
    }

    private function endpoint(): string
    {
        $endpoint = $this->envValue((string)($this->config['endpoint_env'] ?? 'OLLAMA_ENDPOINT'));
        if ($endpoint !== null) {
            return rtrim($endpoint, '/');
        }

        return rtrim((string)($this->config['endpoint'] ?? self::DEFAULT_ENDPOINT), '/');
    }

    private function defaultModel(): ?string
    {
        $model = $this->stringConfig('default_model');
        if ($model !== null) {
            return $model;
        }

        return $this->envValue((string)($this->config['default_model_env'] ?? 'OLLAMA_MODEL_DEFAULT')) ?? self::DEFAULT_MODEL;
    }

    private function apiUrl(string $path): string
    {
        return $this->endpoint() . $path;
    }

    private function timeoutSeconds(): int
    {
        return max(1, min(300, (int)($this->config['timeout_seconds'] ?? 120)));
    }

    private function healthTimeoutSeconds(): int
    {
        return max(1, min(30, (int)($this->config['health_timeout_seconds'] ?? 2)));
    }

    private function isLocalEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return $host === 'localhost'
            || $host === '::1'
            || $host === '0:0:0:0:0:0:0:1'
            || str_starts_with($host, '127.');
    }

    private function providerErrorMessage(string $message, string $model): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'connection') || str_contains($lower, 'refused') || str_contains($lower, 'failed') || str_contains($lower, 'reachable')) {
            return $this->unreachableMessage($message);
        }
        if (str_contains($lower, 'model') || str_contains($lower, 'pull')) {
            return 'Ollama model is not available locally. Run `ollama pull ' . $model . '` and retry. Provider said: ' . $message;
        }

        return $message;
    }

    private function unreachableMessage(string $message): string
    {
        return 'Ollama is not reachable at ' . $this->endpoint() . '. Install Ollama, start it with `ollama serve`, and pull the configured model. Provider said: ' . $message;
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

    private function estimateTokens(string $text): int
    {
        $length = strlen(trim($text));
        if ($length === 0) {
            return 0;
        }

        return max(1, (int)ceil($length / 4));
    }
}
