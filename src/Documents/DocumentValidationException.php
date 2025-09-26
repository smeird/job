<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;

class DocumentValidationException extends RuntimeException
{
    /** @var int */
    private $statusCode;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(string $message, int $statusCode = 400)
    {
        parent::__construct($message);

        $this->statusCode = $statusCode;
    }

    /**
     * Handle the status code operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
