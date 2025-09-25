<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use PDO;

class RateLimiter
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AuditLogger $auditLogger,
        private readonly int $limit,
        private readonly DateInterval $interval
    ) {
    }

    public function tooManyAttempts(string $ipAddress, string $identifier, string $action): bool
    {
        $windowStart = (new DateTimeImmutable())->sub($this->interval)->format('Y-m-d H:i:s');

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE ip_address = :ip AND email = :email AND action = :action AND created_at >= :start');
        $statement->execute([
            'ip' => $ipAddress,
            'email' => $identifier,
            'action' => $action,
            'start' => $windowStart,
        ]);

        $count = (int) $statement->fetchColumn();

        return $count >= $this->limit;
    }

    public function hit(string $ipAddress, string $identifier, string $action, ?string $userAgent = null, array $details = []): void
    {
        $this->auditLogger->log($action, $details, null, $identifier, $ipAddress, $userAgent);
    }

    public function getIntervalSeconds(): int
    {
        $reference = new DateTimeImmutable('@0');

        return (int) $reference->add($this->interval)->format('U');
    }
}
