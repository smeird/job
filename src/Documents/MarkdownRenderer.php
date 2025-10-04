<?php

declare(strict_types=1);

namespace App\Documents;

use League\CommonMark\CommonMarkConverter;

class MarkdownRenderer
{
    /** @var CommonMarkConverter */
    private $converter;

    /**
     * Construct the renderer with a CommonMark converter instance.
     *
     * Accepting an optional converter keeps the class easy to test while
     * guaranteeing safe defaults for production rendering.
     */
    public function __construct(?CommonMarkConverter $converter = null)
    {
        $this->converter = $converter ?? new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Convert the supplied markdown into sanitized HTML.
     *
     * Returning a string keeps the view layer simple while ensuring markdown
     * documents display with full formatting support.
     */
    public function toHtml(string $markdown): string
    {
        return $this->converter->convertToHtml($markdown);
    }
}
