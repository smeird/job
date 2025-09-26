<?php

declare(strict_types=1);

namespace App\Generations;

use App\DB;
use DateTimeImmutable;
use PDO;

class GenerationStreamRepository
{
    /** @var PDO */
    private $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? DB::getConnection();
    }

    public function fetchSnapshot(int $generationId): ?GenerationStreamSnapshot
    {
        $statement = $this->connection->prepare(
            'SELECT id, status, progress_percent, cost_pence, error_message, updated_at FROM generations WHERE id = :id LIMIT 1'
        );

        $statement->execute(['id' => $generationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $progressPercent = max(0, min(100, (int) ($row['progress_percent'] ?? 0)));
        $costPence = (int) ($row['cost_pence'] ?? 0);
        $status = (string) ($row['status'] ?? 'pending');
        $errorMessage = $row['error_message'] ?? null;
        $updatedAt = new DateTimeImmutable((string) $row['updated_at']);

        $totalsStatement = $this->connection->prepare(
            'SELECT COALESCE(SUM(tokens_used), 0) AS total_tokens, MAX(created_at) AS latest_output_at FROM generation_outputs WHERE generation_id = :id'
        );
        $totalsStatement->execute(['id' => $generationId]);
        $totals = $totalsStatement->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalTokens = (int) ($totals['total_tokens'] ?? 0);
        $latestOutputAt = null;

        if (!empty($totals['latest_output_at'])) {
            $latestOutputAt = new DateTimeImmutable((string) $totals['latest_output_at']);
        }

        return new GenerationStreamSnapshot(
            (int) $row['id'],
            $status,
            $progressPercent,
            $costPence,
            $totalTokens,
            $errorMessage !== null ? (string) $errorMessage : null,
            $updatedAt,
            $latestOutputAt,
        );
    }
}
