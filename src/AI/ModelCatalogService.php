<?php

declare(strict_types=1);

namespace App\AI;

use App\Settings\SiteSettingsRepository;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use JsonException;
use Throwable;

use function array_values;
use function getenv;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function rtrim;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;

/**
 * ModelCatalogService exposes current text-generation models without coupling the UI
 * to a hard-coded controller allow-list.
 */
final class ModelCatalogService
{
    private const CACHE_KEY = 'openai_model_catalog';
    private const CACHE_TIME_KEY = 'openai_model_catalog_refreshed_at';
    private const CACHE_TTL_SECONDS = 21600;
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    /** @var array<int, array{value: string, label: string, description: string}> */
    private const FALLBACK_MODELS = [
        [
            'value' => 'gpt-5.6-sol',
            'label' => 'GPT-5.6 Sol',
            'description' => 'Highest quality for demanding CV tailoring.',
        ],
        [
            'value' => 'gpt-5.6-terra',
            'label' => 'GPT-5.6 Terra',
            'description' => 'Balanced quality, speed, and cost.',
        ],
        [
            'value' => 'gpt-5.6-luna',
            'label' => 'GPT-5.6 Luna',
            'description' => 'Fast, economical drafting for simpler roles.',
        ],
        [
            'value' => 'gpt-5.4',
            'label' => 'GPT-5.4',
            'description' => 'Previous flagship model retained for compatibility.',
        ],
        [
            'value' => 'gpt-5.4-mini',
            'label' => 'GPT-5.4 Mini',
            'description' => 'Previous balanced model retained for compatibility.',
        ],
        [
            'value' => 'gpt-5.4-nano',
            'label' => 'GPT-5.4 Nano',
            'description' => 'Previous fast model retained for compatibility.',
        ],
    ];

    /** @var SiteSettingsRepository */
    private $settingsRepository;

    /** @var ClientInterface */
    private $client;

    /** @var string|null */
    private $apiKey;

    /** @var bool */
    private $lastRefreshSucceeded = false;

