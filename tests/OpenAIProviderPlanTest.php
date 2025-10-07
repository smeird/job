<?php

declare(strict_types=1);

use App\AI\OpenAIProvider;
use App\Settings\SiteSettingsRepository;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\' => __DIR__ . '/../src/',
        'GuzzleHttp\\' => __DIR__ . '/stubs/GuzzleHttp/',
        'Psr\\Http\\Message\\' => __DIR__ . '/stubs/Psr/Http/Message/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strpos($class, $prefix) !== 0) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $path = rtrim($baseDir, '/') . '/' . $relative . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});

/**
 * FakeClient captures outgoing requests and replays predefined responses.
 */
final class FakeClient implements ClientInterface
{
    /** @var array<int, mixed> */
    private $responses;

    /** @var array<int, array{method: string, uri: string, options: array<string, mixed>}> */
    private $requests = [];

    /**
     * @param array<int, mixed> $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    /**
     * Record and return the next configured response for a request invocation.
     */
    public function request($method, $uri, array $options = []): ResponseInterface
    {
        $this->requests[] = [
            'method' => (string) $method,
            'uri' => (string) $uri,
            'options' => $options,
        ];

        if ($this->responses === []) {
            throw new RuntimeException('No responses configured for FakeClient.');
        }

        $next = array_shift($this->responses);

        if ($next instanceof Throwable) {
            throw $next;
        }

        if (!$next instanceof ResponseInterface) {
            throw new RuntimeException('FakeClient received an invalid response type.');
        }

        return $next;
    }

    /**
     * send is unused in the test double and therefore raises to surface mistakes.
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        throw new BadMethodCallException('send is not implemented on FakeClient.');
    }

    /**
     * sendAsync is unused in the test double and therefore raises to surface mistakes.
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        throw new BadMethodCallException('sendAsync is not implemented on FakeClient.');
    }

    /**
     * requestAsync is unused in the test double and therefore raises to surface mistakes.
     */
    public function requestAsync($method, $uri, array $options = []): PromiseInterface
    {
        throw new BadMethodCallException('requestAsync is not implemented on FakeClient.');
    }

    /**
     * The fake client exposes no configuration values during the test run.
     */
    public function getConfig($option = null)
    {
        return null;
    }

    /**
     * @return array<int, array{method: string, uri: string, options: array<string, mixed>}> 
     */
    public function recordedRequests(): array
    {
        return $this->requests;
    }
}

putenv('OPENAI_API_KEY=test-key');
putenv('OPENAI_MODEL_PLAN=gpt-plan');
putenv('OPENAI_MODEL_DRAFT=gpt-draft');
putenv('OPENAI_MAX_TOKENS=200');
putenv('OPENAI_TARIFF_JSON={"gpt-plan":{"prompt":0.0,"completion":0.0}}');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE site_settings (name TEXT PRIMARY KEY, value TEXT)');
$pdo->exec('CREATE TABLE api_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    provider TEXT,
    endpoint TEXT,
    tokens_used INTEGER,
    cost_pence INTEGER,
    metadata TEXT
)');
$settings = new SiteSettingsRepository($pdo);

$firstFailure = new RequestException(
    'response_format is unsupported',
    new Request('POST', 'responses'),
    new Response(
        400,
        ['Content-Type' => 'application/json'],
        json_encode(['error' => ['message' => 'response_format is unsupported on this model.']])
    )
);

$successPayload = [
    'id' => 'resp_123',
    'model' => 'gpt-plan',
    'output' => [
        [
            'content' => [
                [
                    'type' => 'output_text',
                    'text' => json_encode([
                        'summary' => 'Done',
                        'strengths' => ['Skill'],
                        'gaps' => ['Gap'],
                        'next_steps' => [
                            ['task' => 'Task', 'rationale' => 'Reason', 'priority' => 'high', 'estimated_minutes' => 5],
                        ],
                    ]),
                ],
            ],
            'finish_reason' => 'stop',
        ],
    ],
    'usage' => [
        'prompt_tokens' => 10,
        'completion_tokens' => 20,
        'total_tokens' => 30,
    ],
];

$client = new FakeClient([
    $firstFailure,
    new Response(200, ['Content-Type' => 'application/json'], json_encode($successPayload)),
]);

$provider = new OpenAIProvider(1, $client, $pdo, $settings);

$plan = $provider->plan('Job text', 'CV text');

$requests = $client->recordedRequests();

