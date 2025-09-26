<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddleware implements MiddlewareInterface
{
    /** @var AuthService */
    private $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $sessionToken = $cookies['job_session'] ?? null;
        $user = $this->authService->authenticateWithSession($sessionToken);

        if ($user !== null && $sessionToken !== null) {
            $this->authService->touchSession($sessionToken);
            $request = $request->withAttribute('user', $user)->withAttribute('sessionToken', $sessionToken);
        }

        return $handler->handle($request);
    }
}
