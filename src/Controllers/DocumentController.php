<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Documents\DocumentPreviewer;
use App\Documents\DocumentRepository;
use App\Documents\DocumentService;
use App\Documents\DocumentValidationException;
use App\Generations\GenerationRepository;
use App\Generations\GenerationTokenService;
use App\Views\Renderer;
use DateTimeImmutable;
use Exception;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class DocumentController
{
    /** @var Renderer */
    private $renderer;

    /** @var DocumentRepository */
    private $documentRepository;

    /** @var DocumentService */
    private $documentService;

    /** @var DocumentPreviewer */
    private $documentPreviewer;

    /** @var GenerationRepository */
    private $generationRepository;

    /** @var GenerationTokenService|null */

    private $generationTokenService;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        Renderer $renderer,
        DocumentRepository $documentRepository,
        DocumentService $documentService,
        DocumentPreviewer $documentPreviewer,
        GenerationRepository $generationRepository,
        ?GenerationTokenService $generationTokenService

    ) {
        $this->renderer = $renderer;
        $this->documentRepository = $documentRepository;
        $this->documentService = $documentService;
        $this->documentPreviewer = $documentPreviewer;
        $this->generationRepository = $generationRepository;
        $this->generationTokenService = $generationTokenService;
    }

    /**
     * Display the index page for managing stored documents.
     *
     * Keeping listing concerns together ensures consistent rendering of overview screens.
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $status = $request->getQueryParams()['status'] ?? null;

        return $this->renderer->render($response, 'documents', [
            'title' => 'Documents',
            'subtitle' => 'Upload CVs and job descriptions for tailoring.',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('documents'),
            'jobDocuments' => $this->mapDocuments($this->documentRepository->listForUserAndType($userId, 'job_description')),
            'cvDocuments' => $this->mapDocuments($this->documentRepository->listForUserAndType($userId, 'cv')),
            'tailoredGenerations' => $this->mapGenerations($userId, $this->generationRepository->listForUser($userId)),
            'errors' => [],
            'status' => $status,
        ]);
    }

    /**
     * Handle the upload workflow for incoming files or payloads.
     *
     * A single upload routine guarantees validation and storage steps stay uniform.
     */
    public function upload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $data = $request->getParsedBody();
        $errors = [];
        $documentType = '';

        if (is_array($data)) {
            $documentType = isset($data['document_type']) ? trim((string) $data['document_type']) : '';
        }

        if (!in_array($documentType, ['job_description', 'cv'], true)) {
            $errors[] = 'Choose whether the file is a job description or a CV.';
        }

        $files = $request->getUploadedFiles();
        $uploadedFile = $files['document'] ?? null;

        if (!$uploadedFile instanceof UploadedFileInterface || $uploadedFile->getError() === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Select a file to upload.';
        }

        if ($errors === []) {
            try {
                $document = $this->documentService->storeUploadedDocument($uploadedFile, $userId, $documentType);

                $label = $documentType === 'cv' ? 'CV' : 'job description';
                $message = sprintf('Uploaded "%s" as your %s.', $document->filename(), $label);

                return $response
                    ->withHeader('Location', '/documents?status=' . rawurlencode($message))
                    ->withStatus(302);
            } catch (DocumentValidationException $exception) {
                $errors[] = $exception->getMessage();
            } catch (PDOException $exception) {
                $errors[] = 'We could not store the document. Please try a different file.';
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return $this->renderer->render($response->withStatus(422), 'documents', [
            'title' => 'Documents',
            'subtitle' => 'Upload CVs and job descriptions for tailoring.',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('documents'),
            'jobDocuments' => $this->mapDocuments($this->documentRepository->listForUserAndType($userId, 'job_description')),
            'cvDocuments' => $this->mapDocuments($this->documentRepository->listForUserAndType($userId, 'cv')),
            'tailoredGenerations' => $this->mapGenerations($userId, $this->generationRepository->listForUser($userId)),
            'errors' => $errors,
            'status' => null,
        ]);
    }

    /**
     * Display the stored document so the user can review its contents.
     *
     * Centralising the preview flow helps ensure ownership checks and messaging remain consistent.
     * @param array<string, string> $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $documentId = isset($args['id']) ? (int) $args['id'] : 0;

        try {
            $document = $this->documentService->getForUser($userId, $documentId);
        } catch (RuntimeException $exception) {
            return $response
                ->withHeader('Location', '/documents?status=' . rawurlencode($exception->getMessage()))
                ->withStatus(302);
        }

        $preview = $this->documentPreviewer->render($document);
        $documentType = $document->documentType() === 'cv' ? 'CV' : 'Job description';

        return $this->renderer->render($response, 'document-view', [
            'title' => $document->filename() . ' · Documents',
            'subtitle' => 'Document preview',
            'fullWidth' => true,
            'navLinks' => $this->navLinks('documents'),
            'document' => [
                'id' => $document->id(),
                'filename' => $document->filename(),
                'created_at' => $document->createdAt()->format('Y-m-d H:i'),
                'size' => $this->formatBytes($document->sizeBytes()),
                'mime_type' => $document->mimeType(),
                'type_label' => $documentType,
                'preview' => $preview,
            ],
        ]);
    }

    /**
     * Handle the delete workflow for stored documents.
     *
     * Centralising deletion ensures ownership checks and messaging remain consistent.
     * @param array<string, string> $args
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $documentId = isset($args['id']) ? (int) $args['id'] : 0;

        try {
            $this->documentService->deleteForUser($userId, $documentId);
            $message = 'Document deleted successfully.';
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }

        return $response
            ->withHeader('Location', '/documents?status=' . rawurlencode($message))
            ->withStatus(302);
    }

    /**
     * Map the provided data set into the desired shape.
     *
     * @param array<int, \App\Documents\Document> $documents
     * @return array<int, array{id: int|null, filename: string, created_at: string, size: string, view_url: string|null}>
     */
    private function mapDocuments(array $documents): array
    {
        return array_map(function ($document) {
            return [
                'id' => $document->id(),
                'filename' => $document->filename(),
                'created_at' => $document->createdAt()->format('Y-m-d H:i'),
                'size' => $this->formatBytes($document->sizeBytes()),
                'view_url' => $document->id() !== null
                    ? '/documents/' . rawurlencode((string) $document->id())
                    : null,
            ];
        }, $documents);
    }

    /**
     * Convert the raw byte count into a readable size string.
     *
     * The helper keeps size formatting consistent across the interface.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KiB', 'MiB'];
        $value = $bytes / 1024;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return sprintf('%.1f %s', $value, $units[$unitIndex]);
    }

    /**
     * Map the generation rows into the view-friendly structure.
     *
     * The helper keeps tailored CV metadata formatting consistent across the documents workspace
     * while avoiding download token creation for generations that are not yet completed.
     * @param array<int, array<string, mixed>> $generations
     * @return array<int, array<string, mixed>>
     */
    private function mapGenerations(int $userId, array $generations): array
    {
        $mapped = [];

        foreach ($generations as $generation) {
            $createdAt = new DateTimeImmutable();

            if (isset($generation['created_at'])) {
                try {
                    $createdAt = new DateTimeImmutable((string) $generation['created_at']);
                } catch (Exception $exception) {
                    $createdAt = new DateTimeImmutable();
                }
            }

            $status = isset($generation['status']) ? (string) $generation['status'] : '';
            $downloads = [];

            if ($status === 'completed') {
                $downloads = $this->buildDownloadLinks($userId, (int) $generation['id']);
            }

            $mapped[] = [
                'id' => (int) $generation['id'],
                'status' => $status,
                'model' => (string) $generation['model'],
                'thinking_time' => (int) $generation['thinking_time'],
                'created_at' => $createdAt->format('Y-m-d H:i'),
                'job_document' => [
                    'id' => (int) $generation['job_document']['id'],
                    'filename' => (string) $generation['job_document']['filename'],
                ],
                'cv_document' => [
                    'id' => (int) $generation['cv_document']['id'],
                    'filename' => (string) $generation['cv_document']['filename'],
                ],
                'downloads' => $downloads,
            ];
        }

        return $mapped;
    }

    /**
     * Build the signed download URLs for the provided generation.
     *
     * Centralising link creation ensures each page exposes consistent tokenised
     * URLs that respect the per-user security constraints enforced by the
     * download controller. When the token service is disabled the method
     * returns an empty list so the UI hides download options gracefully.

     *
     * @return array<string, string>
     */
    private function buildDownloadLinks(int $userId, int $generationId): array
    {
        if ($this->generationTokenService === null) {
            return [];
        }

        $token = $this->generationTokenService->createToken($userId, $generationId, 'md');

        return [
            'md' => sprintf(
                '/generations/%d/download?format=md&token=%s',
                $generationId,
                rawurlencode($token)
            ),
        ];
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

        return array_map(function ($key, $link) use ($current) {
            return [
                'href' => $link['href'],
                'label' => $link['label'],
                'current' => $key === $current,
            ];
        }, array_keys($links), $links);
    }
}
