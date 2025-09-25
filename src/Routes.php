<?php

declare(strict_types=1);

namespace App;

use App\Controllers\AuthController;
use App\Controllers\HomeController;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpBadRequestException;

class Routes
{
    public static function register(App $app): void
    {

        $container = $app->getContainer();
        $homeController = $container->get(HomeController::class);
        $authController = $container->get(AuthController::class);

        $app->get('/', [$homeController, 'index']);

        $app->get('/auth/register', [$authController, 'showRegister']);
        $app->post('/auth/register', [$authController, 'register']);
        $app->get('/auth/register/verify', [$authController, 'showRegisterVerify']);
        $app->post('/auth/register/verify', [$authController, 'registerVerify']);

        $app->get('/auth/login', [$authController, 'showLogin']);
        $app->post('/auth/login', [$authController, 'login']);
        $app->get('/auth/login/verify', [$authController, 'showLoginVerify']);
        $app->post('/auth/login/verify', [$authController, 'loginVerify']);

        $app->get('/auth/backup-codes', [$authController, 'showBackupCodes']);
        $app->post('/auth/backup-codes', [$authController, 'backupCodes']);
        $app->post('/auth/logout', [$authController, 'logout']);


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
    }
}
