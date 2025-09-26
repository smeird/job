<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;

class RetentionPolicyService
{
    private const TABLE_NAME = 'retention_settings';

    /** @var PDO */
    private $pdo;

    /**
     * @var array<int, string>
     */
    private const ALLOWED_RESOURCES = [
        'documents',
        'generation_outputs',
        'api_usage',
        'audit_logs',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    /**
     * @return array{purge_after_days: int, apply_to: array<int, string>}
     */
    public function getPolicy(): array
    {
        $statement = $this->pdo->prepare(
            sprintf('SELECT purge_after_days, apply_to FROM %s WHERE id = :id LIMIT 1', self::TABLE_NAME)
        );
        $statement->bindValue(':id', 1, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return [
                'purge_after_days' => 30,
                'apply_to' => self::ALLOWED_RESOURCES,
            ];
        }

        try {
            $applyTo = json_decode((string) $row['apply_to'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $applyTo = [];
        }

        if (!is_array($applyTo)) {
            $applyTo = [];
        }

        $filtered = array_values(array_intersect(self::ALLOWED_RESOURCES, array_map('strval', $applyTo)));

        if ($filtered === []) {
            $filtered = self::ALLOWED_RESOURCES;
        }

        return [
            'purge_after_days' => max(1, (int) $row['purge_after_days']),
            'apply_to' => $filtered,
        ];
    }

    /**
     * @param array<int, string> $applyTo
     */
    public function updatePolicy(int $purgeAfterDays, array $applyTo): void
    {
        if ($purgeAfterDays < 1) {
            throw new RuntimeException('Retention period must be at least one day.');
        }

        $allowed = $this->getAllowedResources();
        $filtered = array_values(array_unique(array_intersect($allowed, array_map('strval', $applyTo))));

        if ($filtered === []) {
            throw new RuntimeException('Select at least one data type to apply retention.');
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $payload = json_encode($filtered, JSON_THROW_ON_ERROR);

        $this->pdo->beginTransaction();

        try {
            $existing = $this->pdo->prepare(
                sprintf('SELECT 1 FROM %s WHERE id = :id LIMIT 1', self::TABLE_NAME)
            );
            $existing->bindValue(':id', 1, PDO::PARAM_INT);
            $existing->execute();
            $hasRow = (bool) $existing->fetchColumn();

            if ($hasRow) {
                $update = $this->pdo->prepare(
                    sprintf(
                        'UPDATE %s SET purge_after_days = :purge_after_days, apply_to = :apply_to, updated_at = :updated_at WHERE id = :id',
                        self::TABLE_NAME
                    )
                );
                $update->bindValue(':purge_after_days', $purgeAfterDays, PDO::PARAM_INT);
                $update->bindValue(':apply_to', $payload);
                $update->bindValue(':updated_at', $now);
                $update->bindValue(':id', 1, PDO::PARAM_INT);
                $update->execute();
            } else {
                $insert = $this->pdo->prepare(
                    sprintf(
                        'INSERT INTO %s (id, purge_after_days, apply_to, created_at, updated_at) VALUES (:id, :purge_after_days, :apply_to, :created_at, :updated_at)',
                        self::TABLE_NAME
                    )
                );
                $insert->bindValue(':id', 1, PDO::PARAM_INT);
                $insert->bindValue(':purge_after_days', $purgeAfterDays, PDO::PARAM_INT);
                $insert->bindValue(':apply_to', $payload);
                $insert->bindValue(':created_at', $now);
                $insert->bindValue(':updated_at', $now);
                $insert->execute();
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedResources(): array
    {
        return self::ALLOWED_RESOURCES;
    }

    private function ensureTable(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->pdo->exec(
                sprintf(
                    'CREATE TABLE IF NOT EXISTS %s (
                        id TINYINT UNSIGNED PRIMARY KEY,
                        purge_after_days INT UNSIGNED NOT NULL,
                        apply_to JSON NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                    self::TABLE_NAME
                )
            );

            return;
        }

        $this->pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    id INTEGER PRIMARY KEY,
                    purge_after_days INTEGER NOT NULL,
                    apply_to TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )',
                self::TABLE_NAME
            )
        );
    }
}
