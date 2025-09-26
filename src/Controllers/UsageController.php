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

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(UsageService $usageService, Renderer $renderer)
    {
        $this->usageService = $usageService;
        $this->renderer = $renderer;
    }

    /**
     * Display the usage analytics overview screen.
     *
     * Keeping listing concerns together ensures consistent rendering of overview screens.
     */
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

    /**
     * Provide the JSON dataset consumed by the analytics dashboard.
     *
     * Returning the data through one route keeps serialisation and error handling consistent.
     */
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
        } catch (JsonException $exception) {
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
