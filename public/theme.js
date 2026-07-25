'use strict';

/** Toggles and persists the user's preferred light or dark presentation. */
function toggleTheme() {
  const dark = document.documentElement.classList.toggle('dark');
  localStorage.setItem('job-tune-theme', dark ? 'dark' : 'light');
}

document.getElementById('theme-toggle')?.addEventListener('click', toggleTheme);
