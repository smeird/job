<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use JsonException;
use PDO;

class UsageService
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
     * Retrieve the usage for user.
     *
     * The helper centralises access to the usage for user so callers stay tidy.
     */
    public function getUsageForUser(int $userId): array
    {
        [$perRun, $totals] = $this->fetchPerRun($userId);
        $monthly = $this->fetchMonthlySummary($userId);

        return [
            'per_run' => $perRun,
            'totals' => $totals,
            'monthly' => $monthly,
        ];
    }

    /**
     * Fetch the per run from its provider.
     *
     * Centralised fetching makes upstream integrations easier to evolve.
     * @return array{0: array<int, array<string, int|string|null>>, 1: array{current_month: array<string, int>, lifetime: array<string, int>}}
     */
    private function fetchPerRun(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, provider, endpoint, tokens_used, cost_pence, metadata, created_at '
            . 'FROM api_usage WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        $perRun = [];
        $now = new DateTimeImmutable('now');
        $currentMonthStart = $now->modify('first day of this month 00:00:00');

        $monthTotals = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost_pence' => 0,
        ];
        $lifetimeTotals = $monthTotals;

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $createdAt = $this->normaliseDate($row['created_at'] ?? null);
            $metadata = $this->decodeMetadata($row['metadata'] ?? null);

            $promptTokens = (int) ($metadata['prompt_tokens'] ?? 0);
            $completionTokens = (int) ($metadata['completion_tokens'] ?? 0);
            $totalTokens = (int) ($metadata['total_tokens'] ?? $row['tokens_used'] ?? 0);
            $costPence = (int) ($row['cost_pence'] ?? 0);
            $model = (string) ($metadata['model'] ?? 'unknown');

            $entry = [
                'id' => (int) ($row['id'] ?? 0),
                'provider' => (string) ($row['provider'] ?? ''),
                'endpoint' => (string) ($row['endpoint'] ?? ''),
                'model' => $model !== '' ? $model : 'unknown',
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'cost_pence' => $costPence,
                'created_at' => $createdAt !== null ? $createdAt->format(DATE_ATOM) : null,
            ];

            if ($entry['created_at'] === null && isset($row['created_at'])) {
                $entry['created_at'] = (string) $row['created_at'];
            }

            $perRun[] = $entry;

            $lifetimeTotals['prompt_tokens'] += $promptTokens;
            $lifetimeTotals['completion_tokens'] += $completionTokens;
            $lifetimeTotals['total_tokens'] += $totalTokens;
            $lifetimeTotals['cost_pence'] += $costPence;

            if ($createdAt !== null && $createdAt >= $currentMonthStart) {
                $monthTotals['prompt_tokens'] += $promptTokens;
                $monthTotals['completion_tokens'] += $completionTokens;
                $monthTotals['total_tokens'] += $totalTokens;
                $monthTotals['cost_pence'] += $costPence;
            }
        }

        return [
            $perRun,
            [
                'current_month' => $monthTotals,
                'lifetime' => $lifetimeTotals,
            ],
        ];
    }

    /**
     * Fetch the monthly summary from its provider.
     *
     * Centralised fetching makes upstream integrations easier to evolve.
     * @return array<int, array{month: string, total_tokens: int, cost_pence: int}>
     */
    private function fetchMonthlySummary(int $userId): array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $monthExpression = "strftime('%Y-%m-01', created_at)";
        } else {
            $monthExpression = "DATE_FORMAT(created_at, '%Y-%m-01')";
        }

        $sql = sprintf(
            'SELECT %s AS month_start, SUM(tokens_used) AS total_tokens, SUM(cost_pence) AS total_cost '
            . 'FROM api_usage WHERE user_id = :user_id GROUP BY month_start ORDER BY month_start',
            $monthExpression
        );

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['user_id' => $userId]);

        $summary = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $monthStart = isset($row['month_start']) ? (string) $row['month_start'] : null;

            if ($monthStart === null || $monthStart === '') {
                continue;
            }

            $summary[] = [
                'month' => $monthStart,
                'total_tokens' => (int) ($row['total_tokens'] ?? 0),
                'cost_pence' => (int) ($row['total_cost'] ?? 0),
            ];
        }

        return $summary;
    }

    /**
     * Handle the decode metadata operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function decodeMetadata(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Handle the normalise date workflow.
     *
     * This helper keeps the normalise date logic centralised for clarity and reuse.
     * @param mixed $value
     */
    private function normaliseDate($value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
