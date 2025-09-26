<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Load configuration from a .env file if present. Values provided via environment
 * variables take precedence. Returns an associative array of relevant keys.
 */
function verify_load_env(string $projectRoot): array
{
    $envPath = rtrim($projectRoot, DIRECTORY_SEPARATOR);
    $envFile = $envPath . DIRECTORY_SEPARATOR . '.env';

    if (is_readable($envFile)) {
        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->safeLoad();
    }

    return [
        'APP_URL' => getenv('APP_URL') ?: 'https://job.smeird.com',
        'TEST_EMAIL_DOMAIN' => getenv('TEST_EMAIL_DOMAIN') ?: 'example.com',
        'OPENAI_MODEL_PLAN' => getenv('OPENAI_MODEL_PLAN') ?: null,
        'OPENAI_MODEL_DRAFT' => getenv('OPENAI_MODEL_DRAFT') ?: null,
        'DB_HOST' => getenv('DB_HOST') ?: null,
        'DB_NAME' => getenv('DB_NAME') ?: null,
        'DB_USER_RO' => getenv('DB_USER_RO') ?: null,
        'DB_PASS_RO' => getenv('DB_PASS_RO') ?: null,
    ];
}

/**
 * Handle the verify env workflow.
 *
 * This helper keeps the verify env logic centralised for clarity and reuse.
 * @param mixed $default
 * @return mixed
 */
function verify_env(array $config, string $key, $default = null)
{
    return array_key_exists($key, $config) ? $config[$key] : $default;
}

/**
 * Handle the verify color operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_color(string $text, string $color): string
{
    $map = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'cyan' => "\033[36m",
        'magenta' => "\033[35m",
        'reset' => "\033[0m",
    ];

    $prefix = $map[$color] ?? '';
    $suffix = $prefix ? $map['reset'] : '';

    return $prefix . $text . $suffix;
}

/**
 * Handle the verify status emoji operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_status_emoji(bool $pass, bool $critical = true): string
{
    if ($pass) {
        return '✅';
    }

    return $critical ? '❌' : '⚠️';
}

/**
 * Handle the verify pretty json workflow.
 *
 * This helper keeps the verify pretty json logic centralised for clarity and reuse.
 * @param mixed $data
 */
function verify_pretty_json($data): string
{
    if (is_string($data)) {
        $decoded = json_decode($data, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $data;
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Handle the verify now operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_now(): float
{
    return microtime(true);
}

/**
 * Handle the verify elapsed operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_elapsed(float $start): float
{
    return microtime(true) - $start;
}

/**
 * Handle the verify format duration operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_format_duration(float $seconds): string
{
    if ($seconds < 1) {
        return number_format($seconds * 1000, 0) . ' ms';
    }

    return number_format($seconds, 2) . ' s';
}

/**
 * Handle the verify client operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_client(string $baseUri, CookieJar $jar): Client
{
    return new Client([
        'base_uri' => rtrim($baseUri, '/') . '/',
        'http_errors' => false,
        'allow_redirects' => false,
        'cookies' => $jar,
        'timeout' => 30,
        'headers' => [
            'User-Agent' => 'job-verifier/1.0 (+https://job.smeird.com)',
            'Accept' => 'application/json, text/plain, */*',
        ],
    ]);
}

