<?php

declare(strict_types=1);

namespace App\Applications;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

use function html_entity_decode;
use function is_string;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function strip_tags;
use function trim;

final class JobPostingFetcher
{
    /** @var ClientInterface */
    private $client;

    /**
     * Construct the fetcher with an HTTP client that can retrieve public job adverts.
     */
    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => 20,
            'allow_redirects' => ['max' => 5],
            'headers' => [
                'User-Agent' => 'JobTracker/1.0 (+https://job.smeird.com)',
                'Accept' => 'text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.8',
            ],
        ]);
    }

    /**
     * Fetch a public job advert URL and return a normalised title and description.
     *
     * @return array{title: string, description: string, source_url: string}
     */
    public function fetch(string $url): array
    {
        $normalisedUrl = trim($url);

        if ($normalisedUrl === '' || filter_var($normalisedUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Provide a valid job advert URL before importing.');
        }

        try {
            $response = $this->client->request('GET', $normalisedUrl);
        } catch (RequestException $exception) {
            throw new RuntimeException('Unable to fetch that job advert. Paste the description manually if the job board blocks imports.', 0, $exception);
        }

        $body = (string) $response->getBody();
        $description = $this->extractReadableText($body);

        if ($description === '') {
            throw new RuntimeException('The fetched page did not contain readable job description text.');
        }

        return [
            'title' => $this->extractTitle($body),
            'description' => mb_substr($description, 0, 60000),
            'source_url' => $normalisedUrl,
        ];
    }

    /**
     * Extract a concise title from the fetched HTML document.
     */
    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) === 1 && isset($matches[1])) {
            return trim(html_entity_decode(strip_tags((string) $matches[1]), ENT_QUOTES, 'UTF-8'));
        }

        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches) === 1 && isset($matches[1])) {
            return trim(html_entity_decode(strip_tags((string) $matches[1]), ENT_QUOTES, 'UTF-8'));
        }

        return '';
    }

    /**
     * Convert HTML into readable plain text while removing scripts, styles, and excess spacing.
     */
    private function extractReadableText(string $html): string
    {
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', is_string($text) ? $text : $html);
        $text = preg_replace('/<\/(p|div|section|article|li|ul|ol|h[1-6]|br)>/i', "\n", is_string($text) ? $text : $html);
        $text = strip_tags(is_string($text) ? $text : $html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", is_string($text) ? $text : '');

        return trim(is_string($text) ? $text : '');
    }
}
