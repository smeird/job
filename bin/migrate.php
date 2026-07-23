#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\DB;
use Dotenv\Dotenv;

require __DIR__ . '/../autoload.php';

/**
 * Execute one migration statement while emulating portable ADD COLUMN IF NOT EXISTS support on MySQL.
 */
function execute_migration_statement(PDO $pdo, string $sql): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $pattern = '/^\s*ALTER\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s+ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s+(.+)$/is';

    if ($driver === 'mysql' && preg_match($pattern, $sql, $matches) === 1) {
        $check = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
        );
        $check->execute([
            ':table_name' => $matches[1],
            ':column_name' => $matches[2],
        ]);

        if ((int) $check->fetchColumn() > 0) {
            return;
        }

        $sql = sprintf(
            'ALTER TABLE `%s` ADD COLUMN `%s` %s',
            $matches[1],
            $matches[2],
            $matches[3]
        );
    }

    $pdo->exec($sql);
}

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

    $supportsTransactionalDdl = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql';

    if ($supportsTransactionalDdl) {
        $pdo->beginTransaction();
    }

    try {
        foreach ($migration['up'] as $sql) {
            execute_migration_statement($pdo, $sql);
        }

        $insert = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $insert->execute(['migration' => $migrationId]);

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }
}

echo "Migrations complete.\n";
