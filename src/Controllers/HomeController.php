<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Applications\JobApplicationRepository;
use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeController
{
    /** @var Renderer */
    private $renderer;

    /** @var JobApplicationRepository */
    private $jobApplicationRepository;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        Renderer $renderer,
        JobApplicationRepository $jobApplicationRepository
    ) {
        $this->renderer = $renderer;
        $this->jobApplicationRepository = $jobApplicationRepository;
    }

    /**
     * Display the personalised dashboard or welcome screen for the user.
     *
     * Keeping listing concerns together ensures consistent rendering of overview screens.
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user !== null) {
            $userId = (int) $user['user_id'];

            $outstandingApplications = array_map(
                static function ($application) {
                    return [
                        'id' => $application->id(),
                        'title' => $application->title(),
                        'source_url' => $application->sourceUrl(),
                        'created_at' => $application->createdAt()->format('Y-m-d H:i'),
                    ];
                },
                $this->jobApplicationRepository->listForUserAndStatus($userId, 'outstanding', 3)
            );

            $outstandingCount = $this->jobApplicationRepository->countForUserAndStatus($userId, 'outstanding');

            return $this->renderer->render($response, 'dashboard', [
                'title' => 'Dashboard',
                'subtitle' => 'Keep growing your career with confidence.',
                'fullWidth' => true,
                'navLinks' => $this->navLinks('dashboard'),
                'email' => $user['email'],
                'outstandingApplications' => $outstandingApplications,
                'outstandingApplicationsCount' => $outstandingCount,
            ]);
        }

        return $this->renderer->render($response, 'home', [
            'title' => 'job.smeird.com',
        ]);
    }

    /**
     * Handle the nav links workflow.
     *
     * This helper keeps the nav links logic centralised for clarity and reuse.
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
