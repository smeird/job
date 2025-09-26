<?php

declare(strict_types=1);

namespace App\Queue;

interface JobHandlerInterface
{
    /**
     * Handle the queued job execution workflow.
     *
     * Centralising job handling logic makes worker behaviour predictable and easy to audit.
     */
    public function handle(Job $job): void;

    /**
     * Handle the on failure workflow.
     *
     * Grouping failure handling in one callback keeps retries and logging consistent.
     */
    public function onFailure(Job $job, string $error, bool $willRetry): void;
}
