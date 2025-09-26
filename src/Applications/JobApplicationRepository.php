<?php

declare(strict_types=1);

namespace App\Applications;

use DateTimeImmutable;
use PDO;
use RuntimeException;

class JobApplicationRepository
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
     * Handle the create operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function create(int $userId, string $title, ?string $sourceUrl, string $description): JobApplication
    {
        $now = new DateTimeImmutable('now');
        $createdAt = $now->format('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO job_applications (user_id, title, source_url, description, status, applied_at, reason_code, created_at, updated_at)'
             VALUES (:user_id, :title, :source_url, :description, :status, :applied_at, :reason_code, :created_at, :updated_at)'
        );

        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':title', $title);

        if ($sourceUrl === null) {
            $statement->bindValue(':source_url', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':source_url', $sourceUrl);
        }

        $statement->bindValue(':description', $description);
        $statement->bindValue(':status', 'outstanding');
        $statement->bindValue(':applied_at', null, PDO::PARAM_NULL);
        $statement->bindValue(':reason_code', null, PDO::PARAM_NULL);
        $statement->bindValue(':created_at', $createdAt);
        $statement->bindValue(':updated_at', $createdAt);

        $statement->execute();

        $id = (int) $this->pdo->lastInsertId();

        return new JobApplication(
            $id,
            $userId,
            $title,
            $sourceUrl,
            $description,
            'outstanding',
            null,
            null,
            new DateTimeImmutable($createdAt),
            new DateTimeImmutable($createdAt)
        );
    }

    /**
     * Handle the list for user and status workflow.
     *
     * This helper keeps the list operation centralised for clarity and reuse.
     * @return JobApplication[]
     */
    public function listForUserAndStatus(int $userId, string $status, ?int $limit = null): array
    {
        $sql = 'SELECT * FROM job_applications WHERE user_id = :user_id AND status = :status ORDER BY created_at DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
        }

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':status', $status);

        if ($limit !== null) {
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $statement->execute();

        $items = [];

        while ($row = $statement->fetch()) {
            $items[] = $this->hydrate($row);
        }

        return $items;
    }

    /**
     * Handle the count for user and status workflow.
     *
     * The helper keeps counting logic in one place to avoid duplication.
     */
    public function countForUserAndStatus(int $userId, string $status): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM job_applications WHERE user_id = :user_id AND status = :status');
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':status', $status);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * Handle the find for user operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function findForUser(int $userId, int $applicationId): ?JobApplication
    {
        $statement = $this->pdo->prepare('SELECT * FROM job_applications WHERE id = :id AND user_id = :user_id LIMIT 1');
        $statement->bindValue(':id', $applicationId, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Handle the update status operation.
     *
     * The helper keeps state transitions consistent across the service layer.
     */
    public function updateStatus(JobApplication $application, string $status, ?string $reasonCode): JobApplication
    {
        $now = new DateTimeImmutable('now');
        $appliedAt = $application->appliedAt();

        if ($status === 'applied' && $appliedAt === null) {
            $appliedAt = $now;
        }

        if ($status === 'outstanding') {
            $appliedAt = null;
        }

        $statement = $this->pdo->prepare(
            'UPDATE job_applications
             SET status = :status, applied_at = :applied_at, reason_code = :reason_code, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id'
        );

        $statement->bindValue(':status', $status);
        if ($appliedAt !== null) {
            $statement->bindValue(':applied_at', $appliedAt->format('Y-m-d H:i:s'));
        } else {
            $statement->bindValue(':applied_at', null, PDO::PARAM_NULL);
        }

        if ($reasonCode !== null) {
            $statement->bindValue(':reason_code', $reasonCode);
        } else {
            $statement->bindValue(':reason_code', null, PDO::PARAM_NULL);
        }

        $statement->bindValue(':updated_at', $now->format('Y-m-d H:i:s'));
        $statement->bindValue(':id', (int) $application->id(), PDO::PARAM_INT);
        $statement->bindValue(':user_id', $application->userId(), PDO::PARAM_INT);

        $statement->execute();

        if ($statement->rowCount() === 0) {
            throw new RuntimeException('No job application was updated.');
        }

        return $application->withStatus($status, $appliedAt, $reasonCode, $now);
    }

    /**
     * Handle the ensure schema operation.
     *
     * This helper keeps schema bootstrapping predictable across environments.
     */
    private function ensureSchema(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS job_applications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL DEFAULT '',
                source_url TEXT NULL,
                description LONGTEXT NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'outstanding',
                applied_at DATETIME NULL,
                reason_code VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_job_applications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_job_applications_user_status (user_id, status),
                INDEX idx_job_applications_user_created (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL;

            $this->pdo->exec($sql);

            $this->ensureReasonCodeColumn();

            return;
        }

        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS job_applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL DEFAULT '',
            source_url TEXT NULL,
            description TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'outstanding',
            applied_at TEXT NULL,
            reason_code TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
        SQL;

        $this->pdo->exec($sql);

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_job_applications_user_status ON job_applications (user_id, status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_job_applications_user_created ON job_applications (user_id, created_at)');
        $this->ensureReasonCodeColumn();

    }

    /**
     * Handle the hydrate workflow.
     *
     * This helper keeps model hydration logic consistent for reuse.
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): JobApplication
    {
        $appliedAt = null;

        if (!empty($row['applied_at'])) {
            $appliedAt = new DateTimeImmutable((string) $row['applied_at']);
        }

        return new JobApplication(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) $row['user_id'],
            (string) ($row['title'] ?? ''),
            array_key_exists('source_url', $row) && $row['source_url'] !== null ? (string) $row['source_url'] : null,
            (string) $row['description'],
            (string) $row['status'],
            $appliedAt,
            array_key_exists('reason_code', $row) && $row['reason_code'] !== null ? (string) $row['reason_code'] : null,
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at'])
        );
    }

    /**
     * Handle the ensure reason code column workflow.
     *
     * This helper keeps schema updates for reason tracking predictable across drivers.
     */
    private function ensureReasonCodeColumn(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $statement = $this->pdo->prepare("SHOW COLUMNS FROM job_applications LIKE 'reason_code'");
            $statement->execute();

            if ($statement->fetch() === false) {
                $this->pdo->exec("ALTER TABLE job_applications ADD COLUMN reason_code VARCHAR(64) NULL AFTER applied_at");
            }

            return;
        }

        $statement = $this->pdo->query("PRAGMA table_info(job_applications)");

        $hasColumn = false;

        while ($row = $statement->fetch()) {
            if (isset($row['name']) && $row['name'] === 'reason_code') {
                $hasColumn = true;
                break;
            }
        }

        if ($hasColumn === false) {
            $this->pdo->exec('ALTER TABLE job_applications ADD COLUMN reason_code TEXT NULL');
        }
    }
}
