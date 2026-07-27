'use strict';

/** Returns the shared long-running-task overlay when it is present on the current page. */
function taskProgressOverlay() {
  return document.getElementById('task-progress');
}

/** Restores the page after browser history navigation or an interrupted submission. */
function resetTaskProgress() {
  const overlay = taskProgressOverlay();
  if (!overlay) return;
  overlay.classList.add('hidden');
  overlay.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('task-is-running');
  document.body.removeAttribute('aria-busy');
  document.querySelectorAll('[data-progress-disabled="true"]').forEach((button) => {
    button.removeAttribute('disabled');
    button.removeAttribute('data-progress-disabled');
  });
  document.querySelectorAll('form[data-progress-active="true"]').forEach((form) => form.removeAttribute('data-progress-active'));
}

/** Presents honest indeterminate feedback and prevents accidental duplicate submissions. */
function showTaskProgress(form) {
  const overlay = taskProgressOverlay();
  if (!overlay || form.dataset.progressActive === 'true') return;
  form.dataset.progressActive = 'true';
  const title = document.getElementById('task-progress-title');
  const message = document.getElementById('task-progress-message');
  if (title) title.textContent = form.dataset.progressTitle || 'Job Tune is working';
  if (message) message.textContent = form.dataset.progressMessage || 'Your request is being processed securely. This may take a moment.';
  overlay.classList.remove('hidden');
  overlay.setAttribute('aria-hidden', 'false');
  document.body.classList.add('task-is-running');
  document.body.setAttribute('aria-busy', 'true');
  window.setTimeout(() => {
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
      button.setAttribute('disabled', '');
      button.setAttribute('data-progress-disabled', 'true');
    });
  }, 0);
}

/** Activates progress feedback only for valid, explicitly marked slow forms. */
function handleProgressSubmission(event) {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.dataset.progressTitle || event.defaultPrevented || !form.checkValidity()) return;
  if (form.dataset.progressActive === 'true') {
    event.preventDefault();
    return;
  }
  showTaskProgress(form);
}

/** Adds consistent feedback to known slow routes whose forms may appear in several server-rendered views. */
function prepareSlowForms() {
  const tasks = [
    { pattern: /^\/career\/import$/, title: 'Extracting grounded career evidence', message: 'Reading the selected CV, checking every proposed fact against its source, and organising the results.' },
    { pattern: /^\/settings\/models\/refresh$/, title: 'Checking available AI models', message: 'Asking the configured provider for compatible models and safely updating the shared catalogue.' },
    { pattern: /^\/tailored\/\d+\/email$/, title: 'Sending your application documents', message: 'Creating the requested Word attachments and handing them securely to the configured mail service.' },
  ];
  document.querySelectorAll('form').forEach((form) => {
    const task = tasks.find((candidate) => candidate.pattern.test(new URL(form.action, window.location.href).pathname));
    if (!task || form.dataset.progressTitle) return;
    form.dataset.progressTitle = task.title;
    form.dataset.progressMessage = task.message;
  });
}

prepareSlowForms();
document.addEventListener('submit', handleProgressSubmission);
window.addEventListener('pageshow', resetTaskProgress);
