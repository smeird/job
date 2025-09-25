<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

class DB
{
    public static function getConnection(): PDO
    {
        $config = self::resolveConfig();

        try {
            $pdo = new PDO(
                $config['dsn'],
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to the database.', 0, $exception);
        }

        return $pdo;
    }

    /**
     * @return array{dsn: string, username: string|null, password: string|null, options: array<int, mixed>}
     */
    private static function resolveConfig(): array
    {
        $dsn = self::env('DB_DSN');
        $username = self::env('DB_USERNAME');
        $password = self::env('DB_PASSWORD');

        if ($dsn === null || $dsn === '') {
            $driver = self::env('DB_DRIVER') ?? 'mysql';

            if ($driver === 'sqlite') {
                $database = self::env('DB_DATABASE') ?? ':memory:';
                $dsn = sprintf('sqlite:%s', $database);
            } else {
                $host = self::env('DB_HOST') ?? '127.0.0.1';
                $port = self::env('DB_PORT') ?? '3306';
                $database = self::env('DB_DATABASE') ?? 'app';
                $charset = self::env('DB_CHARSET') ?? 'utf8mb4';
                $unixSocket = self::env('DB_SOCKET');

                if ($unixSocket !== null && $unixSocket !== '') {
                    $dsn = sprintf('%s:unix_socket=%s;dbname=%s;charset=%s', $driver, $unixSocket, $database, $charset);
                } else {
                    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $driver, $host, $port, $database, $charset);
                }
            }
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if (str_starts_with($dsn, 'mysql:')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return [
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
            'options' => $options,
        ];
    }

    private static function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return null;
        }

        $trimmed = is_string($value) ? trim($value) : null;

        return $trimmed === '' ? null : $trimmed;
    }
}