/**
 * Handle the verify assert status operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_assert_status(ResponseInterface $response, int $expected, string $label = ''): void
{
    if ($response->getStatusCode() !== $expected) {
        $body = (string) $response->getBody();
        $prefix = $label ? $label . ': ' : '';
        throw new RuntimeException($prefix . 'Expected status ' . $expected . ' got ' . $response->getStatusCode() . ' — ' . substr($body, 0, 500));
    }
}

/**
 * Handle the verify collect headers operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_collect_headers(ResponseInterface $response): array
{
    $headers = [];
    foreach ($response->getHeaders() as $name => $values) {
        $headers[strtolower($name)] = $values;
    }

    return $headers;
}

/**
 * Handle the verify header has operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_header_has(array $headers, string $name): bool
{
    return array_key_exists(strtolower($name), $headers);
}

/**
 * Handle the verify header value operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_header_value(array $headers, string $name): ?string
{
    $lower = strtolower($name);
    if (!array_key_exists($lower, $headers) || count($headers[$lower]) === 0) {
        return null;
    }

    return $headers[$lower][0];
}

/**
 * Handle the verify make cookie jar operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_make_cookie_jar(): CookieJar
{
    return new CookieJar();
}

/**
 * Handle the verify make fixture markdown operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_make_fixture_markdown(): array
{
    $content = <<<MD
# Sample Candidate

- Location: Remote
- Experience: 5 years in PHP and Slim Framework
- Highlights: Automated verification harness authoring
MD;

    return [
        'filename' => 'sample.md',
        'mime' => 'text/markdown',
        'contents' => $content,
    ];
}

/**
 * Handle the verify make fixture text operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_make_fixture_text(): array
{
    $content = <<<TXT
Senior Backend Engineer needed for AI-driven resume tailoring platform. Responsibilities include
building secure APIs, managing queue workers, and ensuring data retention policies are enforced.
TXT;

    return [
        'filename' => 'sample.txt',
        'mime' => 'text/plain',
        'contents' => $content,
    ];
}

/**
 * Handle the verify make fixture docx operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_make_fixture_docx(): array
{
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addTitle('Candidate Summary', 1);
    $section->addText('Expert in Slim 4, PHP 8.3, OpenAI integrations, and secure file handling.');
    $section->addTextBreak();
    $section->addListItem('Auth flows with passcodes');
    $section->addListItem('Streaming SSE clients');
    $section->addListItem('Retention governance');

    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $temp = fopen('php://temp', 'w+b');
    $writer->save($temp);
    rewind($temp);
    $contents = stream_get_contents($temp);
    fclose($temp);

    return [
        'filename' => 'sample.docx',
        'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'contents' => $contents,
    ];
}

/**
 * Handle the verify make fixture pdf operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_make_fixture_pdf(): array
{
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml('<h1>Verification PDF</h1><p>This is a generated PDF used for upload validation.</p>');
    $dompdf->setPaper('A4');
    $dompdf->render();
    $contents = $dompdf->output();

    return [
        'filename' => 'sample.pdf',
        'mime' => 'application/pdf',
        'contents' => $contents,
    ];
}

/**
 * Handle the verify make fixture oversize operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_make_fixture_oversize(): array
{
    $contents = random_bytes(1024) . str_repeat('A', 1024 * 1024 * 1 + 512 * 1024);

    return [
        'filename' => 'oversize.bin',
        'mime' => 'application/octet-stream',
        'contents' => $contents,
    ];
}

/**
 * Handle the verify table operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_table(array $rows): string
{
    $nameWidth = max(array_map(fn ($row) => strlen($row['name']), $rows));
    $resultWidth = max(array_map(fn ($row) => strlen($row['result']), $rows));

    $line = str_repeat('-', $nameWidth + $resultWidth + 35);
    $output = [];
    $output[] = sprintf("%-" . ($nameWidth + 2) . "s  %-" . ($resultWidth + 2) . "s  %s", 'Check', 'Result', 'Notes');
    $output[] = $line;
    foreach ($rows as $row) {
        $output[] = sprintf("%-" . ($nameWidth + 2) . "s  %-" . ($resultWidth + 2) . "s  %s", $row['name'], $row['result'], $row['notes']);
    }
    $output[] = $line;

    return implode(PHP_EOL, $output);
}

/**
 * Handle the verify prompt passcode operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_prompt_passcode(string $email): string
{
    fwrite(STDOUT, 'Enter passcode for ' . $email . ': ');
    $code = trim(fgets(STDIN));

    if ($code === '') {
        throw new RuntimeException('No passcode provided.');
    }

    return $code;
}

/**
 * Handle the verify fetch test passcode operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_fetch_test_passcode(Client $client, string $email): ?string
{
    try {
        $response = $client->get('test/last-passcode', [
            'query' => ['email' => $email],
        ]);
    } catch (GuzzleException) {
        return null;
    }

    if ($response->getStatusCode() !== 200) {
        return null;
    }

    $body = json_decode((string) $response->getBody(), true);
    if (!is_array($body)) {
        return null;
    }

    return $body['passcode'] ?? null;
}

/**
 * Handle the verify fetch db passcode operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_fetch_db_passcode(array $config, string $email): ?string
{
    $host = verify_env($config, 'DB_HOST');
    $dbname = verify_env($config, 'DB_NAME');
    $user = verify_env($config, 'DB_USER_RO');
    $pass = verify_env($config, 'DB_PASS_RO');

    if (!$host || !$dbname || !$user) {
        return null;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbname);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (Throwable) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT passcode FROM passcodes WHERE email = :email ORDER BY created_at DESC LIMIT 1');
    $stmt->execute(['email' => $email]);
    $code = $stmt->fetchColumn();

    return $code ?: null;
}

/**
 * Handle the verify sse stream operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_sse_stream(Client $client, string $path, int $timeoutSeconds): array
{
    $events = [];
    $start = verify_now();

    $response = $client->get($path, [
        'stream' => true,
        'headers' => ['Accept' => 'text/event-stream'],
    ]);

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('SSE endpoint returned status ' . $response->getStatusCode());
    }

    $body = $response->getBody();

    $buffer = '';
    while (!$body->eof()) {
        $buffer .= $body->read(1024);
        $lines = explode("\n", $buffer);
        $buffer = array_pop($lines);

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $eventName = trim(substr($line, 6));
                $events[] = ['event' => $eventName, 'data' => null];
                continue;
            }

            if (str_starts_with($line, 'data:')) {
                $data = trim(substr($line, 5));
                if (!empty($events)) {
                    $events[count($events) - 1]['data'] = $data;
                } else {
                    $events[] = ['event' => 'message', 'data' => $data];
                }
            }
        }

        if (verify_elapsed($start) > $timeoutSeconds) {
            break;
        }
    }

    return $events;
}

/**
 * Handle the verify zip has entry operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_zip_has_entry(string $binary, string $entry): bool
{
    $temp = tmpfile();
    fwrite($temp, $binary);
    $meta = stream_get_meta_data($temp);
    $filename = $meta['uri'];

    if (!class_exists('ZipArchive')) {
        fclose($temp);

        return false;
    }

    $zip = new ZipArchive();
    $result = $zip->open($filename);
    if ($result !== true) {
        fclose($temp);
        return false;
    }

    $has = $zip->locateName($entry) !== false;
    $zip->close();
    fclose($temp);

    return $has;
}

/**
 * Handle the verify append readme note operation.
 *
 * Documenting this helper clarifies its role within the wider workflow.
 */
function verify_append_readme_note(): void
{
    echo PHP_EOL;
    echo verify_color('How to run:', 'cyan') . PHP_EOL;
    echo '  composer verify' . PHP_EOL;
    echo 'This command executes the full production-safe verifier against the configured APP_URL.' . PHP_EOL;
    echo 'Inspect the PASS/FAIL table above to understand which subsystems succeeded.' . PHP_EOL;
}
