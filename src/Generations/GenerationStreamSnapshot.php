<?php

declare(strict_types=1);

namespace App\Generations;

use DateTimeImmutable;

class GenerationStreamSnapshot
{
    /** @var int */
    public $id;

    /** @var string */
    public $status;

    /** @var int */
    public $progressPercent;

    /** @var int */
    public $costPence;

    /** @var int */
    public $totalTokens;

    /** @var string|null */
    public $errorMessage;

    /** @var DateTimeImmutable */
    public $updatedAt;

    /** @var DateTimeImmutable|null */
    public $latestOutputAt;

    public function __construct(
        int $id,
        string $status,
        int $progressPercent,
        int $costPence,
        int $totalTokens,
        ?string $errorMessage,
        DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $latestOutputAt
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
