<?php

declare(strict_types=1);

namespace App;

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

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
    }
}
