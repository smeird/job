<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

/**
 * Minimal promise contract required by the client interface stub.
 */
interface PromiseInterface
{
    /**
     * Register callbacks for completion or failure of the asynchronous operation.
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null);
}
