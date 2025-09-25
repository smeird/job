<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;

class DocumentValidationException extends RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 400)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
