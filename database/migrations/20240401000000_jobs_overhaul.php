<?php

declare(strict_types=1);

return [
    'id' => '20240401000000_jobs_overhaul',
    'up' => [
        'DROP TABLE IF EXISTS jobs',
        "CREATE TABLE jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(100) NOT NULL,
            payload_json JSON NOT NULL,
            run_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_jobs_status_run_after (status, run_after),
            KEY idx_jobs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS jobs',
        "CREATE TABLE jobs (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];
