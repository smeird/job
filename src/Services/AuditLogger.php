<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use JsonException;
use PDO;

class AuditLogger
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(
        string $action,
        array $details = [],
        ?int $userId = null,
        ?string $email = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, email, action, ip_address, user_agent, details, created_at) VALUES (:user_id, :email, :action, :ip_address, :user_agent, :details, :created_at)'
        );

        $detailsPayload = null;

        if ($details !== []) {
            try {
                $detailsPayload = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $detailsPayload = null;
            }
        }

        $statement->execute([
            'user_id' => $userId,
            'email' => $email,
            'action' => $action,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'details' => $detailsPayload,
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
