<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Controllers\AuthController;
use App\Controllers\GenerationDownloadController;
use App\Controllers\HomeController;
use App\Controllers\RetentionController;
use App\Documents\DocumentRepository;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\Migrator;
use App\Middleware\CsrfMiddleware;
use App\Middleware\InputValidationMiddleware;
use App\Middleware\PathThrottleMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use App\Routes;
use App\Services\AuthService;
use App\Services\AuditLogger;
use App\Services\RateLimiter;
use App\Services\RetentionPolicyService;
use App\Services\UsageService;
use App\Views\Renderer;
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;
use App\Controllers\GenerationController;
use App\Generations\GenerationRepository;
use App\Generations\GenerationDownloadService;
use App\Generations\GenerationTokenService;

require_once __DIR__ . '/../autoload.php';

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

$container->set(DocumentRepository::class, static function (Container $c): DocumentRepository {
    return new DocumentRepository($c->get(\PDO::class));
});

$container->set(GenerationRepository::class, static function (Container $c): GenerationRepository {
    return new GenerationRepository($c->get(\PDO::class));
});

$container->set(AuditLogger::class, static function (Container $c): AuditLogger {
    return new AuditLogger($c->get(\PDO::class));
});

$container->set('rateLimiter.request', static function (Container $c): RateLimiter {
    return new RateLimiter($c->get(\PDO::class), $c->get(AuditLogger::class), 5, new \DateInterval('PT10M'));
});

$container->set('rateLimiter.verify', static function (Container $c): RateLimiter {
    return new RateLimiter($c->get(\PDO::class), $c->get(AuditLogger::class), 10, new \DateInterval('PT10M'));
});

$container->set('rateLimiter.route.auth', static function (Container $c): RateLimiter {
    return new RateLimiter($c->get(\PDO::class), $c->get(AuditLogger::class), 20, new \DateInterval('PT5M'));
});

$container->set('rateLimiter.route.upload', static function (Container $c): RateLimiter {
    return new RateLimiter($c->get(\PDO::class), $c->get(AuditLogger::class), 10, new \DateInterval('PT10M'));
});

$container->set(AuthService::class, static function (Container $c): AuthService {
    return new AuthService(
        $c->get(\PDO::class),
        $c->get('rateLimiter.request'),
        $c->get('rateLimiter.verify'),
        $c->get(AuditLogger::class)
    );
});

$container->set(AuthController::class, static function (Container $c): AuthController {
    return new AuthController($c->get(AuthService::class), $c->get(Renderer::class));
});

$container->set(HomeController::class, static function (Container $c): HomeController {
    return new HomeController(
        $c->get(Renderer::class),
        $c->get(DocumentRepository::class),
        $c->get(GenerationRepository::class)
    );
});

$container->set(GenerationController::class, static function (Container $c): GenerationController {
    return new GenerationController(
        $c->get(GenerationRepository::class),
        $c->get(DocumentRepository::class)
    );
});


$container->set(UsageService::class, static function (Container $c): UsageService {
    return new UsageService($c->get(\PDO::class));
});

$container->set(UsageController::class, static function (Container $c): UsageController {
    return new UsageController($c->get(UsageService::class), $c->get(Renderer::class));

});

$container->set(RetentionPolicyService::class, static function (Container $c): RetentionPolicyService {
    return new RetentionPolicyService($c->get(\PDO::class));
});

$container->set(RetentionController::class, static function (Container $c): RetentionController {
    return new RetentionController(
        $c->get(Renderer::class),
        $c->get(RetentionPolicyService::class)
    );
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

$appUrl = Bootstrap::init($app, $rootPath);

$securityHeadersMiddleware = new SecurityHeadersMiddleware($appUrl);
$csrfMiddleware = new CsrfMiddleware($app->getResponseFactory(), $container->get(AuditLogger::class));
$inputValidationMiddleware = new InputValidationMiddleware($app->getResponseFactory(), $container->get(AuditLogger::class));
$pathThrottleMiddleware = new PathThrottleMiddleware(
    $container->get('rateLimiter.route.auth'),
    $container->get('rateLimiter.route.upload'),
    $app->getResponseFactory(),
    $container->get(AuditLogger::class)
);

$app->addRoutingMiddleware();
$app->add($securityHeadersMiddleware);
$app->add($csrfMiddleware);
$app->add($pathThrottleMiddleware);
$app->add($inputValidationMiddleware);
$app->addBodyParsingMiddleware();
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
