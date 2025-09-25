<?php

declare(strict_types=1);

namespace App\Queue;

use Throwable;

final class JobWorker
{
    private const BASE_BACKOFF_SECONDS = 5;
    private const MAX_BACKOFF_SECONDS = 300;

    /**
     * @param array<string, JobHandlerInterface> $handlers
     */
    public function __construct(
        private readonly JobRepository $repository,
        private readonly array $handlers,
        private readonly int $maxAttempts = 5
    ) {
    }

    public function process(Job $job): void
    {
        $handler = $this->handlers[$job->type] ?? null;

        if ($handler === null) {
            $this->repository->markFailed($job, 'No handler registered for job type: ' . $job->type);

            return;
        }

        try {
            $handler->handle($job);
            $this->repository->markCompleted($job);
        } catch (TransientJobException $exception) {
            $willRetry = $job->attempts() < $this->maxAttempts;
            $handler->onFailure($job, $exception->getMessage(), $willRetry);

            if ($willRetry) {
                $delay = $this->calculateBackoffSeconds($job->attempts());
                $this->repository->scheduleRetry($job, $delay, $exception->getMessage());
            } else {
                $this->repository->markFailed($job, $exception->getMessage());
            }
        } catch (Throwable $exception) {
            $handler->onFailure($job, $exception->getMessage(), false);
            $this->repository->markFailed($job, $exception->getMessage());
        }
    }

    private function calculateBackoffSeconds(int $attempt): int
    {
        $attempt = max(1, $attempt);
        $delay = self::BASE_BACKOFF_SECONDS * (2 ** ($attempt - 1));

        return (int) min(self::MAX_BACKOFF_SECONDS, $delay);
    }
}
