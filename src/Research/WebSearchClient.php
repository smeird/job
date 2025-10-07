<?php

declare(strict_types=1);

namespace App\Research;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_map;
use function array_values;
use function count;
use function getenv;
use function is_array;
use function is_string;
use function json_decode;
use function rtrim;
use function trim;

final class WebSearchClient
{
    private const DEFAULT_BASE_URL = 'https://api.search.local/v1';
    private const DEFAULT_ENDPOINT = 'search';

    /** @var ClientInterface */
    private $client;

    /** @var string|null */
    private $apiKey;

    /** @var string */
    private $endpoint;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(?ClientInterface $client = null)
    {
        $this->apiKey = $this->env('SEARCH_API_KEY');
        $baseUrl = $this->env('SEARCH_API_BASE_URL') ?? self::DEFAULT_BASE_URL;
        $endpoint = $this->env('SEARCH_API_ENDPOINT') ?? self::DEFAULT_ENDPOINT;
        $this->endpoint = trim($endpoint, '/');
        $baseUri = rtrim($baseUrl, '/') . '/';

        $this->client = $client ?? new Client([
            'base_uri' => $baseUri,
            'timeout' => 20,
        ]);
    }

    /**
     * Execute a search query and normalise the response payload.
     *
     * The helper translates diverse search provider schemas into a predictable
     * tuple list containing title, url, and snippet keys for downstream use.
     *
     * @return array<int, array{title: string, url: string, snippet: string}>
     */
    public function search(string $query, int $limit = 5): array
    {
        if ($this->apiKey === null) {
            throw new RuntimeException('Search provider credentials are not configured.');
        }

        $parameters = [
            'query' => [
                'q' => $query,
                'count' => $limit,
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
        ];

        try {
            $response = $this->client->request('GET', $this->endpoint, $parameters);
        } catch (RequestException $exception) {
            $statusCode = null;
            $response = $exception->getResponse();

            if ($response !== null) {
                $statusCode = $response->getStatusCode();
            }

            $message = $statusCode === 429
                ? 'Search provider rate limit exceeded.'
                : 'Search provider request failed.';

            throw new RuntimeException($message, (int) ($statusCode ?? 0), $exception);
        }

        $payload = $this->decodeResponse($response);
        $items = $this->extractItems($payload);

        $normalised = array_values(array_map(function ($item) {
            $title = isset($item['title']) && is_string($item['title']) ? trim($item['title']) : '';
            $url = isset($item['url']) && is_string($item['url']) ? trim($item['url']) : '';
            $snippet = isset($item['snippet']) && is_string($item['snippet']) ? trim($item['snippet']) : '';

            if ($title === '' && isset($item['name']) && is_string($item['name'])) {
                $title = trim($item['name']);
            }

            if ($url === '' && isset($item['link']) && is_string($item['link'])) {
                $url = trim($item['link']);
            }

            if ($snippet === '' && isset($item['description']) && is_string($item['description'])) {
                $snippet = trim($item['description']);
            }

            if ($snippet === '' && isset($item['summary']) && is_string($item['summary'])) {
                $snippet = trim($item['summary']);
            }

            return [
                'title' => $title,
                'url' => $url,
                'snippet' => $snippet,
            ];
        }, $items));

        $filtered = [];

        foreach ($normalised as $entry) {
            if ($entry['title'] === '' || $entry['url'] === '') {
                continue;
            }

            $filtered[] = $entry;

            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * Indicate whether the client has sufficient configuration to make requests.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== null;
    }

    /**
     * Decode a JSON response payload into a PHP array.
     *
     * Wrapping json_decode with error handling keeps failure messaging tidy.
     *
     * @return array<string, mixed>
     */
    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Search provider returned malformed JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Search provider responded with an unexpected payload.');
        }

        return $decoded;
    }

    /**
     * Extract the candidate result entries from the provider-specific schema.
     *
     * Supporting a variety of property names keeps the client portable across
     * common search APIs without leaking provider-specific logic elsewhere.
     *
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(array $payload): array
    {
        if (isset($payload['items']) && is_array($payload['items'])) {
            return $payload['items'];
        }

        if (isset($payload['results']) && is_array($payload['results'])) {
            return $payload['results'];
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return [];
    }

    /**
     * Fetch an environment variable while permitting empty strings.
     */
    private function env(string $key): ?string
    {
        $value = getenv($key);

        if ($value === false) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

}
