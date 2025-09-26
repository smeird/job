<?php

declare(strict_types=1);

namespace App\AI;

use App\DB;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

use function is_array;
use function is_numeric;
use function json_decode;
use function json_encode;
use function max;
use function random_int;
use function rtrim;
use function sprintf;
use function trim;
use function usleep;

final class OpenAIProvider
{
    private const PROVIDER = 'openai';
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    private const ENDPOINT_CHAT_COMPLETIONS = '/chat/completions';
    private const MAX_ATTEMPTS = 5;
    private const INITIAL_BACKOFF_MS = 200;
    private const MAX_BACKOFF_MS = 4000;

    /** @var ClientInterface */
    private $client;

    /** @var PDO */
    private $pdo;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $modelPlan;

    /** @var string */
    private $modelDraft;

    /** @var int */
    private $maxTokens;

    /** @var int */
    private $userId;

    /**
     * @var array<string, array{prompt: float, completion: float}>
     */
    private $tariffs;

    public function __construct(
        int $userId,
        ?ClientInterface $client = null,
        ?PDO $pdo = null
    ) {
        $this->userId = $userId;
        $this->apiKey = $this->requireEnv('OPENAI_API_KEY');
        $this->baseUrl = rtrim($this->env('OPENAI_BASE_URL') ?? self::DEFAULT_BASE_URL, '/');
        $this->modelPlan = $this->requireEnv('OPENAI_MODEL_PLAN');
        $this->modelDraft = $this->requireEnv('OPENAI_MODEL_DRAFT');
        $this->maxTokens = $this->resolveMaxTokens();
        $this->tariffs = $this->parseTariffs($this->env('OPENAI_TARIFF_JSON'));

        $this->client = $client ?? new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 60,
        ]);

        try {
            $this->pdo = $pdo ?? DB::getConnection();
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to obtain a database connection.', 0, $exception);
        }
    }

    /**
     * Generate a structured plan in JSON format.
     */
    public function plan(string $jobText, string $cvText, ?callable $streamHandler = null): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a planning assistant that prepares tailored job application strategies. '
                    . 'Always respond with a valid JSON object following this schema: '
                    . '{"summary": string, "strengths": string[], "gaps": string[], "next_steps": [{"task": string, "rationale": string, "priority": "high"|"medium"|"low", "estimated_minutes": int}]}. '
                    . 'Ensure arrays are never empty: use informative entries. Avoid markdown or prose outside JSON.',
            ],
            [
                'role' => 'user',
                'content' => sprintf(
                    "Job description:\n%s\n\nCandidate CV:\n%s\n\nCreate the plan.",
                    trim($jobText),
                    trim($cvText)
                ),
            ],
        ];

        $payload = [
            'model' => $this->modelPlan,
            'messages' => $messages,
            'temperature' => 0.2,
            'max_tokens' => $this->maxTokens,
            'response_format' => ['type' => 'json_object'],
        ];

        $result = $this->performChatRequest($payload, 'plan', $streamHandler);
        $content = trim($result['content']);

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to decode JSON plan produced by OpenAI.', 0, $exception);
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate a Markdown draft tailored to the supplied plan and constraints.
     */
    public function draft(string $plan, string $constraints, ?callable $streamHandler = null): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a professional writer assisting with job application materials. '
                    . 'Draft polished markdown content that aligns with the provided plan. '
                    . 'Use headings, bullet lists, and emphasis where helpful. '
                    . 'Never include fenced code blocks unless explicitly requested.',
            ],
            [
                'role' => 'user',
                'content' => sprintf(
                    "Plan JSON:\n%s\n\nConstraints:\n%s\n\nProduce the draft in Markdown.",
                    trim($plan),
                    trim($constraints)
                ),
            ],
        ];

        $payload = [
            'model' => $this->modelDraft,
            'messages' => $messages,
            'temperature' => 0.6,
            'max_tokens' => $this->maxTokens,
        ];

        $result = $this->performChatRequest($payload, 'draft', $streamHandler);

        return trim($result['content']);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{content: string, usage: array<string, int>, response: array<string, mixed>}
     */
    private function performChatRequest(array $payload, string $operation, ?callable $streamHandler): array
    {
        $isStreaming = $streamHandler !== null;
        $requestPayload = $payload;

        if ($isStreaming) {
            $requestPayload['stream'] = true;
        }

        $attempt = 0;
        $delayMs = self::INITIAL_BACKOFF_MS;

        while (true) {
            try {
                $options = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($requestPayload, JSON_THROW_ON_ERROR),
                ];

                if ($isStreaming) {
                    $options['stream'] = true;
                }

                $response = $this->client->request('POST', self::ENDPOINT_CHAT_COMPLETIONS, $options);

                if ($isStreaming && $streamHandler !== null) {
                    $parsed = $this->consumeStream($response, $streamHandler);
                } else {
                    $parsed = $this->parseJsonResponse($response);
                }

                $usage = $parsed['usage'];
                $responseMeta = $parsed['response'];

                $metadata = [
                    'operation' => $operation,
                    'response_id' => $responseMeta['id'] ?? null,
                    'model' => $responseMeta['model'] ?? ($payload['model'] ?? null),
                    'finish_reason' => $responseMeta['choices'][0]['finish_reason'] ?? null,
                ];

                $this->recordUsage(self::ENDPOINT_CHAT_COMPLETIONS, $payload['model'] ?? 'unknown', $usage, $metadata);

                return [
                    'content' => $parsed['content'],
                    'usage' => $usage,
                    'response' => $responseMeta,
                ];
            } catch (RequestException $exception) {
                $attempt++;
                $response = $exception->getResponse();
                $statusCode = $response !== null ? $response->getStatusCode() : null;

                if ($attempt >= self::MAX_ATTEMPTS || !$this->shouldRetry($statusCode)) {
                    throw new RuntimeException('OpenAI API request failed: ' . $exception->getMessage(), 0, $exception);
                }

                $this->waitWithJitter($delayMs);
                $delayMs = min($delayMs * 2, self::MAX_BACKOFF_MS);
            } catch (JsonException $exception) {
                throw new RuntimeException('Unable to encode OpenAI request payload.', 0, $exception);
            }
        }
    }

    /**
     * @return array{content: string, usage: array<string, int>, response: array<string, mixed>}
     */
    private function parseJsonResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to decode OpenAI API response.', 0, $exception);
        }

        $choice = $data['choices'][0] ?? [];
        $message = $choice['message']['content'] ?? '';

        return [
            'content' => is_string($message) ? $message : '',
            'usage' => $this->normaliseUsage($data['usage'] ?? []),
            'response' => $data,
        ];
    }

    /**
     * @return array{content: string, usage: array<string, int>, response: array<string, mixed>}
     */
    private function consumeStream(ResponseInterface $response, callable $handler): array
    {
        $body = $response->getBody();
        $buffer = '';
        $content = '';
        $usage = [];
        $responseMeta = [
            'id' => $response->getHeaderLine('x-request-id') ?: null,
            'model' => null,
            'choices' => [['finish_reason' => null]],
        ];

        while (!$body->eof()) {
            $buffer .= $body->read(8192);

            while (($delimiterPosition = strpos($buffer, "\n\n")) !== false) {
                $segment = substr($buffer, 0, $delimiterPosition);
                $buffer = (string) substr($buffer, $delimiterPosition + 2);

                foreach (explode("\n", (string) $segment) as $line) {
                    $line = trim($line);

                    if ($line === '' || !str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $payload = trim(substr($line, 5));

                    if ($payload === '[DONE]') {
                        break 3;
                    }

                    try {
                        /** @var array<string, mixed> $event */
                        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        continue;
                    }

                    if (isset($event['choices'][0]['delta']['content'])) {
                        $chunk = (string) $event['choices'][0]['delta']['content'];
                        $content .= $chunk;
                        $handler($chunk);
                    }

                    if (isset($event['choices'][0]['finish_reason'])) {
                        $responseMeta['choices'][0]['finish_reason'] = $event['choices'][0]['finish_reason'];
                    }

                    if (isset($event['model'])) {
                        $responseMeta['model'] = $event['model'];
                    }

                    if (isset($event['usage'])) {
                        $usage = $this->normaliseUsage($event['usage']);
                    }
                }
            }
        }

        return [
            'content' => $content,
            'usage' => $usage,
            'response' => $responseMeta,
        ];
    }

    /**
     * @param array<string, mixed> $usage
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    private function normaliseUsage(array $usage): array
    {
        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($prompt + $completion));

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
        ];
    }

    /**
     * @param array<string, int> $usage
     * @param array<string, mixed> $metadata
     */
    private function recordUsage(string $endpoint, string $model, array $usage, array $metadata): void
    {
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;
        $totalTokens = $usage['total_tokens'] ?? ($promptTokens + $completionTokens);
        $cost = $this->calculateCost($model, $promptTokens, $completionTokens);

        $metadata['prompt_tokens'] = $promptTokens;
        $metadata['completion_tokens'] = $completionTokens;
        $metadata['total_tokens'] = $totalTokens;
        $metadata['cost_minor_units'] = $cost;

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO api_usage (user_id, provider, endpoint, tokens_used, cost_pence, metadata) '
                . 'VALUES (:user_id, :provider, :endpoint, :tokens_used, :cost_pence, :metadata)'
            );

            $statement->execute([
                ':user_id' => $this->userId,
                ':provider' => self::PROVIDER,
                ':endpoint' => $endpoint,
                ':tokens_used' => $totalTokens,
                ':cost_pence' => $cost,
                ':metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable $exception) {
            error_log('Failed to record OpenAI usage: ' . $exception->getMessage());
        }
    }

    private function calculateCost(string $model, int $promptTokens, int $completionTokens): int
    {
        $key = strtolower($model);
        $tariff = $this->tariffs[$key] ?? null;

        if ($tariff === null) {
            return 0;
        }

        $promptCost = ($promptTokens / 1000) * $tariff['prompt'];
        $completionCost = ($completionTokens / 1000) * $tariff['completion'];

        return (int) round($promptCost + $completionCost);
    }

    /**
     * @return array<string, array{prompt: float, completion: float}>
     */
    private function parseTariffs(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('OPENAI_TARIFF_JSON is not valid JSON.', 0, $exception);
        }

        $tariffs = [];

        if (!is_array($data)) {
            return $tariffs;
        }

        foreach ($data as $model => $tariff) {
            if (!is_string($model)) {
                continue;
            }

            $key = strtolower($model);

            if (is_numeric($tariff)) {
                $value = (float) $tariff;
                $tariffs[$key] = ['prompt' => $value, 'completion' => $value];
                continue;
            }

            if (!is_array($tariff)) {
                continue;
            }

            $prompt = $this->extractTariffValue($tariff, ['prompt', 'input', 'default']);
            $completion = $this->extractTariffValue($tariff, ['completion', 'output', 'default'], $prompt);

            $tariffs[$key] = [
                'prompt' => $prompt,
                'completion' => $completion,
            ];
        }

        return $tariffs;
    }

    /**
     * @param array<string, mixed> $tariff
     * @param string[] $keys
     */
    private function extractTariffValue(array $tariff, array $keys, ?float $fallback = null): float
    {
        foreach ($keys as $key) {
            if (isset($tariff[$key]) && is_numeric($tariff[$key])) {
                return (float) $tariff[$key];
            }
        }

        return $fallback ?? 0.0;
    }

    private function resolveMaxTokens(): int
    {
        $maxTokens = $this->env('OPENAI_MAX_TOKENS');

        if ($maxTokens === null) {
            return 1024;
        }

        return max(1, (int) $maxTokens);
    }

    private function shouldRetry(?int $statusCode): bool
    {
        if ($statusCode === null) {
            return true;
        }

        if ($statusCode === 429) {
            return true;
        }

        return $statusCode >= 500 && $statusCode < 600;
    }

    private function waitWithJitter(int $milliseconds): void
    {
        $jitter = random_int(0, (int) ($milliseconds * 0.2));
        $total = $milliseconds + $jitter;
        usleep($total * 1000);
    }

    private function requireEnv(string $key): string
    {
        $value = $this->env($key);

        if ($value === null || $value === '') {
            throw new RuntimeException(sprintf('Environment variable %s must be set.', $key));
        }

        return $value;
    }

    private function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
