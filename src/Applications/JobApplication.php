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
     * Handle the with status operation.
     *
     * This helper keeps the immutable update workflow neat and predictable.
     */
    public function withStatus(string $status, ?DateTimeImmutable $appliedAt, DateTimeImmutable $updatedAt): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->sourceUrl,
            $this->description,
            $status,
            $appliedAt,
            $this->createdAt,
            $updatedAt
        );
    }
}
