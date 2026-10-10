<?php

declare(strict_types=1);

require_once __DIR__ . '/PromptTemplateRecord.php';

final class PromptRenderer
{
    public function render(array $template, array $variables): array
    {
        $template = PromptTemplateRecord::normalize($template);
        PromptTemplateRecord::validate($template);

        $required = $template['required_variables_json'];
        $optional = $template['optional_variables_json'];
        $missing = [];
        foreach ($required as $name) {
            if (!array_key_exists($name, $variables) || $variables[$name] === null || $variables[$name] === '') {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw new InvalidArgumentException('Missing required prompt variable(s): ' . implode(', ', $missing));
        }

        $messages = [];
        foreach ([
            'system' => $template['system_prompt'],
            'developer' => $template['developer_prompt'],
            'user' => $template['user_prompt_template'],
        ] as $role => $content) {
            $content = trim($this->renderString($content, $variables, $required, $optional));
            if ($content !== '') {
                $messages[] = [
                    'role' => $role,
                    'content' => $content,
                ];
            }
        }

        return [
            'template' => $template,
            'messages' => $messages,
            'output_format' => $template['output_format'],
            'parser_mode' => $template['parser_mode'],
            'safety_level' => $template['safety_level'],
            'used_variables' => array_values(array_intersect(array_keys($variables), array_merge($required, $optional))),
        ];
    }

    private function renderString(string $content, array $variables, array $required, array $optional): string
    {
        $known = array_merge($required, $optional);

        return preg_replace_callback('/{{\s*([A-Za-z0-9_.-]+)\s*}}/', function (array $match) use ($variables, $known): string {
            $name = $match[1];
            if (array_key_exists($name, $variables)) {
                return $this->stringValue($variables[$name]);
            }

            if (in_array($name, $known, true)) {
                return '';
            }

            throw new InvalidArgumentException('Unknown prompt variable placeholder: ' . $name);
        }, $content) ?? $content;
    }

    private function stringValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new RuntimeException('Unable to encode prompt variable value.');
            }

            return $json;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string)$value;
    }
}

