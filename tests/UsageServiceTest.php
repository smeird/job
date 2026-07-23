<?php

declare(strict_types=1);

use App\Services\UsageService;

require __DIR__ . '/../autoload.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE api_usage ('
    . 'id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, provider TEXT, endpoint TEXT, '
    . 'tokens_used INTEGER, cost_pence INTEGER, metadata TEXT, created_at TEXT)'
);

$insert = $pdo->prepare(
    'INSERT INTO api_usage (user_id, provider, endpoint, tokens_used, cost_pence, metadata, created_at) '
    . 'VALUES (:user_id, :provider, :endpoint, :tokens_used, :cost_pence, :metadata, :created_at)'
);
$insert->execute([
    ':user_id' => 7,
    ':provider' => 'openai',
    ':endpoint' => '/responses',
    ':tokens_used' => 30,
    ':cost_pence' => 0,
    ':metadata' => json_encode([
        'model_requested' => 'gpt-5.6-terra',
        'prompt_tokens' => 10,
        'completion_tokens' => 20,
        'total_tokens' => 30,
        'cost_pence_precise' => 0.018,
        'cost_available' => true,
    ]),
    ':created_at' => date('Y-m-d H:i:s'),
]);
$insert->execute([
    ':user_id' => 7,
    ':provider' => 'openai',
    ':endpoint' => '/responses',
    ':tokens_used' => 12,
    ':cost_pence' => 0,
    ':metadata' => json_encode([
        'model_requested' => 'gpt-6',
        'prompt_tokens' => 5,
        'completion_tokens' => 7,
        'total_tokens' => 12,
        'cost_pence_precise' => null,
        'cost_available' => false,
    ]),
    ':created_at' => date('Y-m-d H:i:s'),
]);

$usage = (new UsageService($pdo))->getUsageForUser(7);

if (count($usage['per_run']) !== 2 || $usage['totals']['lifetime']['total_tokens'] !== 42) {
    throw new RuntimeException('Usage token aggregation returned unexpected results.');
}

if (abs((float) $usage['totals']['lifetime']['cost_pence'] - 0.018) > 0.000001) {
    throw new RuntimeException('Precise sub-penny usage cost was lost during aggregation.');
}

if ($usage['totals']['lifetime']['cost_complete'] !== false) {
    throw new RuntimeException('Missing model pricing was not surfaced as partial pricing.');
}

$unpricedRun = null;

foreach ($usage['per_run'] as $run) {
    if ($run['model'] === 'gpt-6') {
        $unpricedRun = $run;
        break;
    }
}

if (!is_array($unpricedRun) || $unpricedRun['cost_pence'] !== null) {
    throw new RuntimeException('Unpriced usage row should retain its model and expose an unavailable cost.');
}

if (count($usage['monthly']) !== 1 || $usage['monthly'][0]['cost_complete'] !== false) {
    throw new RuntimeException('Monthly usage did not preserve partial-pricing state.');
}

echo 'UsageServiceTest passed' . PHP_EOL;
