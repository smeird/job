<?php

declare(strict_types=1);

namespace App\Queue\Handler;

use App\AI\OpenAIProvider;
use App\Prompts\PromptLibrary;
use App\Queue\Job;
use App\Queue\JobHandlerInterface;
use App\Queue\TransientJobException;
use App\Settings\SiteSettingsRepository;
use DateTimeImmutable;
use League\CommonMark\CommonMarkConverter;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_merge;
use function array_map;
use function array_values;
use function implode;
use function is_array;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function mb_strlen;
use function mb_substr;
use function sprintf;
use function str_replace;
use function strip_tags;
use function trim;

final class TailorCvJobHandler implements JobHandlerInterface
{
    /** @var CommonMarkConverter */
    private $markdownConverter;

    /** @var PDO */
    private $pdo;

    /** @var SiteSettingsRepository */
    private $settingsRepository;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->settingsRepository = new SiteSettingsRepository($pdo);
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
        $contactDetails = $this->extractContactDetails($payload);

        error_log(sprintf(
            'TailorCvJobHandler starting generation %d for user %d (job_description=%s, cv_markdown=%s)',
            $generationId,
            $userId,
            $jobDescription === '' ? 'empty' : 'present',
            $cvMarkdown === '' ? 'empty' : 'present'
        ));

        $this->updateGenerationStatus($generationId, 'processing');

        $provider = new OpenAIProvider($userId, null, $this->pdo, $this->settingsRepository);

        $plan = $this->generatePlan($provider, $jobDescription, $cvMarkdown);
        $constraints = $this->buildConstraints($payload, $cvMarkdown);
        $draft = $this->generateDraft($provider, $plan, $constraints);
        $converted = $this->convertDraft($draft);

        $coverLetterPrompt = $this->buildCoverLetterPrompt($payload, $plan, $jobDescription, $cvMarkdown, $contactDetails);
        $coverLetterDraft = $this->generateCoverLetterDraft($provider, $coverLetterPrompt);
        $coverLetterConverted = $this->convertDraft($coverLetterDraft);

        $records = array_merge(
            [$this->buildTextOutput($generationId, 'cv_plan', 'application/json', $plan)],
            $this->createDocumentOutputs($generationId, 'cv', $draft, $converted['html'], $converted['text']),
            $this->createDocumentOutputs(
                $generationId,
                'cover_letter',
                $coverLetterDraft,
                $coverLetterConverted['html'],
                $coverLetterConverted['text']
            )
        );

