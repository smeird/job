#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\DB;
use App\Services\RetentionPolicyService;
use Dotenv\Dotenv;

require __DIR__ . '/../autoload.php';

$rootPath = dirname(__DIR__);

if (is_dir($rootPath)) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

try {
    $pdo = DB::getConnection();
    $retentionService = new RetentionPolicyService($pdo);
    $policy = $retentionService->getPolicy();

    $purgeAfterDays = (int) $policy['purge_after_days'];
    $applyTo = $policy['apply_to'];

    if ($purgeAfterDays < 1) {
        echo "Retention policy disabled. Nothing to purge.\n";
        exit(0);
    }

    if (!is_array($applyTo) || $applyTo === []) {
        echo "No data types selected for retention. Nothing to purge.\n";
        exit(0);
    }

    $interval = new DateInterval(sprintf('P%dD', $purgeAfterDays));
    $cutoff = (new DateTimeImmutable('now'))->sub($interval)->format('Y-m-d H:i:s');

    $resources = [
        'documents' => ['table' => 'documents', 'column' => 'created_at'],
        'generation_outputs' => ['table' => 'generation_outputs', 'column' => 'created_at'],
        'api_usage' => ['table' => 'api_usage', 'column' => 'created_at'],
        'audit_logs' => ['table' => 'audit_logs', 'column' => 'created_at'],
    ];

    foreach ($applyTo as $resource) {
        if (!isset($resources[$resource])) {
            continue;
        }

        $table = $resources[$resource]['table'];
        $column = $resources[$resource]['column'];

        $statement = $pdo->prepare(sprintf('DELETE FROM %s WHERE %s < :cutoff', $table, $column));
        $statement->bindValue(':cutoff', $cutoff);
        $statement->execute();

        $count = $statement->rowCount();
        printf("Purged %d row%s from %s.\n", $count, $count === 1 ? '' : 's', $table);
    }

    echo sprintf("Retention purge completed using cutoff %s.\n", $cutoff);
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf("Retention purge failed: %s\n", $throwable->getMessage()));
    exit(1);
}
