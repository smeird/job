<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

class Migrator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function migrate(): void
    {
        $this->createUsersTable();
        $this->createPendingPasscodesTable();
        $this->createSessionsTable();
        $this->createBackupCodesTable();
        $this->createAuditLogsTable();
    }

    private function createUsersTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }

    private function createPendingPasscodesTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS pending_passcodes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            action VARCHAR(32) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_pending_passcodes_email_action (email, action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }

    private function createSessionsTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash VARBINARY(255) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            CONSTRAINT fk_sessions_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_sessions_token_hash (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }

    private function createBackupCodesTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS backup_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_backup_codes_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }

    private function createAuditLogsTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            email VARCHAR(255) NOT NULL,
            action VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_audit_logs_lookup (ip_address, email, action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }
}
