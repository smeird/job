<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;

class Migrator
{
    /** @var PDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Handle the migrate operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function migrate(): void
    {
        $this->createUsersTable();
        $this->createPendingPasscodesTable();
        $this->createSessionsTable();
        $this->createDocumentsTable();
        $this->createJobApplicationsTable();
        $this->createGenerationsTable();
        $this->createGenerationOutputsTable();
        $this->createApiUsageTable();
        $this->createBackupCodesTable();
        $this->createAuditLogsTable();
        $this->createRetentionSettingsTable();
        $this->createJobsTable();
    }

    /**
     * Create the users table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    private function createUsersTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            totp_secret VARCHAR(64) DEFAULT NULL,
            totp_period_seconds INT UNSIGNED DEFAULT NULL,
            totp_digits TINYINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
        $this->ensureUserColumnExists('totp_secret', 'ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER email');
        $this->ensureUserColumnExists('totp_period_seconds', 'ADD COLUMN totp_period_seconds INT UNSIGNED DEFAULT NULL AFTER totp_secret');
        $this->ensureUserColumnExists('totp_digits', 'ADD COLUMN totp_digits TINYINT UNSIGNED DEFAULT NULL AFTER totp_period_seconds');
    }

    /**
     * Create the pending passcodes table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    private function createPendingPasscodesTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS pending_passcodes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            action VARCHAR(32) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            totp_secret VARCHAR(64) DEFAULT NULL,
            period_seconds INT UNSIGNED NOT NULL DEFAULT 600,
            digits TINYINT UNSIGNED NOT NULL DEFAULT 6,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_pending_passcodes_email_action (email, action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);

        $this->ensurePendingPasscodeColumnExists('totp_secret', 'ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER code_hash');
        $this->ensurePendingPasscodeColumnExists('period_seconds', 'ADD COLUMN period_seconds INT UNSIGNED NOT NULL DEFAULT 600 AFTER totp_secret');
        $this->ensurePendingPasscodeColumnExists('digits', 'ADD COLUMN digits TINYINT UNSIGNED NOT NULL DEFAULT 6 AFTER period_seconds');
    }

    /**
     * Create the sessions table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
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

    /**
     * Create the documents table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
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

    /**
     * Create the job applications table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    private function createJobApplicationsTable(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS job_applications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL DEFAULT '',
                source_url TEXT NULL,
                description LONGTEXT NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'outstanding',
                applied_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_job_applications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_job_applications_user_status (user_id, status),
                INDEX idx_job_applications_user_created (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL;

            $this->pdo->exec($sql);

            return;
        }

        $sql = <<<'SQL'
        CREATE TABLE IF NOT EXISTS job_applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL DEFAULT '',
            source_url TEXT NULL,
            description TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'outstanding',
            applied_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        SQL;

        $this->pdo->exec($sql);

        $indexStatusSql = <<<'SQL'
        CREATE INDEX IF NOT EXISTS idx_job_applications_user_status
            ON job_applications (user_id, status);
        SQL;
        $this->pdo->exec($indexStatusSql);

        $indexCreatedSql = <<<'SQL'
        CREATE INDEX IF NOT EXISTS idx_job_applications_user_created
            ON job_applications (user_id, created_at);
        SQL;
        $this->pdo->exec($indexCreatedSql);
    }

    /**
     * Create the generations table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    private function createGenerationsTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS generations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            job_document_id BIGINT UNSIGNED NOT NULL,
            cv_document_id BIGINT UNSIGNED NOT NULL,
            model VARCHAR(128) NOT NULL,
            thinking_time TINYINT UNSIGNED NOT NULL DEFAULT 30,
            status VARCHAR(32) NOT NULL DEFAULT 'queued',
            progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            cost_pence BIGINT UNSIGNED NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_generations_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_generations_job_document FOREIGN KEY (job_document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_generations_cv_document FOREIGN KEY (cv_document_id) REFERENCES documents(id) ON DELETE CASCADE,
            INDEX idx_generations_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
        $this->ensureGenerationsColumnExists('job_document_id', 'ADD COLUMN job_document_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER user_id');
        $this->ensureGenerationsColumnExists('cv_document_id', 'ADD COLUMN cv_document_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER job_document_id');
        $this->ensureGenerationsColumnExists('thinking_time', 'ADD COLUMN thinking_time TINYINT UNSIGNED NOT NULL DEFAULT 30 AFTER model');
        $this->dropGenerationsColumnIfExists('temperature');
        $this->ensureGenerationsColumnExists('progress_percent', 'ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status');
        $this->ensureGenerationsColumnExists('cost_pence', 'ADD COLUMN cost_pence BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER progress_percent');
        $this->ensureGenerationsColumnExists('error_message', 'ADD COLUMN error_message TEXT NULL AFTER cost_pence');
    }

    /**
     * Create the generation outputs table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    private function createGenerationOutputsTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS generation_outputs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            generation_id BIGINT UNSIGNED NOT NULL,
            mime_type VARCHAR(191) NULL,
            content LONGBLOB NULL,
            output_text LONGTEXT NULL,
            tokens_used INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_generation_outputs_generation FOREIGN KEY (generation_id) REFERENCES generations(id) ON DELETE CASCADE,
            INDEX idx_generation_outputs_generation_created (generation_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }

    /**
     * Create the api usage table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    private function createApiUsageTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS api_usage (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(128) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            tokens_used INT UNSIGNED NULL,
            cost_pence BIGINT UNSIGNED NOT NULL DEFAULT 0,
            metadata JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_api_usage_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_api_usage_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }

    /**
     * Create the backup codes table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
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

    /**
     * Create the audit logs table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
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

    /**
     * Create the retention settings table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    private function createRetentionSettingsTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS retention_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            purge_after_days INT UNSIGNED NOT NULL,
            apply_to JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }

    /**
     * Create the jobs table instance.
     *
     * This method standardises construction so other code can rely on it.
     */
    private function createJobsTable(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(100) NOT NULL,
            payload_json JSON NOT NULL,
            run_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_jobs_status_run_after (status, run_after),
            INDEX idx_jobs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->pdo->exec($sql);
    }

