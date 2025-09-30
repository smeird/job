<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Lightweight request representation used to satisfy the provider contract in tests.
 */
final class Request implements RequestInterface
{
    /** @var string */
    private $method;

    /** @var string */
    private $uri;

    /** @var array<string, string> */
    private $headers;

    /** @var StreamInterface */
    private $body;

    /**
     * @param array<string, string> $headers
     */
    /**
     * Initialise the request representation with its method, URI, and payload.
     */
    public function __construct(string $method, string $uri, array $headers = [], string $body = '')
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->headers = $headers;
        $this->body = new Stream($body);
    }

    /**
     * Return the HTTP verb originally supplied for the request.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Return the URI that the request targets.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Expose the backing stream so tests can inspect the payload.
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * Fetch a header value when one was supplied during construction.
     */
    public function getHeaderLine(string $name): string
    {
        return $this->headers[$name] ?? '';
    }
}
