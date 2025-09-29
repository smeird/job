<?php

declare(strict_types=1);

namespace App\Queue\Handler;

use App\AI\OpenAIProvider;
use App\Prompts\PromptLibrary;
use App\Queue\Job;
use App\Queue\JobHandlerInterface;
use App\Queue\TransientJobException;
use DateTimeImmutable;
use League\CommonMark\CommonMarkConverter;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function is_array;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function mb_substr;
use function sprintf;
use function strip_tags;
use function trim;

final class TailorCvJobHandler implements JobHandlerInterface
{
    /** @var CommonMarkConverter */
    private $markdownConverter;

    /** @var PDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->markdownConverter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Handle the queued job execution workflow.
     *
     * Centralising job handling logic makes worker behaviour predictable and easy to audit.
     */
    public function handle(Job $job): void
    {
        $payload = $job->payload();
        $generationId = $this->extractInt($payload, 'generation_id');
        $userId = $this->extractInt($payload, 'user_id');
        $jobDescription = $this->extractString($payload, 'job_description');
        $cvMarkdown = $this->extractString($payload, 'cv_markdown');

        $this->updateGenerationStatus($generationId, 'processing');

        $provider = new OpenAIProvider($userId, null, $this->pdo);

        $plan = $this->generatePlan($provider, $jobDescription, $cvMarkdown);
        $constraints = $this->buildConstraints($payload, $cvMarkdown);
        $draft = $this->generateDraft($provider, $plan, $constraints);
        $converted = $this->convertDraft($draft);

        $this->persistOutputs($generationId, $plan, $draft, $converted['html'], $converted['text']);
        $this->updateGenerationStatus($generationId, 'completed');
    }

