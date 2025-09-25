<?php

declare(strict_types=1);

namespace App\Queue;

interface JobHandlerInterface
{
    public function handle(Job $job): void;

    public function onFailure(Job $job, string $error, bool $willRetry): void;
}
