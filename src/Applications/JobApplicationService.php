<?php

declare(strict_types=1);

namespace App\Applications;

use App\Documents\Document;
use App\Documents\DocumentRepository;
use App\Generations\GenerationRepository;
use PDOException;
use RuntimeException;

class JobApplicationService
{
    private const FAILURE_REASONS = [
        'no_response' => 'No response received',
        'position_filled' => 'Position filled by employer',
        'skills_gap' => 'Skills or experience gap',
        'salary_misaligned' => 'Salary expectations misaligned',
        'other' => 'Other or unspecified reason',
    ];

    private const PIPELINE_STATUSES = ['outstanding', 'applied', 'interviewing', 'contracting', 'failed'];

    /** @var JobApplicationRepository */
    private $repository;

    /** @var GenerationRepository */
    private $generationRepository;

    /** @var DocumentRepository */
    private $documentRepository;

    /** @var JobPostingFetcher */
    private $postingFetcher;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        JobApplicationRepository $repository,
        GenerationRepository $generationRepository,
        DocumentRepository $documentRepository,
        ?JobPostingFetcher $postingFetcher = null
    ) {
        $this->repository = $repository;
        $this->generationRepository = $generationRepository;
        $this->documentRepository = $documentRepository;
        $this->postingFetcher = $postingFetcher ?? new JobPostingFetcher();
    }

    /**
     * Handle the create from submission workflow.
     *
     * This helper centralises validation and persistence for new applications.
     * @param array<string, mixed>|null $input
     * @return array{application: ?JobApplication, errors: array<int, string>}
     */
    public function createFromSubmission(int $userId, ?array $input): array
    {
        $title = '';
        $sourceUrl = '';
        $description = '';
        $errors = [];

        if (is_array($input)) {
            $title = isset($input['title']) ? trim((string) $input['title']) : '';
            $sourceUrl = isset($input['source_url']) ? trim((string) $input['source_url']) : '';
            $description = isset($input['description']) ? trim((string) $input['description']) : '';
        }

        if ($description === '') {
            $errors[] = 'Paste the job description text before saving the record.';
        }

        if ($sourceUrl !== '' && filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Provide a valid URL so the job posting can be revisited later.';
        }

        if ($errors !== []) {
            return [
                'application' => null,
                'errors' => $errors,
            ];
        }

        $title = $title === '' ? 'Untitled application' : $title;
        $storedUrl = $sourceUrl === '' ? null : $sourceUrl;

        $application = $this->repository->create($userId, $title, $storedUrl, $description);

        $this->storeJobDescriptionDocument($application);

        return [
            'application' => $application,
            'errors' => [],
        ];
    }

    /**
     * Handle the update from submission workflow.
     *
     * This helper mirrors creation validation while targeting existing records.
     * @param array<string, mixed>|null $input
     * @return array{application: ?JobApplication, errors: array<int, string>}
     */
    public function updateFromSubmission(int $userId, int $applicationId, ?array $input): array
    {
        $application = $this->repository->findForUser($userId, $applicationId);

        if ($application === null) {
            return [
                'application' => null,
                'errors' => ['The requested job application could not be found.'],
            ];
        }

        $title = $application->title();
        $sourceUrl = $application->sourceUrl() ?? '';
        $description = $application->description();
        $status = $application->status();
        $reasonCode = $application->reasonCode() ?? '';
        $errors = [];

        if (is_array($input)) {
            if (isset($input['title'])) {
                $title = trim((string) $input['title']);
            }

            if (isset($input['source_url'])) {
                $sourceUrl = trim((string) $input['source_url']);
            }

            if (isset($input['description'])) {
                $description = trim((string) $input['description']);
            }

            if (isset($input['status'])) {
                $status = strtolower(trim((string) $input['status']));
            }

            if (isset($input['reason_code'])) {
                $reasonCode = trim((string) $input['reason_code']);
            }
        }

        if ($description === '') {
            $errors[] = 'Paste the job description text before saving the record.';
        }

        if ($sourceUrl !== '' && filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Provide a valid URL so the job posting can be revisited later.';
        }

        $availableStatuses = array_keys($this->statusOptions());

        if (!in_array($status, $availableStatuses, true)) {
            $errors[] = 'Select a valid status before saving the application.';
            $status = $application->status();
        }

        $normalisedReason = null;

        if ($status === 'failed') {
            $normalisedReason = $this->normaliseReasonCode($reasonCode);

            if ($normalisedReason === null) {
                $errors[] = 'Select a valid rejection reason before marking the application as failed.';
            }
        }

        if ($status !== 'failed') {
            $normalisedReason = null;
        }

        if ($errors !== []) {
            return [
                'application' => $application,
                'errors' => $errors,
            ];
        }

        $resolvedTitle = $title === '' ? 'Untitled application' : $title;
        $storedUrl = $sourceUrl === '' ? null : $sourceUrl;

        $updated = $this->repository->updateDetails(
            $application,
            $resolvedTitle,
            $storedUrl,
            $description,
            $status,
            $normalisedReason
        );

        $this->storeJobDescriptionDocument($updated);

        return [
            'application' => $updated,
            'errors' => [],
        ];
    }

    /**
     * Handle the status transition workflow.
     *
     * This helper keeps status updates predictable and access-controlled.
     */
    public function transitionStatus(
        int $userId,
        int $applicationId,
        string $status,
        ?string $reasonCode = null
    ): JobApplication {
        $normalisedStatus = in_array($status, self::PIPELINE_STATUSES, true) ? $status : 'outstanding';
        $normalisedReason = null;

        if ($normalisedStatus === 'failed') {
            $normalisedReason = $this->normaliseReasonCode($reasonCode);

            if ($normalisedReason === null) {
                throw new RuntimeException('Select a valid rejection reason before marking the application as failed.');
            }
        }

        $application = $this->repository->findForUser($userId, $applicationId);

        if ($application === null) {
            throw new RuntimeException('The requested job application could not be found.');
        }

        return $this->repository->updateStatus($application, $normalisedStatus, $normalisedReason);
    }


    /**
     * Import a public job advert URL into a normalised draft posting payload.
     *
     * This gives the core workflow a single mechanism for pulling a description
     * from the web before the user saves and tracks the opportunity.
     * @return array{title: string, source_url: string, description: string}
     */
    public function fetchPostingFromUrl(string $url): array
    {
        return $this->postingFetcher->fetch($url);
    }

    /**
     * Queue tailored documents for an existing tracked application and link the result.
     *
     * The method connects the prime workflow: saved advert, master CV, generated
     * tailored CV and cover letter, then the application tracker record.
     */
    public function tailorApplication(int $userId, int $applicationId, int $cvDocumentId, string $model, int $thinkingTime, string $prompt): JobApplication
    {
        $application = $this->repository->findForUser($userId, $applicationId);

        if ($application === null) {
            throw new RuntimeException('The requested job application could not be found.');
        }

        $jobDocument = $this->findJobDescriptionDocument($application);
        $cvDocument = $this->documentRepository->findForUserByType($userId, $cvDocumentId, 'cv');

        if ($jobDocument === null) {
            throw new RuntimeException('Save the job description before tailoring documents for this application.');
        }

        if ($cvDocument === null) {
            throw new RuntimeException('Select a master CV that belongs to your workspace.');
        }

        $generation = $this->generationRepository->create($userId, $jobDocument, $cvDocument, $model, $thinkingTime, $prompt);
        $generationId = isset($generation['id']) ? (int) $generation['id'] : 0;

        if ($generationId <= 0) {
            throw new RuntimeException('The tailoring job was queued but could not be linked to this application.');
        }

        return $this->repository->updateGeneration($application, $generationId);
    }


    /**
     * List master CV documents available for application-specific tailoring.
     *
     * Keeping this lookup in the service preserves user ownership boundaries for controllers.
     * @return array<int, array{id: int, label: string}>
     */
    public function cvOptionsForUser(int $userId): array
    {
        $options = [];
        $documents = $this->documentRepository->listForUserAndType($userId, 'cv');

        foreach ($documents as $document) {
            $id = $document->id();

            if ($id === null) {
                continue;
            }

            $options[] = [
                'id' => $id,
                'label' => $document->filename(),
            ];
        }

        return $options;
    }

    /**
     * Handle the generation listing workflow.
     *
     * This helper keeps access to tailored CV runs centralised for the controller.
     * @return array<int, array<string, mixed>>
     */
    public function generationsForUser(int $userId): array
    {
        return $this->generationRepository->listForUser($userId);
    }

    /**
     * Handle the assign generation workflow.
     *
     * This helper ensures only owned tailored drafts can be linked to an application.
     */
    public function assignGeneration(int $userId, int $applicationId, ?int $generationId): JobApplication
    {
        if ($applicationId <= 0) {
            throw new RuntimeException('The requested job application could not be found.');
        }

        $application = $this->repository->findForUser($userId, $applicationId);

        if ($application === null) {
            throw new RuntimeException('The requested job application could not be found.');
        }

        $resolvedGenerationId = null;

        if ($generationId !== null && $generationId > 0) {
            $generation = $this->generationRepository->findForUser($userId, $generationId);

            if ($generation === null) {
                throw new RuntimeException('Select a tailored CV that belongs to your workspace.');
            }

            $resolvedGenerationId = (int) $generation['id'];
        }

        return $this->repository->updateGeneration($application, $resolvedGenerationId);
    }

    /**
     * Handle the delete for user operation.
     *
     * This helper keeps ownership checks and messaging consistent for removals.
     */
    public function deleteForUser(int $userId, int $applicationId): void
    {
        if ($applicationId <= 0) {
            throw new RuntimeException('The requested job application could not be found.');
        }

        if (!$this->repository->deleteForUser($userId, $applicationId)) {
            throw new RuntimeException('The requested job application could not be found.');
        }
    }

    /**
     * Handle the failure reasons workflow.
     *
     * This helper keeps the configured rejection codes available to consumers.
     * @return array<string, string>
     */
    public function failureReasons(): array
    {
        return self::FAILURE_REASONS;
    }

    /**
     * Handle the status option workflow.
     *
     * Exposing labels and helper copy keeps presentation logic out of the templates.
     * @return array<string, array{label: string, description: string}>
     */
    public function statusOptions(): array
    {
        return [
            'outstanding' => [
                'label' => 'Queued',
                'description' => 'Roles waiting on materials or next steps.',
            ],
            'applied' => [
                'label' => 'Submitted',
                'description' => 'Applications that have been sent to the employer.',
            ],
            'interviewing' => [
                'label' => 'Interviewing',
                'description' => 'Opportunities where conversations or interviews are in motion.',
            ],
            'contracting' => [
                'label' => 'Contracting',
                'description' => 'Roles that have progressed to offer reviews or contract discussions.',
            ],
            'failed' => [
                'label' => 'Learning',
                'description' => 'Opportunities marked as unsuccessful with a reason for reflection.',
            ],
        ];
    }

    /**
     * Handle the reason normalisation workflow.
     *
     * This helper ensures only known rejection codes make it to persistence.
     */
    private function normaliseReasonCode(?string $reasonCode): ?string
    {
        if ($reasonCode === null) {
            return null;
        }

        $key = trim($reasonCode);

        if ($key === '') {
            return null;
        }

        if (!array_key_exists($key, self::FAILURE_REASONS)) {
            return null;
        }

        return $key;
    }


    /**
     * Locate the stored document that mirrors an application's current job description.
     *
     * Reusing the existing document keeps the Tailor wizard, generated outputs, and
     * application tracker attached to the same source posting text.
     */
    private function findJobDescriptionDocument(JobApplication $application): ?Document
    {
        $description = $application->description();

        if ($description === '' || $application->id() === null) {
            return null;
        }

        $sha256 = hash('sha256', $description . '|' . (string) $application->id());
        $documents = $this->documentRepository->listForUserAndType($application->userId(), 'job_description');

        foreach ($documents as $document) {
            if ($document->sha256() === $sha256) {
                return $document;
            }
        }

        $this->storeJobDescriptionDocument($application);
        $documents = $this->documentRepository->listForUserAndType($application->userId(), 'job_description');

        foreach ($documents as $document) {
            if ($document->sha256() === $sha256) {
                return $document;
            }
        }

        return null;
    }

    /**
     * Persist the job description text as a document for the tailoring workflow.
     *
     * Centralising this logic ensures each saved application automatically exposes its description to the Tailor CV wizard.
     */
    private function storeJobDescriptionDocument(JobApplication $application): void
    {
        $description = $application->description();

        if ($description === '') {
            return;
        }

        $document = new Document(
            null,
            $application->userId(),
            'job_description',
            $this->buildJobDescriptionFilename($application),
            'text/plain',
            strlen($description),
            hash('sha256', $description . '|' . (string) $application->id()),
            $description,
            $application->createdAt()
        );

        try {
            $this->documentRepository->save($document);
        } catch (PDOException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * Generate a descriptive filename for the persisted job description text.
     *
     * Creating the filename here keeps presentation consistent across the applications and documents workspaces.
     */
    private function buildJobDescriptionFilename(JobApplication $application): string
    {
        $title = preg_replace('/[^A-Za-z0-9]+/', '-', $application->title());
        $normalised = trim((string) $title, '-');

        if ($normalised === '') {
            $normalised = 'job-description';
        }

        $timestamp = $application->createdAt()->format('Ymd_His');

        return strtolower($normalised) . '-' . $timestamp . '.txt';
    }

    /**
     * Determine whether the provided PDO exception represents a unique constraint violation.
     *
     * Isolating the detection keeps the storage helper resilient without silencing unrelated database errors.
     */
    private function isUniqueConstraintViolation(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        if (isset($errorInfo[0]) && $errorInfo[0] === '23000') {
            return true;
        }

        $driverCode = isset($errorInfo[1]) ? (int) $errorInfo[1] : null;

        return in_array($driverCode, [1062, 1555, 2067], true);
    }
}
