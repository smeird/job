<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use Slim\App;

class Bootstrap
{
    public static function init(App $app, string $rootPath): string
    {
        self::loadEnvironment($rootPath);
        $appUrl = self::ensureAppUrl();

        return $appUrl;
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
}
