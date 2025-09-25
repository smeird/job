<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuditLogger;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const TOKEN_SESSION_KEY = 'csrf_token';

    private ResponseFactoryInterface $responseFactory;
    private AuditLogger $auditLogger;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        AuditLogger $auditLogger
    ) {
        $this->responseFactory = $responseFactory;
        $this->auditLogger = $auditLogger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->ensureToken();
        $request = $request->withAttribute(self::TOKEN_SESSION_KEY, $token);

        if (!$this->requiresValidation($request->getMethod())) {
            return $handler->handle($request);
        }

        $provided = $this->extractToken($request);

        if (!is_string($provided) || !hash_equals($token, $provided)) {
            $ip = $this->getClientIp($request);
            $userAgent = $request->getHeaderLine('User-Agent') ?: null;

            $this->auditLogger->log('security.csrf.blocked', [
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
            ], null, null, $ip, $userAgent);

            $response = $this->responseFactory->createResponse(403);
            $response->getBody()->write('CSRF token mismatch.');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        return $handler->handle($request);
    }

    private function ensureToken(): string
    {
        $token = $_SESSION[self::TOKEN_SESSION_KEY] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::TOKEN_SESSION_KEY] = $token;
        }

        return $token;
    }

    private function requiresValidation(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $parsed = $request->getParsedBody();

        if (is_array($parsed) && isset($parsed['_token'])) {
            return is_string($parsed['_token']) ? $parsed['_token'] : null;
        }

        $header = $request->getHeaderLine('X-CSRF-Token');

        return $header !== '' ? $header : null;
    }

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
