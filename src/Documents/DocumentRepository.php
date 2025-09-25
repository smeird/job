<?php

declare(strict_types=1);

namespace App\Documents;

use DateTimeImmutable;
use PDO;

class DocumentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->ensureSchema();
    }

    public function save(Document $document): Document
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO documents (filename, mime_type, size_bytes, sha256, content, created_at) VALUES (:filename, :mime_type, :size_bytes, :sha256, :content, :created_at)'
        );

        $createdAt = $document->createdAt()->format('Y-m-d H:i:s');

        $statement->bindValue(':filename', $document->filename());
        $statement->bindValue(':mime_type', $document->mimeType());
        $statement->bindValue(':size_bytes', $document->sizeBytes(), PDO::PARAM_INT);
        $statement->bindValue(':sha256', $document->sha256());
        $statement->bindValue(':content', $document->content(), PDO::PARAM_LOB);
        $statement->bindValue(':created_at', $createdAt);

        $statement->execute();

        $id = (int) $this->pdo->lastInsertId();

        return $document->withId($id);
    }

    public function find(int $id): ?Document
    {
        $statement = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return new Document(
            (int) $row['id'],
            $row['filename'],
            $row['mime_type'],
            (int) $row['size_bytes'],
            $row['sha256'],
            is_resource($row['content']) ? stream_get_contents($row['content']) ?: '' : (string) $row['content'],
            new DateTimeImmutable($row['created_at']),
        );
    }

    private function ensureSchema(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS documents (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    filename VARCHAR(255) NOT NULL,
                    mime_type VARCHAR(191) NOT NULL,
                    size_bytes BIGINT UNSIGNED NOT NULL,
                    sha256 CHAR(64) NOT NULL UNIQUE,
                    content LONGBLOB NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                sha256 TEXT NOT NULL UNIQUE,
                content BLOB NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }
}
