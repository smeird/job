<?php

declare(strict_types=1);

return [
    'id' => '20240326000000_initial',
    'up' => [
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_users_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS passcodes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            passcode CHAR(12) NOT NULL,
            context VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY fk_passcodes_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_passcodes_user_id (user_id),
            KEY idx_passcodes_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS backup_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            code CHAR(32) NOT NULL,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY fk_backup_codes_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uq_backup_codes_user_code (user_id, code),
            KEY idx_backup_codes_user_id (user_id),
            KEY idx_backup_codes_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(127) NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL,
            checksum CHAR(64) NULL,
            content LONGBLOB NULL,
            extracted_text LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY fk_documents_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_documents_user_id (user_id),
            KEY idx_documents_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS generations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            document_id BIGINT UNSIGNED NULL,
            model VARCHAR(128) NOT NULL,
            prompt LONGTEXT NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            cost_pence BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY fk_generations_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY fk_generations_document (document_id) REFERENCES documents(id) ON DELETE SET NULL,
            KEY idx_generations_user_id (user_id),
            KEY idx_generations_created_at (created_at),
            KEY idx_generations_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS generation_outputs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            generation_id BIGINT UNSIGNED NOT NULL,
            mime_type VARCHAR(127) NULL,
            content LONGBLOB NULL,
            output_text LONGTEXT NULL,
            tokens_used INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY fk_generation_outputs_generation (generation_id) REFERENCES generations(id) ON DELETE CASCADE,
            KEY idx_generation_outputs_generation_id (generation_id),
            KEY idx_generation_outputs_created_at (created_at)
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
            FOREIGN KEY fk_api_usage_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_api_usage_user_id (user_id),
            KEY idx_api_usage_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            action VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            details JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY fk_audit_logs_user (user_id) REFERENCES users(id) ON DELETE SET NULL,
            KEY idx_audit_logs_user_id (user_id),
            KEY idx_audit_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS retention_policies (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            resource_type VARCHAR(64) NOT NULL,
            retention_days INT UNSIGNED NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY fk_retention_policies_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_retention_policies_user_id (user_id),
            KEY idx_retention_policies_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue VARCHAR(64) NOT NULL,
            payload LONGTEXT NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reserved_at DATETIME NULL,
            completed_at DATETIME NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_jobs_queue_available (queue, available_at),
            KEY idx_jobs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    'down' => [
        'DROP TABLE IF EXISTS generation_outputs',
        'DROP TABLE IF EXISTS generations',
        'DROP TABLE IF EXISTS documents',
        'DROP TABLE IF EXISTS jobs',
        'DROP TABLE IF EXISTS retention_policies',
        'DROP TABLE IF EXISTS audit_logs',
        'DROP TABLE IF EXISTS api_usage',
        'DROP TABLE IF EXISTS backup_codes',
        'DROP TABLE IF EXISTS passcodes',
        'DROP TABLE IF EXISTS users'
    ],
];
