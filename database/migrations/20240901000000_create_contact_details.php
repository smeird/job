<?php

declare(strict_types=1);

return [
    'id' => '20240901000000_create_contact_details',
    'up' => [
        "CREATE TABLE IF NOT EXISTS user_contact_details (
            user_id BIGINT UNSIGNED NOT NULL,
            address TEXT NOT NULL,
            phone VARCHAR(64) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_id),
            CONSTRAINT fk_user_contact_details_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS user_contact_details',
    ],
];
