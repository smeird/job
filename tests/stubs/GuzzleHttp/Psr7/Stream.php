<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * In-memory stream implementation used to satisfy the provider contract in tests.
 */
final class Stream implements StreamInterface
{
    /** @var string */
    private $contents;

    /** @var int */
    private $position = 0;

    /**
     * Build the stream wrapper around the provided string buffer.
     */
    public function __construct(string $contents = '')
    {
        $this->contents = $contents;
    }

    /**
     * Cast the stream to its full string contents.
     */
    public function __toString()
    {
        return $this->contents;
    }

    /**
     * Retrieve the remaining contents from the current cursor position.
     */
    public function getContents()
    {
        if ($this->position >= strlen($this->contents)) {
            return '';
        }

        $result = substr($this->contents, $this->position);
        $this->position = strlen($this->contents);

        return $result === false ? '' : $result;
    }

    /**
     * Read a fixed number of bytes from the stream buffer.
     */
    public function read($length)
    {
        $chunk = substr($this->contents, $this->position, (int) $length);

        if ($chunk === false) {
            $chunk = '';
        }

        $this->position += strlen($chunk);

        return $chunk;
    }

    /**
     * Determine whether the stream cursor has reached the end of the buffer.
     */
    public function eof(): bool
    {
        return $this->position >= strlen($this->contents);
    }
}
