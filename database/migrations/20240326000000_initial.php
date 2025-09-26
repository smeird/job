<?php

declare(strict_types=1);

return [
    'id' => '20240326000000_initial',
    'up' => [
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            totp_secret VARCHAR(64) DEFAULT NULL,
            totp_period_seconds INT UNSIGNED DEFAULT NULL,
            totp_digits TINYINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS pending_passcodes (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash VARBINARY(255) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            CONSTRAINT fk_sessions_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_sessions_token_hash (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS documents (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS generations (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS generation_outputs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            generation_id BIGINT UNSIGNED NOT NULL,
            mime_type VARCHAR(191) NULL,
            content LONGBLOB NULL,
            output_text LONGTEXT NULL,
            tokens_used INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_generation_outputs_generation (generation_id) REFERENCES generations(id) ON DELETE CASCADE,
            INDEX idx_generation_outputs_generation_created (generation_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS api_usage (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS backup_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_backup_codes_user (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NOT NULL,
            email VARCHAR(255) NULL,
            action VARCHAR(64) NOT NULL,
            user_agent VARCHAR(255) NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_audit_logs_lookup (ip_address, email, action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS retention_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            purge_after_days INT UNSIGNED NOT NULL,
            apply_to JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS jobs (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'down' => [
        'DROP TABLE IF EXISTS jobs',
        'DROP TABLE IF EXISTS retention_settings',
        'DROP TABLE IF EXISTS generation_outputs',
        'DROP TABLE IF EXISTS generations',
        'DROP TABLE IF EXISTS documents',
        'DROP TABLE IF EXISTS api_usage',
        'DROP TABLE IF EXISTS audit_logs',
        'DROP TABLE IF EXISTS backup_codes',
        'DROP TABLE IF EXISTS sessions',
        'DROP TABLE IF EXISTS pending_passcodes',
        'DROP TABLE IF EXISTS users'
    ],
];
