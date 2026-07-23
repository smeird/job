<?php

declare(strict_types=1);

use App\AI\ModelCatalogService;
use App\Settings\SiteSettingsRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

require __DIR__ . '/../autoload.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE site_settings ('
    . 'name TEXT PRIMARY KEY, value TEXT NULL, created_at TEXT, updated_at TEXT)'
);

$_ENV['OPENAI_API_KEY'] = 'test-key';
$_SERVER['OPENAI_API_KEY'] = 'test-key';
putenv('OPENAI_API_KEY=test-key');

$remotePayload = [
    'object' => 'list',
    'data' => [
        ['id' => 'gpt-6'],
        ['id' => 'gpt-5.6-terra'],
        ['id' => 'gpt-5.6-sol'],
        ['id' => 'gpt-5.6-sol-2026-07-01'],
        ['id' => 'gpt-5.6-codex'],
        ['id' => 'gpt-4o-audio-preview'],
    ],
];
$mock = new MockHandler([
    new Response(200, ['Content-Type' => 'application/json'], json_encode($remotePayload)),
]);
$client = new Client(['handler' => HandlerStack::create($mock)]);
$settings = new SiteSettingsRepository($pdo);
$catalog = new ModelCatalogService($settings, $client);
$models = $catalog->models(true);
$ids = array_map(static function (array $model): string {
    return $model['value'];
}, $models);

if ($ids !== ['gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-6']) {
    throw new RuntimeException('Remote model catalogue was not filtered and ordered as expected.');
}

if (!$catalog->lastRefreshSucceeded() || $catalog->refreshedAt() === null) {
    throw new RuntimeException('Successful model refresh status was not persisted.');
}

$settings->saveValue('openai_model_draft', 'gpt-5.6-terra');

if ($catalog->defaultModel() !== 'gpt-5.6-terra') {
    throw new RuntimeException('Stored drafting model was not used as the default.');
}

if (!$catalog->isSelectable('gpt-6') || $catalog->isSelectable('gpt-5.6-codex')) {
    throw new RuntimeException('Model allow-list validation did not use the filtered catalogue.');
}

unset($_ENV['OPENAI_API_KEY'], $_SERVER['OPENAI_API_KEY']);
putenv('OPENAI_API_KEY');

$cachedCatalog = new ModelCatalogService($settings, new Client([
    'handler' => HandlerStack::create(new MockHandler([])),
]));

if ($cachedCatalog->models() !== $models) {
    throw new RuntimeException('Fresh cached model catalogue was not reused without an API key.');
}

echo 'ModelCatalogServiceTest passed' . PHP_EOL;
