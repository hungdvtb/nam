<?php

declare(strict_types=1);

final class SqliteDocumentRepository implements DocumentRepositoryInterface
{
    private PDO $pdo;
    private string $namespace;

    public function __construct(PDO $pdo, string $namespace = 'default')
    {
        $this->pdo = $pdo;
        $this->namespace = $namespace;
    }

    public function storageName(): string
    {
        return 'sqlite_documents';
    }

    public function health(): array
    {
        $this->pdo->query('SELECT 1')->fetchColumn();

        return [
            'storage' => $this->storageName(),
            'namespace' => $this->namespace,
            'readable' => true,
        ];
    }

    public function read(string $documentName, mixed $fallback = []): mixed
    {
        $statement = $this->pdo->prepare(
            'SELECT payload_json FROM storage_documents WHERE namespace = :namespace AND document_key = :document_key'
        );
        $statement->execute([
            ':namespace' => $this->namespace,
            ':document_key' => $documentName,
        ]);

        $json = $statement->fetchColumn();
        if (!is_string($json) || trim($json) === '') {
            return $fallback;
        }

        $decoded = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
    }

    public function write(string $documentName, mixed $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode SQLite document: ' . $documentName);
        }

        $now = gmdate('c');
        if ($this->exists($documentName)) {
            $statement = $this->pdo->prepare(
                'UPDATE storage_documents
                 SET payload_json = :payload_json, updated_at = :updated_at
                 WHERE namespace = :namespace AND document_key = :document_key'
            );
            $statement->execute([
                ':payload_json' => $json,
                ':updated_at' => $now,
                ':namespace' => $this->namespace,
                ':document_key' => $documentName,
            ]);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO storage_documents (namespace, document_key, payload_json, created_at, updated_at)
             VALUES (:namespace, :document_key, :payload_json, :created_at, :updated_at)'
        );
        $statement->execute([
            ':namespace' => $this->namespace,
            ':document_key' => $documentName,
            ':payload_json' => $json,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function exists(string $documentName): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM storage_documents WHERE namespace = :namespace AND document_key = :document_key'
        );
        $statement->execute([
            ':namespace' => $this->namespace,
            ':document_key' => $documentName,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function list(string $prefix = ''): array
    {
        if ($prefix === '') {
            $statement = $this->pdo->prepare(
                'SELECT document_key, updated_at FROM storage_documents WHERE namespace = :namespace ORDER BY document_key'
            );
            $statement->execute([':namespace' => $this->namespace]);
            return $statement->fetchAll();
        }

        $statement = $this->pdo->prepare(
            'SELECT document_key, updated_at
             FROM storage_documents
             WHERE namespace = :namespace AND document_key LIKE :prefix
             ORDER BY document_key'
        );
        $statement->execute([
            ':namespace' => $this->namespace,
            ':prefix' => $prefix . '%',
        ]);

        return $statement->fetchAll();
    }
}
