<?php

declare(strict_types=1);

return [
    'id' => '20240718000001_add_generation_stream_columns',
    'up' => [
        "ALTER TABLE generations ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status",
        "ALTER TABLE generations ADD COLUMN error_message TEXT NULL AFTER cost_pence",
    ],
    'down' => [
        'ALTER TABLE generations DROP COLUMN error_message',
        'ALTER TABLE generations DROP COLUMN progress_percent',
    ],
];
