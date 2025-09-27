<?php

declare(strict_types=1);

namespace App\Applications;

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

    /** @var JobApplicationRepository */
    private $repository;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(JobApplicationRepository $repository)
    {
        $this->repository = $repository;
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

        return [
            'application' => $application,
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
        $normalisedStatus = in_array($status, ['applied', 'outstanding', 'failed'], true) ? $status : 'outstanding';
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
}
