'use strict';

const passkeyStatus = document.getElementById('passkey-status');

/** Updates the visible ceremony state without exposing credential details. */
function showPasskeyStatus(message, failed = false) {
  passkeyStatus.textContent = message;
  passkeyStatus.className = `mt-4 text-sm ${failed ? 'text-rose-600 dark:text-rose-300' : 'text-cyan-700 dark:text-cyan-300'}`;
}

/** Parses the small JSON contract shared by every passkey endpoint. */
async function parsePasskeyResponse(response) {
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.error || 'The passkey request failed.');
  return payload;
}

/** Starts discoverable-credential authentication directly from a user gesture. */
async function signInWithPasskey() {
  try {
    showPasskeyStatus('Waiting for your passkey…');
    const ceremony = await parsePasskeyResponse(await fetch('/auth/passkeys/authenticate/options', { method: 'POST' }));
    const credential = await SimpleWebAuthnBrowser.startAuthentication({ optionsJSON: ceremony.options });
    await parsePasskeyResponse(await fetch('/auth/passkeys/authenticate/verify', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: ceremony.token, credential }) }));
    window.location.assign('/');
  } catch (error) {
    showPasskeyStatus(error instanceof Error ? error.message : 'Passkey sign-in failed.', true);
  }
}

/** Starts passkey registration for a new account or the current signed-in user. */
async function registerPasskey() {
  try {
    const emailInput = document.getElementById('passkey-email');
    const email = emailInput ? emailInput.value.trim() : undefined;
    showPasskeyStatus('Preparing your new passkey…');
    const ceremony = await parsePasskeyResponse(await fetch('/auth/passkeys/register/options', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email }) }));
    const credential = await SimpleWebAuthnBrowser.startRegistration({ optionsJSON: ceremony.options });
    await parsePasskeyResponse(await fetch('/auth/passkeys/register/verify', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: ceremony.token, credential }) }));
    window.location.assign('/');
  } catch (error) {
    showPasskeyStatus(error instanceof Error ? error.message : 'Passkey registration failed.', true);
  }
}

document.getElementById('passkey-sign-in')?.addEventListener('click', signInWithPasskey);
document.getElementById('passkey-register')?.addEventListener('click', registerPasskey);

/** Disables one passkey action when the browser lacks WebAuthn support. */
function disablePasskeyButton(element) {
  if (element.tagName === 'BUTTON') element.disabled = true;
}

if (!window.PublicKeyCredential) {
  showPasskeyStatus('This browser does not support passkeys.', true);
  document.querySelectorAll('[id^="passkey-"]').forEach(disablePasskeyButton);
}
