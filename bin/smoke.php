#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Dotenv {
    final class Dotenv
    {
        private function __construct()
        {
        }

        public static function createImmutable(string $path): self
        {
            return new self();
        }

        public function safeLoad(): void
        {
            // No-op for smoke testing; environment variables are optional.
        }
    }
}

namespace Ramsey\Uuid {
    final class Uuid
    {
        private string $value;

        private function __construct(string $value)
        {
            $this->value = $value;
        }

        public static function uuid4(): self
        {
            return new self(bin2hex(random_bytes(16)));
        }

        public function toString(): string
        {
            return $this->value;
        }
    }
}

namespace League\CommonMark {
    final class RenderedContent
    {
        private string $content;

        public function __construct(string $content)
        {
            $this->content = $content;
        }

        public function getContent(): string
        {
            return $this->content;
        }
    }

    final class CommonMarkConverter
    {
        public function __construct(array $config = [])
        {
        }

        public function convert(string $markdown): RenderedContent
        {
            $escaped = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $escaped = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $escaped);
            $escaped = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $escaped);
            $escaped = preg_replace('/^\* (.*)$/m', '<li>$1</li>', $escaped);
            $escaped = str_replace("\n\n", '</p><p>', $escaped);
            $html = '<p>' . str_replace("\n", '<br>', $escaped) . '</p>';

            return new RenderedContent($html);
        }

        public function convertToHtml(string $markdown): RenderedContent
        {
            return $this->convert($markdown);
        }
    }
}

namespace Psr\Http\Message {
    interface StreamInterface
    {
        public function __toString(): string;

        public function close(): void;

        /** @return resource|null */
        public function detach();

        public function getSize(): ?int;

        public function tell(): int;

        public function eof(): bool;

        public function isSeekable(): bool;

        public function seek(int $offset, int $whence = SEEK_SET): void;

        public function rewind(): void;

        public function isWritable(): bool;

        public function write(string $string): int;

        public function isReadable(): bool;

        public function read(int $length): string;

        public function getContents(): string;

        /** @return array<string, mixed>|mixed|null */
        public function getMetadata(?string $key = null);
    }

    interface UploadedFileInterface
    {
        public function getStream(): StreamInterface;

        public function moveTo(string $targetPath): void;

        public function getSize(): ?int;

        public function getError(): int;

        public function getClientFilename(): ?string;

        public function getClientMediaType(): ?string;
    }
}

namespace {

use App\DB;
use App\Documents\DocumentRepository;
use App\Documents\DocumentService;
use App\Documents\DocumentValidator;
use App\Extraction\Extractor;
use App\Generations\GenerationDownloadService;
use App\Queue\Handler\TailorCvJobHandler;
use App\Queue\Job;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\RateLimiter;
use App\Services\RetentionPolicyService;
use DateInterval as GlobalDateInterval;
use DateTimeImmutable as GlobalDateTimeImmutable;
use Dotenv\Dotenv;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\RenderedContent;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;
use PDO as GlobalPDO;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, 4)) . '.php';

        if (is_file($path)) {
            require $path;
        }
    }
});

if (!defined('PASSWORD_ARGON2ID')) {
    define('PASSWORD_ARGON2ID', PASSWORD_BCRYPT);
}

final class SmokeStream implements StreamInterface
{
    /** @var resource */
    private $resource;

    public function __construct($resource)
    {
        if (!is_resource($resource)) {
            throw new RuntimeException('Invalid stream resource.');
        }

        $this->resource = $resource;
    }

