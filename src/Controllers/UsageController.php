<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UsageService;
use App\Views\Renderer;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UsageController
{
    /** @var UsageService */
    private $usageService;

    /** @var Renderer */
    private $renderer;

    public function __construct(UsageService $usageService, Renderer $renderer)
    {
        $this->usageService = $usageService;
        $this->renderer = $renderer;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
        }

        return $this->renderer->render($response, 'usage', [
            'title' => 'Usage analytics',
        ]);
    }

    public function data(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            $payload = json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $response->getBody()->write((string) $payload);

            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        try {
            $dataset = $this->usageService->getUsageForUser((int) $user['user_id']);
            $payload = json_encode($dataset, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            $response->getBody()->write((string) json_encode(
                ['error' => 'Unable to encode response.'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ));

            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write((string) $payload);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
