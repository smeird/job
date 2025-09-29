#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Dotenv {
    final class Dotenv
    {
        /**
         * Construct the object with its required dependencies.
         *
         * This ensures collaborating services are available for subsequent method calls.
         */
        private function __construct()
        {
        }

        /**
         * Create the immutable instance.
         *
         * This method standardises construction so other code can rely on it.
         */
        public static function createImmutable(string $path): self
        {
            return new self();
        }

        /**
         * Handle the safe load operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function safeLoad(): void
        {
            // No-op for smoke testing; environment variables are optional.
        }
    }
}

namespace Ramsey\Uuid {
    final class Uuid
    {
        /** @var string */
        private $value;

        /**
         * Construct the object with its required dependencies.
         *
         * This ensures collaborating services are available for subsequent method calls.
         */
        private function __construct(string $value)
        {
            $this->value = $value;
        }

        /**
         * Handle the uuid4 operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public static function uuid4(): self
        {
            return new self(bin2hex(random_bytes(16)));
        }

        /**
         * Handle the to string operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function toString(): string
        {
            return $this->value;
        }
    }
}

namespace League\CommonMark {
    final class RenderedContent
    {
        /** @var string */
        private $content;

        /**
         * Construct the object with its required dependencies.
         *
         * This ensures collaborating services are available for subsequent method calls.
         */
        public function __construct(string $content)
        {
            $this->content = $content;
        }

        /**
         * Retrieve the content.
         *
         * The helper centralises access to the content so callers stay tidy.
         */
        public function getContent(): string
        {
            return $this->content;
        }
    }

    final class CommonMarkConverter
    {
        /**
         * Construct the object with its required dependencies.
         *
         * This ensures collaborating services are available for subsequent method calls.
         */
        public function __construct(array $config = [])
        {
        }

        /**
         * Handle the convert operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
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

        /**
         * Convert the to html into the desired format.
         *
         * Having a dedicated converter isolates formatting concerns.
         */
        public function convertToHtml(string $markdown): RenderedContent
        {
            return $this->convert($markdown);
        }
    }
}

namespace Psr\Http\Message {
    interface StreamInterface
    {
        /**
         * Handle the to string operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function __toString(): string;

        /**
         * Handle the close operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function close(): void;

        /**
         * Handle the detach workflow.
         *
         * This helper keeps the detach logic centralised for clarity and reuse.
         *
         * @return resource|null
         */
        public function detach();

        /**
         * Retrieve the size.
         *
         * The helper centralises access to the size so callers stay tidy.
         */
        public function getSize(): ?int;

        /**
         * Handle the tell operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function tell(): int;

        /**
         * Handle the eof operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function eof(): bool;

        /**
         * Determine whether the seekable condition holds.
         *
         * Wrapping this check simplifies decision making for the caller.
         */
        public function isSeekable(): bool;

        /**
         * Handle the seek operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function seek(int $offset, int $whence = SEEK_SET): void;

        /**
         * Handle the rewind operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function rewind(): void;

        /**
         * Determine whether the writable condition holds.
         *
         * Wrapping this check simplifies decision making for the caller.
         */
        public function isWritable(): bool;

        /**
         * Handle the write operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function write(string $string): int;

        /**
         * Determine whether the readable condition holds.
         *
         * Wrapping this check simplifies decision making for the caller.
         */
        public function isReadable(): bool;

        /**
         * Handle the read operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function read(int $length): string;

        /**
         * Retrieve the contents.
         *
         * The helper centralises access to the contents so callers stay tidy.
         */
        public function getContents(): string;

        /**
         * Retrieve the metadata.
         *
         * The helper centralises access to the metadata so callers stay tidy.
         *
         * @param string|null $key
         * @return array<string, mixed>|mixed|null
         */
        public function getMetadata(?string $key = null);
    }

    interface UploadedFileInterface
    {
        /**
         * Retrieve the stream.
         *
         * The helper centralises access to the stream so callers stay tidy.
         */
        public function getStream(): StreamInterface;

        /**
         * Handle the move to operation.
         *
         * Documenting this helper clarifies its role within the wider workflow.
         */
        public function moveTo(string $targetPath): void;

        /**
         * Retrieve the size.
         *
         * The helper centralises access to the size so callers stay tidy.
         */
        public function getSize(): ?int;

        /**
         * Retrieve the error.
         *
         * The helper centralises access to the error so callers stay tidy.
         */
        public function getError(): int;

        /**
         * Retrieve the client filename.
         *
         * The helper centralises access to the client filename so callers stay tidy.
         */
        public function getClientFilename(): ?string;

        /**
         * Retrieve the client media type.
         *
         * The helper centralises access to the client media type so callers stay tidy.
         */
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

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct($resource)
    {
        if (!is_resource($resource)) {
            throw new RuntimeException('Invalid stream resource.');
        }

        $this->resource = $resource;
    }

    /**
     * Handle the to string operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Handle the close operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    /**
     * Handle the detach operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }

    /**
     * Retrieve the size.
     *
     * The helper centralises access to the size so callers stay tidy.
     */
    public function getSize(): ?int
    {
        $stats = $this->getMetadata();

        return isset($stats['uri']) && is_file($stats['uri']) ? filesize($stats['uri']) ?: null : null;
    }

