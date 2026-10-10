<?php

declare(strict_types=1);

final class PromptTemplateRecord
{
    public const STATUSES = ['Draft', 'Review', 'Approved', 'Active', 'Deprecated', 'Archived'];

    public static function normalize(array $template): array
    {
        $now = gmdate('c');
        $record = [
            'id' => trim((string)($template['id'] ?? '')),
            'template_key' => trim((string)($template['template_key'] ?? $template['templateKey'] ?? '')),
            'version' => self::intPayload($template['version'] ?? 1),
            'name' => trim((string)($template['name'] ?? '')),
            'description' => (string)($template['description'] ?? ''),
            'owner_module' => trim((string)($template['owner_module'] ?? $template['ownerModule'] ?? '')),
            'status' => self::normalizeStatus((string)($template['status'] ?? 'Draft')),
            'system_prompt' => (string)($template['system_prompt'] ?? $template['systemPrompt'] ?? ''),
            'developer_prompt' => (string)($template['developer_prompt'] ?? $template['developerPrompt'] ?? ''),
            'user_prompt_template' => (string)($template['user_prompt_template'] ?? $template['userPromptTemplate'] ?? ''),
            'required_variables_json' => self::arrayPayload($template['required_variables_json'] ?? $template['requiredVariables'] ?? $template['required_variables'] ?? []),
            'optional_variables_json' => self::arrayPayload($template['optional_variables_json'] ?? $template['optionalVariables'] ?? $template['optional_variables'] ?? []),
            'output_format' => trim((string)($template['output_format'] ?? $template['outputFormat'] ?? 'plain_text')),
            'parser_mode' => trim((string)($template['parser_mode'] ?? $template['parserMode'] ?? 'plain_text')),
            'safety_level' => trim((string)($template['safety_level'] ?? $template['safetyLevel'] ?? 'normal')),
            'created_at' => (string)($template['created_at'] ?? $template['createdAt'] ?? $now),
            'updated_at' => (string)($template['updated_at'] ?? $template['updatedAt'] ?? $now),
            'notes' => (string)($template['notes'] ?? ''),
        ];

        $record['required_variables_json'] = self::normalizeVariableNames($record['required_variables_json']);
        $record['optional_variables_json'] = self::normalizeVariableNames($record['optional_variables_json']);

        return self::withAliases($record);
    }

    public static function validate(array $template): void
    {
        foreach (['id', 'template_key', 'name', 'owner_module', 'status', 'created_at', 'updated_at'] as $field) {
            if (trim((string)($template[$field] ?? '')) === '') {
                throw new InvalidArgumentException('Prompt template is missing required field: ' . $field);
            }
        }

        if ((int)$template['version'] < 1) {
            throw new InvalidArgumentException('Prompt template version must be greater than zero.');
        }

        if (!in_array((string)$template['status'], self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported prompt template status: ' . (string)$template['status']);
        }

        if (trim((string)$template['user_prompt_template']) === '') {
            throw new InvalidArgumentException('Prompt template user_prompt_template is required.');
        }

        foreach (['required_variables_json', 'optional_variables_json'] as $field) {
            if (!is_array($template[$field])) {
                throw new InvalidArgumentException('Prompt template variable list must be an array: ' . $field);
            }
        }
    }

    public static function normalizeStatus(string $status): string
    {
        $status = trim($status);
        foreach (self::STATUSES as $allowed) {
            if (strcasecmp($status, $allowed) === 0) {
                return $allowed;
            }
        }

        return $status === '' ? 'Draft' : $status;
    }

    private static function withAliases(array $record): array
    {
        $record['templateKey'] = $record['template_key'];
        $record['ownerModule'] = $record['owner_module'];
        $record['systemPrompt'] = $record['system_prompt'];
        $record['developerPrompt'] = $record['developer_prompt'];
        $record['userPromptTemplate'] = $record['user_prompt_template'];
        $record['requiredVariables'] = $record['required_variables_json'];
        $record['optionalVariables'] = $record['optional_variables_json'];
        $record['outputFormat'] = $record['output_format'];
        $record['parserMode'] = $record['parser_mode'];
        $record['safetyLevel'] = $record['safety_level'];
        $record['createdAt'] = $record['created_at'];
        $record['updatedAt'] = $record['updated_at'];

        return $record;
    }

    private static function intPayload(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 1;
        }

        return is_numeric($value) ? max(1, (int)$value) : 1;
    }

    private static function arrayPayload(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values($decoded);
            }

            return preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return [];
    }

    private static function normalizeVariableNames(array $variables): array
    {
        $normalized = [];
        foreach ($variables as $variable) {
            $name = trim((string)$variable);
            if ($name !== '' && !in_array($name, $normalized, true)) {
                $normalized[] = $name;
            }
        }

        return $normalized;
    }
}

