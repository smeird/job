<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Documents\DocumentRepository;
use App\Documents\DocumentService;
use App\Documents\DocumentValidationException;
use App\Views\Renderer;
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

    public function __construct(
        Renderer $renderer,
        DocumentRepository $documentRepository,
        DocumentService $documentService
    ) {
        $this->renderer = $renderer;
        $this->documentRepository = $documentRepository;
        $this->documentService = $documentService;
    }

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
            'errors' => [],
            'status' => $status,
        ]);
    }

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
            'errors' => $errors,
            'status' => null,
        ]);
    }

    /**
     * @param array<int, \App\Documents\Document> $documents
     * @return array<int, array{filename: string, created_at: string, size: string}>
     */
    private function mapDocuments(array $documents): array
    {
        return array_map(function ($document) {
            return [
                'filename' => $document->filename(),
                'created_at' => $document->createdAt()->format('Y-m-d H:i'),
                'size' => $this->formatBytes($document->sizeBytes()),
            ];
        }, $documents);
    }

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
     * @return array<int, array{href: string, label: string, current: bool}>
     */
    private function navLinks(string $current): array
    {
        $links = [
            'dashboard' => ['href' => '/', 'label' => 'Dashboard'],
            'documents' => ['href' => '/documents', 'label' => 'Documents'],
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
