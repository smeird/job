<?php

declare(strict_types=1);

namespace App\AI;

use App\DB;
use App\Settings\SiteSettingsRepository;
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
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function strpos;
use function strtolower;
use function random_int;
use function rtrim;
use function sprintf;
use function trim;
use function usleep;

final class OpenAIProvider
{
    private const PROVIDER = 'openai';
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    private const ENDPOINT_RESPONSES = 'responses';

    private const MAX_ATTEMPTS = 5;
    private const INITIAL_BACKOFF_MS = 200;
    private const MAX_BACKOFF_MS = 4000;

    /** @var ClientInterface */
    private $client;

    /** @var PDO */
    private $pdo;

    /** @var SiteSettingsRepository */
    private $settingsRepository;

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

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        int $userId,
        ?ClientInterface $client = null,
        ?PDO $pdo = null,
        ?SiteSettingsRepository $settingsRepository = null
    ) {
        $this->userId = $userId;

        try {
            $this->pdo = $pdo ?? DB::getConnection();
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to obtain a database connection.', 0, $exception);
        }

        $this->settingsRepository = $settingsRepository ?? new SiteSettingsRepository($this->pdo);
        $this->apiKey = $this->requireEnv('OPENAI_API_KEY');
        $baseUrl = $this->env('OPENAI_BASE_URL') ?? self::DEFAULT_BASE_URL;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->modelPlan = $this->requireEnv('OPENAI_MODEL_PLAN');
        $this->modelDraft = $this->requireEnv('OPENAI_MODEL_DRAFT');
        $this->maxTokens = $this->resolveMaxTokens();
        $this->tariffs = $this->parseTariffs($this->env('OPENAI_TARIFF_JSON'));

        $this->client = $client ?? new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 60,
        ]);
    }

    /**
     * Generate a structured plan in JSON format.
     *
     * The helper wraps the necessary OpenAI request so consumers always receive
     * a fully decoded and normalised JSON payload.
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
            'input' => $this->formatMessagesForResponses($messages),
            'max_output_tokens' => $this->maxTokens,
            'response' => [
                'format' => $this->buildPlanJsonSchema(),
            ],
        ];

        try {
            $result = $this->performChatRequest($payload, 'plan', $streamHandler);
        } catch (RuntimeException $exception) {
            if ($this->shouldFallbackToLegacyResponseFormat($exception)) {
                $legacyPayload = $payload;

                if (isset($legacyPayload['response'])) {
                    $legacyPayload['response_format'] = $legacyPayload['response']['format'] ?? [];
                    unset($legacyPayload['response']);
                }

                error_log('Retrying plan request with legacy response_format parameter: ' . $exception->getMessage());

                try {
                    $result = $this->performChatRequest($legacyPayload, 'plan', $streamHandler, true);
                } catch (RuntimeException $legacyException) {
                    if (!$this->shouldFallbackToJsonObject($legacyException)) {
                        throw $legacyException;
                    }

                    $legacyPayload['response_format'] = ['type' => 'json_object'];
                    error_log('Falling back to json_object legacy response format after plan request failure: ' . $legacyException->getMessage());
                    $result = $this->performChatRequest($legacyPayload, 'plan', $streamHandler, true);
                }
            } else {
                if (!$this->shouldFallbackToJsonObject($exception)) {
                    throw $exception;
                }

                $payload['response'] = ['format' => ['type' => 'json_object']];
                error_log('Falling back to json_object response format after plan request failure: ' . $exception->getMessage());
                $result = $this->performChatRequest($payload, 'plan', $streamHandler);
            }
        }

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
     *
     * This guides the language model to produce presentation-ready prose in a
     * consistent format for display to end users.
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
            'input' => $this->formatMessagesForResponses($messages),
            'max_output_tokens' => $this->maxTokens,
        ];

        $result = $this->performChatRequest($payload, 'draft', $streamHandler);

        return trim($result['content']);
    }

    /**
     * Execute the chat completion call and capture both the content and usage
     * metadata for billing and auditing purposes.
     *
     * @param array<string, mixed> $payload
     * @param bool $preserveLegacyResponseFormat Indicates whether the payload should be sent without
     *                                           normalising modern response parameters.
     * @return array{content: string, usage: array<string, int>, response: array<string, mixed>}
     */
    private function performChatRequest(
        array $payload,
        string $operation,
        ?callable $streamHandler,
        bool $preserveLegacyResponseFormat = false
    ): array
    {
        $isStreaming = $streamHandler !== null;
        $requestPayload = $preserveLegacyResponseFormat
            ? $payload
            : $this->normaliseRequestPayload($payload);

        if ($isStreaming) {
            $requestPayload['stream'] = true;
            $requestPayload['stream_options'] = ['include_usage' => true];
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

                $response = $this->client->request('POST', self::ENDPOINT_RESPONSES, $options);

                if ($isStreaming && $streamHandler !== null) {
                    $parsed = $this->consumeStream($response, $streamHandler);
                } else {
                    $parsed = $this->parseJsonResponse($response);
                }

                $usage = $parsed['usage'];
                $responseMeta = $parsed['response'];
                $finishReason = $responseMeta['choices'][0]['finish_reason'] ?? null;

                if ($finishReason === null && isset($responseMeta['output'][0]['finish_reason'])) {
                    $finishReason = $responseMeta['output'][0]['finish_reason'];
                }

                $metadata = [
                    'operation' => $operation,
                    'response_id' => $responseMeta['id'] ?? null,
                    'model' => $responseMeta['model'] ?? ($payload['model'] ?? null),
                    'finish_reason' => $finishReason,
                ];


                $this->recordUsage('/' . ltrim(self::ENDPOINT_RESPONSES, '/'), $payload['model'] ?? 'unknown', $usage, $metadata);


                return [
                    'content' => $parsed['content'],
                    'usage' => $usage,
                    'response' => $responseMeta,
                ];
            } catch (RequestException $exception) {
                $attempt++;
                $statusCode = null;
                $response = $exception->getResponse();

                if ($response !== null) {
                    $statusCode = $response->getStatusCode();
                }

                if ($attempt >= self::MAX_ATTEMPTS || !$this->shouldRetry($statusCode)) {
                    $detail = $this->extractRequestErrorDetail($exception);

                    throw new RuntimeException(
                        $this->buildRequestFailureMessage($statusCode, $detail, $exception),
                        0,
                        $exception
                    );
                }

                $this->waitWithJitter($delayMs);
                $delayMs = min($delayMs * 2, self::MAX_BACKOFF_MS);
            } catch (JsonException $exception) {
                throw new RuntimeException('Unable to encode OpenAI request payload.', 0, $exception);
            }
        }
    }

    /**
     * Normalise the outgoing payload to match the latest OpenAI Responses API expectations.
     *
     * This helper safeguards against legacy configuration values that still populate the
     * deprecated `response_format` key by converting them into the modern `response.format`
     * structure accepted by the API.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normaliseRequestPayload(array $payload): array
    {
        if (isset($payload['response_format'])) {
            $format = $payload['response_format'];
            unset($payload['response_format']);

            if (!isset($payload['response']) || !is_array($payload['response'])) {
                $payload['response'] = [];
            }

            if (!isset($payload['response']['format'])) {
                $payload['response']['format'] = $format;
            }
        }

        return $payload;
    }

    /**
     * Parse a non-streaming response body into a uniform structure that exposes
     * the message content and usage counters.
     *
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

        $output = $data['output'] ?? [];
        $content = $this->extractTextFromOutput(is_array($output) ? $output : []);

        return [
            'content' => $content,
            'usage' => $this->normaliseUsage($data['usage'] ?? []),
            'response' => $this->normaliseResponseMeta($data),
        ];
    }

    /**
     * Consume a streaming response while invoking the provided handler for each
     * partial content chunk emitted by the API.
     *
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
        $streamEnded = false;

        while (!$body->eof() && !$streamEnded) {
            $buffer .= $body->read(8192);

            while (($delimiterPosition = strpos($buffer, "\n\n")) !== false) {
                $segment = substr($buffer, 0, $delimiterPosition);
                $buffer = (string) substr($buffer, $delimiterPosition + 2);

                foreach (explode("\n", (string) $segment) as $line) {
                    $line = trim($line);

                    if ($line === '' || strncmp($line, 'data:', 5) !== 0) {
                        continue;
                    }

                    $payload = trim(substr($line, 5));

                    if ($payload === '[DONE]') {
                        $streamEnded = true;
                        break;
                    }

                    try {
                        /** @var array<string, mixed> $event */
                        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        continue;
                    }

                    $type = isset($event['type']) ? (string) $event['type'] : '';

                    if ($type === 'response.output_text.delta') {
                        $chunk = (string) ($event['delta'] ?? '');

                        if ($chunk !== '') {
                            $content .= $chunk;
                            $handler($chunk);
                        }

                        if (isset($event['response']['model'])) {
                            $responseMeta['model'] = $event['response']['model'];
                        }

                        if (isset($event['response']['id'])) {
                            $responseMeta['id'] = $event['response']['id'];
                        }
                    } elseif ($type === 'response.completed') {
                        if (isset($event['response'])) {
                            $responseMeta = $event['response'] + $responseMeta;
                            $usage = $this->normaliseUsage($event['response']['usage'] ?? []);

                            if ($content === '' && isset($event['response']['output']) && is_array($event['response']['output'])) {
                                $content = $this->extractTextFromOutput($event['response']['output']);
                            }

                            if (isset($event['response']['output'][0]['finish_reason'])) {
                                $responseMeta['choices'][0]['finish_reason'] = $event['response']['output'][0]['finish_reason'];
                            }
                        }

                        $streamEnded = true;
                        break;
                    } elseif ($type === 'response.error') {
                        $message = isset($event['error']['message']) ? (string) $event['error']['message'] : 'Unknown streaming error.';
                        throw new RuntimeException('OpenAI streaming error: ' . $message);
                    }
                }

                if ($streamEnded) {
                    break;
                }
            }
        }

        return [
            'content' => $content,
            'usage' => $usage,
            'response' => $this->normaliseResponseMeta($responseMeta),
        ];
    }

    /**
     * Transform chat-style message arrays into the Responses API input schema.
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array{role: string, content: array<int, array{type: string, text: string}>}>
     */
    private function formatMessagesForResponses(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            $role = isset($message['role']) ? (string) $message['role'] : 'user';
            $content = $message['content'] ?? '';

            if (is_array($content)) {
                $parts = [];

                foreach ($content as $part) {
                    if (is_array($part) && isset($part['type'], $part['text'])) {
                        $parts[] = [
                            'type' => (string) $part['type'],
                            'text' => (string) $part['text'],
                        ];
                    } elseif (is_string($part)) {
                        $parts[] = [
                            'type' => 'text',
                            'text' => $part,
                        ];
                    }
                }

                if ($parts === []) {
                    $parts[] = [
                        'type' => 'text',
                        'text' => is_string($content) ? $content : '',
                    ];
                }
            } else {
                $parts = [[
                    'type' => 'text',
                    'text' => (string) $content,
                ]];
            }

            $formatted[] = [
                'role' => $role,
                'content' => $parts,
            ];
        }

        return $formatted;
    }

    /**
     * Extract plain text output from the Responses API output array.
     *
     * @param array<int, array<string, mixed>> $output
     */
    private function extractTextFromOutput(array $output): string
    {
        $content = '';

        foreach ($output as $item) {
            if (!is_array($item)) {
                continue;
            }

            $segments = $item['content'] ?? [];

            if (!is_array($segments)) {
                continue;
            }

            foreach ($segments as $segment) {
                if (!is_array($segment)) {
                    continue;
                }

                $type = isset($segment['type']) ? (string) $segment['type'] : '';

                if (($type === 'output_text' || $type === 'text') && isset($segment['text'])) {
                    $content .= (string) $segment['text'];
                }
            }
        }

        return $content;
    }

    /**
     * Normalise response metadata into a schema compatible with downstream consumers.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function normaliseResponseMeta(array $response): array
    {
        if (!isset($response['choices'])) {
            $finishReason = null;

            if (isset($response['output']) && is_array($response['output'])) {
                $firstOutput = $response['output'][0] ?? null;

                if (is_array($firstOutput) && isset($firstOutput['finish_reason'])) {
                    $finishReason = $firstOutput['finish_reason'];
                }
            }

            $response['choices'] = [
                ['finish_reason' => $finishReason],
            ];
        }

        return $response;
    }

    /**
     * Build the JSON schema declaration for plan responses.
     *
     * Centralising the schema keeps the request payload readable and makes it
     * easy to update field requirements without hunting through inline arrays.
     */
    private function buildPlanJsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'tailoring_plan',
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['summary', 'strengths', 'gaps', 'next_steps'],
                    'properties' => [
                        'summary' => [
                            'type' => 'string',
                            'minLength' => 1,
                        ],
                        'strengths' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'items' => [
                                'type' => 'string',
                                'minLength' => 1,
                            ],
                        ],
                        'gaps' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'items' => [
                                'type' => 'string',
                                'minLength' => 1,
                            ],
                        ],
                        'next_steps' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['task', 'rationale', 'priority', 'estimated_minutes'],
                                'properties' => [
                                    'task' => [
                                        'type' => 'string',
                                        'minLength' => 1,
                                    ],
                                    'rationale' => [
                                        'type' => 'string',
                                        'minLength' => 1,
                                    ],
                                    'priority' => [
                                        'type' => 'string',
                                        'enum' => ['high', 'medium', 'low'],
                                    ],
                                    'estimated_minutes' => [
                                        'type' => 'integer',
                                        'minimum' => 1,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Decide whether to retry plan generation with a simpler JSON object response format.
     *
     * Certain OpenAI models do not yet support the structured `json_schema` format. When the
     * API reports that limitation we fall back to requesting a plain JSON object so plan
     * generation can continue without manual intervention.
     */
    private function shouldFallbackToJsonObject(RuntimeException $exception): bool
    {
        $previous = $exception->getPrevious();

        if (!$previous instanceof RequestException) {
            return false;
        }

        $response = $previous->getResponse();

        if ($response === null || $response->getStatusCode() !== 400) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        if ($message !== '' && $this->mentionsUnsupportedSchema($message)) {
            return true;
        }

        $body = (string) $response->getBody();

        if ($body !== '' && $this->mentionsUnsupportedSchema(strtolower($body))) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the failure indicates that the legacy response_format parameter is required.
     *
     * Some OpenAI models continue to expect the historic response_format key rather than the newer
     * response.format structure. When that situation is detected we retry the request using the legacy
     * parameter to maintain compatibility.
     */
    private function shouldFallbackToLegacyResponseFormat(RuntimeException $exception): bool
    {
        $previous = $exception->getPrevious();

        if (!$previous instanceof RequestException) {
            return false;
        }

        $response = $previous->getResponse();

        if ($response === null || $response->getStatusCode() !== 400) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        if ($message !== '' && $this->mentionsUnknownResponseParameter($message)) {
            return true;
        }

        $body = strtolower((string) $response->getBody());

        return $body !== '' && $this->mentionsUnknownResponseParameter($body);
    }

    /**
     * Check whether the supplied message references the response parameter as being unsupported.
     */
    private function mentionsUnknownResponseParameter(string $message): bool
    {
        if (strpos($message, 'unknown parameter') !== false && strpos($message, "'response'") !== false) {
            return true;
        }

        if (strpos($message, 'unknown argument') !== false && strpos($message, 'response') !== false) {
            return true;
        }

        return strpos($message, 'response is not allowed') !== false;
    }

    /**
     * Evaluate whether an error payload indicates that structured outputs are unsupported.
     */
    private function mentionsUnsupportedSchema(string $message): bool
    {
        return strpos($message, 'response_format') !== false
            || strpos($message, 'response.format') !== false
            || strpos($message, 'json_schema') !== false
            || strpos($message, 'structured output') !== false;
    }

    /**
     * Normalise the token usage section from OpenAI into a predictable schema.
     *
     * @param array<string, mixed> $usage
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    private function normaliseUsage(array $usage): array
    {
        $prompt = (int) ($usage['prompt_tokens'] ?? ($usage['input_tokens'] ?? 0));
        $completion = (int) ($usage['completion_tokens'] ?? ($usage['output_tokens'] ?? 0));
        $total = (int) ($usage['total_tokens'] ?? ($prompt + $completion));

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
        ];
    }

    /**
     * Persist usage metrics for later reporting alongside per-request metadata
     * that captures the model, endpoint, and cost information.
     *
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

    /**
     * Calculate the cost value.
     *
     * Keeping the formula together prevents duplication across services.
     */
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
     * Parse a JSON tariff definition into an internal lookup table keyed by
     * model identifier.
     *
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
     * Extract the tariff value from a nested data structure, falling back to a
     * supplied default when a preferred key is missing.
     *
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

    /**
     * Handle the resolve max tokens operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function resolveMaxTokens(): int
    {
        $maxTokens = $this->env('OPENAI_MAX_TOKENS');

        if ($maxTokens === null) {
            return 1024;
        }

        return max(1, (int) $maxTokens);
    }

    /**
     * Evaluate whether the retry should occur.
     *
     * Providing a single decision point keeps policy logic together.
     */
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

    /**
     * Extract a helpful error description from a failed HTTP response.
     *
     * Normalising the detail here keeps error handling consistent and avoids duplicating JSON
     * decoding logic throughout the class.
     */
    private function extractRequestErrorDetail(RequestException $exception): ?string
    {
        $response = $exception->getResponse();

        if ($response === null) {
            return null;
        }

        $body = (string) $response->getBody();

        if ($body === '') {
            return null;
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $ignored) {
            return trim($body);
        }

        if (isset($data['error']['message']) && is_string($data['error']['message'])) {
            return trim($data['error']['message']);
        }

        if (isset($data['message']) && is_string($data['message'])) {
            return trim($data['message']);
        }

        return trim($body);
    }

    /**
     * Build a clear exception message summarising the API failure for logging and UX.
     */
    private function buildRequestFailureMessage(?int $statusCode, ?string $detail, RequestException $exception): string
    {
        $prefix = 'OpenAI API request failed';

        if ($statusCode !== null) {
            $prefix .= sprintf(' (status %d)', $statusCode);
        }

        if ($detail !== null && $detail !== '') {
            return $prefix . ': ' . $detail;
        }

        return $prefix . ': ' . $exception->getMessage();
    }

    /**
     * Handle the wait with jitter operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function waitWithJitter(int $milliseconds): void
    {
        $jitter = random_int(0, (int) ($milliseconds * 0.2));
        $total = $milliseconds + $jitter;
        usleep($total * 1000);
    }

    /**
     * Handle the require env operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function requireEnv(string $key): string
    {
        $value = $this->env($key);

        if ($value === null || $value === '') {
            throw new RuntimeException(
                sprintf('Configuration value %s must be set via environment variables or site settings.', $key)
            );
        }

        return $value;
    }

    /**
     * Handle the env operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value !== false && $value !== null) {
            $trimmed = trim((string) $value);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        $stored = $this->settingsRepository->findValue($this->normaliseSettingKey($key));

        if ($stored === null) {
            return null;
        }

        $trimmed = trim($stored);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Normalise the provided environment key into a site settings identifier.
     *
     * Having a consistent mapping allows the worker and web contexts to share
     * credentials without duplicating configuration logic.
     */
    private function normaliseSettingKey(string $key): string
    {
        return strtolower($key);
    }
}