    /**
     * Handle the ensure audit column exists operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Handle the ensure pending passcode column exists operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function ensurePendingPasscodeColumnExists(string $column, string $alterStatement): void
    {
        try {
            $statement = $this->pdo->prepare('SHOW COLUMNS FROM pending_passcodes LIKE :column');
            $statement->execute(['column' => $column]);

            if ($statement->fetch() !== false) {
                return;
            }

            $this->pdo->exec(sprintf('ALTER TABLE pending_passcodes %s', $alterStatement));
        } catch (PDOException $exception) {
            // Ignore inability to inspect or alter the table.
        }
    }

    /**
     * Handle the ensure generations column exists operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function ensureGenerationsColumnExists(string $column, string $alterStatement): void
    {
        try {
            $statement = $this->pdo->prepare('SHOW COLUMNS FROM generations LIKE :column');
            $statement->execute(['column' => $column]);

            if ($statement->fetch() !== false) {
                return;
            }

            $this->pdo->exec(sprintf('ALTER TABLE generations %s', $alterStatement));
        } catch (PDOException $exception) {
            // Ignore inability to inspect or alter the table.
        }
    }

    /**
     * Handle the drop generations column if exists operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function dropGenerationsColumnIfExists(string $column): void
    {
        try {
            $statement = $this->pdo->prepare('SHOW COLUMNS FROM generations LIKE :column');
            $statement->execute(['column' => $column]);

            if ($statement->fetch() === false) {
                return;
            }

            $this->pdo->exec(sprintf('ALTER TABLE generations DROP COLUMN %s', $column));
        } catch (PDOException $exception) {
            // Ignore inability to inspect or alter the table.
        }
    }

    /**
     * Handle the ensure audit email nullable operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Handle the ensure user column exists operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function ensureUserColumnExists(string $column, string $alterStatement): void
    {
        try {
            $statement = $this->pdo->prepare('SHOW COLUMNS FROM users LIKE :column');
            $statement->execute(['column' => $column]);

            if ($statement->fetch() !== false) {
                return;
            }

            $this->pdo->exec(sprintf('ALTER TABLE users %s', $alterStatement));
        } catch (PDOException $exception) {
            // Ignore inability to inspect or alter the table.
        }
    }
}
