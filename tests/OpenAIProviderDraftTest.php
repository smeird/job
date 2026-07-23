<?php

declare(strict_types=1);

use App\AI\OpenAIProvider;
use App\Settings\SiteSettingsRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

require __DIR__ . '/../autoload.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE site_settings (name TEXT PRIMARY KEY, value TEXT NULL, created_at TEXT, updated_at TEXT)');
$pdo->exec(
    'CREATE TABLE api_usage ('
    . 'id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, provider TEXT, endpoint TEXT, '
    . 'tokens_used INTEGER, cost_pence INTEGER, metadata TEXT)'
);

foreach ([
    'OPENAI_API_KEY' => 'test-key',
    'OPENAI_MODEL_PLAN' => 'gpt-5.6-sol',
    'OPENAI_MODEL_DRAFT' => 'gpt-5.6-terra',
    'OPENAI_MAX_TOKENS' => '8000',
    'OPENAI_TARIFF_JSON' => '{}',
] as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}

$planText = json_encode([
    'summary' => 'Evidence map',
    'strengths' => ['Platform leadership evidenced by Acme role'],
    'gaps' => ['No Kubernetes evidence'],
    'next_steps' => [[
        'task' => 'Lead with platform recovery',
        'rationale' => 'Direct match',
        'priority' => 'high',
        'estimated_minutes' => 5,
    ]],
]);
$planResponse = [
    'id' => 'resp_plan',
    'model' => 'gpt-5.6-sol',
    'output' => [[
        'content' => [['type' => 'output_text', 'text' => $planText]],
        'finish_reason' => 'stop',
    ]],
    'usage' => ['input_tokens' => 100, 'output_tokens' => 40, 'total_tokens' => 140],
];
$draftResponse = [
    'id' => 'resp_draft',
    'model' => 'gpt-5.6-luna',
    'output' => [[
        'content' => [['type' => 'output_text', 'text' => "# Candidate\n\nTailored CV"]],
        'finish_reason' => 'stop',
    ]],
    'usage' => ['input_tokens' => 180, 'output_tokens' => 60, 'total_tokens' => 240],
];
$history = [];
$mock = new MockHandler([
    new Response(200, ['Content-Type' => 'application/json'], json_encode($planResponse)),
    new Response(200, ['Content-Type' => 'application/json'], json_encode($draftResponse)),
]);
$stack = HandlerStack::create($mock);
$stack->push(Middleware::history($history));
$client = new Client(['handler' => $stack]);
$settings = new SiteSettingsRepository($pdo);
$provider = new OpenAIProvider(9, $client, $pdo, $settings, null, 'gpt-5.6-luna', 50);

$jobDescription = 'Senior Platform Director requiring cloud recovery and portfolio governance.';
$sourceCv = '# Candidate' . "\n\n## Experience\nAcme — Platform Lead. Recovered a cloud platform.";
$plan = $provider->plan($jobDescription, $sourceCv);
$draft = $provider->draft($plan, 'Preserve chronology.', null, $jobDescription, $sourceCv);

if ($draft !== "# Candidate\n\nTailored CV" || count($history) !== 2) {
    throw new RuntimeException('Planner and drafter did not complete the expected two-request workflow.');
}

$planPayload = json_decode((string) $history[0]['request']->getBody(), true);
$draftPayload = json_decode((string) $history[1]['request']->getBody(), true);

if (($planPayload['model'] ?? null) !== 'gpt-5.6-sol') {
    throw new RuntimeException('Configured analysis model was not used for the evidence plan.');
}

if (($draftPayload['model'] ?? null) !== 'gpt-5.6-luna') {
    throw new RuntimeException('Per-run drafting model override did not reach the OpenAI request.');
}

if (($planPayload['reasoning']['effort'] ?? null) !== 'high'
    || ($draftPayload['reasoning']['effort'] ?? null) !== 'high'
) {
    throw new RuntimeException('Analysis depth was not translated into GPT-5.6 reasoning effort.');
}

$draftInput = isset($draftPayload['input'][1]['content'][0]['text'])
    ? (string) $draftPayload['input'][1]['content'][0]['text']
    : '';

if (strpos($draftInput, $jobDescription) === false || strpos($draftInput, $sourceCv) === false) {
    throw new RuntimeException('Final drafting request was not grounded in the full job description and source CV.');
}

$usageRows = (int) $pdo->query('SELECT COUNT(*) FROM api_usage')->fetchColumn();

if ($usageRows !== 2) {
    throw new RuntimeException('OpenAI usage was not recorded for both analysis and drafting requests.');
}

echo 'OpenAIProviderDraftTest passed' . PHP_EOL;
