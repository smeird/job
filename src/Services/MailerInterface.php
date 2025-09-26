<?php

declare(strict_types=1);

namespace App\Services;

interface MailerInterface
{
    /**
     * Handle the send operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function send(string $to, string $subject, string $body): void;
}
