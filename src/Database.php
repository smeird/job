<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

class Database
{
    private static ?PDO $connection = null;

    /**
     * Handle the connection operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = $_ENV['DB_DSN'] ?? getenv('DB_DSN');

        if ($dsn === false || $dsn === null || $dsn === '') {
            $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
            $database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');

            if ($host && $database) {
                $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
                $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);
            }
        }

        if (!$dsn) {
            throw new RuntimeException('Database configuration missing. Set DB_DSN or DB_HOST/DB_DATABASE.');
        }

        $username = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: null;
        $password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        self::$connection = new PDO($dsn, $username, $password, $options);

        return self::$connection;
    }
}
