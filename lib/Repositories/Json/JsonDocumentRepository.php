<?php

declare(strict_types=1);

final class JsonDocumentRepository implements DocumentRepositoryInterface
{
    private JsonCompatibilityAdapter $adapter;

    public function __construct(JsonCompatibilityAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function storageName(): string
    {
        return 'legacy_json';
    }

    public function health(): array
    {
        return [
            'storage' => $this->storageName(),
            'datasets' => count($this->adapter->datasets()),
            'logs' => count($this->adapter->logs()),
            'readable' => true,
        ];
    }

    public function read(string $documentName, mixed $fallback = []): mixed
    {
        return $this->adapter->read($documentName, $fallback);
    }

    public function write(string $documentName, mixed $payload): void
    {
        $this->adapter->write($documentName, $payload);
    }

    public function exists(string $documentName): bool
    {
        return $this->adapter->hasDataset($documentName);
    }

    public function list(string $prefix = ''): array
    {
        $documents = [];
        foreach (array_keys($this->adapter->datasets()) as $name) {
            if ($prefix === '' || str_starts_with($name, $prefix)) {
                $documents[] = $name;
            }
        }

        return $documents;
    }
}
