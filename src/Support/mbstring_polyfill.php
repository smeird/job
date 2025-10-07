<?php

declare(strict_types=1);

if (!function_exists('mb_strcut')) {
    /**
     * Provide a lightweight polyfill for mb_strcut when the mbstring extension is unavailable.
     *
     * The fallback relies on substr to deliver a byte-wise slice so conversion routines that
     * depend on mb_strcut can continue operating in limited hosting environments.
     */
    function mb_strcut($string, $start, $length = null, $encoding = null)
    {
        $start = (int) $start;
        $length = $length !== null ? (int) $length : null;

        if ($start < 0) {
            $start = 0;
        }

        $slice = $length === null
            ? substr($string, $start)
            : substr($string, $start, $length);

        return $slice === false ? '' : $slice;
    }
}
