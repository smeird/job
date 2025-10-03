<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Documents\DocumentPreviewer;
use App\Documents\DocumentRepository;
use App\Documents\DocumentService;
use App\Documents\DocumentValidationException;

use App\Generations\GenerationAccessDeniedException;
use App\Generations\GenerationDownloadService;
use App\Generations\GenerationNotFoundException;
use App\Generations\GenerationOutputUnavailableException;

use App\Generations\GenerationRepository;
use App\Views\Renderer;
use DateTimeImmutable;
use Exception;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use function strtolower;
use function strtoupper;

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

    /** @var GenerationDownloadService */
    private $generationDownloadService;

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
        DocumentService $documentService,
        DocumentPreviewer $documentPreviewer,
        GenerationDownloadService $generationDownloadService,
        GenerationRepository $generationRepository
    ) {
        $this->renderer = $renderer;
        $this->documentRepository = $documentRepository;
        $this->documentService = $documentService;
        $this->documentPreviewer = $documentPreviewer;
        $this->generationDownloadService = $generationDownloadService;
        $this->generationRepository = $generationRepository;
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
            'tailoredGenerations' => $this->mapGenerations($this->generationRepository->listForUser($userId)),
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
            'tailoredGenerations' => $this->mapGenerations($this->generationRepository->listForUser($userId)),
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
     * Remove a tailored CV run owned by the authenticated user.
     *
     * Exposing deletion within the documents workspace lets users tidy up
     * historic drafts once they are no longer required.
     * @param array<string, string> $args
     */
    public function deleteGeneration(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $generationId = isset($args['id']) ? (int) $args['id'] : 0;
        $message = 'The tailored CV could not be found.';

        if ($generationId > 0) {
            try {
                $deleted = $this->generationRepository->deleteForUser($userId, $generationId);
                $message = $deleted
                    ? 'Tailored CV deleted successfully.'
                    : 'The tailored CV could not be found.';
            } catch (RuntimeException $exception) {
                $message = 'We could not delete the tailored CV. Please try again.';
            }
        }

        return $response
            ->withHeader('Location', '/documents?status=' . rawurlencode($message))
            ->withStatus(302);
    }

    /**
     * Promote a completed tailored CV into the primary CV library.
     *
     * Saving the generated draft ensures it appears alongside uploaded CVs so
     * future tailoring runs can reuse the improved version.
     * @param array<string, string> $args
     */
    public function promoteGeneration(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        $userId = (int) $user['user_id'];
        $generationId = isset($args['id']) ? (int) $args['id'] : 0;

        if ($generationId <= 0) {
            return $response
                ->withHeader('Location', '/documents?status=' . rawurlencode('The tailored CV could not be found.'))
                ->withStatus(302);
        }

        $generation = $this->generationRepository->findForUser($userId, $generationId);

        if ($generation === null) {
            return $response
                ->withHeader('Location', '/documents?status=' . rawurlencode('The tailored CV could not be found.'))
                ->withStatus(302);
        }

        $status = isset($generation['status']) ? (string) $generation['status'] : '';

        if ($status !== 'completed') {
            return $response
                ->withHeader('Location', '/documents?status=' . rawurlencode('Only completed tailored CVs can be saved.'))
                ->withStatus(302);
        }

        try {
            $download = $this->generationDownloadService->fetch($generationId, $userId, 'md');
        } catch (GenerationNotFoundException | GenerationAccessDeniedException $exception) {
            return $response
                ->withHeader('Location', '/documents?status=' . rawurlencode('The tailored CV could not be found.'))
                ->withStatus(302);
        } catch (GenerationOutputUnavailableException $exception) {
            return $response
                ->withHeader('Location', '/documents?status=' . rawurlencode('The tailored CV output is not yet available.'))
                ->withStatus(302);
        } catch (RuntimeException $exception) {
            return $response
                ->withHeader('Location', '/documents?status=' . rawurlencode('We could not access the tailored CV output.'))
                ->withStatus(302);
        }

        $filename = $this->generateTailoredFilename($generation);

        try {
            $document = $this->documentService->storeDocumentFromContent(
                $userId,
                'cv',
                $filename,
                (string) $download['content']
            );
            $message = sprintf('Saved tailored CV as "%s".', $document->filename());
        } catch (DocumentValidationException $exception) {
            $message = $exception->getMessage();
        } catch (PDOException $exception) {
            $message = 'We could not save the tailored CV to your library. Please try again.';
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
     * Build a descriptive filename for promoted tailored CV drafts.
     *
     * @param array<string, mixed> $generation
     */
    private function generateTailoredFilename(array $generation): string
    {
        $jobFilename = '';

        if (isset($generation['job_document']) && is_array($generation['job_document'])) {
            $job = $generation['job_document'];

            if (isset($job['filename'])) {
                $jobFilename = (string) $job['filename'];
            }
        }

        $base = $jobFilename !== ''
            ? pathinfo($jobFilename, PATHINFO_FILENAME)
            : 'tailored-cv';

        $clean = preg_replace('/[^A-Za-z0-9 _-]+/', '', (string) $base);

        if (!is_string($clean) || trim($clean) === '') {
            $clean = 'tailored-cv';
        } else {
            $collapsed = preg_replace('/\s+/', ' ', $clean);
            $clean = is_string($collapsed) ? trim($collapsed) : trim($clean);

            if ($clean === '') {
                $clean = 'tailored-cv';
            }
        }

        $timestamp = new DateTimeImmutable();

        if (isset($generation['created_at'])) {
            try {
                $timestamp = new DateTimeImmutable((string) $generation['created_at']);
            } catch (Exception $exception) {
                $timestamp = new DateTimeImmutable();
            }
        }

        return sprintf('%s - tailored-%s.md', $clean, $timestamp->format('Ymd-His'));
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
     * while exposing permanent download URLs only when background runs have completed.
     * @param array<int, array<string, mixed>> $generations
     * @return array<int, array<string, mixed>>
     */
    private function mapGenerations(array $generations): array
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
                $downloads = $this->buildDownloadLinks((int) $generation['id']);
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
                'is_completed' => $status === 'completed',
                'delete_url' => '/documents/tailored/' . rawurlencode((string) $generation['id']) . '/delete',
                'promote_url' => $status === 'completed'
                    ? '/documents/tailored/' . rawurlencode((string) $generation['id']) . '/promote'
                    : null,
            ];
        }

        return $mapped;
    }

    /**
     * Build the permanent download URLs for the provided generation.
     *
     * Centralising link creation ensures each page exposes consistent URLs
     * while still surfacing every available format when binary exports are stored.

     *
     * @return array<int, array{artifact: string, label: string, links: array<int, array{format: string, url: string, label: string}>}>
     */
    private function buildDownloadLinks(int $generationId): array
    {
        $availableFormats = $this->generationDownloadService->availableFormats($generationId);

        if ($availableFormats === []) {
            return [];
        }

        $downloads = [];

        foreach ($availableFormats as $artifact => $formats) {
            if (!is_array($formats) || $formats === []) {
                continue;
            }

            $links = [];

            foreach ($formats as $format) {
                $links[] = [
                    'format' => (string) $format,
                    'url' => sprintf(
                        '/generations/%d/download?artifact=%s&format=%s',
                        $generationId,
                        rawurlencode((string) $artifact),
                        rawurlencode((string) $format)
                    ),
                    'label' => $this->downloadLabel((string) $format),
                ];
            }

            if ($links !== []) {
                $downloads[] = [
                    'artifact' => (string) $artifact,
                    'label' => $this->artifactLabel((string) $artifact),
                    'links' => $links,
                ];
            }
        }

        return $downloads;
    }

    /**
     * Produce a human-friendly label for a stored artifact identifier.
     */
    private function artifactLabel(string $artifact): string
    {
        if ($artifact === 'cover_letter') {
            return 'Cover letter';
        }

        return 'Tailored CV';
    }

    /**
     * Provide a readable label for download buttons rendered in the documents view.
     */
    private function downloadLabel(string $format): string
    {
        $key = strtolower($format);

        if ($key === 'md') {
            return 'Download markdown';
        }

        if ($key === 'pdf') {
            return 'Download PDF';
        }

        if ($key === 'docx') {
            return 'Download Word';
        }

        if ($key !== '') {
            return 'Download ' . strtoupper($key);
        }

        return 'Download file';
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

        return array_map(function ($key, $link) use ($current) {
            return [
                'href' => $link['href'],
                'label' => $link['label'],
                'current' => $key === $current,
            ];
        }, array_keys($links), $links);
    }
}
