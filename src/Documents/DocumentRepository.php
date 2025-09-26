<?php

declare(strict_types=1);

namespace App\Documents;

use DateTimeImmutable;
use PDO;

class DocumentRepository
{
    /** @var PDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    /**
     * Handle the save operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function save(Document $document): Document
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO documents (user_id, document_type, filename, mime_type, size_bytes, sha256, content, created_at, updated_at) VALUES (:user_id, :document_type, :filename, :mime_type, :size_bytes, :sha256, :content, :created_at, :updated_at)'
        );

        $createdAt = $document->createdAt()->format('Y-m-d H:i:s');

        $statement->bindValue(':user_id', $document->userId(), PDO::PARAM_INT);
        $statement->bindValue(':document_type', $document->documentType());
        $statement->bindValue(':filename', $document->filename());
        $statement->bindValue(':mime_type', $document->mimeType());
        $statement->bindValue(':size_bytes', $document->sizeBytes(), PDO::PARAM_INT);
        $statement->bindValue(':sha256', $document->sha256());
        $statement->bindValue(':content', $document->content(), PDO::PARAM_LOB);
        $statement->bindValue(':created_at', $createdAt);
        $statement->bindValue(':updated_at', $createdAt);

        $statement->execute();

        $id = (int) $this->pdo->lastInsertId();

        return $document->withId($id);
    }

    /**
     * Handle the find operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function find(int $id): ?Document
    {
        $statement = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Handle the find for user operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function findForUser(int $userId, int $documentId): ?Document
    {
        $statement = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id AND user_id = :user_id LIMIT 1');
        $statement->bindValue(':id', $documentId, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Handle the find for user by type operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function findForUserByType(int $userId, int $documentId, string $documentType): ?Document
    {
        $statement = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id AND user_id = :user_id AND document_type = :document_type LIMIT 1');
        $statement->bindValue(':id', $documentId, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':document_type', $documentType);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Handle the list for user and type workflow.
     *
     * This helper keeps the list for user and type logic centralised for clarity and reuse.
     * @return Document[]
     */
    public function listForUserAndType(int $userId, string $documentType): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM documents WHERE user_id = :user_id AND document_type = :document_type ORDER BY created_at DESC');
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':document_type', $documentType);
        $statement->execute();

        $documents = [];

        while ($row = $statement->fetch()) {
            $documents[] = $this->hydrate($row);
        }

        return $documents;
    }

    /**
     * Handle the ensure schema operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function ensureSchema(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS documents (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    document_type VARCHAR(32) NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    mime_type VARCHAR(191) NOT NULL,
                    size_bytes BIGINT UNSIGNED NOT NULL,
                    sha256 CHAR(64) NOT NULL UNIQUE,
                    content LONGBLOB NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_documents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_documents_user_type (user_id, document_type),
                    INDEX idx_documents_user_created (user_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                document_type TEXT NOT NULL,
                filename TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                sha256 TEXT NOT NULL UNIQUE,
                content BLOB NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    /**
     * Handle the hydrate workflow.
     *
     * This helper keeps the hydrate logic centralised for clarity and reuse.
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Document
    {
        return new Document(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['document_type'],
            $row['filename'],
            $row['mime_type'],
            (int) $row['size_bytes'],
            $row['sha256'],
            is_resource($row['content']) ? stream_get_contents($row['content']) ?: '' : (string) $row['content'],
            new DateTimeImmutable($row['created_at'])
        );
    }
}
