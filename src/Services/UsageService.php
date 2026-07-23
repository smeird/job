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
        $monthly = $this->buildMonthlySummary($perRun);

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
     * @return array{0: array<int, array<string, mixed>>, 1: array{current_month: array<string, mixed>, lifetime: array<string, mixed>}}
     */
    private function fetchPerRun(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, provider, endpoint, tokens_used, cost_pence, metadata, created_at '
            . 'FROM api_usage WHERE user_id = :user_id ORDER BY created_at DESC, id DESC'
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
            'cost_complete' => true,
        ];
        $lifetimeTotals = $monthTotals;

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $createdAt = $this->normaliseDate($row['created_at'] ?? null);
            $metadata = $this->decodeMetadata($row['metadata'] ?? null);

            $promptTokens = (int) ($metadata['prompt_tokens'] ?? 0);
            $completionTokens = (int) ($metadata['completion_tokens'] ?? 0);
            $totalTokens = (int) ($metadata['total_tokens'] ?? $row['tokens_used'] ?? 0);
            $costAvailable = isset($metadata['cost_available'])
                ? (bool) $metadata['cost_available']
                : true;
            $costPence = $costAvailable && isset($metadata['cost_pence_precise'])
                ? (float) $metadata['cost_pence_precise']
                : ($costAvailable ? (float) ($row['cost_pence'] ?? 0) : null);
            $model = $this->resolveModelFromMetadata($metadata);

            $entry = [
                'id' => (int) ($row['id'] ?? 0),
                'provider' => (string) ($row['provider'] ?? ''),
                'endpoint' => (string) ($row['endpoint'] ?? ''),
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'cost_pence' => $costPence,
                'cost_available' => $costAvailable,
                'created_at' => $createdAt !== null ? $createdAt->format(DATE_ATOM) : null,
            ];

            if ($entry['created_at'] === null && isset($row['created_at'])) {
                $entry['created_at'] = (string) $row['created_at'];
            }

            $perRun[] = $entry;

            $lifetimeTotals['prompt_tokens'] += $promptTokens;
            $lifetimeTotals['completion_tokens'] += $completionTokens;
            $lifetimeTotals['total_tokens'] += $totalTokens;
            if ($costPence !== null) {
                $lifetimeTotals['cost_pence'] += $costPence;
            }
            $lifetimeTotals['cost_complete'] = $lifetimeTotals['cost_complete'] && $costAvailable;

            if ($createdAt !== null && $createdAt >= $currentMonthStart) {
                $monthTotals['prompt_tokens'] += $promptTokens;
                $monthTotals['completion_tokens'] += $completionTokens;
                $monthTotals['total_tokens'] += $totalTokens;
                if ($costPence !== null) {
                    $monthTotals['cost_pence'] += $costPence;
                }
                $monthTotals['cost_complete'] = $monthTotals['cost_complete'] && $costAvailable;
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
     * Resolve the most accurate model identifier from stored metadata.
     *
     * The method prefers the requested model so fallback runs reflect the configuration the
     * application actually attempted before considering legacy metadata fields. This keeps the
     * usage table consistent even when providers echo unexpected model names in their responses.
     *
     * @param array<string, mixed> $metadata Usage metadata captured at request time.
     */
    private function resolveModelFromMetadata(array $metadata): string
    {
        $requested = isset($metadata['model_requested']) ? (string) $metadata['model_requested'] : '';

        if ($requested !== '') {
            return $this->normaliseModelIdentifier($requested);
        }

        $primary = isset($metadata['model']) ? (string) $metadata['model'] : '';

        if ($primary !== '') {
            return $this->normaliseModelIdentifier($primary);
        }

        $reported = isset($metadata['model_reported']) ? (string) $metadata['model_reported'] : '';

        if ($reported !== '') {
            return $this->normaliseModelIdentifier($reported);
        }

        return 'unknown';
    }

    /**
     * Harmonise known model aliases into their canonical identifiers.
     *
     * Centralising the mapping ensures legacy rows that reference older labels
     * continue to render alongside the current GPT-5 line-up without confusing
     * suffixes leaking into the analytics table.
     */
    private function normaliseModelIdentifier(string $model): string
    {
        $key = strtolower($model);

        $aliases = [
            'gpt-5-main' => 'gpt-5.4',
            'gpt5-main' => 'gpt-5.4',
            'gpt5' => 'gpt-5.4',
            'gpt-5' => 'gpt-5.4',
            'gpt-5-mini' => 'gpt-5.4-mini',
            'gpt-5-nano' => 'gpt-5.4-nano',
            'gpt-5-strategist' => 'gpt-5.4',
            'gpt-5.0-strategist' => 'gpt-5.4',
            'gpt5-strategist' => 'gpt-5.4',
            'gpt5.0-strategist' => 'gpt-5.4',
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        return $model;
    }

    /**
     * Fetch the monthly summary from its provider.
     *
     * Centralised fetching makes upstream integrations easier to evolve.
     * @return array<int, array{month: string, total_tokens: int, cost_pence: float, cost_complete: bool}>
     */
    private function buildMonthlySummary(array $perRun): array
    {
        $months = [];

        foreach ($perRun as $row) {
            $createdAt = $this->normaliseDate(isset($row['created_at']) ? $row['created_at'] : null);

            if ($createdAt === null) {
                continue;
            }

            $month = $createdAt->format('Y-m-01');

            if (!isset($months[$month])) {
                $months[$month] = [
                    'month' => $month,
                    'total_tokens' => 0,
                    'cost_pence' => 0.0,
                    'cost_complete' => true,
                ];
            }

            $months[$month]['total_tokens'] += (int) ($row['total_tokens'] ?? 0);

            if (isset($row['cost_pence']) && $row['cost_pence'] !== null) {
                $months[$month]['cost_pence'] += (float) $row['cost_pence'];
            }

            $months[$month]['cost_complete'] = $months[$month]['cost_complete']
                && !empty($row['cost_available']);
        }

        ksort($months);

        return array_values($months);
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
        } catch (JsonException $exception) {
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
        } catch (\Exception $exception) {
            return null;
        }
    }
}
