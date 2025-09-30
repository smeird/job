#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\AI\OpenAIProvider;
use App\Settings\SiteSettingsRepository;

require __DIR__ . '/../autoload.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php bin/openai_plan_debug.php <user_id> <job_description_file> <cv_markdown_file>" . PHP_EOL);
    exit(1);
}

$userId = (int) $argv[1];
$jobPath = (string) $argv[2];
$cvPath = (string) $argv[3];

if (!is_file($jobPath)) {
    fwrite(STDERR, "Job description file not found: {$jobPath}" . PHP_EOL);
    exit(1);
}

if (!is_file($cvPath)) {
    fwrite(STDERR, "CV markdown file not found: {$cvPath}" . PHP_EOL);
    exit(1);
}

$jobDescription = (string) file_get_contents($jobPath);
$cvMarkdown = (string) file_get_contents($cvPath);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$settingsRepository = new SiteSettingsRepository($pdo);

$provider = new OpenAIProvider($userId, null, $pdo, $settingsRepository);

echo 'Starting plan generation diagnostic...' . PHP_EOL;

echo 'Job description length: ' . strlen($jobDescription) . PHP_EOL;

echo 'CV markdown length: ' . strlen($cvMarkdown) . PHP_EOL;

try {
    $plan = $provider->plan($jobDescription, $cvMarkdown);
    echo 'Plan generation succeeded.' . PHP_EOL;
    echo $plan . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Plan generation failed: ' . $exception->getMessage() . PHP_EOL);
    $previous = $exception->getPrevious();

    if ($previous !== null) {
        fwrite(STDERR, 'Previous exception: ' . get_class($previous) . ' - ' . $previous->getMessage() . PHP_EOL);
    }

    exit(2);
}
