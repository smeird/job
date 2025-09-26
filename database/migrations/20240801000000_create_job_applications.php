<?php

declare(strict_types=1);

return [
    'id' => '20240801000000_create_job_applications',
    'up' => [
        "CREATE TABLE IF NOT EXISTS job_applications (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS job_applications',
    ],
];