        $this->persistOutputs($generationId, $records);
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
     * Generate the cover letter draft using the specialised OpenAI prompt.
     *
     * Separating this call keeps the cover letter lifecycle distinct from the CV while sharing
     * the provider dependency and retry behaviour.
     */
    private function generateCoverLetterDraft(OpenAIProvider $provider, string $instructions): string
    {
        try {
            return $provider->draftCoverLetter($instructions);
        } catch (Throwable $exception) {
            throw new TransientJobException('Failed to generate cover letter draft: ' . $exception->getMessage(), 0, $exception);
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

     * Build the output rows associated with a specific document artifact.
     *
     * Centralising the mapping keeps persistence logic compact, storing the markdown and rendered
     * text variants while binary conversions occur at download time.
     *
     * @return array<int, array<string, mixed>>
     */
    private function createDocumentOutputs(
        int $generationId,
        string $artifact,
        string $markdown,
        string $html,
        string $plainText
    ): array {
        $outputs = [];

        $outputs[] = $this->buildTextOutput($generationId, $artifact, 'text/markdown', $markdown);
        $outputs[] = $this->buildTextOutput($generationId, $artifact, 'text/html', $html);
        $outputs[] = $this->buildTextOutput($generationId, $artifact, 'text/plain', $plainText);

        return $outputs;
    }

    /**
     * Create a text-based generation output payload ready for persistence.
     *
     * @return array<string, mixed>
     */
    private function buildTextOutput(int $generationId, string $artifact, string $mimeType, string $content): array
    {
        return [
            'generation_id' => $generationId,
            'artifact' => $artifact,
            'mime_type' => $mimeType,
            'content' => null,
            'output_text' => $content,
            'tokens_used' => null,
        ];
    }

    /**
     * Handle the persist outputs operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function persistOutputs(int $generationId, array $records): void
    {
        try {
            $this->pdo->beginTransaction();

            $delete = $this->pdo->prepare('DELETE FROM generation_outputs WHERE generation_id = :generation_id');
            $delete->execute([':generation_id' => $generationId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO generation_outputs (generation_id, artifact, mime_type, content, output_text, tokens_used) '
                . 'VALUES (:generation_id, :artifact, :mime_type, :content, :output_text, :tokens_used)'
            );

            foreach ($records as $record) {
                $insert->execute([
                    ':generation_id' => $record['generation_id'],
                    ':artifact' => $record['artifact'],
                    ':mime_type' => $record['mime_type'],
                    ':content' => $record['content'],
                    ':output_text' => $record['output_text'],
                    ':tokens_used' => $record['tokens_used'],
                ]);
            }

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
        $competencyList = $this->prepareCompetencyList($payload);

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
     * Build the cover letter instructions using the dedicated template and contextual data.
     *
     * Producing a rich prompt here keeps the AI call focused on the relevant accomplishments and tone.
     */
    private function buildCoverLetterPrompt(
        array $payload,
        string $plan,
        string $jobDescription,
        string $cvMarkdown,
        array $contactDetails
    ): string {
        $template = PromptLibrary::coverLetterPrompt();
        $jobTitle = $this->optionalString($payload, 'job_title');
        $company = $this->optionalString($payload, 'company');
        $competencyList = $this->prepareCompetencyList($payload);
        $jobExcerpt = $this->truncateContent($jobDescription, 2000);
        $cvExcerpt = $this->truncateContent($cvMarkdown, 2000);
        $contactJson = $this->encodeContactDetails($contactDetails);

        return strtr($template, [
            '{{title}}' => $jobTitle !== '' ? $jobTitle : 'Not specified',
            '{{company}}' => $company !== '' ? $company : 'Not specified',
            '{{competencies}}' => $competencyList !== '' ? $competencyList : 'Not specified',
            '{{job_description}}' => $jobExcerpt,
            '{{cv_sections}}' => $cvExcerpt,
            '{{plan}}' => trim($plan),
            '{{contact_details}}' => $contactJson,
        ]);
    }

    /**
     * Encode the optional contact details into JSON for the cover letter prompt.
     *
     * Serialising the data centrally ensures the AI receives a consistent
     * structure regardless of which fields the user completed.
     */
    private function encodeContactDetails(array $contactDetails): string
    {
        if ($contactDetails === [] || !isset($contactDetails['address'])) {
            return '{}';
        }

        $payload = ['address' => $contactDetails['address']];

        if (isset($contactDetails['email']) && $contactDetails['email'] !== '') {
            $payload['email'] = $contactDetails['email'];
        }

        if (isset($contactDetails['phone']) && $contactDetails['phone'] !== '') {
            $payload['phone'] = $contactDetails['phone'];
        }

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Normalise the competencies payload into a readable comma-separated string.
     */
    private function prepareCompetencyList(array $payload): string
    {
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

            return implode(', ', $cleaned);
        }

        return trim((string) $competencies);
    }

    /**
     * Trim long-form content to keep prompts within manageable limits for the model.
     */
    private function truncateContent(string $content, int $limit): string
    {
        if ($limit <= 0) {
            return $content;
        }

        if (mb_strlen($content) <= $limit) {
            return $content;
        }

        return mb_substr($content, 0, $limit) . '…';
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
     * Extract and normalise the optional contact details from the payload.
     *
     * Returning a trimmed associative array keeps downstream prompt logic
     * simple and avoids juggling null values during template substitution.
     *
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function extractContactDetails(array $payload): array
    {
        if (!isset($payload['contact_details']) || !is_array($payload['contact_details'])) {
            return [];
        }

        $raw = $payload['contact_details'];
        $address = isset($raw['address']) ? trim((string) $raw['address']) : '';

        if ($address === '') {
            return [];
        }

        $details = [
            'address' => str_replace(["\r\n", "\r"], "\n", $address),
        ];

        $email = isset($raw['email']) ? trim((string) $raw['email']) : '';

        if ($email !== '') {
            $details['email'] = $email;
        }

        $phone = isset($raw['phone']) ? trim((string) $raw['phone']) : '';

        if ($phone !== '') {
            $details['phone'] = $phone;
        }

        return $details;
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
