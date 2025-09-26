<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuditLogger;
use App\Services\RateLimiter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PathThrottleMiddleware implements MiddlewareInterface
{
    /** @var RateLimiter */
    private $authLimiter;

    /** @var RateLimiter */
    private $uploadLimiter;

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /** @var AuditLogger */
    private $auditLogger;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(
        RateLimiter $authLimiter,
        RateLimiter $uploadLimiter,
        ResponseFactoryInterface $responseFactory,
        AuditLogger $auditLogger
    ) {
        $this->authLimiter = $authLimiter;
        $this->uploadLimiter = $uploadLimiter;
        $this->responseFactory = $responseFactory;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Handle the process operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();

        if ($this->isAuthPath($path)) {
            return $this->enforce($request, $handler, $this->authLimiter, 'route:/auth');
        }

        if ($this->isDocumentUploadPath($path)) {
            return $this->enforce($request, $handler, $this->uploadLimiter, 'route:/documents/upload');
        }

        return $handler->handle($request);
    }

    /**
     * Handle the enforce operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    private function enforce(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        RateLimiter $limiter,
        string $identifier
    ): ResponseInterface {
        $ip = $this->getClientIp($request) ?? '0.0.0.0';
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;
        $action = $identifier;

        if ($limiter->tooManyAttempts($ip, $identifier, $action)) {
            $this->auditLogger->log('security.throttle.blocked', [
                'path' => $request->getUri()->getPath(),
                'identifier' => $identifier,
            ], null, null, $ip, $userAgent);

            $response = $this->responseFactory->createResponse(429);
            $response->getBody()->write('Too many requests.');

            $retryAfter = $limiter->getIntervalSeconds();

            return $response
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withHeader('Retry-After', (string) $retryAfter);
        }

        $limiter->hit($ip, $identifier, $action, $userAgent, [
            'path' => $request->getUri()->getPath(),
        ]);

        return $handler->handle($request);
    }

    /**
     * Determine whether the auth path condition holds.
     *
     * Wrapping this check simplifies decision making for the caller.
     */
    private function isAuthPath(string $path): bool
    {
        return $path === '/auth' || str_starts_with($path, '/auth/');
    }

    /**
     * Determine whether the document upload path condition holds.
     *
     * Wrapping this check simplifies decision making for the caller.
     */
    private function isDocumentUploadPath(string $path): bool
    {
        return $path === '/documents/upload';
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
