<?php

declare(strict_types=1);

namespace App\Security;

final class CspConfig
{
    public const ALPINE_INIT_SCRIPT = "document.addEventListener('alpine:init',function(){document.documentElement.dataset.alpineReady='true';});";

    /**
     * Handle the alpine init hash operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public static function alpineInitHash(): string
    {
        return "'sha256-" . base64_encode(hash('sha256', self::ALPINE_INIT_SCRIPT, true)) . "'";
    }
}
