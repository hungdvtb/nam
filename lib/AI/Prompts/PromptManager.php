<?php

declare(strict_types=1);

require_once __DIR__ . '/PromptTemplateRecord.php';
require_once __DIR__ . '/PromptRenderer.php';
require_once __DIR__ . '/../../Repositories/Contracts/AiTradeRepositoryInterface.php';
require_once __DIR__ . '/../../Repositories/Contracts/PromptTemplateRepositoryInterface.php';

final class PromptManager
{
    public function __construct(
        private PromptTemplateRepositoryInterface $templates,
        private ?PromptRenderer $renderer = null
    ) {
        $this->renderer = $this->renderer ?? new PromptRenderer();
    }

    public function seedDefaultTemplates(): void
    {
        foreach (self::defaultTemplates() as $template) {
            if ($this->templates->findByKeyVersion((string)$template['template_key'], (int)$template['version']) === null) {
                $this->templates->save($template);
            }
        }
    }

    public function createTemplate(array $payload): array
    {
        $now = gmdate('c');
        if (isset($payload['id']) && trim((string)$payload['id']) === '') {
            unset($payload['id']);
        }
        $templateKey = trim((string)($payload['template_key'] ?? $payload['templateKey'] ?? ''));
        $version = isset($payload['version']) && (int)$payload['version'] > 0
            ? (int)$payload['version']
            : $this->nextVersion($templateKey);

        $template = PromptTemplateRecord::normalize(array_merge([
            'id' => $this->newId($templateKey),
            'version' => $version,
            'created_at' => $now,
            'updated_at' => $now,
            'status' => 'Draft',
        ], $payload));
        PromptTemplateRecord::validate($template);

        if ($this->templates->findByKeyVersion((string)$template['template_key'], (int)$template['version']) !== null) {
            throw new InvalidArgumentException('Prompt template key/version already exists: ' . $template['template_key'] . ' v' . $template['version']);
        }

        return $this->templates->save($template);
    }

    public function updateTemplate(string $id, array $changes): array
    {
        $current = $this->findTemplate($id);
        if ($current === null) {
            throw new InvalidArgumentException('Prompt template not found: ' . $id);
        }

        unset($changes['id'], $changes['created_at'], $changes['createdAt']);
        $updated = PromptTemplateRecord::normalize(array_merge($current, $changes, ['updated_at' => gmdate('c')]));
        PromptTemplateRecord::validate($updated);

        $sameKeyVersion = $this->templates->findByKeyVersion((string)$updated['template_key'], (int)$updated['version']);
        if ($sameKeyVersion !== null && $sameKeyVersion['id'] !== $id) {
            throw new InvalidArgumentException('Prompt template key/version already exists: ' . $updated['template_key'] . ' v' . $updated['version']);
        }

        return $this->templates->save($updated);
    }

    public function activateTemplate(string $id): array
    {
        return $this->templates->activate($id);
    }

    public function archiveTemplate(string $id): array
    {
        return $this->templates->archive($id);
    }

    public function findTemplate(string $id): ?array
    {
        return $this->templates->find($id);
    }

    public function findActiveTemplate(string $templateKey): ?array
    {
        return $this->templates->active($templateKey);
    }

    public function listTemplates(int $limit = 100, array $filters = []): array
    {
        return $this->templates->list($limit, $filters);
    }

    public function render(string $templateKey, array $variables, ?int $version = null): array
    {
        $template = $version === null
            ? $this->templates->active($templateKey)
            : $this->templates->findByKeyVersion($templateKey, $version);

        if ($template === null) {
            throw new InvalidArgumentException('Prompt template not found or not active: ' . $templateKey);
        }

        return $this->renderer->render($template, $variables);
    }

