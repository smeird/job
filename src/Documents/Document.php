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

    public function id(): ?int
    {
        return $this->id;
    }

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

    public function userId(): int
    {
        return $this->userId;
    }

    public function documentType(): string
    {
        return $this->documentType;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
