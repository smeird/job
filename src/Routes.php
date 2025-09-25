<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

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
    }
}