    /**
     * Handle the on failure operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function onFailure(Job $job, string $error, bool $willRetry): void
    {
        $payload = $job->payload();
        $generationId = isset($payload['generation_id']) ? (int) $payload['generation_id'] : null;

        if ($generationId === null || $generationId <= 0) {
            return;
        }

        if ($willRetry) {
            $this->updateGenerationStatus($generationId, 'processing');

            return;
        }

        $context = is_array($payload) ? $payload : [];

        $this->updateGenerationStatus($generationId, 'failed', $error, $context);
    }

    /**
     * Handle the generate plan operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function generatePlan(OpenAIProvider $provider, string $jobDescription, string $cvMarkdown): string
    {
        try {
            return $provider->plan($jobDescription, $cvMarkdown);
        } catch (Throwable $exception) {
            throw new TransientJobException('Failed to generate tailoring plan: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Handle the generate draft operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function generateDraft(OpenAIProvider $provider, string $plan, string $constraints): string
    {
        try {
            return $provider->draft($plan, $constraints);
        } catch (Throwable $exception) {
            throw new TransientJobException('Failed to generate tailored draft: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Convert the draft into the desired format.
     *
     * Having a dedicated converter isolates formatting concerns.
     */
    private function convertDraft(string $draft): array
    {
        try {
            if (method_exists($this->markdownConverter, 'convertToHtml')) {
                $converted = $this->markdownConverter->convertToHtml($draft);
            } else {
                $converted = $this->markdownConverter->convert($draft);
            }

            $html = is_object($converted) && method_exists($converted, 'getContent')
                ? $converted->getContent()
                : (string) $converted;
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to convert draft markdown into HTML.', 0, $exception);
        }

        $text = trim(strip_tags($html));

        return [
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * Handle the persist outputs operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function persistOutputs(int $generationId, string $plan, string $draft, string $html, string $plainText): void
    {
        try {
            $this->pdo->beginTransaction();

            $delete = $this->pdo->prepare('DELETE FROM generation_outputs WHERE generation_id = :generation_id');
            $delete->execute([':generation_id' => $generationId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO generation_outputs (generation_id, mime_type, content, output_text) '
                . 'VALUES (:generation_id, :mime_type, :content, :output_text)'
            );

            $insert->execute([
                ':generation_id' => $generationId,
                ':mime_type' => 'application/json',
                ':content' => null,
                ':output_text' => $plan,
            ]);

            $insert->execute([
                ':generation_id' => $generationId,
                ':mime_type' => 'text/markdown',
                ':content' => null,
                ':output_text' => $draft,
            ]);

            $insert->execute([
                ':generation_id' => $generationId,
                ':mime_type' => 'text/html',
                ':content' => null,
                ':output_text' => $html,
            ]);

            $insert->execute([
                ':generation_id' => $generationId,
                ':mime_type' => 'text/plain',
                ':content' => null,
                ':output_text' => $plainText,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException('Failed to persist generation outputs.', 0, $exception);
        }
    }

    /**
     * Build the constraints representation.
     *
     * Centralised construction avoids duplicating structural knowledge elsewhere.
     */
    private function buildConstraints(array $payload, string $cvMarkdown): string
    {
        $templateValue = isset($payload['prompt']) ? trim((string) $payload['prompt']) : '';
        $template = $templateValue !== '' ? $templateValue : PromptLibrary::tailorPrompt();
        $jobTitle = $this->optionalString($payload, 'job_title');
        $company = $this->optionalString($payload, 'company');
        $competencies = $payload['competencies'] ?? [];

        if (is_array($competencies)) {
            $cleaned = array_map(
                static function ($value): string {
                    return trim((string) $value);
                },
                $competencies
            );
            $cleaned = array_values(array_filter(
                $cleaned,
                static function (string $value): bool {
                    return $value !== '';
                }
            ));
            $competencyList = implode(', ', $cleaned);
        } else {
            $competencyList = trim((string) $competencies);
        }

        $cvSections = $this->optionalString($payload, 'cv_sections');
        $cvSections = $cvSections !== '' ? $cvSections : $cvMarkdown;

        return strtr($template, [
            '{{title}}' => $jobTitle !== '' ? $jobTitle : 'Not specified',
            '{{company}}' => $company !== '' ? $company : 'Not specified',
            '{{competencies}}' => $competencyList !== '' ? $competencyList : 'Not specified',
            '{{cv_sections}}' => $cvSections,
        ]);
    }

    /**
     * Handle the update generation status operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function updateGenerationStatus(
        int $generationId,
        string $status,
        ?string $error = null,
        array $context = []
    ): void {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE generations SET status = :status, updated_at = :updated_at WHERE id = :id'
            );

            $statement->execute([
                ':status' => $status,
                ':updated_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ':id' => $generationId,
            ]);

            if ($error !== null && $status === 'failed') {
                $this->recordFailure($generationId, $error, $context);
            }
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to update generation status.', 0, $exception);
        }
    }

    /**
     * Handle the record failure operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function recordFailure(int $generationId, string $error, array $context): void
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO audit_logs (user_id, ip_address, email, action, user_agent, details, created_at) '
                . 'SELECT user_id, :ip_address, NULL, :action, :user_agent, :details, :created_at '
                . 'FROM generations WHERE id = :generation_id'
            );

            $statement->execute([
                ':ip_address' => '127.0.0.1',
                ':action' => 'generation_failed',
                ':user_agent' => 'queue-worker',
                ':details' => $this->encodeFailureDetails($generationId, $error, $context),
                ':created_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ':generation_id' => $generationId,
            ]);
        } catch (Throwable $throwable) {
            // Swallow logging errors to avoid masking the original failure.
        }
    }

    /**
     * Safely encode the failure details into JSON for audit logging.
     *
     * Using a compatibility layer means the handler continues to operate on platforms
     * where JSON_THROW_ON_ERROR might not be available while still surfacing
     * encoding problems in a predictable way.
     */
    private function encodeFailureDetails(int $generationId, string $error, array $context): string
    {
        $payload = $this->buildFailureContext($generationId, $error, $context);

        if (\defined('JSON_THROW_ON_ERROR')) {
            return (string) json_encode($payload, \constant('JSON_THROW_ON_ERROR'));
        }

        $encoded = json_encode($payload);

        if ($encoded === false || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to encode failure details: ' . json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * Build a structured failure context payload for auditing.
     *
     * Creating a single normaliser keeps log detail consistent and ensures sensitive
     * document content is trimmed before it is written to the database.
     *
     * @param array<string, mixed> $context The raw job payload used when the failure occurred.
     * @return array<string, mixed> A condensed representation suitable for persistence.
     */
    private function buildFailureContext(int $generationId, string $error, array $context): array
    {
        return [
            'generation_id' => $generationId,
            'error' => $error,
            'payload' => $this->summarisePayload($context),
        ];
    }

    /**
     * Summarise the payload information for logging purposes.
     *
     * The helper trims large fields and only exposes identifying metadata so the
     * audit trail remains helpful without storing entire documents.
     *
     * @param array<string, mixed> $context The raw job payload used when the failure occurred.
     * @return array<string, mixed> Reduced payload data for diagnostic logging.
     */
    private function summarisePayload(array $context): array
    {
        $summary = [];

        if (isset($context['job_document_id'])) {
            $summary['job_document_id'] = (int) $context['job_document_id'];
        }

        if (isset($context['cv_document_id'])) {
            $summary['cv_document_id'] = (int) $context['cv_document_id'];
        }

        if (isset($context['model'])) {
            $summary['model'] = (string) $context['model'];
        }

        if (isset($context['thinking_time'])) {
            $summary['thinking_time'] = (int) $context['thinking_time'];
        }

        if (isset($context['job_description'])) {
            $summary['job_description_preview'] = mb_substr((string) $context['job_description'], 0, 200);
        }

        if (isset($context['cv_markdown'])) {
            $summary['cv_markdown_preview'] = mb_substr((string) $context['cv_markdown'], 0, 200);
        }

        return $summary;
    }

    /**
     * Handle the extract int operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function extractInt(array $payload, string $key): int
    {
        if (!isset($payload[$key])) {
            throw new RuntimeException(sprintf('Missing required payload key: %s', $key));
        }

        $value = (int) $payload[$key];

        if ($value <= 0) {
            throw new RuntimeException(sprintf('Payload key %s must be a positive integer.', $key));
        }

        return $value;
    }

    /**
     * Handle the extract string operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function extractString(array $payload, string $key): string
    {
        if (!isset($payload[$key])) {
            throw new RuntimeException(sprintf('Missing required payload key: %s', $key));
        }

        $value = trim((string) $payload[$key]);

        if ($value === '') {
            throw new RuntimeException(sprintf('Payload key %s cannot be empty.', $key));
        }

        return $value;
    }

    /**
     * Handle the optional string operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function optionalString(array $payload, string $key): string
    {
        if (!isset($payload[$key])) {
            return '';
        }

        return trim((string) $payload[$key]);
    }
}
