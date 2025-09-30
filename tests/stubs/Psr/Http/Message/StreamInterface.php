<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Minimal stream contract used by the test doubles.
 */
interface StreamInterface
{
    /**
     * Convert the stream contents to a string representation.
     */
    public function __toString();

    /**
     * @return string
     */
    public function getContents();

    /**
     * @param int $length
     * @return string
     */
    public function read($length);

    /**
     * Determine whether the end of the stream has been reached.
     */
    public function eof(): bool;
}
