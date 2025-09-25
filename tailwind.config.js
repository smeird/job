const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
  content: [
    './resources/views/**/*.php',
    './public/**/*.php',
    './public/**/*.html',
    './src/**/*.php',
  ],
  theme: {
    container: {
      center: true,
      padding: {
        DEFAULT: '1.5rem',
        lg: '3rem',
      },
      screens: {
        '2xl': '1280px',
      },
    },
    extend: {
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
      fontFamily: {
        sans: ['"Inter"', ...defaultTheme.fontFamily.sans],
        display: ['"Cal Sans"', '"Inter"', ...defaultTheme.fontFamily.sans],
        mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
      },
      borderRadius: {
        'xl': '1.25rem',
        '2xl': '1.75rem',
        '3xl': '2.25rem',
      },
      backdropBlur: {
        glass: '18px',
        deep: '28px',
      },
      boxShadow: {
        soft: '0 25px 65px -25px rgba(15, 23, 42, 0.45)',
        'soft-xl': '0 40px 120px -45px rgba(15, 23, 42, 0.55)',
        inset: 'inset 0 1px 0 rgba(255, 255, 255, 0.35)',
      },
      transitionDuration: {
        350: '350ms',
        450: '450ms',
      },
      keyframes: {
        'toast-in': {
          '0%': { transform: 'translateY(100%)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        shimmer: {
          '0%': { backgroundPosition: '200% 0' },
          '100%': { backgroundPosition: '-200% 0' },
        },
        pulse: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '.35' },
        },
      },
      animation: {
        'toast-in': 'toast-in 200ms ease-out',
        shimmer: 'shimmer 2.2s linear infinite',
        'progress-pulse': 'pulse 1.8s ease-in-out infinite',
      },
    },
  },
  plugins: [
    function ({ addBase, addComponents, addVariant, theme }) {
      addVariant('theme-dark', '[data-theme="dark"] &');
      addVariant('theme-light', '[data-theme="light"] &');

      addBase({
        ':root': {
          '--color-bg': '#f1f5f9',
          '--color-surface': 'rgba(255, 255, 255, 0.72)',
          '--color-surface-strong': '#ffffff',
          '--color-border': 'rgba(148, 163, 184, 0.35)',
          '--color-text': '#0f172a',
          '--color-text-muted': '#475569',
          '--color-elevated': '#ffffffeb',
          '--color-focus': 'rgba(99, 102, 241, 0.45)',
          '--color-danger': '#dc2626',
          '--color-warning': '#f59e0b',
          '--color-success': '#16a34a',
          '--color-toast-info': '#2563eb',
          '--blur-card': '18px',
          '--radius-card': '1.75rem',
          '--radius-pill': '9999px',
          '--shadow-card': '0 32px 80px -40px rgba(15, 23, 42, 0.45)',
          colorScheme: 'light',
        },
        ':root[data-theme="dark"]': {
          '--color-bg': '#020817',
          '--color-surface': 'rgba(15, 23, 42, 0.72)',
          '--color-surface-strong': '#0f172a',
          '--color-border': 'rgba(71, 85, 105, 0.55)',
          '--color-text': '#e2e8f0',
          '--color-text-muted': '#94a3b8',
          '--color-elevated': 'rgba(15, 23, 42, 0.95)',
          '--color-focus': 'rgba(129, 140, 248, 0.55)',
          '--color-danger': '#f87171',
          '--color-warning': '#fbbf24',
          '--color-success': '#34d399',
          '--color-toast-info': '#60a5fa',
          colorScheme: 'dark',
        },
        '*, *::before, *::after': {
          boxSizing: 'border-box',
        },
        body: {
          backgroundColor: 'var(--color-bg)',
          color: 'var(--color-text)',
          fontFamily: theme('fontFamily.sans').join(', '),
          minHeight: '100vh',
          margin: '0',
          transitionProperty: 'background-color, color',
          transitionDuration: theme('transitionDuration.350'),
          letterSpacing: '-0.01em',
        },
        a: {
          color: theme('colors.primary.500'),
          textDecoration: 'none',
          fontWeight: '500',
        },
        'a:hover': {
          color: theme('colors.primary.400'),
        },
        'button:focus-visible, a:focus-visible': {
          outline: '3px solid var(--color-focus)',
          outlineOffset: '3px',
          borderRadius: '0.75rem',
        },
        '::selection': {
          backgroundColor: theme('colors.primary.200'),
          color: theme('colors.primary.900'),
        },
      });

      addComponents({
        '.glass-card': {
          background: 'linear-gradient(135deg, rgba(255, 255, 255, 0.82), rgba(241, 245, 249, 0.6))',
          borderRadius: 'var(--radius-card)',
          border: '1px solid var(--color-border)',
          boxShadow: 'var(--shadow-card)',
          backdropFilter: 'blur(var(--blur-card))',
          padding: '1.75rem',
          color: 'inherit',
        },
        ':root[data-theme="dark"] .glass-card': {
          background: 'linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(2, 8, 23, 0.78))',
          boxShadow: '0 24px 65px -30px rgba(15, 23, 42, 0.9)',
        },
        '.btn-gradient': {
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          gap: '0.5rem',
          padding: '0.75rem 1.5rem',
          fontWeight: '600',
          borderRadius: 'var(--radius-pill)',
          color: '#fff',
          backgroundImage: 'linear-gradient(135deg, #6366f1 0%, #4338ca 100%)',
          boxShadow: '0 15px 30px -10px rgba(79, 70, 229, 0.55)',
          transition: 'transform 250ms ease, box-shadow 250ms ease',
        },
        '.btn-gradient:hover': {
          transform: 'translateY(-2px) scale(1.01)',
          boxShadow: '0 22px 45px -18px rgba(79, 70, 229, 0.65)',
        },
        '.btn-gradient:active': {
          transform: 'translateY(0)',
          boxShadow: '0 18px 25px -18px rgba(79, 70, 229, 0.75)',
        },
        ':root[data-theme="dark"] .btn-gradient': {
          boxShadow: '0 25px 45px -15px rgba(99, 102, 241, 0.55)',
        },
        '.soft-shadow': {
          boxShadow: 'var(--shadow-card)',
        },
        '.focus-ring': {
          position: 'relative',
        },
        '.focus-ring::after': {
          content: '""',
          position: 'absolute',
          inset: '-6px',
          borderRadius: 'inherit',
          border: '2px solid transparent',
          transition: 'border-color 200ms ease',
        },
        '.focus-ring:focus-visible::after': {
          borderColor: 'var(--color-focus)',
        },
        '.token-progress': {
          position: 'relative',
          backgroundColor: 'rgba(148, 163, 184, 0.35)',
          height: '0.75rem',
          borderRadius: '9999px',
          overflow: 'hidden',
        },
        '.token-progress::after': {
          content: '""',
          position: 'absolute',
          inset: '0',
          borderRadius: 'inherit',
          backgroundImage: 'linear-gradient(135deg, #6366f1 0%, #14b8a6 100%)',
          width: 'var(--progress, 0%)',
          transition: 'width 400ms ease',
        },
        '.token-toast': {
          display: 'flex',
          gap: '0.75rem',
          alignItems: 'flex-start',
          padding: '1rem 1.25rem',
          borderRadius: '1rem',
          backgroundColor: 'var(--color-elevated)',
          border: '1px solid var(--color-border)',
          boxShadow: '0 20px 60px -40px rgba(15, 23, 42, 0.55)',
          backdropFilter: 'blur(18px)',
        },
      });
    },
  ],
};
