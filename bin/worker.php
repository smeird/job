#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\DB;
use App\Queue\Handler\TailorCvJobHandler;
use App\Queue\JobRepository;
use App\Queue\JobWorker;

require __DIR__ . '/../vendor/autoload.php';

if (!extension_loaded('pcntl')) {
    fwrite(STDERR, "The pcntl extension is required to run the worker." . PHP_EOL);
    exit(1);
}

pcntl_async_signals(true);

$stop = false;

pcntl_signal(SIGTERM, static function () use (&$stop): void {
    $stop = true;
});
pcntl_signal(SIGINT, static function () use (&$stop): void {
    $stop = true;
});

$pdo = DB::getConnection();
$repository = new JobRepository($pdo);
$handlers = [
    'tailor_cv' => new TailorCvJobHandler($pdo),
];
$worker = new JobWorker($repository, $handlers, 5);

$backoffSeconds = 1;
$maxIdleBackoff = 30;

while (!$stop) {
    try {
        $job = $repository->reserveNextPending();
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf('[%s] Failed to reserve job: %s%s', date('c'), $exception->getMessage(), PHP_EOL));
        sleep($backoffSeconds);
        $backoffSeconds = min($backoffSeconds * 2, $maxIdleBackoff);
        continue;
    }

    if ($job === null) {
        sleep($backoffSeconds);
        $backoffSeconds = min($backoffSeconds * 2, $maxIdleBackoff);
        continue;
    }

    $backoffSeconds = 1;

    try {
        $worker->process($job);
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf('[%s] Unexpected worker error: %s%s', date('c'), $exception->getMessage(), PHP_EOL));
    }

    if ($stop) {
        break;
    }
}

fwrite(STDOUT, 'Worker shutting down' . PHP_EOL);
