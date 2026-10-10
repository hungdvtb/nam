<?php

declare(strict_types=1);

return new class implements StorageMigrationInterface
{
    public function version(): string
    {
        return '015_prompt_templates';
    }

    public function description(): string
    {
        return 'Create AI prompt template table and seed default Prompt Manager templates.';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_prompt_templates (
                id TEXT PRIMARY KEY,
                template_key TEXT NOT NULL,
                version INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                owner_module TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL CHECK(status IN ('Draft', 'Review', 'Approved', 'Active', 'Deprecated', 'Archived')),
                system_prompt TEXT NOT NULL DEFAULT '',
                developer_prompt TEXT NOT NULL DEFAULT '',
                user_prompt_template TEXT NOT NULL DEFAULT '',
                required_variables_json TEXT NOT NULL DEFAULT '[]',
                optional_variables_json TEXT NOT NULL DEFAULT '[]',
                output_format TEXT NOT NULL DEFAULT 'plain_text',
                parser_mode TEXT NOT NULL DEFAULT 'plain_text',
                safety_level TEXT NOT NULL DEFAULT 'normal',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                notes TEXT NOT NULL DEFAULT '',
                UNIQUE(template_key, version)
            )"
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_ai_prompt_templates_key
             ON ai_prompt_templates(template_key)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_ai_prompt_templates_status
             ON ai_prompt_templates(status)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_ai_prompt_templates_owner
             ON ai_prompt_templates(owner_module)'
        );

        foreach ($this->defaultTemplates() as $template) {
            $statement = $pdo->prepare(
                'INSERT OR IGNORE INTO ai_prompt_templates (
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
            $statement->execute($template);
        }
    }

    private function defaultTemplates(): array
    {
        $now = gmdate('c');
        $system = 'You are an AI Trade V2 research assistant. All trading outputs are proposals only and require validation.';

        return [
            [
                ':id' => 'prompt_strategy_idea_generate_v1',
                ':template_key' => 'strategy.idea.generate',
                ':version' => 1,
                ':name' => 'Generate strategy idea',
                ':description' => 'Create a proposal-only trading strategy idea from research context.',
                ':owner_module' => 'StrategyAI',
                ':status' => 'Active',
                ':system_prompt' => $system,
                ':developer_prompt' => 'Return structured proposal content. Do not approve live trading or production use.',
                ':user_prompt_template' => "Research goal:\n{{research_goal}}\n\nStrategy context:\n{{strategy_context}}\n\nRelevant memory:\n{{memory_lessons}}",
                ':required_variables_json' => json_encode(['research_goal', 'strategy_context'], JSON_UNESCAPED_SLASHES),
                ':optional_variables_json' => json_encode(['memory_lessons'], JSON_UNESCAPED_SLASHES),
                ':output_format' => 'json_object',
                ':parser_mode' => 'schema_bound',
                ':safety_level' => 'trading_research',
                ':created_at' => $now,
                ':updated_at' => $now,
                ':notes' => 'Seeded by Sprint 019.',
            ],
            [
                ':id' => 'prompt_strategy_version_improve_v1',
                ':template_key' => 'strategy.version.improve',
                ':version' => 1,
                ':name' => 'Improve strategy version',
                ':description' => 'Suggest proposal-only improvements for an existing strategy version.',
                ':owner_module' => 'StrategyAI',
                ':status' => 'Active',
                ':system_prompt' => $system,
                ':developer_prompt' => 'Suggest only validated research changes. Keep output proposal-only.',
                ':user_prompt_template' => "Current rules:\n{{current_rules}}\n\nBacktest summary:\n{{backtest_summary}}\n\nKnown weaknesses:\n{{known_weaknesses}}",
                ':required_variables_json' => json_encode(['current_rules', 'backtest_summary'], JSON_UNESCAPED_SLASHES),
                ':optional_variables_json' => json_encode(['known_weaknesses'], JSON_UNESCAPED_SLASHES),
                ':output_format' => 'json_object',
                ':parser_mode' => 'schema_bound',
                ':safety_level' => 'trading_research',
                ':created_at' => $now,
                ':updated_at' => $now,
                ':notes' => 'Seeded by Sprint 019.',
            ],
            [
                ':id' => 'prompt_backtest_summary_v1',
                ':template_key' => 'backtest.summary',
                ':version' => 1,
                ':name' => 'Backtest summary',
                ':description' => 'Summarize backtest metrics and diagnostics for human review.',
                ':owner_module' => 'AnalysisAI',
                ':status' => 'Active',
                ':system_prompt' => $system,
                ':developer_prompt' => 'Explain evidence, weak spots, and next review steps. Do not propose live execution.',
                ':user_prompt_template' => "Backtest summary:\n{{backtest_summary}}\n\nDiagnostics:\n{{diagnostics}}\n\nStrategy version:\n{{strategy_version_id}}",
                ':required_variables_json' => json_encode(['backtest_summary', 'diagnostics'], JSON_UNESCAPED_SLASHES),
                ':optional_variables_json' => json_encode(['strategy_version_id'], JSON_UNESCAPED_SLASHES),
                ':output_format' => 'markdown_report',
                ':parser_mode' => 'markdown_report',
                ':safety_level' => 'analysis',
                ':created_at' => $now,
                ':updated_at' => $now,
                ':notes' => 'Seeded by Sprint 019.',
            ],
            [
                ':id' => 'prompt_experiment_compare_v1',
                ':template_key' => 'experiment.compare',
                ':version' => 1,
                ':name' => 'Compare experiment candidates',
                ':description' => 'Compare experiment candidates and summarize the strongest research candidate.',
                ':owner_module' => 'OptimizerAI',
                ':status' => 'Active',
                ':system_prompt' => $system,
                ':developer_prompt' => 'Rank candidates using validation evidence and risk notes. Keep result proposal-only.',
                ':user_prompt_template' => "Experiment summary:\n{{experiment_summary}}\n\nCandidates:\n{{candidates}}\n\nSelection criteria:\n{{selection_criteria}}",
                ':required_variables_json' => json_encode(['experiment_summary', 'candidates'], JSON_UNESCAPED_SLASHES),
                ':optional_variables_json' => json_encode(['selection_criteria'], JSON_UNESCAPED_SLASHES),
                ':output_format' => 'json_object',
                ':parser_mode' => 'schema_bound',
                ':safety_level' => 'trading_research',
                ':created_at' => $now,
                ':updated_at' => $now,
                ':notes' => 'Seeded by Sprint 019.',
            ],
        ];
    }
};

