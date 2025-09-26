<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use PDO;

class RateLimiter
{
    /** @var PDO */
    private $pdo;

    /** @var AuditLogger */
    private $auditLogger;

    /** @var int */
    private $limit;

    /** @var DateInterval */
    private $interval;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        PDO $pdo,
        AuditLogger $auditLogger,
        int $limit,
        DateInterval $interval
    ) {
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
        $this->limit = $limit;
        $this->interval = $interval;
    }

    /**
     * Handle the too many attempts operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Handle the hit operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function hit(string $ipAddress, string $identifier, string $action, ?string $userAgent = null, array $details = []): void
    {
        $this->auditLogger->log($action, $details, null, $identifier, $ipAddress, $userAgent);
    }

    /**
     * Retrieve the interval seconds.
     *
     * The helper centralises access to the interval seconds so callers stay tidy.
     */
    public function getIntervalSeconds(): int
    {
        $reference = new DateTimeImmutable('@0');

        return (int) $reference->add($this->interval)->format('U');
    }
}
