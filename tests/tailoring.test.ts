import assert from 'node:assert/strict';
import test from 'node:test';
import { buildTailoringPrompt, revisionGroupKey, tailoringControlInstructions, tailoringControlsFromInput } from '../src/tailoring';

/** Verifies that form controls are allow-listed and optional guidance is bounded. */
test('tailoring control validation', () => {
  assert.deepEqual(tailoringControlsFromInput('management', 'formal', '  Prioritise documented mentoring.  '), { focus: 'management', tone: 'formal', notes: 'Prioritise documented mentoring.' });
  assert.deepEqual(tailoringControlsFromInput('invent-experience', 'casual', ''), { focus: 'balanced', tone: 'professional', notes: '' });
  assert.equal(tailoringControlsFromInput('technical', 'concise', 'x'.repeat(700)).notes.length, 500);
});

/** Verifies that presentation preferences remain subordinate to original-CV factual authority. */
test('factual re-tailoring prompt', () => {
  const controls = tailoringControlsFromInput('technical', 'concise', 'Highlight only documented platform work.');
  const prompt = buildTailoringPrompt({ cvText: 'Synthetic source CV fact.', jobDescription: 'Synthetic role description.', contact: '{"fullName":"Alex Example"}', companyName: 'Example Ltd', jobTitle: 'Platform Engineer', controls, framework: 'profile_led' });
  assert.match(tailoringControlInstructions(controls), /Technical depth/);
  assert.match(prompt, /factual source material is the sole authority/i);
  assert.match(prompt, /cannot add facts/i);
  assert.match(prompt, /EMPHASIS: Technical depth/);
  assert.match(prompt, /TONE: Concise and direct/);
  assert.match(prompt, /CV FRAMEWORK: Profile-led/);
  assert.match(prompt, /Do not invent ambitions/);
  assert.match(prompt, /FACTUAL SOURCE MATERIAL:\nSynthetic source CV fact\./);
  assert.match(prompt, /JOB DESCRIPTION:\nSynthetic role description\./);
});

/** Verifies that older packs receive stable group keys without a destructive data backfill. */
test('revision group compatibility', () => {
  assert.equal(revisionGroupKey(42, null), 'legacy-42');
  assert.equal(revisionGroupKey(42, 'synthetic-group'), 'synthetic-group');
});
