<?php

declare(strict_types=1);

namespace App\Services;

class LogMailer implements MailerInterface
{
    public function __construct(private readonly string $logPath)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $message = sprintf("[%s] To: %s | Subject: %s\n%s\n", date('c'), $to, $subject, $body);
        file_put_contents($this->logPath, $message, FILE_APPEND);
    }
}
