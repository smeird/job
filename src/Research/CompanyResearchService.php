<?php

declare(strict_types=1);

namespace App\Research;

use App\AI\OpenAIProvider;
use App\Applications\JobApplication;
use App\Applications\JobApplicationRepository;
use GuzzleHttp\Exception\RequestException;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

use function implode;
use function parse_url;
use function preg_match;
use function strtolower;
use function trim;

final class CompanyResearchService
{
    private const CACHE_TTL_MINUTES = 360;
    private const SEARCH_RESULT_LIMIT = 5;

    /** @var JobApplicationRepository */
    private $repository;

    /** @var WebSearchClient */
    private $searchClient;

    /** @var OpenAIProvider */
    private $openAiProvider;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        JobApplicationRepository $repository,
        WebSearchClient $searchClient,
        OpenAIProvider $openAiProvider
    ) {
        $this->repository = $repository;
        $this->searchClient = $searchClient;
        $this->openAiProvider = $openAiProvider;
    }

    /**
     * Generate or retrieve cached research insights for a job application.
     *
     * The helper orchestrates repository lookups, web search enrichment, and
     * OpenAI summarisation while enforcing caching rules to limit token spend.
     *
     * @return array{
     *     status: 'cached'|'generated',
     *     query: string,
     *     generated_at: string,
     *     summary: string,
     *     search_results: array<int, array{title: string, url: string, snippet: string}>
     * }
     */
    public function research(int $userId, int $applicationId): array
    {
        $application = $this->repository->findForUser($userId, $applicationId);

        if ($application === null) {
            throw new RuntimeException('Job application not found.', 404);
        }

        $cached = $this->repository->findRecentResearch($userId, $applicationId, self::CACHE_TTL_MINUTES);

        if ($cached !== null) {
            return [
                'status' => 'cached',
                'query' => $cached['query'],
                'generated_at' => $cached['generated_at']->format(DateTimeInterface::ATOM),
                'summary' => $cached['summary'],
                'search_results' => $cached['search_results'],
            ];
        }

        $query = $this->buildQuery($application);
        $results = $this->performSearch($query);
        $summary = $this->summarise($userId, $application, $results);
        $generatedAt = new DateTimeImmutable('now');

        $this->repository->saveResearchResult($userId, $applicationId, $query, $summary, $results, $generatedAt);

        return [
            'status' => 'generated',
            'query' => $query,
            'generated_at' => $generatedAt->format(DateTimeInterface::ATOM),
            'summary' => $summary,
            'search_results' => $results,
        ];
    }

    /**
     * Derive a sensible default search query from the application context.
     *
     * The helper combines the job title with any detected domain keywords to
     * improve search quality without requiring user-provided queries.
     */
    private function buildQuery(JobApplication $application): string
    {
        $parts = [];
        $title = trim($application->title());

        if ($title !== '') {
            $parts[] = $title;
        }

        $sourceUrl = $application->sourceUrl();

        if ($sourceUrl !== null) {
            $domain = $this->extractDomain($sourceUrl);

            if ($domain !== null) {
                $parts[] = $domain;
            }
        }

        if ($parts === []) {
            $parts[] = 'company research';
        }

        return implode(' ', $parts);
    }

    /**
     * Extract a domain name from a source URL when available.
     *
     * Restricting the domain to alphanumeric and hyphen characters avoids
     * accidentally feeding path segments into the search query.
     */
    private function extractDomain(string $sourceUrl): ?string
    {
        $host = parse_url($sourceUrl, PHP_URL_HOST);

        if (!is_string($host)) {
            return null;
        }

        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        if (!preg_match('/^[a-z0-9.-]+$/', $host)) {
            return null;
        }

        return $host;
    }

    /**
     * Perform the outbound web search call and map failures into domain errors.
     *
     * Catching upstream exceptions here ensures the controller can surface a
     * predictable message and status code to the caller.
     *
     * @return array<int, array{title: string, url: string, snippet: string}>
     */
    private function performSearch(string $query): array
    {
        try {
            return $this->searchClient->search($query, self::SEARCH_RESULT_LIMIT);
        } catch (RuntimeException $exception) {
            if ($exception->getCode() === 429) {
                throw new RuntimeException('Search provider rate limit reached.', 429, $exception);
            }

            throw new RuntimeException('Unable to contact the search provider.', 0, $exception);
        }
    }

    /**
     * Request an OpenAI-backed cheat sheet that fuses job and company details.
     *
     * @param array<int, array{title: string, url: string, snippet: string}> $results
     */
    private function summarise(int $userId, JobApplication $application, array $results): string
    {
        $provider = $this->openAiProvider->forUser($userId);

        try {
            return $provider->cheatSheet($application->description(), $results);
        } catch (RuntimeException $exception) {
            $statusCode = $this->resolveStatusCode($exception);

            if ($statusCode === 429) {
                throw new RuntimeException('OpenAI rate limit reached.', 429, $exception);
            }

            throw new RuntimeException('Unable to generate research summary.', 0, $exception);
        }
    }

    /**
     * Resolve an HTTP status code from a nested runtime exception hierarchy.
     */
    private function resolveStatusCode(RuntimeException $exception): ?int
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof RuntimeException) {
            $code = $previous->getCode();

            if ($code !== 0) {
                return $code;
            }
        }

        if ($previous instanceof RequestException) {
            $response = $previous->getResponse();

            if ($response !== null) {
                return $response->getStatusCode();
            }
        }

        return null;
    }
}
