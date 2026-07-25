'use strict';

/** Reads one cookie value without exposing the HTTP-only session cookie. */
function readCookie(name) {
  const entry = document.cookie.split(';').map((part) => part.trim()).find((part) => part.startsWith(`${name}=`));
  return entry ? entry.slice(name.length + 1) : '';
}

/** Adds the current CSRF value to one authenticated form before submission. */
function protectForm(form) {
  if ((form.method || 'get').toLowerCase() !== 'post' || form.action.includes('/auth/')) return;
  const field = document.createElement('input');
  field.type = 'hidden';
  field.name = 'csrfToken';
  field.value = readCookie('job_tune_csrf');
  form.appendChild(field);
}

document.querySelectorAll('form').forEach(protectForm);
