<?php

declare(strict_types=1);

namespace App\Contacts;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

use function is_array;

/**
 * ContactDetailsRepository persists and retrieves the per-user contact details
 * used across tailored cover letter generations.
 */
final class ContactDetailsRepository
{
    /** @var PDO */
    private $pdo;

    /**
     * Construct the repository with the application's PDO connection.
     *
     * Sharing the PDO instance keeps transactions consistent across services.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Fetch the saved contact details for the specified user.
     *
     * Returning a normalised associative array keeps downstream consumers
     * decoupled from the underlying database representation.
     *
     * @return array<string, mixed>|null
     */
    public function findForUser(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_id, address, phone, email, created_at, updated_at '
            . 'FROM user_contact_details WHERE user_id = :user_id LIMIT 1'
        );

        $statement->execute([':user_id' => $userId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false || !is_array($row)) {
            return null;
        }

        return [
            'user_id' => (int) $row['user_id'],
            'address' => (string) $row['address'],
            'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
            'email' => $row['email'] !== null ? (string) $row['email'] : null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /**
     * Insert or update the user's contact details in a single operation.
     *
     * Using an UPSERT keeps the storage logic resilient when the user revisits
     * the form multiple times.
     *
     * @return array<string, mixed>
     */
    public function upsert(int $userId, string $address, ?string $phone, ?string $email): array
    {
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO user_contact_details (user_id, address, phone, email, created_at, updated_at) '
            . 'VALUES (:user_id, :address, :phone, :email, :created_at, :updated_at) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'address = VALUES(address), '
            . 'phone = VALUES(phone), '
            . 'email = VALUES(email), '
            . 'updated_at = VALUES(updated_at)'
        );

        try {
            $statement->execute([
                ':user_id' => $userId,
                ':address' => $address,
                ':phone' => $phone,
                ':email' => $email,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to save contact details at this time.', 0, $exception);
        }

        $details = $this->findForUser($userId);

        if ($details === null) {
            throw new RuntimeException('Contact details could not be retrieved after saving.');
        }

        return $details;
    }
}
