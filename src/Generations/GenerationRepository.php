<?php

declare(strict_types=1);

namespace App\Generations;

use App\Documents\Document;
use App\Documents\DocumentPreviewer;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

use function array_fill;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_replace;
use function trim;

use const JSON_THROW_ON_ERROR;

final class GenerationRepository
{
    /** @var PDO */
    private $pdo;

    /** @var DocumentPreviewer */
    private $documentPreviewer;

    /** @var bool */
    private $hasThinkingTimeColumn;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(PDO $pdo, ?DocumentPreviewer $documentPreviewer = null)
    {
        $this->pdo = $pdo;
        $this->documentPreviewer = $documentPreviewer ?? new DocumentPreviewer();
        $this->hasThinkingTimeColumn = $this->detectThinkingTimeColumn();
    }

    /**
     * Handle the create operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function create(
        int $userId,
        Document $jobDocument,
        Document $cvDocument,
        string $model,
        int $thinkingTime,
        string $prompt
    ): array {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $jobDocumentId = (int) $jobDocument->id();
        $cvDocumentId = (int) $cvDocument->id();

        if ($jobDocumentId <= 0 || $cvDocumentId <= 0) {
            throw new RuntimeException('Unable to queue the generation because a required document is missing.');
        }

        $this->pdo->beginTransaction();

        try {
            if ($this->hasThinkingTimeColumn) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO generations (user_id, job_document_id, cv_document_id, model, thinking_time, status, created_at, updated_at)'
                        . ' VALUES (:user_id, :job_document_id, :cv_document_id, :model, :thinking_time, :status, :created_at, :updated_at)'
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
                    'INSERT INTO generations (user_id, job_document_id, cv_document_id, model, status, created_at, updated_at)'
                        . ' VALUES (:user_id, :job_document_id, :cv_document_id, :model, :status, :created_at, :updated_at)'
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

            $this->queueTailorJob($id, $userId, $jobDocument, $cvDocument, $model, $thinkingTime, $prompt, $now);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException('Failed to queue the tailoring job for processing.', 0, $exception);
        }

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
            'SELECT g.id, g.model, ' . $this->thinkingTimeSelectExpression() . ', g.status, g.created_at,'
                . ' jd.id AS job_document_id, jd.filename AS job_filename,'
                . ' cv.id AS cv_document_id, cv.filename AS cv_filename'
                . ' FROM generations g'
                . ' INNER JOIN documents jd ON jd.id = g.job_document_id'
                . ' INNER JOIN documents cv ON cv.id = g.cv_document_id'
                . ' WHERE g.id = :id AND g.user_id = :user_id'
                . ' LIMIT 1'
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
            'SELECT g.id, g.model, ' . $this->thinkingTimeSelectExpression() . ', g.status, g.created_at,'
                . ' jd.id AS job_document_id, jd.filename AS job_filename,'
                . ' cv.id AS cv_document_id, cv.filename AS cv_filename'
                . ' FROM generations g'
                . ' INNER JOIN documents jd ON jd.id = g.job_document_id'
                . ' INNER JOIN documents cv ON cv.id = g.cv_document_id'
                . ' WHERE g.user_id = :user_id'
                . ' ORDER BY g.created_at DESC'
        );

        $statement->execute([':user_id' => $userId]);

        $rows = [];

        while ($row = $statement->fetch()) {
            $rows[] = $this->normaliseRow($row);
        }

        return $rows;
    }

    /**
     * Remove a queued generation from the processing pipeline for the given user.
     *
     * Cancelling removes the background job before it is executed and marks the
     * generation as cancelled so the UI reflects the new terminal state.
     */
    public function cancelQueuedGeneration(int $userId, int $generationId): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'SELECT status FROM generations WHERE id = :id AND user_id = :user_id LIMIT 1'
            );

            $statement->execute([
                ':id' => $generationId,
                ':user_id' => $userId,
            ]);

            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                $this->pdo->rollBack();

                return null;
            }

            $status = (string) $row['status'];

            if ($status !== 'queued') {
                $this->pdo->rollBack();

                return null;
            }

            $jobId = $this->findPendingJobIdForGeneration($generationId);

            if ($jobId === null) {
                $this->pdo->rollBack();

                return null;
            }

            $deleteJob = $this->pdo->prepare('DELETE FROM jobs WHERE id = :id');
            $deleteJob->execute([':id' => $jobId]);

            $updateGeneration = $this->pdo->prepare(
                'UPDATE generations SET status = :status, progress_percent = 0, error_message = NULL, '
                . 'updated_at = :updated_at WHERE id = :id'
            );

            $updateGeneration->execute([
                ':status' => 'cancelled',
                ':updated_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ':id' => $generationId,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException('Failed to cancel the queued generation.', 0, $exception);
        }

        return $this->findForUser($userId, $generationId);
    }

    /**
     * Remove residual queue jobs associated with the supplied user identifier.
     *
     * Cleaning the queue ensures abandoned tailoring runs do not accumulate and
     * keeps the dashboard in sync with the background worker state.
     */
    public function cleanupJobsForUser(int $userId): int
    {
        try {
            $select = $this->pdo->prepare('SELECT id, payload_json FROM jobs WHERE type = :type');
            $select->execute([':type' => 'tailor_cv']);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to retrieve tailoring jobs for cleanup.', 0, $exception);
        }

        try {
            $delete = $this->pdo->prepare('DELETE FROM jobs WHERE id = :id');
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to prepare job deletion statement.', 0, $exception);
        }

        $removed = 0;
        $affectedGenerations = [];

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $jobId = isset($row['id']) ? (int) $row['id'] : 0;

            if ($jobId <= 0) {
                continue;
            }

            $payload = $this->decodeJobPayload($row['payload_json'] ?? null);
            $payloadUserId = isset($payload['user_id']) ? (int) $payload['user_id'] : 0;

            if ($payloadUserId !== $userId) {
                continue;
            }

            $delete->execute([':id' => $jobId]);

            if ($delete->rowCount() > 0) {
                $removed++;

                $generationId = isset($payload['generation_id']) ? (int) $payload['generation_id'] : 0;

                if ($generationId > 0) {
                    $affectedGenerations[] = $generationId;
                }
            }
        }

        if ($removed > 0 && $affectedGenerations !== []) {
            $this->markGenerationsCancelled($affectedGenerations);
        }

        return $removed;
    }

    /**

     * Remove failed generations that belong to the specified user.
     *
     * Clearing out failed rows keeps the tailoring dashboard focused on
     * actionable items and ensures old errors do not linger indefinitely.
     */
    public function cleanupFailedGenerationsForUser(int $userId): int
    {
        try {
            $statement = $this->pdo->prepare(
                "DELETE FROM generations WHERE user_id = :user_id AND status IN ('failed', 'error')"
            );
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to prepare failed generation cleanup statement.', 0, $exception);
        }

        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);

        try {
            $statement->execute();
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to remove failed generations for the user.', 0, $exception);
        }

        return (int) $statement->rowCount();
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
     * Update the supplied generations to reflect their removal from the queue.
     *
     * Marking affected runs as cancelled prevents the UI from reporting that a
     * background task is still pending once its job record has been deleted.
     *
     * @param array<int, int> $generationIds
     */
    private function markGenerationsCancelled(array $generationIds): void
    {
        $unique = [];

        foreach ($generationIds as $generationId) {
            $id = (int) $generationId;

            if ($id > 0) {
                $unique[$id] = $id;
            }
        }

        if ($unique === []) {
            return;
        }

        $ids = array_values($unique);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $sql = 'UPDATE generations SET status = ?, progress_percent = 0, error_message = NULL, updated_at = ? '
            . 'WHERE id IN (' . $placeholders . ") AND status IN ('queued', 'processing')";

        try {
            $statement = $this->pdo->prepare($sql);
            $parameters = array_merge(
                ['cancelled', (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')],
                $ids
            );
            $statement->execute($parameters);
        } catch (Throwable $exception) {
            throw new RuntimeException('Failed to update generations after queue cleanup.', 0, $exception);
        }
    }

    /**
     * Decode the JSON payload stored alongside a queued job.
     *
     * A defensive decoder avoids propagating malformed payloads further into
     * the cleanup workflow when older jobs contain unexpected structures.
     *
     * @return array<string, mixed>
     */
    private function decodeJobPayload($payload): array
    {
        if (!is_string($payload)) {
            return [];
        }

        $trimmed = trim($payload);

        if ($trimmed === '') {
            return [];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Queue the tailor CV job in the background worker.
     *
     * Consolidating the queuing logic keeps database interactions predictable.
     */
    private function queueTailorJob(
        int $generationId,
        int $userId,
        Document $jobDocument,
        Document $cvDocument,
        string $model,
        int $thinkingTime,
        string $prompt,
        string $queuedAt
    ): void {
        $jobDescription = $this->extractDocumentText($jobDocument);
        $cvMarkdown = $this->extractDocumentText($cvDocument);

        if ($jobDescription === '') {
            throw new RuntimeException('The job description could not be converted into text.');
        }

        if ($cvMarkdown === '') {
            throw new RuntimeException('The CV could not be converted into text.');
        }

        $payload = [
            'generation_id' => $generationId,
            'user_id' => $userId,
            'job_document_id' => $jobDocument->id(),
            'cv_document_id' => $cvDocument->id(),
            'job_description' => $jobDescription,
            'cv_markdown' => $cvMarkdown,
            'model' => $model,
            'thinking_time' => $thinkingTime,
            'prompt' => $prompt,
        ];

        $statement = $this->pdo->prepare(
            'INSERT INTO jobs (type, payload_json, run_after, attempts, status, created_at)'
                . ' VALUES (:type, :payload_json, :run_after, 0, :status, :created_at)'
        );

        $statement->execute([
            ':type' => 'tailor_cv',
            ':payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            ':run_after' => $queuedAt,
            ':status' => 'pending',
            ':created_at' => $queuedAt,
        ]);
    }

    /**
     * Extract the textual content from a stored document.
     *
     * Having a central helper keeps conversions consistent across job payloads.
     */
    private function extractDocumentText(Document $document): string
    {
        $mime = $document->mimeType();
        $raw = '';

        if (in_array($mime, ['text/plain', 'text/markdown'], true)) {
            $raw = $document->content();
        } else {
            $raw = $this->documentPreviewer->render($document);
        }

        if ($raw === '') {
            return '';
        }

        return $this->normaliseExtractedText($raw);
    }

    /**
     * Normalise extracted text into a stable format.
     *
     * Consolidating whitespace handling keeps downstream prompts clean and predictable.
     */
    private function normaliseExtractedText(string $text): string
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return '';
        }

        $normalised = preg_replace(["/\r\n?/", "/\n{3,}/"], ["\n", "\n\n"], $trimmed);

        return $normalised === null ? '' : (string) $normalised;
    }

    /**
     * Locate the pending job identifier associated with the supplied generation.
     *
     * The queue stores generation identifiers inside the JSON payload, so the
     * lookup must decode each pending job until it finds a matching entry.
     */
    private function findPendingJobIdForGeneration(int $generationId): ?int
    {
        $statement = $this->pdo->prepare(
            "SELECT id, payload_json FROM jobs WHERE type = :type AND status = 'pending'"
        );

        $statement->execute([':type' => 'tailor_cv']);

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $payload = json_decode((string) $row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
            $payloadGenerationId = isset($payload['generation_id']) ? (int) $payload['generation_id'] : 0;

            if ($payloadGenerationId === $generationId) {
                return (int) $row['id'];
            }
        }

        return null;
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
