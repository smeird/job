<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Documents\DocumentRepository;
use App\Generations\GenerationLogRepository;
use App\Generations\GenerationRepository;
use App\Prompts\PromptLibrary;
use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TailorController
{
    /** @var Renderer */
    private $renderer;

    /** @var DocumentRepository */
    private $documentRepository;

    /** @var GenerationRepository */
    private $generationRepository;

    /** @var GenerationLogRepository */
    private $generationLogRepository;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        Renderer $renderer,
        DocumentRepository $documentRepository,
        GenerationRepository $generationRepository,
        GenerationLogRepository $generationLogRepository
    ) {
        $this->renderer = $renderer;
        $this->documentRepository = $documentRepository;
        $this->generationRepository = $generationRepository;
        $this->generationLogRepository = $generationLogRepository;
    }

    /**
     * Display the tailoring wizard page for authenticated users.
     *
     * Keeping the workflow isolated provides a dedicated space for managing generations.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];

        return $this->renderer->render($response, 'tailor', [
            'title' => 'Tailor your CV',
            'subtitle' => 'Pair your CV with a job description and queue a generation.',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('tailor'),
            'email' => $user['email'],
            'jobDocuments' => $this->mapDocuments($this->documentRepository->listForUserAndType($userId, 'job_description')),
            'cvDocuments' => $this->mapDocuments($this->documentRepository->listForUserAndType($userId, 'cv')),
            'generations' => $this->generationRepository->listForUser($userId),
            'generationLogs' => $this->generationLogRepository->listRecentForUser($userId),
            'modelOptions' => GenerationController::availableModels(),
            'defaultPrompt' => PromptLibrary::tailorPrompt(),
        ]);
    }

    /**
     * Map the provided documents into the structure expected by the wizard.
     *
     * @param array<int, \App\Documents\Document> $documents
     * @return array<int, array<string, mixed>>
     */
    private function mapDocuments(array $documents): array
    {
        return array_map(static function ($document): array {
            return [
                'id' => $document->id(),
                'filename' => $document->filename(),
                'created_at' => $document->createdAt()->format('Y-m-d H:i'),
            ];
        }, $documents);
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
