<?php

declare(strict_types=1);

namespace App\Generations;

use DateTimeImmutable;

class GenerationStreamSnapshot
{
    public int $id;
    public string $status;
    public int $progressPercent;
    public int $costPence;
    public int $totalTokens;
    public ?string $errorMessage;
    public DateTimeImmutable $updatedAt;
    public ?DateTimeImmutable $latestOutputAt;

    public function __construct(
        int $id,
        string $status,
        int $progressPercent,
        int $costPence,
        int $totalTokens,
        ?string $errorMessage,
        DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $latestOutputAt,
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->progressPercent = $progressPercent;
        $this->costPence = $costPence;
        $this->totalTokens = $totalTokens;
        $this->errorMessage = $errorMessage;
        $this->updatedAt = $updatedAt;
        $this->latestOutputAt = $latestOutputAt;
    }
}
