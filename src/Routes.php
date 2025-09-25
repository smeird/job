<?php

declare(strict_types=1);

namespace App;

use App\Documents\DocumentPreviewer;
use App\Documents\DocumentService;
use App\Documents\DocumentValidationException;
use App\Documents\DocumentValidator;
use App\Documents\DocumentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

class Routes
{
    public static function register(App $app): void
    {
        $documentRepository = new DocumentRepository(Database::connection());
        $documentService = new DocumentService($documentRepository, new DocumentValidator());
        $documentPreviewer = new DocumentPreviewer();

        $app->get('/healthz', static function (Request $request, Response $response): Response {
            $response->getBody()->write('ok');

            return $response->withHeader('Content-Type', 'text/plain');
        });

        $app->post('/documents/upload', static function (Request $request, Response $response) use ($documentService): Response {
            $files = $request->getUploadedFiles();
            $uploadedFile = $files['document'] ?? null;

            if ($uploadedFile === null) {
                $response->getBody()->write((string) json_encode(['error' => 'No document uploaded.']));

                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }

            try {
                $document = $documentService->storeUploadedDocument($uploadedFile);
            } catch (DocumentValidationException $exception) {
                $response->getBody()->write((string) json_encode(['error' => $exception->getMessage()]));

                return $response
                    ->withStatus($exception->statusCode())
                    ->withHeader('Content-Type', 'application/json');
            } catch (\Throwable $exception) {
                $response->getBody()->write((string) json_encode(['error' => 'Unable to process the uploaded document.']));

                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }

            $payload = json_encode([
                'id' => $document->id(),
                'filename' => $document->filename(),
                'mime_type' => $document->mimeType(),
                'size_bytes' => $document->sizeBytes(),
                'sha256' => $document->sha256(),
            ]);

            $response->getBody()->write($payload === false ? '{}' : $payload);

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json');
        });

        $app->get('/documents/{id}/preview', static function (Request $request, Response $response, array $args) use ($documentService, $documentPreviewer): Response {
            $id = isset($args['id']) ? (int) $args['id'] : 0;

            if ($id <= 0) {
                $response->getBody()->write('Document not found.');

                return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }

            $document = $documentService->find($id);

            if ($document === null) {
                $response->getBody()->write('Document not found.');

                return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }

            $preview = $documentPreviewer->render($document);

            if (function_exists('mb_substr')) {
                $preview = mb_substr($preview, 0, 5000);
            } else {
                $preview = substr($preview, 0, 5000);
            }

            $response->getBody()->write($preview);

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        });
    }
}
