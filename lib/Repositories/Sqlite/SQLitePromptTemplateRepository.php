<?php

declare(strict_types=1);

require_once __DIR__ . '/../Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../Contracts/PromptTemplateRepositoryInterface.php';
require_once __DIR__ . '/../../AI/Prompts/PromptTemplateRecord.php';

final class SQLitePromptTemplateRepository implements PromptTemplateRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function storageName(): string
    {
        return 'sqlite_prompt_templates';
    }

    public function health(): array
    {
        $this->pdo->query('SELECT 1')->fetchColumn();

        return [
            'storage' => $this->storageName(),
            'table' => 'ai_prompt_templates',
            'readable' => true,
        ];
    }

    public function save(array $template): array
    {
        $template = PromptTemplateRecord::normalize($template);
        PromptTemplateRecord::validate($template);

        if ($this->find((string)$template['id']) === null) {
            $this->insert($template);
        } else {
            $this->update($template);
        }

        return $this->find((string)$template['id']) ?? $template;
    }

    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ai_prompt_templates WHERE id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function findByKeyVersion(string $templateKey, int $version): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ai_prompt_templates WHERE template_key = :template_key AND version = :version');
        $statement->execute([
            ':template_key' => $templateKey,
            ':version' => $version,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function active(string $templateKey): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM ai_prompt_templates
             WHERE template_key = :template_key AND status = 'Active'
             ORDER BY version DESC, updated_at DESC, id DESC
             LIMIT 1"
        );
        $statement->execute([':template_key' => $templateKey]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function list(int $limit = 100, array $filters = []): array
    {
        $conditions = [];
        $params = [];

        foreach (['template_key', 'status', 'owner_module'] as $field) {
            if (!isset($filters[$field]) || trim((string)$filters[$field]) === '') {
                continue;
            }
            $conditions[] = $field . ' = :' . $field;
            $params[':' . $field] = $field === 'status'
                ? PromptTemplateRecord::normalizeStatus((string)$filters[$field])
                : (string)$filters[$field];
        }

        $sql = 'SELECT * FROM ai_prompt_templates';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY template_key ASC, version DESC, updated_at DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn(array $row): array => $this->mapRow($row), $statement->fetchAll());
    }

    public function activate(string $id): array
    {
        $template = $this->find($id);
        if ($template === null) {
            throw new InvalidArgumentException('Prompt template not found: ' . $id);
        }
        if ($template['status'] === 'Archived') {
            throw new InvalidArgumentException('Archived prompt template cannot be activated.');
        }

        $this->pdo->beginTransaction();
        try {
            $now = gmdate('c');
            $deprecate = $this->pdo->prepare(
                "UPDATE ai_prompt_templates
                 SET status = 'Deprecated', updated_at = :updated_at
                 WHERE template_key = :template_key AND status = 'Active' AND id <> :id"
            );
            $deprecate->execute([
                ':template_key' => $template['template_key'],
                ':id' => $id,
                ':updated_at' => $now,
            ]);

            $activate = $this->pdo->prepare(
                "UPDATE ai_prompt_templates
                 SET status = 'Active', updated_at = :updated_at
                 WHERE id = :id"
            );
            $activate->execute([
                ':id' => $id,
                ':updated_at' => $now,
            ]);

            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }

        return $this->find($id) ?? $template;
    }

    public function archive(string $id): array
    {
        $template = $this->find($id);
        if ($template === null) {
            throw new InvalidArgumentException('Prompt template not found: ' . $id);
        }

        $statement = $this->pdo->prepare(
            "UPDATE ai_prompt_templates
             SET status = 'Archived', updated_at = :updated_at
             WHERE id = :id"
        );
        $statement->execute([
            ':id' => $id,
            ':updated_at' => gmdate('c'),
        ]);

        return $this->find($id) ?? $template;
    }

    private function insert(array $template): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ai_prompt_templates (
                id, template_key, version, name, description, owner_module, status,
                system_prompt, developer_prompt, user_prompt_template,
                required_variables_json, optional_variables_json,
                output_format, parser_mode, safety_level,
                created_at, updated_at, notes
            ) VALUES (
                :id, :template_key, :version, :name, :description, :owner_module, :status,
                :system_prompt, :developer_prompt, :user_prompt_template,
                :required_variables_json, :optional_variables_json,
                :output_format, :parser_mode, :safety_level,
                :created_at, :updated_at, :notes
            )'
        );
        $statement->execute($this->params($template));
    }

    private function update(array $template): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE ai_prompt_templates
             SET template_key = :template_key,
                 version = :version,
                 name = :name,
                 description = :description,
                 owner_module = :owner_module,
                 status = :status,
                 system_prompt = :system_prompt,
                 developer_prompt = :developer_prompt,
                 user_prompt_template = :user_prompt_template,
                 required_variables_json = :required_variables_json,
                 optional_variables_json = :optional_variables_json,
                 output_format = :output_format,
                 parser_mode = :parser_mode,
                 safety_level = :safety_level,
                 created_at = :created_at,
                 updated_at = :updated_at,
                 notes = :notes
             WHERE id = :id'
        );
        $statement->execute($this->params($template));
    }

    private function params(array $template): array
    {
        return [
            ':id' => $template['id'],
            ':template_key' => $template['template_key'],
            ':version' => $template['version'],
            ':name' => $template['name'],
            ':description' => $template['description'],
            ':owner_module' => $template['owner_module'],
            ':status' => $template['status'],
            ':system_prompt' => $template['system_prompt'],
            ':developer_prompt' => $template['developer_prompt'],
            ':user_prompt_template' => $template['user_prompt_template'],
            ':required_variables_json' => $this->encodeJson($template['required_variables_json']),
            ':optional_variables_json' => $this->encodeJson($template['optional_variables_json']),
            ':output_format' => $template['output_format'],
            ':parser_mode' => $template['parser_mode'],
            ':safety_level' => $template['safety_level'],
            ':created_at' => $template['created_at'],
            ':updated_at' => $template['updated_at'],
            ':notes' => $template['notes'],
        ];
    }

    private function mapRow(array $row): array
    {
        return PromptTemplateRecord::normalize([
            'id' => $row['id'],
            'template_key' => $row['template_key'],
            'version' => $row['version'],
            'name' => $row['name'],
            'description' => $row['description'],
            'owner_module' => $row['owner_module'],
            'status' => $row['status'],
            'system_prompt' => $row['system_prompt'],
            'developer_prompt' => $row['developer_prompt'],
            'user_prompt_template' => $row['user_prompt_template'],
            'required_variables_json' => $row['required_variables_json'],
            'optional_variables_json' => $row['optional_variables_json'],
            'output_format' => $row['output_format'],
            'parser_mode' => $row['parser_mode'],
            'safety_level' => $row['safety_level'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'notes' => $row['notes'],
        ]);
    }

    private function encodeJson(array $payload): string
    {
        $json = json_encode(array_values($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode prompt template JSON payload.');
        }

        return $json;
    }
}

