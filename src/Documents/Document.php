<?php

declare(strict_types=1);

namespace App\Documents;

use DateTimeImmutable;

class Document
{
    /** @var int|null */
    private $id;

    /** @var int */
    private $userId;

    /** @var string */
    private $documentType;

    /** @var string */
    private $filename;

    /** @var string */
    private $mimeType;

    /** @var int */
    private $sizeBytes;

    /** @var string */
    private $sha256;

    /** @var string */
    private $content;

    /** @var DateTimeImmutable */
    private $createdAt;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        ?int $id,
        int $userId,
        string $documentType,
        string $filename,
        string $mimeType,
        int $sizeBytes,
        string $sha256,
        string $content,
        DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->documentType = $documentType;
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->sha256 = $sha256;
        $this->content = $content;
        $this->createdAt = $createdAt;
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
     * Handle the with id operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->documentType,
            $this->filename,
            $this->mimeType,
            $this->sizeBytes,
            $this->sha256,
            $this->content,
            $this->createdAt
        );
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
     * Handle the document type operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function documentType(): string
    {
        return $this->documentType;
    }

    /**
     * Handle the filename operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * Handle the mime type operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function mimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Handle the size bytes operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }

    /**
     * Handle the sha256 operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function sha256(): string
    {
        return $this->sha256;
    }

    /**
     * Handle the content operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function content(): string
    {
        return $this->content;
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
