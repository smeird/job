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
        $dsn = $this->envValue('DB_DSN', true);

        if ($dsn === null || $dsn === '') {
            $host = $this->envFrom(['DB_HOST', 'MYSQL_HOST'], '127.0.0.1');
            $port = $this->envFrom(['DB_PORT', 'MYSQL_PORT'], '3306');
            $database = $this->envFrom(['DB_DATABASE', 'MYSQL_DATABASE', 'MYSQL_DB'], 'job');
            $charset = $this->envFrom(['DB_CHARSET'], 'utf8mb4');
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);
        }

        $username = $this->envFrom(['DB_USERNAME', 'DB_USER', 'MYSQL_USER'], 'root');
        $password = $this->envFrom(['DB_PASSWORD', 'MYSQL_PASSWORD'], null, true);

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

    private function envFrom(array $keys, ?string $default = null, bool $allowEmpty = false): ?string
    {
        foreach ($keys as $key) {
            $value = $this->envValue($key, $allowEmpty);

            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    private function envValue(string $key, bool $allowEmpty = false): ?string
    {
        $value = $this->readRawEnv($key);

        if (is_string($value)) {
            $value = $allowEmpty ? rtrim($value, "\r\n") : trim($value);

            if ($value === '' && !$allowEmpty) {
                $value = null;
            }
        }

        if ($value === null || ($value === '' && !$allowEmpty)) {
            $fileValue = $this->readEnvFromFile($key, $allowEmpty);

            if ($fileValue !== null) {
                return $fileValue;
            }
        }

        if ($value === null) {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @return string|false|null
     */
    private function readRawEnv(string $key)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);

        return $value === false ? null : $value;
    }

    private function readEnvFromFile(string $key, bool $allowEmpty): ?string
    {
        $fileKey = $key . '_FILE';
        $filePath = $this->readRawEnv($fileKey);

        if (!is_string($filePath) || $filePath === '') {
            return null;
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException(sprintf('Environment variable %s points to unreadable file "%s".', $fileKey, $filePath));
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read environment file "%s" referenced by %s.', $filePath, $fileKey));
        }

        $contents = $allowEmpty ? rtrim($contents, "\r\n") : trim($contents);

        if ($contents === '' && !$allowEmpty) {
            return null;
        }

        return $contents;
    }
}