    public function __toString(): string
    {
        try {
            if (!$this->isReadable()) {
                return '';
            }

            $this->rewind();

            return stream_get_contents($this->resource) ?: '';
        } catch (Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }

    public function getSize(): ?int
    {
        $stats = $this->getMetadata();

        return isset($stats['uri']) && is_file($stats['uri']) ? filesize($stats['uri']) ?: null : null;
    }

    public function tell(): int
    {
        $position = ftell($this->resource);

        if ($position === false) {
            throw new RuntimeException('Unable to determine stream position.');
        }

        return $position;
    }

    public function eof(): bool
    {
        return feof($this->resource);
    }

    public function isSeekable(): bool
    {
        $meta = $this->getMetadata();

        return (bool) ($meta['seekable'] ?? false);
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (fseek($this->resource, $offset, $whence) !== 0) {
            throw new RuntimeException('Unable to seek stream.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        $mode = $this->getMetadata('mode');

        return $mode !== null && strpbrk($mode, 'waxc+') !== false;
    }

    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }

        $written = fwrite($this->resource, $string);

        if ($written === false) {
            throw new RuntimeException('Failed to write to stream.');
        }

        return $written;
    }

    public function isReadable(): bool
    {
        $mode = $this->getMetadata('mode');

        return $mode !== null && strpbrk($mode, 'r+') !== false;
    }

    public function read(int $length): string
    {
        $data = fread($this->resource, $length);

        if ($data === false) {
            throw new RuntimeException('Unable to read from stream.');
        }

        return $data;
    }

    public function getContents(): string
    {
        $data = stream_get_contents($this->resource);

        if ($data === false) {
            throw new RuntimeException('Unable to read stream contents.');
        }

        return $data;
    }

    public function getMetadata(?string $key = null)
    {
        $meta = stream_get_meta_data($this->resource);

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}

final class SmokeUploadedFile implements UploadedFileInterface
{
    private SmokeStream $stream;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private int $error;

    public function __construct(SmokeStream $stream, ?string $clientFilename, ?string $clientMediaType, int $error = UPLOAD_ERR_OK)
    {
        $this->stream = $stream;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
        $this->error = $error;
    }

    public static function fromString(string $filename, string $mime, string $contents): self
    {
        $resource = fopen('php://temp', 'r+');

        if ($resource === false) {
            throw new RuntimeException('Unable to create temporary stream.');
        }

        fwrite($resource, $contents);
        rewind($resource);

        return new self(new SmokeStream($resource), $filename, $mime);
    }

    public function getStream(): StreamInterface
    {
        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        file_put_contents($targetPath, (string) $this->stream);
    }

    public function getSize(): ?int
    {
        return $this->stream->getSize();
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}

final class SmokeEnvironment
{
    private string $databasePath;
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        $this->databasePath = $this->rootPath . '/database/smoke.sqlite';
    }

    public function bootstrap(): void
    {
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        putenv('DB_DSN=sqlite:' . $this->databasePath);
        $_ENV['DB_DSN'] = 'sqlite:' . $this->databasePath;
        $_SERVER['DB_DSN'] = 'sqlite:' . $this->databasePath;

        if (is_file($this->rootPath . '/.env')) {
            Dotenv::createImmutable($this->rootPath)->safeLoad();
        }
    }

    public function path(): string
    {
        return $this->databasePath;
    }
}

final class SmokeSchema
{
    private GlobalPDO $pdo;

    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function migrate(): void
    {
        $this->pdo->setAttribute(GlobalPDO::ATTR_ERRMODE, GlobalPDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            name TEXT NULL,
            status TEXT NOT NULL DEFAULT "active",
            last_login_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS pending_passcodes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            action TEXT NOT NULL,
            code_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash BLOB NOT NULL,
            created_at TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS backup_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            code_hash TEXT NOT NULL,
            consumed_at TEXT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            email TEXT NULL,
            action TEXT NOT NULL,
            ip_address TEXT NULL,
            user_agent TEXT NULL,
            details TEXT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            size_bytes INTEGER NOT NULL,
            sha256 TEXT NOT NULL UNIQUE,
            content BLOB NOT NULL,
            extracted_text TEXT NULL,
            created_at TEXT NOT NULL
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS generations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            document_id INTEGER NULL,
            model TEXT NOT NULL,
            prompt TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            progress_percent INTEGER NOT NULL DEFAULT 0,
            cost_pence INTEGER NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE SET NULL
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS generation_outputs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            generation_id INTEGER NOT NULL,
            mime_type TEXT NULL,
            content BLOB NULL,
            output_text TEXT NULL,
            tokens_used INTEGER NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(generation_id) REFERENCES generations(id) ON DELETE CASCADE
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS api_usage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            provider TEXT NOT NULL,
            endpoint TEXT NOT NULL,
            tokens_used INTEGER NULL,
            cost_pence INTEGER NOT NULL DEFAULT 0,
            metadata TEXT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS retention_settings (
            id INTEGER PRIMARY KEY,
            purge_after_days INTEGER NOT NULL,
            apply_to TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            payload_json TEXT NOT NULL,
            run_after TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "pending",
            error TEXT NULL,
            created_at TEXT NOT NULL
        )');
    }
}

final class SmokeAuth
{
    private AuthService $service;

    private GlobalPDO $pdo;

    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
        $auditLogger = new AuditLogger($pdo);
        $requestLimiter = new RateLimiter($pdo, $auditLogger, 10, new GlobalDateInterval('PT15M'));
        $verifyLimiter = new RateLimiter($pdo, $auditLogger, 10, new GlobalDateInterval('PT15M'));
        $this->service = new AuthService($pdo, $requestLimiter, $verifyLimiter, $auditLogger);
    }

    public function run(): array
    {
        $email = 'smoke@example.com';
        $ip = '127.0.0.1';
        $agent = 'smoke-suite';

        $registration = $this->service->initiateRegistration($email, $ip, $agent);
        $registrationCode = $registration['code'];
        $session = $this->service->verifyRegistration($email, $registrationCode, $ip, $agent);

        $login = $this->service->initiateLogin($email, $ip, $agent);
        $loginCode = $login['code'];
        $this->service->verifyLogin($email, $loginCode, $ip, $agent);

        $statement = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $userId = (int) $statement->fetchColumn();

        $session['user_id'] = $userId;

        return $session;
    }
}

final class SmokeDocuments
{
    private GlobalPDO $pdo;

    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{document_id: int, extracted: string}
     */
    public function run(int $userId): array
    {
        $documentService = new DocumentService(new DocumentRepository($this->pdo), new DocumentValidator());
        $content = "# Sample CV\n\n* Results-driven engineer\n";
        $uploaded = SmokeUploadedFile::fromString('cv.md', 'text/markdown', $content);
        $document = $documentService->storeUploadedDocument($uploaded, $userId, 'cv');

        $tempFile = tempnam(sys_get_temp_dir(), 'smoke-cv-');

        if ($tempFile === false) {
            throw new RuntimeException('Unable to create temporary file for extractor.');
        }

        file_put_contents($tempFile, $content);

        $extractor = new Extractor($this->pdo);
        $extractor->handleUpload($document->id(), $tempFile, 'cv.md', 'text/markdown');
        unlink($tempFile);

        $statement = $this->pdo->prepare('SELECT extracted_text FROM documents WHERE id = :id');
        $statement->execute(['id' => $document->id()]);
        $extracted = (string) $statement->fetchColumn();

        return [
            'document_id' => $document->id(),
            'extracted' => $extracted,
        ];
    }
}

final class SmokeFakeOpenAIProvider
{
    private GlobalPDO $pdo;

    private int $userId;

    public function __construct(int $userId, ?object $client = null, ?GlobalPDO $pdo = null)
    {
        $this->userId = $userId;
        $this->pdo = $pdo ?? DB::getConnection();
    }

    public function plan(string $jobText, string $cvText, ?callable $streamHandler = null): string
    {
        $plan = [
            'summary' => 'Align CV achievements with the role focus.',
            'strengths' => ['Experience extracted: ' . mb_substr($cvText, 0, 32)],
            'gaps' => ['Highlight leadership metrics for: ' . mb_substr($jobText, 0, 32)],
            'next_steps' => [
                ['task' => 'Refine professional summary', 'rationale' => 'Match automation focus', 'priority' => 'high', 'estimated_minutes' => 30],
                ['task' => 'Quantify outcomes', 'rationale' => 'Show measurable wins', 'priority' => 'medium', 'estimated_minutes' => 20],
            ],
        ];

        $this->recordUsage('plan');

        return json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function draft(string $plan, string $constraints, ?callable $streamHandler = null): string
    {
        $this->recordUsage('draft');

        return <<<MARKDOWN
## Tailored Summary

- Utilise automation leadership to deliver cross-regional impact.
- Integrate metrics from prior roles to evidence success.

### Next steps
1. Sync accomplishments with plan items.
2. Keep tone confident and concise.
MARKDOWN;
    }

    private function recordUsage(string $operation): void
    {
        $statement = $this->pdo->prepare('INSERT INTO api_usage (user_id, provider, endpoint, tokens_used, cost_pence, metadata, created_at) VALUES (:user_id, :provider, :endpoint, :tokens_used, :cost_pence, :metadata, :created_at)');
        $statement->execute([
            'user_id' => $this->userId,
            'provider' => 'openai-smoke',
            'endpoint' => $operation,
            'tokens_used' => 100,
            'cost_pence' => 5,
            'metadata' => json_encode(['operation' => $operation], JSON_THROW_ON_ERROR),
            'created_at' => (new GlobalDateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}

final class SmokeGeneration
{
    private GlobalPDO $pdo;

    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array{document_id: int, extracted: string} $document
     * @return array{generation_id: int, downloads: array<string, int>}
     */
    public function run(array $document, int $userId): array
    {
        $now = (new GlobalDateTimeImmutable())->format('Y-m-d H:i:s');
        $payload = [
            'generation_id' => null,
            'user_id' => $userId,
            'job_description' => 'Deliver impactful automation projects across EMEA.',
            'cv_markdown' => $document['extracted'],
            'job_title' => 'Automation Lead',
            'company' => 'Smeird Corp',
            'competencies' => ['Process optimisation', 'Leadership'],
        ];

        $insertGeneration = $this->pdo->prepare('INSERT INTO generations (user_id, document_id, model, prompt, status, progress_percent, cost_pence, created_at, updated_at) VALUES (:user_id, :document_id, :model, :prompt, :status, 0, 0, :created_at, :updated_at)');
        $insertGeneration->execute([
            'user_id' => $userId,
            'document_id' => $document['document_id'],
            'model' => 'gpt-4o-mini',
            'prompt' => 'Tailor CV',
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $generationId = (int) $this->pdo->lastInsertId();
        $payload['generation_id'] = $generationId;

        $insertJob = $this->pdo->prepare('INSERT INTO jobs (type, payload_json, run_after, attempts, status, created_at) VALUES (:type, :payload_json, :run_after, 0, :status, :created_at)');
        $insertJob->execute([
            'type' => 'tailor_cv',
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'run_after' => $now,
            'status' => 'pending',
            'created_at' => $now,
        ]);

        $statement = $this->pdo->query('SELECT id, type, payload_json, run_after, attempts, status FROM jobs ORDER BY id ASC LIMIT 1');
        $row = $statement === false ? false : $statement->fetch();

        if ($row === false) {
            throw new RuntimeException('Unable to load queued smoke job.');
        }

        $jobPayload = json_decode((string) $row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
        $job = new Job(
            (int) $row['id'],
            (string) $row['type'],
            $jobPayload,
            (int) $row['attempts'],
            (string) $row['status'],
            new GlobalDateTimeImmutable((string) $row['run_after'])
        );

        $job->incrementAttempts();
        $handler = new TailorCvJobHandler($this->pdo);

        try {
            $handler->handle($job);
            $this->pdo->prepare('UPDATE jobs SET status = :status, attempts = :attempts, error = NULL WHERE id = :id')->execute([
                'status' => 'completed',
                'attempts' => $job->attempts(),
                'id' => $job->id,
            ]);
        } catch (Throwable $exception) {
            $handler->onFailure($job, $exception->getMessage(), false);
            $this->pdo->prepare('UPDATE jobs SET status = :status, attempts = :attempts, error = :error WHERE id = :id')->execute([
                'status' => 'failed',
                'attempts' => $job->attempts(),
                'error' => $exception->getMessage(),
                'id' => $job->id,
            ]);

            $rootCause = $exception->getPrevious();
            $message = $rootCause instanceof Throwable ? $rootCause->getMessage() : $exception->getMessage();

            throw new RuntimeException('Smoke job processing failed: ' . $message, 0, $exception);
        }

        $this->pdo->prepare('INSERT INTO generation_outputs (generation_id, mime_type, content, output_text, created_at) VALUES (:generation_id, :mime_type, :content, :output_text, :created_at)')->execute([
            'generation_id' => $generationId,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'content' => 'docx-binary-placeholder',
            'output_text' => null,
            'created_at' => $now,
        ]);

        $this->pdo->prepare('INSERT INTO generation_outputs (generation_id, mime_type, content, output_text, created_at) VALUES (:generation_id, :mime_type, :content, :output_text, :created_at)')->execute([
            'generation_id' => $generationId,
            'mime_type' => 'application/pdf',
            'content' => 'pdf-binary-placeholder',
            'output_text' => null,
            'created_at' => $now,
        ]);

        $downloadService = new GenerationDownloadService($this->pdo);
        $downloadService->fetch($generationId, $userId, 'md');
        $downloadService->fetch($generationId, $userId, 'docx');
        $downloadService->fetch($generationId, $userId, 'pdf');

        return [
            'generation_id' => $generationId,
            'downloads' => [
                'md' => 1,
                'docx' => 1,
                'pdf' => 1,
            ],
        ];
    }
}

final class SmokePurge
{
    private GlobalPDO $pdo;

    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function seed(): void
    {
        $old = (new GlobalDateTimeImmutable('-10 days'))->format('Y-m-d H:i:s');

        $this->pdo->exec("INSERT INTO documents (filename, mime_type, size_bytes, sha256, content, extracted_text, created_at) VALUES ('old.txt', 'text/plain', 12, 'hash-old', 'old', 'old', '$old')");
        $this->pdo->exec("INSERT INTO generation_outputs (generation_id, mime_type, content, output_text, tokens_used, created_at) VALUES (1, 'text/plain', 'legacy', 'legacy', 0, '$old')");
        $this->pdo->exec("INSERT INTO api_usage (user_id, provider, endpoint, tokens_used, cost_pence, metadata, created_at) VALUES (1, 'openai', '/chat/completions', 10, 1, '{}', '$old')");
        $this->pdo->exec("INSERT INTO audit_logs (user_id, email, action, ip_address, user_agent, details, created_at) VALUES (1, 'smoke@example.com', 'test', '127.0.0.1', 'smoke', '{}', '$old')");

        $service = new RetentionPolicyService($this->pdo);
        $service->updatePolicy(7, ['documents', 'generation_outputs', 'api_usage', 'audit_logs']);
    }

    public function run(): void
    {
        $service = new RetentionPolicyService($this->pdo);
        $policy = $service->getPolicy();

        $purgeAfterDays = (int) $policy['purge_after_days'];
        $applyTo = $policy['apply_to'];

        if ($purgeAfterDays < 1 || !is_array($applyTo) || $applyTo === []) {
            return;
        }

        $cutoff = (new GlobalDateTimeImmutable('now'))->sub(new GlobalDateInterval('P' . $purgeAfterDays . 'D'))->format('Y-m-d H:i:s');
        $resources = [
            'documents' => ['table' => 'documents', 'column' => 'created_at'],
            'generation_outputs' => ['table' => 'generation_outputs', 'column' => 'created_at'],
            'api_usage' => ['table' => 'api_usage', 'column' => 'created_at'],
            'audit_logs' => ['table' => 'audit_logs', 'column' => 'created_at'],
        ];

        foreach ($applyTo as $resource) {
            if (!isset($resources[$resource])) {
                continue;
            }

            $table = $resources[$resource]['table'];
            $column = $resources[$resource]['column'];
            $statement = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE %s < :cutoff', $table, $column));
            $statement->execute(['cutoff' => $cutoff]);
        }
    }
}

if (!class_exists('App\\AI\\OpenAIProvider', false)) {
    class_alias(SmokeFakeOpenAIProvider::class, 'App\\AI\\OpenAIProvider');
}

try {
    $root = dirname(__DIR__);
    $environment = new SmokeEnvironment($root);
    $environment->bootstrap();

    $pdo = DB::getConnection();
    $schema = new SmokeSchema($pdo);
    $schema->migrate();
    echo "✔ Database migrated to smoke schema\n";

    $auth = new SmokeAuth($pdo);
    $session = $auth->run();
    echo "✔ Authentication flow completed (session expires {$session['expires_at']->format('c')})\n";

    $documents = new SmokeDocuments($pdo);
    $documentResult = $documents->run($session['user_id']);
    echo "✔ Document uploaded and extracted ({$documentResult['extracted']})\n";

    $userId = $session['user_id'];
    $generation = new SmokeGeneration($pdo);
    $generationResult = $generation->run($documentResult, $userId);
    echo "✔ Generation job processed (ID {$generationResult['generation_id']})\n";

    $purge = new SmokePurge($pdo);
    $purge->seed();
    $purge->run();
    echo "✔ Retention purge executed\n";

    echo "Smoke suite completed successfully. Database stored at {$environment->path()}\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Smoke suite failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

}
