<?php

declare(strict_types=1);

return new class implements StorageMigrationInterface
{
    public function version(): string
    {
        return '001_storage_foundation';
    }

    public function description(): string
    {
        return 'Create V2 storage foundation tables for documents, events, and artifact metadata.';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS storage_documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                namespace TEXT NOT NULL,
                document_key TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(namespace, document_key)
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_storage_documents_namespace
             ON storage_documents(namespace)'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS storage_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT NOT NULL,
                aggregate_type TEXT NOT NULL,
                aggregate_id TEXT,
                payload_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_storage_events_aggregate
             ON storage_events(aggregate_type, aggregate_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_storage_events_type
             ON storage_events(event_type)'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS storage_artifacts (
                id TEXT PRIMARY KEY,
                artifact_type TEXT NOT NULL,
                relative_path TEXT NOT NULL,
                metadata_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_storage_artifacts_type
             ON storage_artifacts(artifact_type)'
        );
    }
};
