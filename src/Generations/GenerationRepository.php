<?php

declare(strict_types=1);

namespace App\Generations;

use DateTimeImmutable;
use PDO;

final class GenerationRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $userId, int $jobDocumentId, int $cvDocumentId, string $model, float $temperature): array
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO generations (user_id, job_document_id, cv_document_id, model, temperature, status, created_at, updated_at)
             VALUES (:user_id, :job_document_id, :cv_document_id, :model, :temperature, :status, :created_at, :updated_at)'
        );

        $statement->execute([
            ':user_id' => $userId,
            ':job_document_id' => $jobDocumentId,
            ':cv_document_id' => $cvDocumentId,
            ':model' => $model,
            ':temperature' => $temperature,
            ':status' => 'queued',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return $this->findForUser($userId, $id);
    }

    public function findForUser(int $userId, int $generationId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT g.id, g.model, g.temperature, g.status, g.created_at,
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
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT g.id, g.model, g.temperature, g.status, g.created_at,
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
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normaliseRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'model' => (string) $row['model'],
            'temperature' => (float) $row['temperature'],
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
}
