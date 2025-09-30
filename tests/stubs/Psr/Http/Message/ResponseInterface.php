<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Minimal HTTP response contract used by the test doubles.
 */
interface ResponseInterface
{
    /**
     * Retrieve the status code from the HTTP response message.
     */
    public function getStatusCode(): int;

    /**
     * Retrieve the body stream for the HTTP response message.
     */
    public function getBody(): StreamInterface;

    /**
     * Fetch the first header value for the supplied header name.
     */
    public function getHeaderLine(string $name): string;
}
