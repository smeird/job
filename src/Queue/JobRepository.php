<?php

declare(strict_types=1);

namespace App\Queue;

use DateInterval;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

use function json_decode;
use function mb_substr;

final class JobRepository
{
    private const MAX_ERROR_LENGTH = 1000;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function reserveNextPending(): ?Job
    {
        try {
            $this->pdo->beginTransaction();

            $statement = $this->pdo->prepare(
                'SELECT id, type, payload_json, run_after, attempts, status, created_at '
                . 'FROM jobs '
                . 'WHERE status = :status AND run_after <= NOW() '
                . 'ORDER BY run_after ASC, id ASC '
                . 'LIMIT 1 FOR UPDATE SKIP LOCKED'
            );

            $statement->execute([':status' => 'pending']);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                $this->pdo->commit();

                return null;
            }

            $update = $this->pdo->prepare(
                'UPDATE jobs SET status = :status, attempts = attempts + 1, error = NULL '
                . 'WHERE id = :id'
            );

            $update->execute([
                ':status' => 'running',
                ':id' => $row['id'],
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException('Failed to reserve job: ' . $exception->getMessage(), 0, $exception);
        }

        $payload = $this->decodePayload((string) $row['payload_json']);

        $job = new Job(
            (int) $row['id'],
            (string) $row['type'],
            $payload,
            (int) $row['attempts'],
            (string) $row['status'],
            new DateTimeImmutable((string) $row['run_after'])
        );

        $job->incrementAttempts();
        $job->status = 'running';

        return $job;
    }

    public function markCompleted(Job $job): void
    {
        $this->updateJob($job->id, [
            'status' => 'completed',
            'error' => null,
            'run_after' => (new DateTimeImmutable('now')),
        ]);

        $job->status = 'completed';
    }

    public function markFailed(Job $job, string $error): void
    {
        $this->updateJob($job->id, [
            'status' => 'failed',
            'error' => $this->truncateError($error),
        ]);

        $job->status = 'failed';
    }

    public function scheduleRetry(Job $job, int $delaySeconds, string $error): void
    {
        $runAfter = (new DateTimeImmutable('now'))->add(new DateInterval('PT' . max(1, $delaySeconds) . 'S'));

        $this->updateJob($job->id, [
            'status' => 'pending',
            'error' => $this->truncateError($error),
            'run_after' => $runAfter,
        ]);

        $job->status = 'pending';
        $job->updateRunAfter($runAfter);
    }

    /**
     * @param array{status?: string, error?: ?string, run_after?: DateTimeImmutable} $data
     */
    private function updateJob(int $id, array $data): void
    {
        $fields = [];
        $parameters = [':id' => $id];

        if (array_key_exists('status', $data)) {
            $fields[] = 'status = :status';
            $parameters[':status'] = $data['status'];
        }

        if (array_key_exists('error', $data)) {
            $fields[] = 'error = :error';
            $parameters[':error'] = $data['error'];
        }

        if (array_key_exists('run_after', $data)) {
            $fields[] = 'run_after = :run_after';
            $parameters[':run_after'] = $data['run_after'] instanceof DateTimeImmutable
                ? $data['run_after']->format('Y-m-d H:i:s')
                : $data['run_after'];
        }

        if ($fields === []) {
            return;
        }

        $sql = 'UPDATE jobs SET ' . implode(', ', $fields) . ' WHERE id = :id';

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to update job record.', 0, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $payload): array
    {
        try {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Job payload is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Job payload must decode to an array.');
        }

        return $decoded;
    }

    private function truncateError(string $error): string
    {
        return mb_substr($error, 0, self::MAX_ERROR_LENGTH);
    }
}
