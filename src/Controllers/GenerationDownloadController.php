<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Generations\GenerationAccessDeniedException;
use App\Generations\GenerationDownloadService;
use App\Generations\GenerationNotFoundException;
use App\Generations\GenerationOutputUnavailableException;
use App\Generations\GenerationTokenService;
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
use function time;
use function strtolower;
use function trim;
use function rewind;

final class GenerationDownloadController
{
    private const SUPPORTED_FORMATS = ['md', 'docx', 'pdf'];

    /** @var GenerationDownloadService */
    private $downloadService;

    /** @var GenerationTokenService */
    private $tokenService;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        GenerationDownloadService $downloadService,
        GenerationTokenService $tokenService
    ) {
        $this->downloadService = $downloadService;
        $this->tokenService = $tokenService;
    }

    /**
     * Orchestrate a download response for the generated artifact.
     *
     * Centralising download logic ensures headers and streaming behaviour remain consistent.
     * @param array<string, string> $args
     */
    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $format = strtolower(trim((string) ($request->getQueryParams()['format'] ?? '')));

        if ($format === '' || !in_array($format, self::SUPPORTED_FORMATS, true)) {
            return $this->error($response, 400, 'Invalid or missing format parameter.');
        }

        $token = trim((string) ($request->getQueryParams()['token'] ?? ''));

        if ($token === '') {
            return $this->error($response, 401, 'Download token is required.');
        }

        $payload = $this->tokenService->validateToken($token);

        if ($payload === null || $payload['format'] !== $format) {
            return $this->error($response, 403, 'Invalid download token.');
        }

        $generationId = (int) ($args['id'] ?? 0);

        if ($generationId <= 0 || $payload['generation_id'] !== $generationId) {
            return $this->error($response, 403, 'Download token does not match the requested generation.');
        }

        $now = time();

        if ($payload['expires_at'] < $now) {
            return $this->error($response, 403, 'Download link has expired.');
        }

        $user = $request->getAttribute('user');

        if (is_array($user) && isset($user['user_id']) && (int) $user['user_id'] !== $payload['user_id']) {
            return $this->error($response, 403, 'Authenticated user does not match download token.');
        }

        try {
            $download = $this->downloadService->fetch($generationId, $payload['user_id'], $format);
        } catch (GenerationNotFoundException) {
            return $this->error($response, 404, 'Generation not found.');
        } catch (GenerationAccessDeniedException) {
            return $this->error($response, 403, 'You do not have access to this generation.');
        } catch (GenerationOutputUnavailableException $exception) {
            return $this->error($response, 409, $exception->getMessage());
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
