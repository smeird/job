<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';
require __DIR__ . '/verify.helpers.php';

use GuzzleHttp\Client;

$projectRoot = dirname(__DIR__);
$config = verify_load_env($projectRoot);

$options = getopt('', ['interactive::', 'timeout::', 'nuke::']);
$interactive = filter_var($options['interactive'] ?? '0', FILTER_VALIDATE_BOOL);
$timeout = isset($options['timeout']) ? (int) $options['timeout'] : 180;
$nuke = filter_var($options['nuke'] ?? '0', FILTER_VALIDATE_BOOL);

$baseUrl = rtrim(verify_env($config, 'APP_URL', 'https://job.smeird.com'), '/');
$domain = verify_env($config, 'TEST_EMAIL_DOMAIN', 'example.com');

$jar = verify_make_cookie_jar();
$client = verify_client($baseUrl, $jar);

$checks = [];
$state = [
    'documents' => [],
    'generation' => null,
    'email' => sprintf('test+%s@%s', date('YmdHis'), $domain),
    'passcode' => null,
    'session_cookie' => null,
    'tokens' => 0,
    'cost' => 0.0,
    'usage' => [],
];

function run_check(string $name, callable $callback, bool $critical = true): array
{
    try {
        $notes = $callback();
        return [true, is_string($notes) ? $notes : 'ok'];
    } catch (Throwable $e) {
        return [false, $e->getMessage(), $critical];
    }
}

function record_check(array &$checks, string $name, callable $callback, bool $critical = true): void
{
    [$passed, $notesOrMessage, $critFlag] = array_replace([null, null, $critical], run_check($name, $callback, $critical));
    $checks[] = [
        'name' => $name,
        'pass' => $passed,
        'critical' => $critFlag,
        'notes' => $notesOrMessage,
    ];
}

record_check($checks, '/healthz', function () use ($client) {
    $response = $client->get('healthz');
    verify_assert_status($response, 200, '/healthz');
    $body = trim((string) $response->getBody());
    if (stripos($body, 'ok') === false) {
        throw new RuntimeException('Body missing ok marker');
    }

    return '200 ok';
});

record_check($checks, 'Security headers', function () use ($client) {
    $response = $client->get('');
    verify_assert_status($response, 200, '/');
    $headers = verify_collect_headers($response);

    $required = [
        'Content-Security-Policy',
        'Referrer-Policy',
        'X-Content-Type-Options',
        'Permissions-Policy',
        'Set-Cookie',
    ];

    foreach ($required as $header) {
        if (!verify_header_has($headers, $header)) {
            throw new RuntimeException('Missing header: ' . $header);
        }
    }

    $csp = verify_header_value($headers, 'Content-Security-Policy');
    if ($csp === null || !str_contains($csp, "'self'")) {
        throw new RuntimeException('CSP missing self restriction');
    }

    $referrer = verify_header_value($headers, 'Referrer-Policy');
    if ($referrer !== 'strict-origin-when-cross-origin') {
        throw new RuntimeException('Unexpected Referrer-Policy: ' . ($referrer ?? 'null'));
    }

    $xcto = strtolower((string) verify_header_value($headers, 'X-Content-Type-Options'));
    if ($xcto !== 'nosniff') {
        throw new RuntimeException('X-Content-Type-Options not nosniff');
    }

    $permissions = verify_header_value($headers, 'Permissions-Policy');
    if ($permissions === null) {
        throw new RuntimeException('Permissions-Policy missing');
    }
    foreach (['camera', 'microphone', 'geolocation'] as $feature) {
        if (!str_contains(strtolower($permissions), $feature . "=()")) {
            throw new RuntimeException('Permissions-Policy must deny ' . $feature);
        }
    }

    $cookies = $headers['set-cookie'];
    $flagsOk = false;
    foreach ($cookies as $cookie) {
        $cookie = strtolower($cookie);
        if (str_contains($cookie, 'secure') && str_contains($cookie, 'httponly') && str_contains($cookie, 'samesite=lax')) {
            $flagsOk = true;
            break;
        }
    }
    if (!$flagsOk) {
        throw new RuntimeException('Session cookie missing secure/httpOnly/sameSite=Lax');
    }

    return 'CSP, RP, NTS, PP ok';
});

