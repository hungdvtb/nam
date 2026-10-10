<?php

declare(strict_types=1);

final class ResponseParser
{
    public const MODES = [
        'plain_text',
        'json_object',
        'json_array',
        'schema_bound',
        'markdown_report',
        'code_review_report',
    ];

    public function parse(array|string $providerResponse, ?string $mode = null): array
    {
        $text = is_array($providerResponse) ? (string)($providerResponse['text'] ?? '') : (string)$providerResponse;
        $providerParsed = is_array($providerResponse) && array_key_exists('parsed', $providerResponse)
            ? $providerResponse['parsed']
            : null;
        $mode = $this->normalizeMode($mode);

        if (in_array($mode, ['json_object', 'schema_bound'], true)) {
            return $this->parseJsonObject($text, $mode);
        }

        if ($mode === 'json_array') {
            return $this->parseJsonArray($text);
        }

        return [
            'ok' => true,
            'mode' => $mode,
            'parsed' => is_array($providerParsed) ? $providerParsed : null,
            'validation_status' => trim($text) === '' ? 'empty' : 'valid',
            'warnings' => [],
            'error' => null,
        ];
    }

    private function parseJsonObject(string $text, string $mode): array
    {
        $decoded = $this->decodeJson($text, $mode);
        if (($decoded['ok'] ?? false) !== true) {
            return $decoded;
        }

        if (!is_array($decoded['parsed']) || array_is_list($decoded['parsed'])) {
            return $this->invalid($mode, 'Expected a JSON object response.');
        }

        return $decoded;
    }

    private function parseJsonArray(string $text): array
    {
        $decoded = $this->decodeJson($text, 'json_array');
        if (($decoded['ok'] ?? false) !== true) {
            return $decoded;
        }

        if (!is_array($decoded['parsed']) || !array_is_list($decoded['parsed'])) {
            return $this->invalid('json_array', 'Expected a JSON array response.');
        }

        return $decoded;
    }

    private function decodeJson(string $text, string $mode): array
    {
        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->invalid($mode, 'Invalid JSON response: ' . json_last_error_msg());
        }

        return [
            'ok' => true,
            'mode' => $mode,
            'parsed' => $decoded,
            'validation_status' => 'valid',
            'warnings' => [],
            'error' => null,
        ];
    }

    private function invalid(string $mode, string $message): array
    {
        return [
            'ok' => false,
            'mode' => $mode,
            'parsed' => null,
            'validation_status' => 'invalid',
            'warnings' => [],
            'error' => [
                'category' => 'invalid_response',
                'message' => $message,
            ],
        ];
    }

    private function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string)$mode));

        return in_array($mode, self::MODES, true) ? $mode : 'plain_text';
    }
}
