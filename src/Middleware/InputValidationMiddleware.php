<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuditLogger;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class InputValidationMiddleware implements MiddlewareInterface
{
    private const DEFAULT_MAX_BODY_BYTES = 1048576; // 1 MiB
    private const DEFAULT_MAX_FIELD_LENGTH = 10000;

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /** @var AuditLogger */
    private $auditLogger;

    /** @var int */
    private $maxBodyBytes;

    /** @var int */
    private $maxFieldLength;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        AuditLogger $auditLogger,
        int $maxBodyBytes = self::DEFAULT_MAX_BODY_BYTES,
        int $maxFieldLength = self::DEFAULT_MAX_FIELD_LENGTH
    ) {
        $this->responseFactory = $responseFactory;
        $this->auditLogger = $auditLogger;
        $this->maxBodyBytes = $maxBodyBytes;
        $this->maxFieldLength = $maxFieldLength;
    }

    /**
     * Handle the process operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->requiresValidation($request->getMethod())) {
            return $handler->handle($request);
        }

        $contentLength = $this->parseContentLength($request);

        if ($contentLength !== null && $contentLength > $this->maxBodyBytes) {
            return $this->reject($request, 413, 'Request payload too large.');
        }

        $parsed = $request->getParsedBody();

        if (is_array($parsed) && $this->containsOversizedField($parsed)) {
            return $this->reject($request, 400, 'One or more fields exceed allowed length.');
        }

        return $handler->handle($request);
    }

    /**
     * Handle the requires validation operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function requiresValidation(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Handle the parse content length operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function parseContentLength(ServerRequestInterface $request): ?int
    {
        $header = $request->getHeaderLine('Content-Length');

        if ($header === '') {
            return null;
        }

        $value = filter_var($header, FILTER_VALIDATE_INT);

        return $value === false ? null : $value;
    }

    /**
     * Handle the contains oversized field operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function containsOversizedField(array $payload): bool
    {
        foreach ($payload as $value) {
            if (is_array($value)) {
                if ($this->containsOversizedField($value)) {
                    return true;
                }

                continue;
            }

            if (is_string($value) && mb_strlen($value) > $this->maxFieldLength) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle the reject operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function reject(ServerRequestInterface $request, int $status, string $message): ResponseInterface
    {
        $ip = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;

        $this->auditLogger->log('security.input.rejected', [
            'path' => $request->getUri()->getPath(),
            'status' => $status,
            'reason' => $message,
        ], null, null, $ip, $userAgent);

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($message);

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Retrieve the client ip.
     *
     * The helper centralises access to the client ip so callers stay tidy.
     */
    private function getClientIp(ServerRequestInterface $request): ?string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');

        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            $candidate = trim($parts[0]);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        $serverParams = $request->getServerParams();

        return isset($serverParams['REMOTE_ADDR']) ? (string) $serverParams['REMOTE_ADDR'] : null;
    }
}
