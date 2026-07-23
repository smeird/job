<?php

declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestedFile = is_string($requestPath) ? realpath(__DIR__ . $requestPath) : false;
$publicPrefix = __DIR__ . DIRECTORY_SEPARATOR;

if (is_string($requestedFile)
    && strpos($requestedFile, $publicPrefix) === 0
    && is_file($requestedFile)
) {
    return false;
}

require __DIR__ . '/index.php';