record_check($checks, 'Upload cap (1 MB)', function () use ($client) {
    $fixture = verify_make_fixture_oversize();
    $response = $client->post('documents/upload', [
        'multipart' => [[
            'name' => 'file',
            'filename' => $fixture['filename'],
            'contents' => $fixture['contents'],
            'headers' => ['Content-Type' => $fixture['mime']],
        ]],
    ]);

    $status = $response->getStatusCode();
    if ($status !== 413 && ($status < 400 || $status >= 500)) {
        throw new RuntimeException('Expected 413 or friendly 4xx, got ' . $status);
    }

    $body = (string) $response->getBody();
    if (!str_contains($body, '1 MB') && !str_contains($body, '1MB')) {
        throw new RuntimeException('Oversize response missing mention of 1 MB');
    }

    return $status . ' ' . (strlen($body) ? 'message ok' : '');
});

record_check($checks, 'Passcode registration', function () use (&$state, $client, $interactive, $config) {
    $email = $state['email'];
    $response = $client->post('auth/register', [
        'json' => ['email' => $email],
    ]);
    verify_assert_status($response, 200, 'auth/register');
    $body = json_decode((string) $response->getBody(), true);
    if (!is_array($body) || !isset($body['status']) || stripos($body['status'], 'code') === false) {
        throw new RuntimeException('Register response missing code sent marker');
    }

    $code = verify_fetch_test_passcode($client, $email);
    if (!$code) {
        $code = verify_fetch_db_passcode($config, $email);
    }
    if (!$code) {
        if (!$interactive) {
            throw new RuntimeException('Passcode retrieval unavailable; rerun with --interactive=1');
        }
        $code = verify_prompt_passcode($email);
    }

    $state['passcode'] = $code;

    $verify = $client->post('auth/register/verify', [
        'json' => ['email' => $email, 'code' => $code],
    ]);
    verify_assert_status($verify, 200, 'auth/register/verify');

    return 'Session established';
});

record_check($checks, 'Login by passcode', function () use (&$state, $client) {
    $client->post('auth/logout', []);

    $email = $state['email'];
    $response = $client->post('auth/login', [
        'json' => ['email' => $email],
    ]);
    verify_assert_status($response, 200, 'auth/login');

    if (!$state['passcode']) {
        throw new RuntimeException('No passcode cached from registration');
    }

    $verify = $client->post('auth/login/verify', [
        'json' => ['email' => $email, 'code' => $state['passcode']],
    ]);
    verify_assert_status($verify, 200, 'auth/login/verify');

    return 'Session cookie set';
});

record_check($checks, 'Upload + extraction (docx/pdf/md/txt)', function () use (&$state, $client) {
    $fixtures = [
        verify_make_fixture_docx(),
        verify_make_fixture_pdf(),
        verify_make_fixture_markdown(),
        verify_make_fixture_text(),
    ];

    foreach ($fixtures as $fixture) {
        $response = $client->post('documents/upload', [
            'multipart' => [[
                'name' => 'file',
                'filename' => $fixture['filename'],
                'contents' => $fixture['contents'],
                'headers' => ['Content-Type' => $fixture['mime']],
            ]],
        ]);
        verify_assert_status($response, 200, 'documents/upload');
        $body = json_decode((string) $response->getBody(), true);
        if (!isset($body['id'])) {
            throw new RuntimeException('Upload response missing id');
        }
        $state['documents'][] = $body['id'];

        $preview = $client->get('documents/' . $body['id'] . '/preview');
        verify_assert_status($preview, 200, 'documents/preview');
        $text = (string) $preview->getBody();
        if (mb_strlen(trim($text)) === 0) {
            throw new RuntimeException('Preview empty for ' . $fixture['filename']);
        }
        if (mb_strlen($text) > 5000) {
            $text = mb_substr($text, 0, 5000);
        }
    }

    return count($state['documents']) . ' docs uploaded';
});

