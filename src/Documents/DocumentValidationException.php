<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;

class DocumentValidationException extends RuntimeException
{
    /** @var int */
    private $statusCode;

    public function __construct(string $message, int $statusCode = 400)
    {
        parent::__construct($message);

        $this->statusCode = $statusCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
