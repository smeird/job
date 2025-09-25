<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

class Connection
{
    private PDO $pdo;

    public function __construct()
    {
        $dsn = getenv('DB_DSN');

        if ($dsn === false || $dsn === '') {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $database = getenv('DB_DATABASE') ?: 'job';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);
        }

        $username = getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to database: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
