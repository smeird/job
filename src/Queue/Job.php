<?php

declare(strict_types=1);

namespace App\Queue;

use DateTimeImmutable;
use RuntimeException;

final class Job
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        private array $payload,
        private int $attempts,
        public string $status,
        public DateTimeImmutable $runAfter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    public function updateRunAfter(DateTimeImmutable $runAfter): void
    {
        $this->runAfter = $runAfter;
    }

    public function runAfter(): DateTimeImmutable
    {
        return $this->runAfter;
    }

    public function replacePayload(array $payload): void
    {
        if ($payload === []) {
            throw new RuntimeException('Job payload cannot be empty.');
        }

        $this->payload = $payload;
    }
}
