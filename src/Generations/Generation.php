<?php

declare(strict_types=1);

namespace App\Generations;

use DateTimeImmutable;

final class Generation
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly int $jobDocumentId,
        private readonly int $cvDocumentId,
        private readonly string $model,
        private readonly float $temperature,
        private readonly string $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function jobDocumentId(): int
    {
        return $this->jobDocumentId;
    }

    public function cvDocumentId(): int
    {
        return $this->cvDocumentId;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function temperature(): float
    {
        return $this->temperature;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
