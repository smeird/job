import assert from 'node:assert/strict';
import test from 'node:test';
import { CV_FRAMEWORKS, cvFrameworkFromInput, cvFrameworkInstructions, DEFAULT_CV_FRAMEWORK } from '../src/cv-frameworks';

/** Verifies that framework values are closed and unsafe inputs fall back to the conventional structure. */
test('CV framework validation', () => {
  assert.equal(cvFrameworkFromInput('skills_led'), 'skills_led');
  assert.equal(cvFrameworkFromInput('invent-an-executive-profile'), DEFAULT_CV_FRAMEWORK);
  assert.deepEqual(Object.keys(CV_FRAMEWORKS), ['experience_led', 'profile_led', 'skills_led', 'hybrid']);
});

/** Verifies that every structural choice remains explicitly subordinate to factual evidence. */
test('CV framework instructions remain grounded', () => {
  for (const framework of Object.keys(CV_FRAMEWORKS) as Array<keyof typeof CV_FRAMEWORKS>) {
    const guidance = cvFrameworkInstructions(framework);
    assert.match(guidance, /CV FRAMEWORK:/);
    assert.match(guidance, /factual source/i);
    assert.match(guidance, /must never introduce candidate facts/i);
  }
  assert.match(cvFrameworkInstructions('profile_led'), /Do not invent ambitions/);
  assert.match(cvFrameworkInstructions('skills_led'), /reverse chronology/);
});
