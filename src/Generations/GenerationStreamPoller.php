<?php

declare(strict_types=1);

namespace App\Generations;

use DateTimeInterface;

class GenerationStreamPoller
{
    private const TERMINAL_STATUSES = ['completed', 'succeeded', 'success', 'failed', 'cancelled', 'canceled'];

    /** @var GenerationStreamRepository */
    private $repository;

    /** @var int */
    private $generationId;

    /** @var int */
    private $pollIntervalSeconds;

    /** @var int */
    private $heartbeatIntervalSeconds;

    /** @var int */
    private $timeoutSeconds;

    /** @var string|null */
    private $lastStatus = null;

    /** @var int|null */
    private $lastProgress = null;

    /** @var int|null */
    private $lastTokens = null;

    /** @var int|null */
    private $lastCost = null;

    /** @var string|null */
    private $lastError = null;

    /** @var bool */
    private $initialised = false;

    /** @var bool */
    private $terminated = false;

    /** @var float */
    private $lastHeartbeat;

    /** @var float */
    private $startedAt;

    /** @var GenerationStreamSnapshot|null */
    private $primedSnapshot;

    public function __construct(
        GenerationStreamRepository $repository,
        int $generationId,
        int $pollIntervalSeconds = 1,
        int $heartbeatIntervalSeconds = 15,
        int $timeoutSeconds = 300,
        ?GenerationStreamSnapshot $initialSnapshot = null
    ) {
        $this->repository = $repository;
        $this->generationId = $generationId;
        $this->pollIntervalSeconds = $pollIntervalSeconds;
        $this->heartbeatIntervalSeconds = $heartbeatIntervalSeconds;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->startedAt = microtime(true);
        $this->lastHeartbeat = $this->startedAt;
        $this->primedSnapshot = $initialSnapshot;
    }

    public function nextChunk(): ?string
    {
        if ($this->terminated) {
            return null;
        }

        while (true) {
            $snapshot = $this->primedSnapshot ?? $this->repository->fetchSnapshot($this->generationId);
            $this->primedSnapshot = null;

            if ($snapshot === null) {
                $this->terminated = true;

                return $this->formatEvent('error', ['message' => 'Generation not found.']);
            }

            $payload = $this->buildPayload($snapshot);

            if ($payload !== '') {
                if ($this->isTerminalStatus($snapshot->status)) {
                    $this->terminated = true;
                }

                $this->lastHeartbeat = microtime(true);

                return $payload;
            }

            $now = microtime(true);

            if (($now - $this->lastHeartbeat) >= $this->heartbeatIntervalSeconds) {
                $this->lastHeartbeat = $now;

                if ($this->isTerminalStatus($snapshot->status)) {
                    $this->terminated = true;
                }

                return ": ping\n\n";
            }

            if (($now - $this->startedAt) >= $this->timeoutSeconds) {
                $this->terminated = true;

                return $this->formatEvent('error', ['message' => 'Stream timeout.']);
            }

            usleep($this->pollIntervalSeconds * 1000000);
        }
    }

    private function buildPayload(GenerationStreamSnapshot $snapshot): string
    {
        $chunks = [];

        if (!$this->initialised) {
            $this->initialised = true;
            $chunks[] = $this->formatEvent('status', [
                'value' => $snapshot->status,
                'updated_at' => $snapshot->updatedAt->format(DateTimeInterface::ATOM),
            ]);
            $chunks[] = $this->formatEvent('progress', ['percent' => $snapshot->progressPercent]);
            $chunks[] = $this->formatEvent('tokens', [
                'total' => $snapshot->totalTokens,
                'updated_at' => $snapshot->latestOutputAt !== null
                    ? $snapshot->latestOutputAt->format(DateTimeInterface::ATOM)
                    : null,
            ]);
            $chunks[] = $this->formatEvent('cost', [
                'pence' => $snapshot->costPence,
                'updated_at' => $snapshot->updatedAt->format(DateTimeInterface::ATOM),
            ]);

            if ($snapshot->errorMessage !== null && $snapshot->errorMessage !== '') {
                $chunks[] = $this->formatEvent('error', ['message' => $snapshot->errorMessage]);
            }

            $this->lastStatus = $snapshot->status;
            $this->lastProgress = $snapshot->progressPercent;
            $this->lastTokens = $snapshot->totalTokens;
            $this->lastCost = $snapshot->costPence;
            $this->lastError = $snapshot->errorMessage;

            return implode('', $chunks);
        }

        if ($this->lastStatus !== $snapshot->status) {
            $this->lastStatus = $snapshot->status;
            $chunks[] = $this->formatEvent('status', [
                'value' => $snapshot->status,
                'updated_at' => $snapshot->updatedAt->format(DateTimeInterface::ATOM),
            ]);
        }

        if ($this->lastProgress !== $snapshot->progressPercent) {
            $this->lastProgress = $snapshot->progressPercent;
            $chunks[] = $this->formatEvent('progress', ['percent' => $snapshot->progressPercent]);
        }

        if ($this->lastTokens !== $snapshot->totalTokens) {
            $this->lastTokens = $snapshot->totalTokens;
            $chunks[] = $this->formatEvent('tokens', [
                'total' => $snapshot->totalTokens,
                'updated_at' => $snapshot->latestOutputAt !== null
                    ? $snapshot->latestOutputAt->format(DateTimeInterface::ATOM)
                    : null,
            ]);
        }

        if ($this->lastCost !== $snapshot->costPence) {
            $this->lastCost = $snapshot->costPence;
            $chunks[] = $this->formatEvent('cost', [
                'pence' => $snapshot->costPence,
                'updated_at' => $snapshot->updatedAt->format(DateTimeInterface::ATOM),
            ]);
        }

        $errorMessage = $snapshot->errorMessage ?? '';

        if ($errorMessage !== '' && $errorMessage !== $this->lastError) {
            $this->lastError = $errorMessage;
            $chunks[] = $this->formatEvent('error', ['message' => $errorMessage]);
        }

        return implode('', $chunks);
    }

    private function formatEvent(string $event, array $data): string
    {
        $encoded = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return sprintf("event: %s\ndata: %s\n\n", $event, $encoded);
    }

    private function isTerminalStatus(string $status): bool
    {
        return in_array(strtolower($status), self::TERMINAL_STATUSES, true);
    }
}
