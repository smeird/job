<?php

declare(strict_types=1);

if (!function_exists('str_contains')) {
    /**
     * Handle the str contains operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    function str_contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    /**
     * Handle the str starts with operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    function str_starts_with(string $haystack, string $needle): bool
    {
        $needleLength = strlen($needle);

        if ($needleLength === 0) {
            return true;
        }

        return substr($haystack, 0, $needleLength) === $needle;
    }
}

if (!function_exists('str_ends_with')) {
    /**
     * Handle the str ends with operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    function str_ends_with(string $haystack, string $needle): bool
    {
        $needleLength = strlen($needle);

        if ($needleLength === 0) {
            return true;
        }

        if ($needleLength > strlen($haystack)) {
            return false;
        }

        return substr($haystack, -$needleLength) === $needle;
    }
}