if (count($requests) !== 2) {
    throw new RuntimeException('Expected two requests but recorded ' . count($requests));
}

$firstRequestBody = json_decode($requests[0]['options']['body'], true);
$secondRequestBody = json_decode($requests[1]['options']['body'], true);

if (!is_array($firstRequestBody) || !isset($firstRequestBody['response_format'])) {
    throw new RuntimeException('Initial request payload is missing response_format.');
}

if (isset($secondRequestBody['response'])) {
    throw new RuntimeException('Fallback request should not include the legacy response key.');
}

if (isset($secondRequestBody['response_format'])) {
    throw new RuntimeException('Fallback request should not include response_format after stripping.');
}

$decoded = json_decode($plan, true);

if (!is_array($decoded) || $decoded['summary'] !== 'Done') {
    throw new RuntimeException('Plan JSON did not decode as expected.');
}


putenv('OPENAI_MODEL_PLAN=missing-model');
$_ENV['OPENAI_MODEL_PLAN'] = 'missing-model';
$_SERVER['OPENAI_MODEL_PLAN'] = 'missing-model';

$missingModelFailure = new RequestException(
    'model not found',
    new Request('POST', 'responses'),
    new Response(
        400,
        ['Content-Type' => 'application/json'],
        json_encode(['error' => ['message' => "The requested model 'missing-model' does not exist."]])
    )
);

$fallbackClient = new FakeClient([
    $missingModelFailure,
    new Response(200, ['Content-Type' => 'application/json'], json_encode($successPayload)),
]);

$fallbackProvider = new OpenAIProvider(1, $fallbackClient, $pdo, $settings);
$fallbackPlan = $fallbackProvider->plan('Job text', 'CV text');

$fallbackRequests = $fallbackClient->recordedRequests();

if (count($fallbackRequests) !== 2) {
    throw new RuntimeException('Expected two requests during model fallback testing.');
}

$initialFallbackRequest = json_decode($fallbackRequests[0]['options']['body'], true);
$secondFallbackRequest = json_decode($fallbackRequests[1]['options']['body'], true);

if (!is_array($initialFallbackRequest) || $initialFallbackRequest['model'] !== 'missing-model') {
    throw new RuntimeException('Initial plan request did not target the configured missing model.');
}

if (!is_array($secondFallbackRequest) || $secondFallbackRequest['model'] !== 'gpt-5-mini') {
    throw new RuntimeException('Fallback plan request did not target the expected default model.');
}

$fallbackDecoded = json_decode($fallbackPlan, true);

if (!is_array($fallbackDecoded) || $fallbackDecoded['summary'] !== 'Done') {
    throw new RuntimeException('Fallback plan JSON did not decode as expected.');
}


putenv('OPENAI_MODEL_PLAN=gpt-5.0-strategist');
$_ENV['OPENAI_MODEL_PLAN'] = 'gpt-5.0-strategist';
$_SERVER['OPENAI_MODEL_PLAN'] = 'gpt-5.0-strategist';

$aliasSuccessPayload = $successPayload;
$aliasSuccessPayload['model'] = 'gpt-5-strategist';

$aliasClient = new FakeClient([
    $firstFailure,
    new Response(200, ['Content-Type' => 'application/json'], json_encode($aliasSuccessPayload)),
]);

$aliasProvider = new OpenAIProvider(1, $aliasClient, $pdo, $settings);
$aliasPlan = $aliasProvider->plan('Job text', 'CV text');

$aliasRequests = $aliasClient->recordedRequests();

if (count($aliasRequests) !== 2) {
    throw new RuntimeException('Expected two requests during alias normalisation testing.');
}

$aliasFirstRequest = json_decode($aliasRequests[0]['options']['body'], true);
$aliasSecondRequest = json_decode($aliasRequests[1]['options']['body'], true);

if (!is_array($aliasFirstRequest) || $aliasFirstRequest['model'] !== 'gpt-5-strategist') {
    throw new RuntimeException('Initial alias plan request did not normalise the marketing model name.');
}

if (!is_array($aliasSecondRequest) || $aliasSecondRequest['model'] !== 'gpt-5-strategist') {
    throw new RuntimeException('Second alias plan request did not retain the normalised model identifier.');
}

$aliasDecoded = json_decode($aliasPlan, true);

if (!is_array($aliasDecoded) || $aliasDecoded['summary'] !== 'Done') {
    throw new RuntimeException('Alias plan JSON did not decode as expected.');
}


echo 'OpenAIProviderPlanTest passed' . PHP_EOL;