    public static function defaultTemplates(): array
    {
        $now = gmdate('c');
        $system = 'You are an AI Trade V2 research assistant. All trading outputs are proposals only and require validation.';

        return [
            [
                'id' => 'prompt_strategy_idea_generate_v1',
                'template_key' => 'strategy.idea.generate',
                'version' => 1,
                'name' => 'Generate strategy idea',
                'description' => 'Create a proposal-only trading strategy idea from research context.',
                'owner_module' => 'StrategyAI',
                'status' => 'Active',
                'system_prompt' => $system,
                'developer_prompt' => 'Return structured proposal content. Do not approve live trading or production use.',
                'user_prompt_template' => "Research goal:\n{{research_goal}}\n\nStrategy context:\n{{strategy_context}}\n\nRelevant memory:\n{{memory_lessons}}",
                'required_variables_json' => ['research_goal', 'strategy_context'],
                'optional_variables_json' => ['memory_lessons'],
                'output_format' => 'json_object',
                'parser_mode' => 'schema_bound',
                'safety_level' => 'trading_research',
                'created_at' => $now,
                'updated_at' => $now,
                'notes' => 'Seeded by Sprint 019.',
            ],
            [
                'id' => 'prompt_strategy_version_improve_v1',
                'template_key' => 'strategy.version.improve',
                'version' => 1,
                'name' => 'Improve strategy version',
                'description' => 'Suggest proposal-only improvements for an existing strategy version.',
                'owner_module' => 'StrategyAI',
                'status' => 'Active',
                'system_prompt' => $system,
                'developer_prompt' => 'Suggest only validated research changes. Keep output proposal-only.',
                'user_prompt_template' => "Current rules:\n{{current_rules}}\n\nBacktest summary:\n{{backtest_summary}}\n\nKnown weaknesses:\n{{known_weaknesses}}",
                'required_variables_json' => ['current_rules', 'backtest_summary'],
                'optional_variables_json' => ['known_weaknesses'],
                'output_format' => 'json_object',
                'parser_mode' => 'schema_bound',
                'safety_level' => 'trading_research',
                'created_at' => $now,
                'updated_at' => $now,
                'notes' => 'Seeded by Sprint 019.',
            ],
            [
                'id' => 'prompt_backtest_summary_v1',
                'template_key' => 'backtest.summary',
                'version' => 1,
                'name' => 'Backtest summary',
                'description' => 'Summarize backtest metrics and diagnostics for human review.',
                'owner_module' => 'AnalysisAI',
                'status' => 'Active',
                'system_prompt' => $system,
                'developer_prompt' => 'Explain evidence, weak spots, and next review steps. Do not propose live execution.',
                'user_prompt_template' => "Backtest summary:\n{{backtest_summary}}\n\nDiagnostics:\n{{diagnostics}}\n\nStrategy version:\n{{strategy_version_id}}",
                'required_variables_json' => ['backtest_summary', 'diagnostics'],
                'optional_variables_json' => ['strategy_version_id'],
                'output_format' => 'markdown_report',
                'parser_mode' => 'markdown_report',
                'safety_level' => 'analysis',
                'created_at' => $now,
                'updated_at' => $now,
                'notes' => 'Seeded by Sprint 019.',
            ],
            [
                'id' => 'prompt_experiment_compare_v1',
                'template_key' => 'experiment.compare',
                'version' => 1,
                'name' => 'Compare experiment candidates',
                'description' => 'Compare experiment candidates and summarize the strongest research candidate.',
                'owner_module' => 'OptimizerAI',
                'status' => 'Active',
                'system_prompt' => $system,
                'developer_prompt' => 'Rank candidates using validation evidence and risk notes. Keep result proposal-only.',
                'user_prompt_template' => "Experiment summary:\n{{experiment_summary}}\n\nCandidates:\n{{candidates}}\n\nSelection criteria:\n{{selection_criteria}}",
                'required_variables_json' => ['experiment_summary', 'candidates'],
                'optional_variables_json' => ['selection_criteria'],
                'output_format' => 'json_object',
                'parser_mode' => 'schema_bound',
                'safety_level' => 'trading_research',
                'created_at' => $now,
                'updated_at' => $now,
                'notes' => 'Seeded by Sprint 019.',
            ],
        ];
    }

    private function nextVersion(string $templateKey): int
    {
        if ($templateKey === '') {
            return 1;
        }

        $max = 0;
        foreach ($this->templates->list(500, ['template_key' => $templateKey]) as $template) {
            $max = max($max, (int)$template['version']);
        }

        return $max + 1;
    }

    private function newId(string $templateKey): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($templateKey)) ?: 'prompt';

        return 'prompt_' . trim($slug, '_') . '_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
    }
}
