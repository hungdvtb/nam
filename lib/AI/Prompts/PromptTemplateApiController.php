<?php

declare(strict_types=1);

require_once __DIR__ . '/PromptManager.php';

final class PromptTemplateApiController
{
    public function __construct(private PromptManager $prompts)
    {
    }

    public function handle(string $action, array $payload): array
    {
        return match ($action) {
            'prompt_template_list' => $this->list($payload),
            'prompt_template_find' => $this->find($payload),
            'prompt_template_create' => $this->create($payload),
            'prompt_template_update' => $this->update($payload),
            'prompt_template_activate' => $this->activate($payload),
            'prompt_template_archive' => $this->archive($payload),
            default => throw new InvalidArgumentException('Unsupported prompt template API action: ' . $action),
        };
    }

    private function list(array $payload): array
    {
        $this->prompts->seedDefaultTemplates();

        return $this->payload($this->limit($payload), null, false, $this->filters($payload));
    }

    private function find(array $payload): array
    {
        $template = null;
        if ($this->id($payload, false) !== '') {
            $template = $this->prompts->findTemplate($this->id($payload));
        } else {
            $key = trim((string)($payload['template_key'] ?? $payload['templateKey'] ?? ''));
            if ($key === '') {
                throw new InvalidArgumentException('Prompt template id or template_key is required.');
            }
            $version = isset($payload['version']) && (int)$payload['version'] > 0 ? (int)$payload['version'] : null;
            $template = $version === null
                ? $this->prompts->findActiveTemplate($key)
                : $this->findByKeyVersion($key, $version);
        }

        if ($template === null) {
            throw new InvalidArgumentException('Prompt template not found.');
        }

        return $this->payload(template: $template);
    }

    private function create(array $payload): array
    {
        $template = $this->prompts->createTemplate($this->templatePayload($payload));

        return $this->payload(template: $template);
    }

    private function update(array $payload): array
    {
        $template = $this->prompts->updateTemplate($this->id($payload), $this->templatePayload($payload));

        return $this->payload(template: $template);
    }

    private function activate(array $payload): array
    {
        $template = $this->prompts->activateTemplate($this->id($payload));

        return $this->payload(template: $template);
    }

    private function archive(array $payload): array
    {
        $template = $this->prompts->archiveTemplate($this->id($payload));

        return $this->payload(template: $template, archived: true);
    }

    private function findByKeyVersion(string $templateKey, int $version): ?array
    {
        foreach ($this->prompts->listTemplates(500, ['template_key' => $templateKey]) as $template) {
            if ((int)$template['version'] === $version) {
                return $template;
            }
        }

        return null;
    }

    private function payload(int $limit = 100, ?array $template = null, bool $archived = false, array $filters = []): array
    {
        return [
            'ok' => true,
            'template' => $template,
            'templates' => $this->prompts->listTemplates($limit, $filters),
            'archived' => $archived,
        ];
    }

    private function templatePayload(array $payload): array
    {
        if (isset($payload['template']) && is_array($payload['template'])) {
            return array_merge($payload, $payload['template']);
        }

        return $payload;
    }

    private function id(array $payload, bool $required = true): string
    {
        $id = trim((string)($payload['id'] ?? $payload['template_id'] ?? $payload['templateId'] ?? ''));
        if ($required && $id === '') {
            throw new InvalidArgumentException('Prompt template id is required.');
        }

        return $id;
    }

    private function filters(array $payload): array
    {
        $filters = [];
        foreach (['template_key', 'status', 'owner_module'] as $field) {
            if (isset($payload[$field]) && trim((string)$payload[$field]) !== '') {
                $filters[$field] = (string)$payload[$field];
            }
        }

        return $filters;
    }

    private function limit(array $payload): int
    {
        return max(1, min(500, (int)($payload['limit'] ?? 100)));
    }
}

