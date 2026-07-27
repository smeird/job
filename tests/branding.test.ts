import assert from 'node:assert/strict';
import test from 'node:test';
import { publicSiteOrigin, SITE_DESCRIPTION, SITE_SHARE_TITLE } from '../src/branding';

/** Confirms explicit public configuration takes precedence and is reduced to a safe origin. */
test('public link-preview origin', () => {
  assert.equal(publicSiteOrigin({ PUBLIC_ORIGIN: 'https://job.example.test/path', WEBAUTHN_ORIGIN: 'https://auth.example.test' }), 'https://job.example.test');
  assert.equal(publicSiteOrigin({ WEBAUTHN_ORIGIN: 'https://job.example.test' }), 'https://job.example.test');
  assert.equal(publicSiteOrigin({ PUBLIC_ORIGIN: 'javascript:alert(1)', PORT: '3100' }), 'http://localhost:3100');
});

/** Guards the useful content and sensible length of the social-preview copy. */
test('link-preview copy describes the product', () => {
  assert.match(SITE_SHARE_TITLE, /Job Tune/);
  assert.match(SITE_DESCRIPTION, /factual, tailored CVs/i);
  assert.match(SITE_DESCRIPTION, /track every job application/i);
  assert.ok(SITE_DESCRIPTION.length <= 200);
});
