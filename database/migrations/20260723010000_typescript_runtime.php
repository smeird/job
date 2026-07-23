<?php

declare(strict_types=1);

return [
    'id' => '20260723010000_typescript_runtime',
    'up' => [
        "ALTER TABLE job_applications ADD COLUMN IF NOT EXISTS reason_code VARCHAR(64) NULL AFTER applied_at",
        "ALTER TABLE job_applications ADD COLUMN IF NOT EXISTS generation_id BIGINT UNSIGNED NULL AFTER reason_code",
        "ALTER TABLE job_applications ADD INDEX IF NOT EXISTS idx_job_applications_generation (generation_id)",
        "ALTER TABLE job_applications ADD CONSTRAINT IF NOT EXISTS fk_job_applications_generation FOREIGN KEY (generation_id) REFERENCES generations(id) ON DELETE SET NULL",
        "ALTER TABLE jobs ADD COLUMN IF NOT EXISTS runtime_queue VARCHAR(32) NOT NULL DEFAULT 'php' AFTER payload_json",
        "UPDATE jobs SET runtime_queue = 'php' WHERE runtime_queue IS NULL OR runtime_queue = ''",
        "ALTER TABLE jobs ADD INDEX IF NOT EXISTS idx_jobs_runtime_status_run_after (runtime_queue, status, run_after)",
    ],
    'down' => [
        'ALTER TABLE jobs DROP INDEX idx_jobs_runtime_status_run_after',
        'ALTER TABLE jobs DROP COLUMN runtime_queue',
    ],
];
