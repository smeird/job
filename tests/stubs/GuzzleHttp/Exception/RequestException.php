<?php

declare(strict_types=1);

namespace GuzzleHttp\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Lightweight RequestException surrogate that exposes request and response context.
 */
class RequestException extends RuntimeException
{
    /** @var RequestInterface */
    private $request;

    /** @var ResponseInterface|null */
    private $response;

    /**
     * Capture the request and optional response associated with a failure.
     */
    public function __construct(
        string $message,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?RuntimeException $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Expose the request that triggered the failure.
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Provide the response returned by the remote API when available.
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
