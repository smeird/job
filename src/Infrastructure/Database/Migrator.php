<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;

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
        $this->createDocumentsTable();
        $this->createGenerationsTable();
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

    private function createDocumentsTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            document_type VARCHAR(32) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(191) NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL,
            sha256 CHAR(64) NOT NULL UNIQUE,
            content LONGBLOB NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_documents_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_documents_user_type (user_id, document_type),
            INDEX idx_documents_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }

    private function createGenerationsTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS generations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            job_document_id BIGINT UNSIGNED NOT NULL,
            cv_document_id BIGINT UNSIGNED NOT NULL,
            model VARCHAR(128) NOT NULL,
            temperature DECIMAL(4,2) NOT NULL DEFAULT 0.20,
            status VARCHAR(32) NOT NULL DEFAULT 'queued',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_generations_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_generations_job_document FOREIGN KEY (job_document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_generations_cv_document FOREIGN KEY (cv_document_id) REFERENCES documents(id) ON DELETE CASCADE,
            INDEX idx_generations_user_created (user_id, created_at)
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
            user_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NOT NULL,
            email VARCHAR(255) NULL,
            action VARCHAR(64) NOT NULL,
            user_agent VARCHAR(255) NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_audit_logs_lookup (ip_address, email, action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
        $this->ensureAuditColumnExists('audit_logs', 'user_id', 'ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id');
        $this->ensureAuditColumnExists('audit_logs', 'user_agent', 'ADD COLUMN user_agent VARCHAR(255) NULL AFTER action');
        $this->ensureAuditColumnExists('audit_logs', 'details', 'ADD COLUMN details TEXT NULL AFTER user_agent');
        $this->ensureAuditEmailNullable();
    }

    private function ensureAuditColumnExists(string $table, string $column, string $alterStatement): void
    {
        try {
            $statement = $this->pdo->prepare(sprintf('SHOW COLUMNS FROM %s LIKE :column', $table));
            $statement->execute(['column' => $column]);

            if ($statement->fetch() !== false) {
                return;
            }

            $this->pdo->exec(sprintf('ALTER TABLE %s %s', $table, $alterStatement));
        } catch (PDOException $exception) {
            // Ignore inability to inspect or alter the table; migration may be running on a database without SHOW COLUMNS support.
        }
    }

    private function ensureAuditEmailNullable(): void
    {
        try {
            $statement = $this->pdo->query("SHOW COLUMNS FROM audit_logs LIKE 'email'");
            $column = $statement === false ? false : $statement->fetch();

            if ($column !== false && isset($column['Null']) && $column['Null'] === 'NO') {
                $this->pdo->exec('ALTER TABLE audit_logs MODIFY email VARCHAR(255) NULL');
            }
        } catch (PDOException $exception) {
            // Ignore if the column cannot be inspected.
        }
    }
}
