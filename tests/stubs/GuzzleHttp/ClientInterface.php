<?php

declare(strict_types=1);

namespace GuzzleHttp;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Minimal subset of the HTTP client contract required by the unit test doubles.
 */
interface ClientInterface
{
    /**
     * Dispatch a fully formed request synchronously.
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface;

    /**
     * Dispatch a fully formed request asynchronously.
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface;

    /**
     * Issue a request based on a method, URI, and option array.
     */
    public function request($method, $uri, array $options = []): ResponseInterface;

    /**
     * Issue an asynchronous request based on a method, URI, and option array.
     */
    public function requestAsync($method, $uri, array $options = []): PromiseInterface;

    /**
     * @param string|null $option
     * @return mixed
     */
    public function getConfig($option = null);
}
