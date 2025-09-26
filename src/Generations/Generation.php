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

    /** @var int */
    private $thinkingTime;

    /** @var string */
    private $status;

    /** @var DateTimeImmutable */
    private $createdAt;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        int $id,
        int $userId,
        int $jobDocumentId,
        int $cvDocumentId,
        string $model,
        int $thinkingTime,
        string $status,
        DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->jobDocumentId = $jobDocumentId;
        $this->cvDocumentId = $cvDocumentId;
        $this->model = $model;
        $this->thinkingTime = $thinkingTime;
        $this->status = $status;
        $this->createdAt = $createdAt;
    }

    /**
     * Handle the id operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function id(): int
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
     * Handle the job document id operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function jobDocumentId(): int
    {
        return $this->jobDocumentId;
    }

    /**
     * Handle the cv document id operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function cvDocumentId(): int
    {
        return $this->cvDocumentId;
    }

    /**
     * Handle the model operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function model(): string
    {
        return $this->model;
    }

    /**
     * Handle the thinking time operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function thinkingTime(): int
    {
        return $this->thinkingTime;
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
     * Create the d at instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
