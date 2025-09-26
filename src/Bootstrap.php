<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use Slim\App;

/**
 * Bootstrap coordinates shared application initialization routines such as
 * reading the environment configuration and preparing global settings that
 * other services expect to exist before handling a request.
 */
class Bootstrap
{
    /**
     * Initialize the Slim application by loading the environment variables and
     * ensuring the application URL is available for downstream services.
     */
    public static function init(App $app, string $rootPath): string
    {
        self::loadEnvironment($rootPath);
        $appUrl = self::ensureAppUrl();

        return $appUrl;
    }

    /**
     * Load environment variables from the provided project root when the
     * directory and .env file are available, defaulting to a safe load so the
     * application can continue when optional values are missing.
     */
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

    /**
     * Guarantee that the APP_URL configuration is populated in PHP's global
     * environment, falling back to a sensible default when it is not already
     * defined.
     */
    private static function ensureAppUrl(): string
    {
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'https://job.smeird.com';

        putenv('APP_URL=' . $appUrl);
        $_ENV['APP_URL'] = $appUrl;
        $_SERVER['APP_URL'] = $appUrl;

        return $appUrl;
    }

}
