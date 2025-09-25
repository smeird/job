<?php

declare(strict_types=1);

namespace App;

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\RetentionController;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\App;
use Slim\Exception\HttpBadRequestException;

class Routes
{
    public static function register(App $app): void
    {

        $viewPath = dirname(__DIR__) . '/resources/views/home.php';

        $app->get('/', static function (Request $request, Response $response) use ($viewPath): Response {
            $html = (static function (string $path): string {
                if (!is_file($path)) {
                    return '<!DOCTYPE html><title>View missing</title><body><h1>View missing</h1></body>';
                }

                ob_start();
                include $path;

                return (string) ob_get_clean();
            })($viewPath);

            $response->getBody()->write($html);

            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        });


        $app->get('/healthz', static function (Request $request, Response $response): Response {
            $response->getBody()->write('ok');

            return $response->withHeader('Content-Type', 'text/plain');
        });


        $app->get('/prompts', static function (Request $request, Response $response): Response {
            $payload = [
                'system' => PromptLibrary::systemPrompt(),
                'tailor' => PromptLibrary::tailorPrompt(),
            ];

            $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->post('/draft/validate', static function (Request $request, Response $response): Response {
            $parsed = $request->getParsedBody();

            if (!is_array($parsed)) {
                throw new HttpBadRequestException($request, 'Invalid request payload.');
            }

            $sourceCv = isset($parsed['source_cv']) ? trim((string) $parsed['source_cv']) : '';
            $draft = isset($parsed['draft_markdown']) ? trim((string) $parsed['draft_markdown']) : '';

            if ($sourceCv === '' || $draft === '') {
                throw new HttpBadRequestException($request, 'Both source_cv and draft_markdown are required.');
            }

            $validator = new DraftValidator();

            try {
                $validator->ensureNoUnknownOrganisations($sourceCv, $draft);
            } catch (InvalidArgumentException $exception) {
                $response->getBody()->write((string) json_encode([
                    'status' => 'rejected',
                    'reason' => $exception->getMessage(),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return $response
                    ->withStatus(422)
                    ->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write((string) json_encode([
                'status' => 'accepted',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');

        });

        $app->get('/retention', static function (Request $request, Response $response) use ($app): Response {
            $container = $app->getContainer();

            if ($container === null) {
                throw new RuntimeException('Container is not available.');
            }

            /** @var RetentionController $controller */
            $controller = $container->get(RetentionController::class);

            return $controller->show($request, $response);
        });

        $app->post('/retention', static function (Request $request, Response $response) use ($app): Response {
            $container = $app->getContainer();

            if ($container === null) {
                throw new RuntimeException('Container is not available.');
            }

            /** @var RetentionController $controller */
            $controller = $container->get(RetentionController::class);

            return $controller->update($request, $response);
        });
    }
}
