#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\DB;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$rootPath = dirname(__DIR__);

if (is_dir($rootPath)) {
    if (is_file($rootPath . '/.env')) {
        Dotenv::createImmutable($rootPath)->safeLoad();
    } else {
        Dotenv::createImmutable($rootPath)->safeLoad();
    }
}

$pdo = DB::getConnection();

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$migrationsDirectory = $rootPath . '/database/migrations';

if (!is_dir($migrationsDirectory)) {
    fwrite(STDERR, "No migrations directory found at {$migrationsDirectory}\n");
    exit(1);
}

$files = glob($migrationsDirectory . '/*.php');
sort($files);

foreach ($files as $file) {
    /** @var array{id?: string, up: array<int, string>, down?: array<int, string>} $migration */
    $migration = require $file;

    if (!isset($migration['up']) || !is_array($migration['up'])) {
        throw new RuntimeException(sprintf('Migration file %s is missing an up() definition.', basename($file)));
    }

    $migrationId = $migration['id'] ?? basename($file, '.php');

    $statement = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = :migration LIMIT 1');
    $statement->execute(['migration' => $migrationId]);

    if ($statement->fetchColumn()) {
        continue;
    }

    echo sprintf("Applying migration %s...\n", $migrationId);

    $pdo->beginTransaction();

    try {
        foreach ($migration['up'] as $sql) {
            $pdo->exec($sql);
        }

        $insert = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $insert->execute(['migration' => $migrationId]);

        $pdo->commit();
    } catch (Throwable $throwable) {
        $pdo->rollBack();

        throw $throwable;
    }
}

echo "Migrations complete.\n";
