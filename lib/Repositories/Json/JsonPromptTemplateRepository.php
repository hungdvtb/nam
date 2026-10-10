<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/PromptTemplateRepositoryInterface.php';
require_once __DIR__ . '/../../AI/Prompts/PromptTemplateRecord.php';

final class JsonPromptTemplateRepository implements PromptTemplateRepositoryInterface
{
    private const FILE = 'ai/prompt_templates.json';

    public function __construct(private JsonStore $store)
    {
    }

    public function storageName(): string
    {
        return 'json_prompt_templates';
    }

    public function health(): array
    {
        return [
            'storage' => $this->storageName(),
            'file' => self::FILE,
            'readable' => true,
        ];
    }

    public function save(array $template): array
    {
        $template = PromptTemplateRecord::normalize($template);
        PromptTemplateRecord::validate($template);

        $templates = $this->readAll();
        $index = $this->indexOf($templates, (string)$template['id']);
        if ($index >= 0) {
            $templates[$index] = $template;
        } else {
            $templates[] = $template;
        }
        $this->writeAll($templates);

        return $this->find((string)$template['id']) ?? $template;
    }

    public function find(string $id): ?array
    {
        foreach ($this->readAll() as $template) {
            if ($template['id'] === $id) {
                return $template;
            }
        }

        return null;
    }

    public function findByKeyVersion(string $templateKey, int $version): ?array
    {
        foreach ($this->readAll() as $template) {
            if ($template['template_key'] === $templateKey && (int)$template['version'] === $version) {
                return $template;
            }
        }

        return null;
    }

    public function active(string $templateKey): ?array
    {
        $matches = array_values(array_filter(
            $this->readAll(),
            fn(array $template): bool => $template['template_key'] === $templateKey && $template['status'] === 'Active'
        ));
        usort($matches, fn(array $a, array $b): int => [(int)$b['version'], $b['updated_at'], $b['id']] <=> [(int)$a['version'], $a['updated_at'], $a['id']]);

        return $matches[0] ?? null;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        $templates = $this->readAll();
        $templates = array_values(array_filter($templates, function (array $template) use ($filters): bool {
            foreach (['template_key', 'status', 'owner_module'] as $field) {
                if (!isset($filters[$field]) || trim((string)$filters[$field]) === '') {
                    continue;
                }
                $expected = $field === 'status'
                    ? PromptTemplateRecord::normalizeStatus((string)$filters[$field])
                    : (string)$filters[$field];
                if ((string)$template[$field] !== $expected) {
                    return false;
                }
            }

            return true;
        }));
        usort($templates, fn(array $a, array $b): int => [$a['template_key'], -(int)$a['version'], $b['updated_at']] <=> [$b['template_key'], -(int)$b['version'], $a['updated_at']]);

        return $limit > 0 ? array_slice($templates, 0, $limit) : $templates;
    }

    public function activate(string $id): array
    {
        $templates = $this->readAll();
        $index = $this->indexOf($templates, $id);
        if ($index < 0) {
            throw new InvalidArgumentException('Prompt template not found: ' . $id);
        }
        if ($templates[$index]['status'] === 'Archived') {
            throw new InvalidArgumentException('Archived prompt template cannot be activated.');
        }

        $now = gmdate('c');
        $templateKey = $templates[$index]['template_key'];
        foreach ($templates as $i => $template) {
            if ($template['template_key'] === $templateKey && $template['status'] === 'Active' && $template['id'] !== $id) {
                $templates[$i]['status'] = 'Deprecated';
                $templates[$i]['updated_at'] = $now;
                $templates[$i] = PromptTemplateRecord::normalize($templates[$i]);
            }
        }
        $templates[$index]['status'] = 'Active';
        $templates[$index]['updated_at'] = $now;
        $templates[$index] = PromptTemplateRecord::normalize($templates[$index]);
        $this->writeAll($templates);

        return $this->find($id) ?? $templates[$index];
    }

    public function archive(string $id): array
    {
        $templates = $this->readAll();
        $index = $this->indexOf($templates, $id);
        if ($index < 0) {
            throw new InvalidArgumentException('Prompt template not found: ' . $id);
        }

        $templates[$index]['status'] = 'Archived';
        $templates[$index]['updated_at'] = gmdate('c');
        $templates[$index] = PromptTemplateRecord::normalize($templates[$index]);
        $this->writeAll($templates);

        return $this->find($id) ?? $templates[$index];
    }

    private function readAll(): array
    {
        $templates = $this->store->readJson(self::FILE, []);
        if (!is_array($templates)) {
            return [];
        }

        return array_map(fn(array $template): array => PromptTemplateRecord::normalize($template), array_values(array_filter($templates, 'is_array')));
    }

    private function writeAll(array $templates): void
    {
        $this->store->writeJson(self::FILE, array_values(array_map(
            fn(array $template): array => PromptTemplateRecord::normalize($template),
            $templates
        )));
    }

    private function indexOf(array $templates, string $id): int
    {
        foreach ($templates as $index => $template) {
            if (($template['id'] ?? '') === $id) {
                return $index;
            }
        }

        return -1;
    }
}

