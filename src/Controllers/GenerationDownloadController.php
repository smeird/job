<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Generations\GenerationAccessDeniedException;
use App\Generations\GenerationDownloadService;
use App\Generations\GenerationNotFoundException;
use App\Generations\GenerationOutputUnavailableException;
use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Stream;

use function fopen;
use function fwrite;
use function in_array;
use function is_array;
use function json_encode;
use function sprintf;
use function strlen;
use function str_replace;
use function strtolower;
use function trim;
use function rewind;

final class GenerationDownloadController
{
    private const SUPPORTED_FORMATS = ['md', 'docx', 'pdf'];
    private const SUPPORTED_ARTIFACTS = ['cv', 'cover_letter'];

    /** @var GenerationDownloadService */
    private $downloadService;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        GenerationDownloadService $downloadService
    ) {
        $this->downloadService = $downloadService;
    }

    /**
     * Orchestrate a download response for the generated artifact.
     *
     * Centralising download logic ensures headers and streaming behaviour remain consistent.
     * @param array<string, string> $args
     */
    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $format = strtolower(trim((string) ($queryParams['format'] ?? '')));
        $artifact = strtolower(trim((string) ($queryParams['artifact'] ?? '')));

        if ($format === '' || !in_array($format, self::SUPPORTED_FORMATS, true)) {
            return $this->error($response, 400, 'Invalid or missing format parameter.');
        }

        if ($artifact === '' || !in_array($artifact, self::SUPPORTED_ARTIFACTS, true)) {
            return $this->error($response, 400, 'Invalid or missing artifact parameter.');
        }

        $generationId = (int) ($args['id'] ?? 0);

        if ($generationId <= 0) {
            return $this->error($response, 400, 'Invalid generation identifier.');
        }

        $user = $request->getAttribute('user');

        if (!is_array($user) || !isset($user['user_id'])) {
            return $this->error($response, 401, 'Authentication required.');
        }

        $userId = (int) $user['user_id'];

        try {

          
            $download = $this->downloadService->fetch($generationId, $userId, $artifact, $format);
        } catch (GenerationNotFoundException $exception) {

            return $this->error($response, 404, 'Generation not found.');
        } catch (GenerationAccessDeniedException $exception) {
            return $this->error($response, 403, 'You do not have access to this generation.');
        } catch (GenerationOutputUnavailableException $exception) {
            return $this->error($response, 409, $exception->getMessage());
        } catch (RuntimeException $exception) {
            return $this->error($response, 400, $exception->getMessage());
        }

        $resource = fopen('php://temp', 'wb+');

        if ($resource === false) {
            return $this->error($response, 500, 'Unable to prepare download stream.');
        }

        fwrite($resource, $download['content']);
        rewind($resource);

        $stream = new Stream($resource);

        $disposition = sprintf('attachment; filename="%s"', $this->sanitizeFilename($download['filename']));

        $response = $response->withBody($stream)
            ->withHeader('Content-Type', $download['mime_type'])
            ->withHeader('Content-Disposition', $disposition)
            ->withHeader('Cache-Control', 'no-store');

        $length = strlen($download['content']);
        $response = $response->withHeader('Content-Length', (string) $length);

        return $response;
    }

    /**
     * Handle the sanitize filename operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function sanitizeFilename(string $filename): string
    {
        return str_replace(['"', '\\', "\r", "\n"], '', $filename);
    }

    /**
     * Handle the error operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function error(ResponseInterface $response, int $status, string $message): ResponseInterface
    {
        $payload = ['error' => $message];
        $resource = fopen('php://temp', 'wb+');

        if ($resource !== false) {
            fwrite($resource, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            rewind($resource);
            $stream = new Stream($resource);
            $response = $response->withBody($stream);
        }

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
