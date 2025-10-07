<?php

declare(strict_types=1);

namespace App\Applications;

use DateInterval;
use DateTimeImmutable;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;

use function error_log;
use function is_array;
use function json_decode;
use function json_encode;

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
            'INSERT INTO job_applications (user_id, title, source_url, description, status, applied_at, reason_code, generation_id, created_at, updated_at) '
            . 'VALUES (:user_id, :title, :source_url, :description, :status, :applied_at, :reason_code, :generation_id, :created_at, :updated_at)'
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
        $statement->bindValue(':generation_id', null, PDO::PARAM_NULL);
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
     * Handle the update generation operation.
     *
     * The helper keeps linking tailored drafts consistent across the service layer.
     */
    public function updateGeneration(JobApplication $application, ?int $generationId): JobApplication
    {
        $now = new DateTimeImmutable('now');
        $statement = $this->pdo->prepare(
            'UPDATE job_applications
             SET generation_id = :generation_id, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id'
        );

        if ($generationId !== null) {
            $statement->bindValue(':generation_id', $generationId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':generation_id', null, PDO::PARAM_NULL);
        }

        $statement->bindValue(':updated_at', $now->format('Y-m-d H:i:s'));
        $statement->bindValue(':id', (int) $application->id(), PDO::PARAM_INT);
        $statement->bindValue(':user_id', $application->userId(), PDO::PARAM_INT);

        $statement->execute();

        if ($statement->rowCount() === 0) {
            throw new RuntimeException('No job application was updated.');
        }

        return $application->withGeneration($generationId, $now);
    }

    /**
     * Retrieve the most recent cached research result for the given user.
     *
     * Returning hydrated arrays keeps the service layer agnostic of storage
     * details while still enforcing freshness windows to control spend.
     *
     * @return array{
     *     query: string,
     *     summary: string,
     *     search_results: array<int, array{title: string, url: string, snippet: string}>,
     *     generated_at: DateTimeImmutable
     * }|null
     */
    public function findRecentResearch(int $userId, int $applicationId, int $maxAgeMinutes): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT query, summary, search_results, generated_at FROM job_application_research '
            . 'WHERE user_id = :user_id AND job_application_id = :application_id LIMIT 1'
        );

        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':application_id', $applicationId, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $generatedAt = new DateTimeImmutable((string) $row['generated_at']);
        $now = new DateTimeImmutable('now');

        if ($maxAgeMinutes > 0) {
            $threshold = $now->sub(new DateInterval('PT' . $maxAgeMinutes . 'M'));

            if ($generatedAt < $threshold) {
                return null;
            }
        }

        try {
            $results = json_decode((string) $row['search_results'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            error_log('Failed to decode cached research search results: ' . $exception->getMessage());

            return null;
        }

        if (!is_array($results)) {
            return null;
        }

        return [
            'query' => (string) $row['query'],
            'summary' => (string) $row['summary'],
            'search_results' => $results,
            'generated_at' => $generatedAt,
        ];
    }

    /**
     * Persist a freshly generated research artefact for future reuse.
     *
     * The helper centralises the upsert logic to ensure both MySQL and SQLite
     * environments maintain a single cached row per application and user.
     *
     * @param array<int, array{title: string, url: string, snippet: string}> $searchResults
     */
    public function saveResearchResult(
        int $userId,
        int $applicationId,
        string $query,
        string $summary,
        array $searchResults,
        DateTimeImmutable $generatedAt
    ): void {
        try {
            $encodedResults = json_encode($searchResults, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode research search results for storage.', 0, $exception);
        }

        $timestamp = $generatedAt->format('Y-m-d H:i:s');
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = <<<SQL
                INSERT INTO job_application_research
                    (user_id, job_application_id, query, summary, search_results, generated_at, created_at, updated_at)
                VALUES
                    (:user_id, :application_id, :query, :summary, :results, :generated_at, :generated_at, :generated_at)
                ON DUPLICATE KEY UPDATE
                    query = VALUES(query),
                    summary = VALUES(summary),
                    search_results = VALUES(search_results),
                    generated_at = VALUES(generated_at),
                    updated_at = VALUES(updated_at)
            SQL;
        } else {
            $sql = <<<SQL
                INSERT INTO job_application_research
                    (user_id, job_application_id, query, summary, search_results, generated_at, created_at, updated_at)
                VALUES
                    (:user_id, :application_id, :query, :summary, :results, :generated_at, :generated_at, :generated_at)
                ON CONFLICT(user_id, job_application_id) DO UPDATE SET
                    query = excluded.query,
                    summary = excluded.summary,
                    search_results = excluded.search_results,
                    generated_at = excluded.generated_at,
                    updated_at = excluded.updated_at
            SQL;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':application_id', $applicationId, PDO::PARAM_INT);
        $statement->bindValue(':query', $query);
        $statement->bindValue(':summary', $summary);
        $statement->bindValue(':results', $encodedResults);
        $statement->bindValue(':generated_at', $timestamp);

        $statement->execute();
    }

    /**
     * Handle the delete for user operation.
     *
     * The helper keeps deletion logic consistent across the service layer.
     */
    public function deleteForUser(int $userId, int $applicationId): bool
    {
        $cleanup = $this->pdo->prepare(
            'DELETE FROM job_application_research WHERE job_application_id = :application_id AND user_id = :user_id'
        );
        $cleanup->bindValue(':application_id', $applicationId, PDO::PARAM_INT);
        $cleanup->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $cleanup->execute();

        $statement = $this->pdo->prepare('DELETE FROM job_applications WHERE id = :id AND user_id = :user_id');
        $statement->bindValue(':id', $applicationId, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() > 0;
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
                generation_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_job_applications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_job_applications_generation FOREIGN KEY (generation_id) REFERENCES generations(id) ON DELETE SET NULL,
                INDEX idx_job_applications_user_status (user_id, status),
                INDEX idx_job_applications_user_created (user_id, created_at),
                INDEX idx_job_applications_generation (generation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL;

            $this->pdo->exec($sql);

            $this->ensureReasonCodeColumn();
            $this->ensureGenerationIdColumn();
            $this->ensureResearchTable();

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
            generation_id INTEGER NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
        SQL;

        $this->pdo->exec($sql);

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_job_applications_user_status ON job_applications (user_id, status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_job_applications_user_created ON job_applications (user_id, created_at)');
        $this->ensureReasonCodeColumn();
        $this->ensureGenerationIdColumn();
        $this->ensureResearchTable();

    }

    /**
     * Ensure the research cache table exists for environments without migrations.
     */
    private function ensureResearchTable(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS job_application_research (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                job_application_id BIGINT UNSIGNED NOT NULL,
                query VARCHAR(512) NOT NULL,
                summary LONGTEXT NOT NULL,
                search_results LONGTEXT NOT NULL,
                generated_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_job_application_research_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_job_application_research_application FOREIGN KEY (job_application_id) REFERENCES job_applications(id) ON DELETE CASCADE,
                UNIQUE KEY uniq_job_application_research_application (user_id, job_application_id),
                INDEX idx_job_application_research_generated (generated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL;

            $this->pdo->exec($sql);

            return;
        }

        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS job_application_research (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            job_application_id INTEGER NOT NULL,
            query TEXT NOT NULL,
            summary TEXT NOT NULL,
            search_results TEXT NOT NULL,
            generated_at TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
        SQL;

        $this->pdo->exec($sql);
        $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uniq_job_application_research_application ON job_application_research (user_id, job_application_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_job_application_research_generated ON job_application_research (generated_at)');
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
            array_key_exists('generation_id', $row) && $row['generation_id'] !== null ? (int) $row['generation_id'] : null,
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

    /**
     * Handle the ensure generation id column workflow.
     *
     * This helper keeps schema updates for tailored CV links predictable across drivers.
     */
    private function ensureGenerationIdColumn(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $statement = $this->pdo->prepare("SHOW COLUMNS FROM job_applications LIKE 'generation_id'");
            $statement->execute();

            if ($statement->fetch() === false) {
                $this->pdo->exec("ALTER TABLE job_applications ADD COLUMN generation_id BIGINT UNSIGNED NULL AFTER reason_code");

                try {
                    $this->pdo->exec(
                        'ALTER TABLE job_applications '
                        . 'ADD CONSTRAINT fk_job_applications_generation '
                        . 'FOREIGN KEY (generation_id) REFERENCES generations(id) ON DELETE SET NULL'
                    );
                } catch (PDOException $exception) {
                    // Ignore inability to add the constraint on legacy installations.
                }

                try {
                    $this->pdo->exec('CREATE INDEX idx_job_applications_generation ON job_applications (generation_id)');
                } catch (PDOException $exception) {
                    // Ignore duplicate index creation attempts.
                }
            }

            return;
        }

        $statement = $this->pdo->query("PRAGMA table_info(job_applications)");

        $hasColumn = false;

        while ($row = $statement->fetch()) {
            if (isset($row['name']) && $row['name'] === 'generation_id') {
                $hasColumn = true;
                break;
            }
        }

        if ($hasColumn === false) {
            $this->pdo->exec('ALTER TABLE job_applications ADD COLUMN generation_id INTEGER NULL');
        }
    }
}
