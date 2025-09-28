<?php

declare(strict_types=1);

namespace App\Generations;

use JsonException;
use PDO;
use PDOException;

use function is_array;
use function is_string;
use function json_decode;
use function max;
use function min;
use function sprintf;
use function trim;

use const JSON_THROW_ON_ERROR;

final class GenerationLogRepository
{
    /** @var PDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve the most recent tailoring process logs recorded for the user.
     *
     * Centralising the lookup keeps the Tailor dashboard lean and consistent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRecentForUser(int $userId, int $limit = 20): array
    {
        $safeLimit = min(max(1, $limit), 50);

        try {
            $statement = $this->pdo->prepare(
                'SELECT id, action, details, created_at '
                . 'FROM audit_logs '
                . 'WHERE user_id = :user_id AND action IN ("generation_failed") '
                . 'ORDER BY created_at DESC '
                . 'LIMIT :limit'
            );
        } catch (PDOException $exception) {
            return [];
        }

        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $statement->execute();

        $logs = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $logs[] = $this->mapRow($row);
        }

        return $logs;
    }

    /**
     * Transform a raw database row into a structured log representation.
     *
     * @param array<string, mixed> $row The database row fetched from audit_logs.
     * @return array<string, mixed> The normalised log payload ready for rendering.
     */
    private function mapRow(array $row): array
    {
        $action = isset($row['action']) ? (string) $row['action'] : '';
        $details = $this->decodeDetails($row['details'] ?? null);
        $error = $this->extractError($details);
        $generationId = isset($details['generation_id']) ? (int) $details['generation_id'] : null;

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'action' => $action,
            'generation_id' => $generationId,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'error' => $error,
            'message' => $this->buildMessage($action, $generationId, $error, $details),
        ];
    }

    /**
     * Decode the JSON payload stored within the audit log details column.
     *
     * @param mixed $payload The JSON string or null when details are absent.
     * @return array<string, mixed> A decoded representation suitable for inspection.
     */
    private function decodeDetails($payload): array
    {
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return ['raw' => $payload];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Extract a human-readable error string from the decoded details payload.
     *
     * @param array<string, mixed> $details The decoded details payload for the log entry.
     */
    private function extractError(array $details): ?string
    {
        if (isset($details['error']) && is_string($details['error'])) {
            $trimmed = trim($details['error']);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        if (isset($details['raw']) && is_string($details['raw'])) {
            $trimmed = trim($details['raw']);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Build the headline message presented alongside each log entry.
     *
     * @param array<string, mixed> $details The decoded details payload for the log entry.
     */
    private function buildMessage(string $action, ?int $generationId, ?string $error, array $details): string
    {
        if ($action === 'generation_failed') {
            $prefix = $generationId !== null && $generationId > 0
                ? sprintf('Generation #%d failed', $generationId)
                : 'Generation failed';

            if ($error !== null) {
                return $prefix . ' · review the error below';
            }

            return $prefix;
        }

        if ($error !== null) {
            return $error;
        }

        if (isset($details['message']) && is_string($details['message'])) {
            $trimmed = trim($details['message']);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return 'Processing log recorded.';
    }
}
