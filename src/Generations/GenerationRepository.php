<?php

declare(strict_types=1);

namespace App\Generations;

use DateTimeImmutable;
use PDO;

final class GenerationRepository
{
    /** @var PDO */
    private $pdo;

    /** @var bool */
    private $hasThinkingTimeColumn;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->hasThinkingTimeColumn = $this->detectThinkingTimeColumn();
    }

    /**
     * Handle the create operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function create(int $userId, int $jobDocumentId, int $cvDocumentId, string $model, int $thinkingTime): array
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($this->hasThinkingTimeColumn) {
            $statement = $this->pdo->prepare(
                'INSERT INTO generations (user_id, job_document_id, cv_document_id, model, thinking_time, status, created_at, updated_at)
                 VALUES (:user_id, :job_document_id, :cv_document_id, :model, :thinking_time, :status, :created_at, :updated_at)'
            );

            $statement->execute([
                ':user_id' => $userId,
                ':job_document_id' => $jobDocumentId,
                ':cv_document_id' => $cvDocumentId,
                ':model' => $model,
                ':thinking_time' => $thinkingTime,
                ':status' => 'queued',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO generations (user_id, job_document_id, cv_document_id, model, status, created_at, updated_at)
                 VALUES (:user_id, :job_document_id, :cv_document_id, :model, :status, :created_at, :updated_at)'
            );

            $statement->execute([
                ':user_id' => $userId,
                ':job_document_id' => $jobDocumentId,
                ':cv_document_id' => $cvDocumentId,
                ':model' => $model,
                ':status' => 'queued',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        $id = (int) $this->pdo->lastInsertId();

        return $this->findForUser($userId, $id);
    }

    /**
     * Handle the find for user operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function findForUser(int $userId, int $generationId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT g.id, g.model, ' . $this->thinkingTimeSelectExpression() . ', g.status, g.created_at,
                    jd.id AS job_document_id, jd.filename AS job_filename,
                    cv.id AS cv_document_id, cv.filename AS cv_filename
             FROM generations g
             INNER JOIN documents jd ON jd.id = g.job_document_id
             INNER JOIN documents cv ON cv.id = g.cv_document_id
             WHERE g.id = :id AND g.user_id = :user_id
             LIMIT 1'
        );

        $statement->execute([
            ':id' => $generationId,
            ':user_id' => $userId,
        ]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normaliseRow($row);
    }

    /**
     * Handle the list for user workflow.
     *
     * This helper keeps the list for user logic centralised for clarity and reuse.
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT g.id, g.model, ' . $this->thinkingTimeSelectExpression() . ', g.status, g.created_at,
                    jd.id AS job_document_id, jd.filename AS job_filename,
                    cv.id AS cv_document_id, cv.filename AS cv_filename
             FROM generations g
             INNER JOIN documents jd ON jd.id = g.job_document_id
             INNER JOIN documents cv ON cv.id = g.cv_document_id
             WHERE g.user_id = :user_id
             ORDER BY g.created_at DESC'
        );

        $statement->execute([':user_id' => $userId]);

        $rows = [];

        while ($row = $statement->fetch()) {
            $rows[] = $this->normaliseRow($row);
        }

        return $rows;
    }

    /**
     * Handle the normalise row workflow.
     *
     * This helper keeps the normalise row logic centralised for clarity and reuse.
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normaliseRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'model' => (string) $row['model'],
            'thinking_time' => isset($row['thinking_time']) ? (int) $row['thinking_time'] : 30,
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
            'job_document' => [
                'id' => (int) $row['job_document_id'],
                'filename' => (string) $row['job_filename'],
            ],
            'cv_document' => [
                'id' => (int) $row['cv_document_id'],
                'filename' => (string) $row['cv_filename'],
            ],
        ];
    }


    /**
     * Determine whether the generations table provides the thinking_time column.
     *
     * Having this knowledge lets the repository remain compatible with older deployments.
     */
    private function detectThinkingTimeColumn(): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $statement = $this->pdo->query("SHOW COLUMNS FROM generations LIKE 'thinking_time'");

            return $statement !== false && $statement->fetch() !== false;
        }

        if ($driver === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(generations)');

            if ($statement === false) {
                return false;
            }

            while ($column = $statement->fetch()) {
                if (($column['name'] ?? '') === 'thinking_time') {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Provide the SQL expression used to fetch the thinking time column.
     *
     * Centralising the logic here keeps the query construction tidy.
     */
    private function thinkingTimeSelectExpression(): string
    {
        if ($this->hasThinkingTimeColumn) {
            return 'COALESCE(g.thinking_time, 30) AS thinking_time';
        }

        return '30 AS thinking_time';
    }
}
