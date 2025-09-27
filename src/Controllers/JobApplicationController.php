<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Applications\JobApplicationRepository;
use App\Applications\JobApplicationService;
use App\Views\Renderer;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class JobApplicationController
{
    /** @var Renderer */
    private $renderer;

    /** @var JobApplicationRepository */
    private $repository;

    /** @var JobApplicationService */
    private $service;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        Renderer $renderer,
        JobApplicationRepository $repository,
        JobApplicationService $service
    ) {
        $this->renderer = $renderer;
        $this->repository = $repository;
        $this->service = $service;
    }

    /**
     * Display the job application tracker overview.
     *
     * Keeping listing concerns together ensures consistent rendering of overview screens.
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $statusMessage = $request->getQueryParams()['status'] ?? null;
        $failureReasons = $this->service->failureReasons();
        $generations = $this->service->generationsForUser($userId);
        $generationIndex = $this->indexGenerations($generations);

        return $this->renderer->render($response, 'applications', [
            'title' => 'Job tracker',
            'subtitle' => 'Capture postings, track applications, and mark outcomes.',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('applications'),
            'outstanding' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'outstanding'), $generationIndex),
            'applied' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'applied'), $generationIndex),
            'failed' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'failed'), $generationIndex),
            'errors' => [],
            'status' => $statusMessage,
            'failureReasons' => $failureReasons,
            'generationOptions' => $this->mapGenerationOptions($generations),
            'form' => [
                'title' => '',
                'source_url' => '',
                'description' => '',
            ],
        ]);
    }

    /**
     * Handle the store workflow for text-based job descriptions.
     *
     * Centralising validation and persistence keeps the workflow predictable.
     */
    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $data = $request->getParsedBody();
        $formInput = is_array($data) ? $data : [];

        $failureReasons = $this->service->failureReasons();
        $generations = $this->service->generationsForUser($userId);
        $generationIndex = $this->indexGenerations($generations);

        $result = $this->service->createFromSubmission($userId, $formInput);

        if ($result['errors'] === []) {
            return $response
                ->withHeader('Location', '/applications?status=Job+application+saved')
                ->withStatus(302);
        }

        return $this->renderer->render($response->withStatus(422), 'applications', [
            'title' => 'Job tracker',
            'subtitle' => 'Capture postings, track applications, and mark outcomes.',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('applications'),
            'outstanding' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'outstanding'), $generationIndex),
            'applied' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'applied'), $generationIndex),
            'failed' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'failed'), $generationIndex),
            'errors' => $result['errors'],
            'status' => null,
            'failureReasons' => $failureReasons,
            'generationOptions' => $this->mapGenerationOptions($generations),
            'form' => [
                'title' => isset($formInput['title']) ? (string) $formInput['title'] : '',
                'source_url' => isset($formInput['source_url']) ? (string) $formInput['source_url'] : '',
                'description' => isset($formInput['description']) ? (string) $formInput['description'] : '',
            ],
        ]);
    }

    /**
     * Handle status transitions for saved applications.
     *
     * This ensures the tracker reflects the latest actions taken by the user.
     * @param array<string, string> $args
     */
    public function updateStatus(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $applicationId = isset($args['id']) ? (int) $args['id'] : 0;
        $data = $request->getParsedBody();
        $desiredStatus = 'applied';
        $reasonCode = null;

        if (is_array($data)) {
            if (isset($data['status'])) {
                $desiredStatus = (string) $data['status'];
            }

            if (isset($data['reason_code'])) {
                $reasonCode = (string) $data['reason_code'];
            }
        }

        $failureReasons = $this->service->failureReasons();

        try {
            $updated = $this->service->transitionStatus($userId, $applicationId, $desiredStatus, $reasonCode);
        } catch (RuntimeException $exception) {
            return $response
                ->withHeader('Location', '/applications?status=' . rawurlencode($exception->getMessage()))
                ->withStatus(302);
        }

        if ($updated->status() === 'failed') {
            $reasonLabel = $updated->reasonCode() !== null && isset($failureReasons[$updated->reasonCode()])
                ? $failureReasons[$updated->reasonCode()]
                : 'Unknown reason';
            $message = 'Marked application as failed (' . $reasonLabel . ').';
        } elseif ($updated->status() === 'applied') {
            $message = 'Marked application as submitted.';
        } else {
            $message = 'Marked application as outstanding.';
        }

        return $response
            ->withHeader('Location', '/applications?status=' . rawurlencode($message))
            ->withStatus(302);
    }

    /**
     * Handle the tailored CV link workflow.
     *
     * This helper keeps the association between applications and generations tidy.
     * @param array<string, string> $args
     */
    public function updateGeneration(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $applicationId = isset($args['id']) ? (int) $args['id'] : 0;
        $data = $request->getParsedBody();
        $generationId = null;

        if (is_array($data) && isset($data['generation_id'])) {
            $value = trim((string) $data['generation_id']);

            if ($value !== '') {
                $generationId = (int) $value;
            }
        }

        try {
            $this->service->assignGeneration($userId, $applicationId, $generationId);
            $message = $generationId !== null
                ? 'Linked tailored CV to application.'
                : 'Cleared tailored CV link.';
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }

        return $response
            ->withHeader('Location', '/applications?status=' . rawurlencode($message))
            ->withStatus(302);
    }

    /**
     * Handle deletion of saved job applications.
     *
     * Centralising this logic keeps ownership validation and flash messaging aligned.
     * @param array<string, string> $args
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $applicationId = isset($args['id']) ? (int) $args['id'] : 0;

        try {
            $this->service->deleteForUser($userId, $applicationId);
            $message = 'Job application deleted successfully.';
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }

        return $response
            ->withHeader('Location', '/applications?status=' . rawurlencode($message))
            ->withStatus(302);
    }

    /**
     * Handle the mapping workflow.
     *
     * This helper keeps response shaping consistent across controller actions.
     * @param array<int, \App\Applications\JobApplication> $applications
     * @param array<int, array<string, mixed>> $generationIndex
     * @return array<int, array<string, mixed>>
     */
    private function mapApplications(array $applications, array $generationIndex): array
    {
        return array_map(static function ($application) use ($generationIndex) {
            $preview = mb_substr($application->description(), 0, 220);

            if (mb_strlen($application->description()) > 220) {
                $preview .= '…';
            }

            $generationId = $application->generationId();
            $linkedGeneration = null;

            if ($generationId !== null && isset($generationIndex[$generationId])) {
                $linkedGeneration = $generationIndex[$generationId];
            }

            return [
                'id' => $application->id(),
                'title' => $application->title(),
                'source_url' => $application->sourceUrl(),
                'status' => $application->status(),
                'reason_code' => $application->reasonCode(),
                'applied_at' => $application->appliedAt() ? $application->appliedAt()->format('Y-m-d H:i') : null,
                'created_at' => $application->createdAt()->format('Y-m-d H:i'),
                'updated_at' => $application->updatedAt()->format('Y-m-d H:i'),
                'description_preview' => $preview,
                'generation_id' => $generationId,
                'generation' => $linkedGeneration,
            ];
        }, $applications);
    }

    /**
     * Handle the generation indexing workflow.
     *
     * This helper keeps lookup data for tailored drafts readily available.
     * @param array<int, array<string, mixed>> $generations
     * @return array<int, array<string, mixed>>
     */
    private function indexGenerations(array $generations): array
    {
        $indexed = [];

        foreach ($generations as $generation) {
            $id = isset($generation['id']) ? (int) $generation['id'] : 0;

            if ($id <= 0) {
                continue;
            }

            $indexed[$id] = [
                'id' => $id,
                'cv_filename' => isset($generation['cv_document']['filename']) ? (string) $generation['cv_document']['filename'] : 'CV draft',
                'job_filename' => isset($generation['job_document']['filename']) ? (string) $generation['job_document']['filename'] : 'Job description',
                'created_at' => $this->formatGenerationTimestamp(isset($generation['created_at']) ? (string) $generation['created_at'] : ''),
            ];
        }

        return $indexed;
    }

    /**
     * Handle the generation options workflow.
     *
     * This helper builds the select box options used for linking tailored drafts.
     * @param array<int, array<string, mixed>> $generations
     * @return array<int, array{id: int, label: string}>
     */
    private function mapGenerationOptions(array $generations): array
    {
        $options = [];

        foreach ($generations as $generation) {
            $id = isset($generation['id']) ? (int) $generation['id'] : 0;

            if ($id <= 0) {
                continue;
            }

            $cvFilename = isset($generation['cv_document']['filename']) ? (string) $generation['cv_document']['filename'] : 'CV draft';
            $jobFilename = isset($generation['job_document']['filename']) ? (string) $generation['job_document']['filename'] : 'Job description';
            $generatedAt = $this->formatGenerationTimestamp(isset($generation['created_at']) ? (string) $generation['created_at'] : '');
            $label = $cvFilename . ' → ' . $jobFilename;

            if ($generatedAt !== '') {
                $label .= ' (generated ' . $generatedAt . ')';
            }

            $options[] = [
                'id' => $id,
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * Handle the generation timestamp formatting workflow.
     *
     * This helper keeps date presentation consistent even when parsing fails.
     */
    private function formatGenerationTimestamp(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i');
        } catch (\Exception $exception) {
            return $value;
        }
    }

    /**
     * Handle the nav links workflow.
     *
     * This helper keeps navigation data aligned across authenticated screens.
     * @return array<int, array{href: string, label: string, current: bool}>
     */
    private function navLinks(string $current): array
    {
        $links = [
            'dashboard' => ['href' => '/', 'label' => 'Dashboard'],
            'tailor' => ['href' => '/tailor', 'label' => 'Tailor CV'],
            'documents' => ['href' => '/documents', 'label' => 'Documents'],
            'applications' => ['href' => '/applications', 'label' => 'Applications'],
            'usage' => ['href' => '/usage', 'label' => 'Usage'],
            'retention' => ['href' => '/retention', 'label' => 'Retention'],
        ];

        return array_map(static function ($key, $link) use ($current) {
            return [
                'href' => $link['href'],
                'label' => $link['label'],
                'current' => $key === $current,
            ];
        }, array_keys($links), $links);
    }
}
