<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Lightweight response representation used to satisfy the provider contract in tests.
 */
final class Response implements ResponseInterface
{
    /** @var int */
    private $statusCode;

    /** @var array<string, string> */
    private $headers;

    /** @var StreamInterface */
    private $body;

    /**
     * @param array<string, string> $headers
     */
    /**
     * Create a lightweight response wrapper for the supplied payload.
     */
    public function __construct(int $status = 200, array $headers = [], string $body = '')
    {
        $this->statusCode = $status;
        $this->headers = $headers;
        $this->body = new Stream($body);
    }

    /**
     * Provide the HTTP status code associated with the response.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Return the stream wrapper containing the response body.
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * Access a single header value by name when one has been defined.
     */
    public function getHeaderLine(string $name): string
    {
        return $this->headers[$name] ?? '';
    }
}
