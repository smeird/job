<?php

declare(strict_types=1);

namespace App\Generations;

use DateTimeImmutable;

final class Generation
{
    /** @var int */
    private $id;

    /** @var int */
    private $userId;

    /** @var int */
    private $jobDocumentId;

    /** @var int */
    private $cvDocumentId;

    /** @var string */
    private $model;

    /** @var float */
    private $temperature;

    /** @var string */
    private $status;

    /** @var DateTimeImmutable */
    private $createdAt;

    public function __construct(
        int $id,
        int $userId,
        int $jobDocumentId,
        int $cvDocumentId,
        string $model,
        float $temperature,
        string $status,
        DateTimeImmutable $createdAt
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