    /**
     * Handle the tell operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function tell(): int
    {
        $position = ftell($this->resource);

        if ($position === false) {
            throw new RuntimeException('Unable to determine stream position.');
        }

        return $position;
    }

    /**
     * Handle the eof operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function eof(): bool
    {
        return feof($this->resource);
    }

    /**
     * Determine whether the seekable condition holds.
     *
     * Wrapping this check simplifies decision making for the caller.
     */
    public function isSeekable(): bool
    {
        $meta = $this->getMetadata();

        return (bool) ($meta['seekable'] ?? false);
    }

    /**
     * Handle the seek operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (fseek($this->resource, $offset, $whence) !== 0) {
            throw new RuntimeException('Unable to seek stream.');
        }
    }

    /**
     * Handle the rewind operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * Determine whether the writable condition holds.
     *
     * Wrapping this check simplifies decision making for the caller.
     */
    public function isWritable(): bool
    {
        $mode = $this->getMetadata('mode');

        return $mode !== null && strpbrk($mode, 'waxc+') !== false;
    }

    /**
     * Handle the write operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Determine whether the readable condition holds.
     *
     * Wrapping this check simplifies decision making for the caller.
     */
    public function isReadable(): bool
    {
        $mode = $this->getMetadata('mode');

        return $mode !== null && strpbrk($mode, 'r+') !== false;
    }

    /**
     * Handle the read operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function read(int $length): string
    {
        $data = fread($this->resource, $length);

        if ($data === false) {
            throw new RuntimeException('Unable to read from stream.');
        }

        return $data;
    }

    /**
     * Retrieve the contents.
     *
     * The helper centralises access to the contents so callers stay tidy.
     */
    public function getContents(): string
    {
        $data = stream_get_contents($this->resource);

        if ($data === false) {
            throw new RuntimeException('Unable to read stream contents.');
        }

        return $data;
    }

    /**
     * Retrieve the metadata.
     *
     * The helper centralises access to the metadata so callers stay tidy.
     */
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
    /** @var SmokeStream */
    private $stream;

    /** @var string|null */
    private $clientFilename;

    /** @var string|null */
    private $clientMediaType;

    /** @var int */
    private $error;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(SmokeStream $stream, ?string $clientFilename, ?string $clientMediaType, int $error = UPLOAD_ERR_OK)
    {
        $this->stream = $stream;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
        $this->error = $error;
    }

    /**
     * Handle the from string operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Retrieve the stream.
     *
     * The helper centralises access to the stream so callers stay tidy.
     */
    public function getStream(): StreamInterface
    {
        return $this->stream;
    }

    /**
     * Handle the move to operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function moveTo(string $targetPath): void
    {
        file_put_contents($targetPath, (string) $this->stream);
    }

    /**
     * Retrieve the size.
     *
     * The helper centralises access to the size so callers stay tidy.
     */
    public function getSize(): ?int
    {
        return $this->stream->getSize();
    }

    /**
     * Retrieve the error.
     *
     * The helper centralises access to the error so callers stay tidy.
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Retrieve the client filename.
     *
     * The helper centralises access to the client filename so callers stay tidy.
     */
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * Retrieve the client media type.
     *
     * The helper centralises access to the client media type so callers stay tidy.
     */
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}

final class SmokeEnvironment
{
    /** @var string */
    private $databasePath;

    /** @var string */
    private $rootPath;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        $this->databasePath = $this->rootPath . '/database/smoke.sqlite';
    }

    /**
     * Handle the bootstrap operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Handle the path operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function path(): string
    {
        return $this->databasePath;
    }
}

final class SmokeSchema
{
    /** @var GlobalPDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Handle the migrate operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function migrate(): void
    {
        $this->pdo->setAttribute(GlobalPDO::ATTR_ERRMODE, GlobalPDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            totp_secret TEXT NULL,
            totp_period_seconds INTEGER NULL,
            totp_digits INTEGER NULL,
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
            totp_secret TEXT NULL,
            period_seconds INTEGER NOT NULL DEFAULT 600,
            digits INTEGER NOT NULL DEFAULT 6,
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
    /** @var AuthService */
    private $service;

    /** @var GlobalPDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
        $auditLogger = new AuditLogger($pdo);
        $requestLimiter = new RateLimiter($pdo, $auditLogger, 10, new GlobalDateInterval('PT15M'));
        $verifyLimiter = new RateLimiter($pdo, $auditLogger, 10, new GlobalDateInterval('PT15M'));
        $this->service = new AuthService($pdo, $requestLimiter, $verifyLimiter, $auditLogger);
    }

    /**
     * Handle the run operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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
    /** @var GlobalPDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Handle the primary workflow for this component.
     *
     * Grouping the core workflow here keeps the surrounding code expressive and simple.
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
    /** @var GlobalPDO */
    private $pdo;

    /** @var int */
    private $userId;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(int $userId, ?object $client = null, ?GlobalPDO $pdo = null)
    {
        $this->userId = $userId;
        $this->pdo = $pdo ?? DB::getConnection();
    }

    /**
     * Handle the plan operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Handle the draft operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Handle the record usage operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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
    /** @var GlobalPDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Handle the primary workflow for this component.
     *
     * Grouping the core workflow here keeps the surrounding code expressive and simple.
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
            'model' => 'gpt-5-mini',
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
    /** @var GlobalPDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(GlobalPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Handle the seed operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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

    /**
     * Handle the run operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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
