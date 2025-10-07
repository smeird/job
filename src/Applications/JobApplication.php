<?php

declare(strict_types=1);

namespace App\Applications;

use DateTimeImmutable;

class JobApplication
{
    /** @var int|null */
    private $id;

    /** @var int */
    private $userId;

    /** @var string */
    private $title;

    /** @var string|null */
    private $sourceUrl;

    /** @var string */
    private $description;

    /** @var string */
    private $status;

    /** @var DateTimeImmutable|null */
    private $appliedAt;

    /** @var string|null */
    private $reasonCode;

    /** @var int|null */
    private $generationId;

    /** @var DateTimeImmutable */
    private $createdAt;

    /** @var DateTimeImmutable */
    private $updatedAt;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        ?int $id,
        int $userId,
        string $title,
        ?string $sourceUrl,
        string $description,
        string $status,
        ?DateTimeImmutable $appliedAt,
        ?string $reasonCode,
        ?int $generationId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->title = $title;
        $this->sourceUrl = $sourceUrl;
        $this->description = $description;
        $this->status = $status;
        $this->appliedAt = $appliedAt;
        $this->reasonCode = $reasonCode;
        $this->generationId = $generationId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Handle the id operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * Handle the user id operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function userId(): int
    {
        return $this->userId;
    }

    /**
     * Handle the title operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Handle the source url operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function sourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    /**
     * Handle the description operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Handle the status operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * Handle the reason code operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function reasonCode(): ?string
    {
        return $this->reasonCode;
    }

    /**
     * Handle the generation id operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function generationId(): ?int
    {
        return $this->generationId;
    }

    /**
     * Handle the applied at operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function appliedAt(): ?DateTimeImmutable
    {
        return $this->appliedAt;
    }

    /**
     * Handle the created at operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Handle the updated at operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Handle the with details operation.
     *
     * This helper keeps immutable updates consistent when editing saved applications.
     */
    public function withDetails(
        string $title,
        ?string $sourceUrl,
        string $description,
        string $status,
        ?DateTimeImmutable $appliedAt,
        ?string $reasonCode,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $this->id,
            $this->userId,
            $title,
            $sourceUrl,
            $description,
            $status,
            $appliedAt,
            $reasonCode,
            $this->generationId,
            $this->createdAt,
            $updatedAt
        );
    }

    /**
     * Handle the with status operation.
     *
     * This helper keeps the immutable update workflow neat and predictable.
     */
    public function withStatus(
        string $status,
        ?DateTimeImmutable $appliedAt,
        ?string $reasonCode,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->sourceUrl,
            $this->description,
            $status,
            $appliedAt,
            $reasonCode,
            $this->generationId,
            $this->createdAt,
            $updatedAt
        );
    }

    /**
     * Handle the with generation operation.
     *
     * This helper keeps immutable updates consistent when linking tailored drafts.
     */
    public function withGeneration(?int $generationId, DateTimeImmutable $updatedAt): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->sourceUrl,
            $this->description,
            $this->status,
            $this->appliedAt,
            $this->reasonCode,
            $generationId,
            $this->createdAt,
            $updatedAt
        );
    }
}