    /**
     * Construct the catalogue with shared settings storage and an optional HTTP client.
     */
    public function __construct(SiteSettingsRepository $settingsRepository, ?ClientInterface $client = null)
    {
        $this->settingsRepository = $settingsRepository;
        $this->apiKey = $this->configurationValue('OPENAI_API_KEY');
        $baseUrl = $this->configurationValue('OPENAI_BASE_URL') ?? self::DEFAULT_BASE_URL;
        $this->client = $client ?? new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout' => 15,
        ]);
    }

    /**
     * Return model options, refreshing them from OpenAI when requested or when the cache is stale.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public function models(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && $this->cacheIsFresh()) {
            $cached = $this->decodeCatalog($this->settingsRepository->findValue(self::CACHE_KEY));

            if ($cached !== []) {
                return $cached;
            }
        }

        $remote = $this->fetchRemoteModels();

        if ($remote !== []) {
            $this->settingsRepository->saveValue(
                self::CACHE_KEY,
                json_encode($remote, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            $this->settingsRepository->saveValue(
                self::CACHE_TIME_KEY,
                (new DateTimeImmutable('now'))->format(DATE_ATOM)
            );

            return $remote;
        }

        $cached = $this->decodeCatalog($this->settingsRepository->findValue(self::CACHE_KEY));

        return $cached !== [] ? $cached : self::FALLBACK_MODELS;
    }

    /**
     * Resolve the configured default while ensuring it remains selectable in the current catalogue.
     */
    public function defaultModel(): string
    {
        $configured = $this->configurationValue('OPENAI_MODEL_DRAFT');
        $options = $this->models();

        if ($configured !== null && $this->containsModel($options, $configured)) {
            return $configured;
        }

        return isset($options[0]['value']) ? (string) $options[0]['value'] : 'gpt-5.6-sol';
    }

    /**
     * Confirm that a submitted model identifier came from the current selectable catalogue.
     */
    public function isSelectable(string $model): bool
    {
        return $this->containsModel($this->models(), trim($model));
    }

    /**
     * Return the last successful remote refresh timestamp for status messaging.
     */
    public function refreshedAt(): ?string
    {
        return $this->settingsRepository->findValue(self::CACHE_TIME_KEY);
    }

    /**
     * Report whether the most recent forced or stale refresh returned a usable remote catalogue.
     */
    public function lastRefreshSucceeded(): bool
    {
        return $this->lastRefreshSucceeded;
    }

    /**
     * Retrieve and filter the account's available models from OpenAI's model listing endpoint.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function fetchRemoteModels(): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            return [];
        }

        try {
            $response = $this->client->request('GET', 'models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
            ]);
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            error_log('Unable to refresh OpenAI model catalogue: ' . $exception->getMessage());

            return [];
        }

        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            return [];
        }

        $remoteIds = [];

        foreach ($payload['data'] as $item) {
            if (!is_array($item) || !isset($item['id']) || !is_string($item['id'])) {
                continue;
            }

            $id = trim($item['id']);

            if ($this->isTextGenerationModel($id) && !in_array($id, $remoteIds, true)) {
                $remoteIds[] = $id;
            }
        }

        if ($remoteIds === []) {
            return [];
        }

        $this->lastRefreshSucceeded = true;

        $orderedIds = [];

        foreach (self::FALLBACK_MODELS as $known) {
            if (in_array($known['value'], $remoteIds, true)) {
                $orderedIds[] = $known['value'];
            }
        }

        foreach ($remoteIds as $id) {
            if (!in_array($id, $orderedIds, true)) {
                $orderedIds[] = $id;
            }
        }

        $options = [];

        foreach ($orderedIds as $id) {
            $options[] = $this->describeModel($id);
        }

        return $options;
    }

    /**
     * Restrict the broad models endpoint to general-purpose GPT text models suitable for Responses API drafting.
     */
    private function isTextGenerationModel(string $model): bool
    {
        $lower = strtolower($model);

        if (preg_match('/^gpt-[a-z0-9][a-z0-9._-]*$/', $lower) !== 1) {
            return false;
        }

        $excluded = [
            'audio', 'realtime', 'transcribe', 'tts', 'image', 'search', 'codex',
            'instruct', 'chat-latest', 'moderation',
        ];

        foreach ($excluded as $fragment) {
            if (strpos($lower, $fragment) !== false) {
                return false;
            }
        }

        return preg_match('/-\d{4}-\d{2}-\d{2}$/', $lower) !== 1;
    }

    /**
     * Convert a model identifier into restrained UI copy while preserving known role descriptions.
     *
     * @return array{value: string, label: string, description: string}
     */
    private function describeModel(string $model): array
    {
        foreach (self::FALLBACK_MODELS as $known) {
            if ($known['value'] === $model) {
                return $known;
            }
        }

        $label = ucwords(str_replace(['-', '_'], ' ', $model));
        $label = str_replace('Gpt ', 'GPT-', $label);

        return [
            'value' => $model,
            'label' => $label,
            'description' => 'Available to the configured OpenAI project.',
        ];
    }

    /**
     * Decode a cached JSON catalogue and discard malformed records.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function decodeCatalog(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $catalog = [];

        foreach ($decoded as $item) {
            if (!is_array($item) || !isset($item['value'], $item['label'])) {
                continue;
            }

            $value = trim((string) $item['value']);
            $label = trim((string) $item['label']);

            if ($value === '' || $label === '') {
                continue;
            }

            $catalog[] = [
                'value' => $value,
                'label' => $label,
                'description' => isset($item['description']) ? (string) $item['description'] : '',
            ];
        }

        return array_values($catalog);
    }

    /**
     * Decide whether the cached catalogue is recent enough to avoid another API request.
     */
    private function cacheIsFresh(): bool
    {
        $refreshedAt = $this->settingsRepository->findValue(self::CACHE_TIME_KEY);

        if ($refreshedAt === null || $refreshedAt === '') {
            return false;
        }

        try {
            $timestamp = new DateTimeImmutable($refreshedAt);
        } catch (Throwable $exception) {
            return false;
        }

        return $timestamp->getTimestamp() >= time() - self::CACHE_TTL_SECONDS;
    }

    /**
     * Check whether an option list contains the exact model identifier supplied by the user.
     *
     * @param array<int, array{value: string, label: string, description: string}> $options
     */
    private function containsModel(array $options, string $model): bool
    {
        foreach ($options as $option) {
            if ($option['value'] === $model) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve OpenAI configuration from environment variables first and shared site settings second.
     */
    private function configurationValue(string $key): ?string
    {
        if (in_array($key, ['OPENAI_MODEL_PLAN', 'OPENAI_MODEL_DRAFT'], true)) {
            $storedModel = $this->settingsRepository->findValue(strtolower($key));

            if ($storedModel !== null && trim($storedModel) !== '') {
                return trim($storedModel);
            }
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value !== false && $value !== null && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        $stored = $this->settingsRepository->findValue(strtolower($key));

        return $stored === null || trim($stored) === '' ? null : trim($stored);
    }
}
