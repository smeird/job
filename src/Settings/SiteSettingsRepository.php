<?php

declare(strict_types=1);

namespace App\Settings;

use PDO;
use PDOException;
use RuntimeException;

/**
 * SiteSettingsRepository provides a thin abstraction for reading global
 * configuration values that are shared between the web front end and the
 * background worker processes.
 */
final class SiteSettingsRepository
{
    /** @var PDO */
    private $pdo;

    /**
     * Construct the repository with the database connection required for all
     * lookups.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve the stored value for the provided configuration key.
     *
     * Keeping the lookup logic here lets application services reuse the same
     * behaviour without duplicating SQL or error handling.
     *
     * @return string|null The stored configuration value or null when missing.
     */
    public function findValue(string $key): ?string
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT value FROM site_settings WHERE name = :name LIMIT 1'
            );
            $statement->execute([':name' => $key]);
            $value = $statement->fetchColumn();

            if ($value === false || $value === null) {
                return null;
            }

            return (string) $value;
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to read site setting value.', 0, $exception);
        }
    }
}
