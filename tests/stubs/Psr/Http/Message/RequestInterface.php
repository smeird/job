<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Minimal HTTP request contract used by the test doubles.
 */
interface RequestInterface
{
    /**
     * Retrieve the HTTP method associated with the request.
     */
    public function getMethod(): string;

    /**
     * @return string
     */
    public function getUri();

    /**
     * Retrieve the body stream associated with the request.
     */
    public function getBody(): StreamInterface;

    /**
     * Fetch the first header value for the supplied header name.
     */
    public function getHeaderLine(string $name): string;
}
