<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;

class Bootstrap
{
    public static function init(App $app, string $rootPath): void
    {
        self::loadEnvironment($rootPath);
        $appUrl = self::ensureAppUrl();
        self::registerSecurityHeaders($app, $appUrl);
    }

    private static function loadEnvironment(string $rootPath): void
    {
        if (!is_dir($rootPath)) {
            return;
        }

        if (!file_exists($rootPath . DIRECTORY_SEPARATOR . '.env')) {
            Dotenv::createImmutable($rootPath)->safeLoad();

            return;
        }

        Dotenv::createImmutable($rootPath)->safeLoad();
    }

    private static function ensureAppUrl(): string
    {
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'https://job.smeird.com';

        putenv('APP_URL=' . $appUrl);
        $_ENV['APP_URL'] = $appUrl;
        $_SERVER['APP_URL'] = $appUrl;

        return $appUrl;
    }

    private static function registerSecurityHeaders(App $app, string $appUrl): void
    {
        $headers = [
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self' {$appUrl}",
                "base-uri 'self'",
                "connect-src 'self' {$appUrl} https://code.highcharts.com",
                "frame-ancestors 'none'",
                "img-src 'self' data: {$appUrl}",
                "script-src 'self' https://cdn.jsdelivr.net https://code.highcharts.com",
                "style-src 'self' https://cdn.jsdelivr.net",
                "form-action 'self' {$appUrl}",
            ]),
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
        ];

        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($headers): ResponseInterface {
            $response = $handler->handle($request);

            foreach ($headers as $name => $value) {
                if (!$response->hasHeader($name)) {
                    $response = $response->withHeader($name, $value);
                }
            }

            return $response;
        });
    }
}
