<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Applications\JobApplicationRepository;
use App\Documents\DocumentRepository;
use App\Generations\GenerationRepository;
use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeController
{
    /** @var Renderer */
    private $renderer;

    /** @var DocumentRepository */
    private $documentRepository;

    /** @var JobApplicationRepository */
    private $jobApplicationRepository;

    /** @var GenerationRepository */
    private $generationRepository;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        Renderer $renderer,
        DocumentRepository $documentRepository,
        GenerationRepository $generationRepository,
        JobApplicationRepository $jobApplicationRepository
    ) {
        $this->renderer = $renderer;
        $this->documentRepository = $documentRepository;
        $this->generationRepository = $generationRepository;
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
                'email' => $user['email'],
                'jobDocuments' => array_map(
                    static function ($document) {
                        return [
                            'id' => $document->id(),
                            'filename' => $document->filename(),
                            'created_at' => $document->createdAt()->format('Y-m-d H:i'),
                        ];
                    },
                    $this->documentRepository->listForUserAndType($userId, 'job_description')
                ),
                'cvDocuments' => array_map(
                    static function ($document) {
                        return [
                            'id' => $document->id(),
                            'filename' => $document->filename(),
                            'created_at' => $document->createdAt()->format('Y-m-d H:i'),
                        ];
                    },
                    $this->documentRepository->listForUserAndType($userId, 'cv')
                ),
                'generations' => $this->generationRepository->listForUser($userId),
                'modelOptions' => GenerationController::availableModels(),
                'outstandingApplications' => $outstandingApplications,
                'outstandingApplicationsCount' => $outstandingCount,
            ]);
        }

        return $this->renderer->render($response, 'home', [
            'title' => 'job.smeird.com',
        ]);
    }
}
