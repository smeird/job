<?php

declare(strict_types=1);

return [
    'id' => '20241001000000_create_job_application_research',
    'up' => [
        <<<SQL
        CREATE TABLE IF NOT EXISTS job_application_research (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            job_application_id BIGINT UNSIGNED NOT NULL,
            query VARCHAR(512) NOT NULL,
            summary LONGTEXT NOT NULL,
            search_results LONGTEXT NOT NULL,
            generated_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_job_application_research_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_job_application_research_application FOREIGN KEY (job_application_id) REFERENCES job_applications(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_job_application_research_application (user_id, job_application_id),
            INDEX idx_job_application_research_generated (generated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS job_application_research',
    ],
];
