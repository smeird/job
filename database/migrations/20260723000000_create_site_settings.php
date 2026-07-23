<?php

declare(strict_types=1);

return [
    'id' => '20260723000000_create_site_settings',
    'up' => [
        "CREATE TABLE IF NOT EXISTS site_settings (
            name VARCHAR(191) NOT NULL PRIMARY KEY,
            value TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS site_settings',
    ],
];
