<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Applications\JobApplicationRepository;
use App\Applications\JobApplicationService;
use App\Views\Renderer;
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

        return $this->renderer->render($response, 'applications', [
            'title' => 'Job tracker',
            'subtitle' => 'Capture postings, track applications, and mark outcomes.',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('applications'),
            'outstanding' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'outstanding')),
            'applied' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'applied')),
            'errors' => [],
            'status' => $statusMessage,
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
            'outstanding' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'outstanding')),
            'applied' => $this->mapApplications($this->repository->listForUserAndStatus($userId, 'applied')),
            'errors' => $result['errors'],
            'status' => null,
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

        if (is_array($data) && isset($data['status'])) {
            $desiredStatus = (string) $data['status'];
        }

        try {
            $updated = $this->service->transitionStatus($userId, $applicationId, $desiredStatus);
        } catch (RuntimeException $exception) {
            return $response
                ->withHeader('Location', '/applications?status=' . rawurlencode($exception->getMessage()))
                ->withStatus(302);
        }

        $message = $updated->status() === 'applied'
            ? 'Marked application as submitted.'
            : 'Marked application as outstanding.';

        return $response
            ->withHeader('Location', '/applications?status=' . rawurlencode($message))
            ->withStatus(302);
    }

    /**
     * Handle the mapping workflow.
     *
     * This helper keeps response shaping consistent across controller actions.
     * @param array<int, \App\Applications\JobApplication> $applications
     * @return array<int, array<string, mixed>>
     */
    private function mapApplications(array $applications): array
    {
        return array_map(static function ($application) {
            $preview = mb_substr($application->description(), 0, 220);

            if (mb_strlen($application->description()) > 220) {
                $preview .= '…';
            }

            return [
                'id' => $application->id(),
                'title' => $application->title(),
                'source_url' => $application->sourceUrl(),
                'status' => $application->status(),
                'applied_at' => $application->appliedAt() ? $application->appliedAt()->format('Y-m-d H:i') : null,
                'created_at' => $application->createdAt()->format('Y-m-d H:i'),
                'description_preview' => $preview,
            ];
        }, $applications);
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
