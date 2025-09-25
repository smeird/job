<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Controllers\AuthController;
use App\Controllers\GenerationDownloadController;
use App\Controllers\HomeController;

use App\Controllers\UsageController;

use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\Migrator;
use App\Middleware\SessionMiddleware;
use App\Routes;
use App\Services\AuthService;
use App\Services\LogMailer;
use App\Services\MailerInterface;
use App\Services\RateLimiter;
use App\Services\RetentionPolicyService;
use App\Services\SmtpMailer;
use App\Services\UsageService;
use App\Views\Renderer;
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;

require_once __DIR__ . '/../vendor/autoload.php';

$rootPath = dirname(__DIR__);

ini_set('upload_max_filesize', '1M');
ini_set('post_max_size', '2M');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_ENV['APP_COOKIE_DOMAIN'] ?? getenv('APP_COOKIE_DOMAIN') ?: 'job.smeird.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$container = new Container();

$container->set(\PDO::class, static function (): \PDO {
    $connection = new Connection();

    return $connection->getPdo();
});

$container->set(Renderer::class, static function () use ($rootPath): Renderer {
    return new Renderer($rootPath . '/resources/views');
});

$container->set(MailerInterface::class, static function () use ($rootPath): MailerInterface {
    $smtpHost = getenv('SMTP_HOST');

    if (!empty($smtpHost)) {
        $smtpPort = (int) (getenv('SMTP_PORT') ?: 25);
        $from = getenv('SMTP_FROM') ?: 'no-reply@job.smeird.com';
        $username = getenv('SMTP_USERNAME') ?: null;
        $password = getenv('SMTP_PASSWORD') ?: null;
        $useTls = filter_var(getenv('SMTP_TLS') ?: false, FILTER_VALIDATE_BOOL);

        return new SmtpMailer($smtpHost, $smtpPort, $from, $username, $password, $useTls);
    }

    $logPath = getenv('MAIL_LOG_PATH') ?: $rootPath . '/storage/logs/mail.log';

    return new LogMailer($logPath);
});

$container->set('rateLimiter.request', static function (Container $c): RateLimiter {
    return new RateLimiter($c->get(\PDO::class), 5, new \DateInterval('PT10M'));
});

$container->set('rateLimiter.verify', static function (Container $c): RateLimiter {
    return new RateLimiter($c->get(\PDO::class), 10, new \DateInterval('PT10M'));
});

$container->set(AuthService::class, static function (Container $c): AuthService {
    return new AuthService(
        $c->get(\PDO::class),
        $c->get(MailerInterface::class),
        $c->get('rateLimiter.request'),
        $c->get('rateLimiter.verify')
    );
});

$container->set(AuthController::class, static function (Container $c): AuthController {
    return new AuthController($c->get(AuthService::class), $c->get(Renderer::class));
});

$container->set(HomeController::class, static function (Container $c): HomeController {
    return new HomeController($c->get(Renderer::class));
});


$container->set(UsageService::class, static function (Container $c): UsageService {
    return new UsageService($c->get(\PDO::class));
});

$container->set(UsageController::class, static function (Container $c): UsageController {
    return new UsageController($c->get(UsageService::class), $c->get(Renderer::class));

});

$container->set(SessionMiddleware::class, static function (Container $c): SessionMiddleware {
    return new SessionMiddleware($c->get(AuthService::class));
});

$container->set(GenerationDownloadService::class, static function (Container $c): GenerationDownloadService {
    return new GenerationDownloadService($c->get(\PDO::class));
});

$container->set(GenerationTokenService::class, static function (): GenerationTokenService {
    $secret = getenv('DOWNLOAD_TOKEN_SECRET') ?: getenv('APP_KEY') ?: '';

    if ($secret === '') {
        throw new RuntimeException('DOWNLOAD_TOKEN_SECRET or APP_KEY must be configured.');
    }

    $ttl = (int) (getenv('DOWNLOAD_TOKEN_TTL') ?: 300);

    if ($ttl <= 0) {
        $ttl = 300;
    }

    return new GenerationTokenService($secret, $ttl);
});

$container->set(GenerationDownloadController::class, static function (Container $c): GenerationDownloadController {
    return new GenerationDownloadController(
        $c->get(GenerationDownloadService::class),
        $c->get(GenerationTokenService::class)
    );
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$migrator = new Migrator($container->get(\PDO::class));
$migrator->migrate();

Bootstrap::init($app, $rootPath);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add($container->get(SessionMiddleware::class));

$displayErrorDetails = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);
$errorMiddleware = new ErrorMiddleware(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    $displayErrorDetails,
    true,
    true
);
$app->add($errorMiddleware);

Routes::register($app);

$app->run();
