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
        $app->get('/healthz', static function (Request $request, Response $response): Response {
            $response->getBody()->write('ok');

            return $response->withHeader('Content-Type', 'text/plain');
        });
    }
}
