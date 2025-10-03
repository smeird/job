<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Documents\DocumentRepository;
use App\Generations\GenerationDownloadService;
use App\Generations\GenerationLogRepository;
use App\Generations\GenerationRepository;
use App\Prompts\PromptLibrary;
use App\Views\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

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

    /** @var GenerationDownloadService */
    private $generationDownloadService;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        Renderer $renderer,
        DocumentRepository $documentRepository,
        GenerationRepository $generationRepository,
        GenerationLogRepository $generationLogRepository,
        GenerationDownloadService $generationDownloadService

    ) {
        $this->renderer = $renderer;
        $this->documentRepository = $documentRepository;
        $this->generationRepository = $generationRepository;
        $this->generationLogRepository = $generationLogRepository;
        $this->generationDownloadService = $generationDownloadService;
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
            'title' => 'Tailor your application',
            'subtitle' => 'Pair your CV with a job description to create a tailored CV and cover letter.',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('tailor'),
            'email' => $user['email'],
            'jobDocuments' => $this->mapDocuments($this->documentRepository->listForUserAndType($userId, 'job_description')),
            'cvDocuments' => $this->mapDocuments($this->documentRepository->listForUserAndType($userId, 'cv')),
            'generations' => $this->mapGenerations($this->generationRepository->listForUser($userId)),
            'generationLogs' => $this->generationLogRepository->listRecentForUser($userId),
            'modelOptions' => GenerationController::availableModels(),
            'defaultPrompt' => PromptLibrary::tailorPrompt(),
        ]);
    }

    /**
     * Handle the cleanup workflow for tailoring jobs and logs.
     *
     * Providing an explicit endpoint keeps the queue and audit trail tidy when
     * users experiment heavily with the tailoring workspace.
     */
    public function cleanup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $this->json($response->withStatus(401), ['error' => 'Authentication required.']);
        }

        $userId = (int) $user['user_id'];

        try {
            $removedJobs = $this->generationRepository->cleanupJobsForUser($userId);

            $removedFailed = $this->generationRepository->cleanupFailedGenerationsForUser($userId);

            $clearedLogs = $this->generationLogRepository->clearForUser($userId);
        } catch (Throwable $exception) {
            return $this->json($response->withStatus(500), ['error' => 'Unable to clean up tailoring data.']);
        }

        return $this->json($response, [
            'removed_jobs' => $removedJobs,

            'removed_failed_generations' => $removedFailed,

            'cleared_logs' => $clearedLogs,
            'generations' => $this->mapGenerations($this->generationRepository->listForUser($userId)),
            'generation_logs' => $this->generationLogRepository->listRecentForUser($userId),
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
     * Map the provided generation rows into the structure expected by the wizard.
     *
     * Centralising this logic ensures every view receives consistent download
     * groups that now remain permanently accessible once a run is complete.

     *
     * @param array<int, array<string, mixed>> $generations
     * @return array<int, array<string, mixed>>
     */
    private function mapGenerations(array $generations): array
    {
        $mapped = [];

        foreach ($generations as $generation) {
            $id = isset($generation['id']) ? (int) $generation['id'] : 0;
            $status = isset($generation['status']) ? (string) $generation['status'] : '';
            $downloads = [];

            if ($status === 'completed' && $id > 0) {
                $availableFormats = $this->generationDownloadService->availableFormats($id);

                foreach ($availableFormats as $artifact => $formats) {
                    if (!is_array($formats) || $formats === []) {
                        continue;
                    }

                    $links = [];

                    foreach ($formats as $format) {
                        $links[$format] = sprintf(
                            '/generations/%d/download?artifact=%s&format=%s',
                            $id,
                            rawurlencode((string) $artifact),
                            rawurlencode((string) $format)
                        );
                    }

                    if ($links !== []) {
                        $downloads[] = [
                            'artifact' => (string) $artifact,
                            'label' => $this->artifactLabel((string) $artifact),
                            'links' => $links,
                        ];
                    }
                }
            }

            $generation['status'] = $status;
            $generation['downloads'] = $downloads;
            $mapped[] = $generation;
        }

        return $mapped;
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
            'tailor' => ['href' => '/tailor', 'label' => 'Tailor CV & letter'],
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

    /**
     * Produce a human-friendly label for the supplied artifact identifier.
     */
    private function artifactLabel(string $artifact): string
    {
        if ($artifact === 'cover_letter') {
            return 'Cover letter';
        }

        return 'Tailored CV';
    }

    /**
     * Emit a JSON response payload for asynchronous tailoring requests.
     *
     * Centralising the encoding logic keeps the controller methods concise and
     * ensures consistent headers across each JSON response.
     */
    private function json(ResponseInterface $response, array $payload): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
