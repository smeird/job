<?php

declare(strict_types=1);

namespace App\Documents;

use DateTimeImmutable;

class Document
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $userId,
        private readonly string $documentType,
        private readonly string $filename,
        private readonly string $mimeType,
        private readonly int $sizeBytes,
        private readonly string $sha256,
        private readonly string $content,
        private readonly DateTimeImmutable $createdAt,
    ) {
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
            $this->createdAt,
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