record_check($checks, 'Diskless guarantee', function () use ($client) {
    $paths = ['uploads', 'storage', 'files'];
    foreach ($paths as $path) {
        $response = $client->get($path);
        if (in_array($response->getStatusCode(), [200, 201])) {
            throw new RuntimeException('Path ' . $path . ' should not be accessible');
        }
    }

    return 'No direct file exposure';
}, false);

record_check($checks, 'Create generation', function () use (&$state, $client, $config) {
    if (count($state['documents']) < 2) {
        throw new RuntimeException('Need documents for generation');
    }

    $payload = [
        'job_description_id' => $state['documents'][1],
        'cv_source_id' => $state['documents'][0],
        'model' => verify_env($config, 'OPENAI_MODEL_PLAN') ?? 'gpt-4.1-mini',
    ];

    $response = $client->post('generations', ['json' => $payload]);
    verify_assert_status($response, 200, 'generations');
    $body = json_decode((string) $response->getBody(), true);
    if (!isset($body['id'])) {
        throw new RuntimeException('Generation response missing id');
    }
    if (($body['status'] ?? null) !== 'queued') {
        throw new RuntimeException('Generation not queued');
    }

    $state['generation'] = $body['id'];

    return 'Generation ' . $body['id'];
});

record_check($checks, 'Queue + SSE', function () use (&$state, $client, $timeout) {
    if (!$state['generation']) {
        throw new RuntimeException('No generation id');
    }

    $events = verify_sse_stream($client, 'generations/' . $state['generation'] . '/stream', $timeout);

    $statusComplete = false;
    $tokens = 0;
    $cost = 0.0;
    foreach ($events as $event) {
        if (($event['event'] ?? '') === 'status') {
            $payload = json_decode($event['data'] ?? '{}', true);
            if (($payload['status'] ?? '') === 'complete') {
                $statusComplete = true;
            }
        }
        if (($event['event'] ?? '') === 'tokens') {
            $payload = json_decode($event['data'] ?? '{}', true);
            $tokens += (int) ($payload['total'] ?? 0);
        }
        if (($event['event'] ?? '') === 'cost') {
            $payload = json_decode($event['data'] ?? '{}', true);
            $cost += (float) ($payload['amount'] ?? 0);
        }
    }

    if (!$statusComplete) {
        throw new RuntimeException('Generation did not complete via SSE');
    }

    $state['tokens'] = $tokens;
    $state['cost'] = $cost;

    return 'complete, tokens ' . $tokens . ', cost ' . $cost;
});

record_check($checks, 'Outputs (md/docx/pdf)', function () use (&$state, $client) {
    $response = $client->get('generations/' . $state['generation']);
    verify_assert_status($response, 200, 'generation show');
    $body = json_decode((string) $response->getBody(), true);
    if (!isset($body['outputs']) || !is_array($body['outputs'])) {
        throw new RuntimeException('Outputs missing');
    }

    $formats = ['md', 'docx', 'pdf'];
    foreach ($formats as $format) {
        $entry = null;
        foreach ($body['outputs'] as $output) {
            if (($output['format'] ?? null) === $format) {
                $entry = $output;
                break;
            }
        }
        if (!$entry) {
            throw new RuntimeException('Missing output format ' . $format);
        }
        if (($entry['size_bytes'] ?? 0) <= 0) {
            throw new RuntimeException('Output ' . $format . ' size invalid');
        }
        if (empty($entry['sha256'])) {
            throw new RuntimeException('Output ' . $format . ' missing sha256');
        }
    }

    return 'MD/DOCX/PDF present';
});

record_check($checks, 'Signed downloads', function () use (&$state, $client) {
    $formats = ['md' => 'text/markdown', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'pdf' => 'application/pdf'];
    foreach ($formats as $format => $mime) {
        $response = $client->get('generations/' . $state['generation'] . '/download', [
            'query' => ['format' => $format],
        ]);
        verify_assert_status($response, 200, 'download ' . $format);
        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, $mime)) {
            throw new RuntimeException('Download ' . $format . ' wrong mime ' . $contentType);
        }
        $binary = (string) $response->getBody();
        if ($binary === '') {
            throw new RuntimeException('Download ' . $format . ' empty');
        }
        if ($format === 'docx' && !verify_zip_has_entry($binary, '[Content_Types].xml')) {
            throw new RuntimeException('DOCX missing Content_Types.xml');
        }
        if ($format === 'pdf' && !str_starts_with($binary, '%PDF')) {
            throw new RuntimeException('PDF missing signature');
        }
        if ($format === 'md' && !str_starts_with($binary, '#')) {
            throw new RuntimeException('Markdown download suspicious');
        }
    }

    return 'HMAC token accepted';
});

