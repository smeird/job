<?php

declare(strict_types=1);

namespace App\Generations;

use DateTimeImmutable;

class GenerationStreamSnapshot
{
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly int $progressPercent,
        public readonly int $costPence,
        public readonly int $totalTokens,
        public readonly ?string $errorMessage,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $latestOutputAt,
    ) {
    }
}
