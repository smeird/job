<?php

declare(strict_types=1);

namespace App\Generations;

use DateTimeImmutable;

final class Generation
{
    private int $id;
    private int $userId;
    private int $jobDocumentId;
    private int $cvDocumentId;
    private string $model;
    private float $temperature;
    private string $status;
    private DateTimeImmutable $createdAt;

    public function __construct(
        int $id,
        int $userId,
        int $jobDocumentId,
        int $cvDocumentId,
        string $model,
        float $temperature,
        string $status,
        DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->jobDocumentId = $jobDocumentId;
        $this->cvDocumentId = $cvDocumentId;
        $this->model = $model;
        $this->temperature = $temperature;
        $this->status = $status;
        $this->createdAt = $createdAt;
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
