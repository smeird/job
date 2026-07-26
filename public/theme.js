'use strict';

/** Synchronises the toggle label and browser chrome with the active colour theme. */
function renderThemeControl() {
  const dark = document.documentElement.classList.contains('dark');
  const button = document.getElementById('theme-toggle');
  if (button) {
    button.textContent = dark ? '☀' : '◐';
    button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    button.setAttribute('aria-pressed', String(dark));
    button.setAttribute('title', dark ? 'Use light mode' : 'Use dark mode');
  }
  const themeColour = document.querySelector('meta[name="theme-color"]');
  if (themeColour) themeColour.setAttribute('content', dark ? '#020617' : '#f8fafc');
}

/** Toggles and persists the user's preferred light or dark presentation. */
function toggleTheme() {
  const dark = document.documentElement.classList.toggle('dark');
  localStorage.setItem('job-tune-theme', dark ? 'dark' : 'light');
  renderThemeControl();
}

document.getElementById('theme-toggle')?.addEventListener('click', toggleTheme);
renderThemeControl();
