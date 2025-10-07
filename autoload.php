<?php

declare(strict_types=1);

require __DIR__ . '/src/Support/mbstring_polyfill.php';

$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    $message = 'Application dependencies are missing. Please run "composer install" from the project root.';

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        fwrite(STDERR, $message . PHP_EOL);
    } else {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Application Error</title>';
        echo '<style>body{font-family:system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;margin:2rem;color:#1f2937;background:#f9fafb;}';
        echo '.card{max-width:32rem;margin:0 auto;background:#fff;padding:2.5rem 2rem;border-radius:1rem;box-shadow:0 10px 30px rgba(15,23,42,0.1);}';
        echo 'h1{font-size:1.5rem;margin-bottom:1rem;color:#111827;}p{margin-bottom:0.75rem;line-height:1.6;}</style></head><body>';
        echo '<div class="card"><h1>Application dependencies missing</h1>';
        echo '<p>The application could not be started because the Composer autoloader was not found.</p>';
        echo '<p>Please install the project dependencies by running <code>composer install</code> from the project root directory.</p>';
        echo '<p>Once the dependencies have been installed the site will load normally.</p></div></body></html>';
    }

    exit(1);
}

return require $autoloadPath;
