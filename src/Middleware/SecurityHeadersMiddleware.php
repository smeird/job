<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Security\CspConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $appUrl)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $csp = $this->buildContentSecurityPolicy();

        $headers = [
            'Content-Security-Policy' => $csp,
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), geolocation=(), microphone=()',
            'X-Content-Type-Options' => 'nosniff',
        ];

        foreach ($headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    private function buildContentSecurityPolicy(): string
    {
        $formActionOrigin = trim($this->appUrl);
        $formActionDirective = "form-action 'self'";

        if ($formActionOrigin !== '') {
            $formActionDirective .= ' ' . rtrim($formActionOrigin, '/');
        }

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "connect-src 'self'",
            "font-src 'self'",
            $formActionDirective,
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            "object-src 'none'",
            "script-src 'self' " . CspConfig::alpineInitHash(),
            "style-src 'self'",
        ];

        return implode('; ', $directives);
    }
}
