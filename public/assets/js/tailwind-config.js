/**
 * Immediately invoke the configuration wrapper so Tailwind's CDN build picks up the
 * design tokens as soon as the script loads.
 */
(function () {
    'use strict';

    /**
     * Configure the Tailwind CDN runtime with the design system overrides.
     *
     * Exporting the configuration ahead of the CDN bundle preserves compatibility
     * with the previous inline script while satisfying the stricter CSP rules.
     */
    window.tailwind = window.tailwind || {};
    window.tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                fontFamily: {
                    sans: ['"Inter"', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'sans-serif'],
                    display: ['"Cal Sans"', '"Inter"', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'sans-serif'],
                    mono: ['"JetBrains Mono"', 'monospace'],
                },
                borderRadius: {
                    xl: '1.25rem',
                    '2xl': '1.75rem',
                    '3xl': '2.25rem',
                },
                boxShadow: {
                    soft: '0 25px 65px -25px rgba(15, 23, 42, 0.45)',
                    'soft-xl': '0 40px 120px -45px rgba(15, 23, 42, 0.55)',
                    inset: 'inset 0 1px 0 rgba(255, 255, 255, 0.35)',
                },
                colors: {
                    primary: {
                        50: '#eef2ff',
                        100: '#e0e7ff',
                        200: '#c7d2fe',
                        300: '#a5b4fc',
                        400: '#818cf8',
                        500: '#6366f1',
                        600: '#4f46e5',
                        700: '#4338ca',
                        800: '#3730a3',
                        900: '#312e81',
                    },
                    accent: {
                        500: '#14b8a6',
                        600: '#0d9488',
                        700: '#0f766e',
                    },
                    surface: {
                        light: 'rgba(255, 255, 255, 0.72)',
                        dark: 'rgba(15, 23, 42, 0.72)',
                    },
                },
                transitionDuration: {
                    350: '350ms',
                    450: '450ms',
                },
            },
        },
    };
})();
