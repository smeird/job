<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use PDO;

class RateLimiter
{
    public function __construct(private readonly PDO $pdo, private readonly int $limit, private readonly DateInterval $interval)
    {
    }

    public function tooManyAttempts(string $ipAddress, string $email, string $action): bool
    {
        $windowStart = (new DateTimeImmutable())->sub($this->interval)->format('Y-m-d H:i:s');

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE ip_address = :ip AND email = :email AND action = :action AND created_at >= :start');
        $statement->execute([
            'ip' => $ipAddress,
            'email' => $email,
            'action' => $action,
            'start' => $windowStart,
        ]);

        $count = (int) $statement->fetchColumn();

        return $count >= $this->limit;
    }

    public function hit(string $ipAddress, string $email, string $action): void
    {
        $statement = $this->pdo->prepare('INSERT INTO audit_logs (ip_address, email, action, created_at) VALUES (:ip, :email, :action, :created_at)');
        $statement->execute([
            'ip' => $ipAddress,
            'email' => $email,
            'action' => $action,
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