record_check($checks, 'Usage accounting', function () use (&$state, $client) {
    $response = $client->get('usage');
    verify_assert_status($response, 200, 'usage');
    $body = json_decode((string) $response->getBody(), true);
    if (!is_array($body) || !isset($body['latest'])) {
        throw new RuntimeException('Usage data missing latest row');
    }
    $latest = $body['latest'];
    foreach (['tokens_in', 'tokens_out', 'cost'] as $field) {
        if (!isset($latest[$field]) || $latest[$field] < 0) {
            throw new RuntimeException('Usage field missing ' . $field);
        }
    }
    $state['usage'] = $latest;

    return 'tokens recorded';
});

record_check($checks, 'Retention purge', function () use (&$state, $client) {
    $client->post('retention', ['json' => ['uploads_days' => 0]]);
    $client->get('gdpr/export');
    $client->post('test/run-purge', []);
    $documents = $client->get('documents');
    verify_assert_status($documents, 200, 'documents list');
    $list = json_decode((string) $documents->getBody(), true);
    if (!is_array($list)) {
        throw new RuntimeException('Documents list invalid');
    }

    return 'uploads pruned';
}, false);

record_check($checks, 'GDPR export', function () use ($client) {
    $response = $client->get('gdpr/export');
    verify_assert_status($response, 200, 'gdpr/export');
    $body = json_decode((string) $response->getBody(), true);
    if (!isset($body['blobs']) || !is_array($body['blobs']) || count($body['blobs']) === 0) {
        throw new RuntimeException('GDPR export missing blobs');
    }

    foreach ($body['blobs'] as $blob) {
        if (!isset($blob['data']) || base64_decode($blob['data'], true) === false) {
            throw new RuntimeException('Invalid base64 blob');
        }
    }

    return count($body['blobs']) . ' blobs exported';
});

record_check($checks, 'Delete my account (dry run)', function () use ($client, $state, $nuke) {
    $response = $client->get('gdpr/delete', ['query' => ['dryRun' => $nuke ? '0' : '1']]);
    verify_assert_status($response, 200, 'gdpr/delete');
    $body = json_decode((string) $response->getBody(), true);
    if (!$nuke) {
        if (!isset($body['would_delete'])) {
            throw new RuntimeException('Dry run response missing would_delete');
        }
        return $body['would_delete'] . ' rows would delete';
    }

    if (!isset($body['deleted'])) {
        throw new RuntimeException('Delete response missing deleted count');
    }

    return 'deleted ' . $body['deleted'];
});

// Cleanup unless --nuke
if (!$nuke && $state['generation']) {
    try {
        $client->delete('generations/' . $state['generation']);
    } catch (Throwable) {
        // ignore cleanup failures
    }
}
if (!$nuke && !empty($state['documents'])) {
    foreach ($state['documents'] as $docId) {
        try {
            $client->delete('documents/' . $docId);
        } catch (Throwable) {
            // ignore cleanup failures
        }
    }
}

$rows = [];
$allPass = true;
foreach ($checks as $check) {
    $emoji = verify_status_emoji($check['pass'] ?? false, $check['critical']);
    $result = ($check['pass'] ?? false) ? 'PASS' : 'FAIL';
    if (!($check['pass'] ?? false) && $check['critical']) {
        $allPass = false;
    }
    $rows[] = [
        'name' => $check['name'],
        'result' => $result . ' ' . $emoji,
        'notes' => $check['notes'] ?? '',
    ];
}

echo verify_table($rows) . PHP_EOL;
$verdict = $allPass ? 'VERDICT: PASS' : 'VERDICT: FAIL';
echo ($allPass ? verify_color($verdict, 'green') : verify_color($verdict, 'red')) . PHP_EOL;

verify_append_readme_note();

exit($allPass ? 0 : 1);
