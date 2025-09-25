<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Routes;
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
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

Bootstrap::init($app, $rootPath);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

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
