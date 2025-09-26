<?php

declare(strict_types=1);

namespace App\Queue;

use DateTimeImmutable;
use RuntimeException;

final class Job
{
    /** @var int */
    public $id;

    /** @var string */
    public $type;

    /** @var array */
    private $payload;

    /** @var int */
    private $attempts;

    /** @var string */
    public $status;

    /** @var DateTimeImmutable */
    public $runAfter;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        int $id,
        string $type,
        array $payload,
        int $attempts,
        string $status,
        DateTimeImmutable $runAfter
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->payload = $payload;
        $this->attempts = $attempts;
        $this->status = $status;
        $this->runAfter = $runAfter;
    }

    /**
     * Handle the payload workflow.
     *
     * This helper keeps the payload logic centralised for clarity and reuse.
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Handle the attempts operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Handle the increment attempts operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    /**
     * Handle the update run after operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function updateRunAfter(DateTimeImmutable $runAfter): void
    {
        $this->runAfter = $runAfter;
    }

    /**
     * Handle the run after operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function runAfter(): DateTimeImmutable
    {
        return $this->runAfter;
    }

    /**
     * Handle the replace payload operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function replacePayload(array $payload): void
    {
        if ($payload === []) {
            throw new RuntimeException('Job payload cannot be empty.');
        }

        $this->payload = $payload;
    }
}
