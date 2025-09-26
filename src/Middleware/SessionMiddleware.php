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

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle the process operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
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
