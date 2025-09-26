<?php

declare(strict_types=1);

namespace App\Services;

class LogMailer implements MailerInterface
{
    /** @var string */
    private $logPath;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(string $logPath)
    {
        $this->logPath = $logPath;
    }

    /**
     * Handle the send operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function send(string $to, string $subject, string $body): void
    {
        $message = sprintf("[%s] To: %s | Subject: %s\n%s\n", date('c'), $to, $subject, $body);
        file_put_contents($this->logPath, $message, FILE_APPEND);
    }
}
