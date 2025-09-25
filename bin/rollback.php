#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\DB;
use Dotenv\Dotenv;

require __DIR__ . '/../autoload.php';

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

$statement = $pdo->query('SELECT migration FROM schema_migrations ORDER BY applied_at DESC, id DESC LIMIT 1');
$migrationId = $statement->fetchColumn();

if (!$migrationId) {
    echo "No migrations to rollback.\n";

    return;
}

$migrationsDirectory = $rootPath . '/database/migrations';
$filePath = $migrationsDirectory . '/' . $migrationId . '.php';

if (!is_file($filePath)) {
    throw new RuntimeException(sprintf('Migration file for %s not found.', $migrationId));
}

/** @var array{id?: string, up: array<int, string>, down?: array<int, string>} $migration */
$migration = require $filePath;

if (!isset($migration['down']) || !is_array($migration['down'])) {
    throw new RuntimeException(sprintf('Migration %s does not support rollback.', $migrationId));
}

echo sprintf("Rolling back migration %s...\n", $migrationId);

$pdo->beginTransaction();

try {
    foreach ($migration['down'] as $sql) {
        $pdo->exec($sql);
    }

    $delete = $pdo->prepare('DELETE FROM schema_migrations WHERE migration = :migration');
    $delete->execute(['migration' => $migrationId]);

    $pdo->commit();
} catch (Throwable $throwable) {
    $pdo->rollBack();

    throw $throwable;
}

echo "Rollback complete.\n";
