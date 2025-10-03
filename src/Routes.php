<?php

declare(strict_types=1);

namespace App;

use App\Controllers\AuthController;
use App\Controllers\DocumentController;
use App\Controllers\GenerationController;
use App\Controllers\GenerationDownloadController;
use App\Controllers\HomeController;
use App\Controllers\TailorController;
use App\Controllers\JobApplicationController;
use App\Controllers\RetentionController;
use App\Controllers\UsageController;
use App\Controllers\ContactDetailsController;
use App\Prompts\PromptLibrary;
use App\Validation\DraftValidator;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpBadRequestException;
use RuntimeException;

class Routes
{
    /**
     * Handle the register operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public static function register(App $app): void
    {
        $container = $app->getContainer();

        if ($container === null) {
            throw new RuntimeException('Container not available.');
        }

        $app->get('/', function (Request $request, Response $response) use ($container) {
            return $container->get(HomeController::class)->index($request, $response);
        });

        $app->get('/tailor', function (Request $request, Response $response) use ($container) {
            return $container->get(TailorController::class)->show($request, $response);
        });

        $app->post('/tailor/cleanup', function (Request $request, Response $response) use ($container) {
            return $container->get(TailorController::class)->cleanup($request, $response);
        });

        $app->get('/documents', function (Request $request, Response $response) use ($container) {
            return $container->get(DocumentController::class)->index($request, $response);
        });

        $app->get('/documents/{id}', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(DocumentController::class)->show($request, $response, $args);
        });

        $app->get('/documents/{id}/download', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(DocumentController::class)->download($request, $response, $args);
        });

        $app->post('/documents/upload', function (Request $request, Response $response) use ($container) {
            return $container->get(DocumentController::class)->upload($request, $response);
        });

        $app->post('/documents/{id}/delete', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(DocumentController::class)->delete($request, $response, $args);
        });

        $app->get('/profile/contact-details', function (Request $request, Response $response) use ($container) {
            return $container->get(ContactDetailsController::class)->show($request, $response);
        });

        $app->post('/profile/contact-details', function (Request $request, Response $response) use ($container) {
            return $container->get(ContactDetailsController::class)->update($request, $response);
        });

        $app->post('/documents/tailored/{id}/delete', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(DocumentController::class)->deleteGeneration($request, $response, $args);
        });

        $app->post('/documents/tailored/{id}/promote', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(DocumentController::class)->promoteGeneration($request, $response, $args);
        });

        $app->get('/applications', function (Request $request, Response $response) use ($container) {
            return $container->get(JobApplicationController::class)->index($request, $response);
        });

        $app->get('/applications/create', function (Request $request, Response $response) use ($container) {
            return $container->get(JobApplicationController::class)->create($request, $response);
        });

        $app->post('/applications', function (Request $request, Response $response) use ($container) {
            return $container->get(JobApplicationController::class)->store($request, $response);
        });

        $app->post('/applications/{id}/status', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(JobApplicationController::class)->updateStatus($request, $response, $args);
        });

        $app->post('/applications/{id}/generation', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(JobApplicationController::class)->updateGeneration($request, $response, $args);
        });

        $app->post('/applications/{id}/delete', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(JobApplicationController::class)->delete($request, $response, $args);
        });


        $app->get('/auth', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->showLogin($request, $response);

        });

        $app->get('/auth/register', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->showRegister($request, $response);
        });

        $app->post('/auth/register', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->register($request, $response);
        });

        $app->get('/auth/register/verify', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->showRegisterVerify($request, $response);
        });

        $app->post('/auth/register/verify', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->registerVerify($request, $response);
        });

        $app->get('/auth/login', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->showLogin($request, $response);
        });

        $app->post('/auth/login', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->login($request, $response);
        });

        $app->get('/auth/login/verify', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->showLoginVerify($request, $response);
        });

        $app->post('/auth/login/verify', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->loginVerify($request, $response);
        });

        $app->get('/auth/backup-codes', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->showBackupCodes($request, $response);
        });

        $app->post('/auth/backup-codes', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->backupCodes($request, $response);
        });

        $app->post('/auth/logout', function (Request $request, Response $response) use ($container) {
            return $container->get(AuthController::class)->logout($request, $response);
        });

        $app->post('/generations', function (Request $request, Response $response) use ($container) {
            return $container->get(GenerationController::class)->create($request, $response);
        });

        $app->get('/generations/{id}', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(GenerationController::class)->show($request, $response, $args);
        });

        $app->post('/generations/{id}/delete', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(GenerationController::class)->delete($request, $response, $args);
        });

        $app->get('/generations/{id}/download', function (Request $request, Response $response, array $args) use ($container) {
            return $container->get(GenerationDownloadController::class)->download($request, $response, $args);
        });

        $app->get('/healthz', function (Request $request, Response $response): Response {
            $response->getBody()->write('ok');

            return $response->withHeader('Content-Type', 'text/plain');
        });

        $app->get('/prompts', function (Request $request, Response $response): Response {
            $payload = [
                'system' => PromptLibrary::systemPrompt(),
                'tailor' => PromptLibrary::tailorPrompt(),
            ];

            $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->post('/draft/validate', function (Request $request, Response $response): Response {
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


        $app->get('/usage', UsageController::class . ':index');
        $app->get('/usage/data', UsageController::class . ':data');

        $app->get('/retention', function (Request $request, Response $response) use ($container) {
            return $container->get(RetentionController::class)->show($request, $response);
        });

        $app->post('/retention', function (Request $request, Response $response) use ($container) {
            return $container->get(RetentionController::class)->update($request, $response);
        });

    }
}
