import assert from 'node:assert/strict';
import test from 'node:test';
import { onboardingJourney } from '../src/onboarding';

/** Confirms a new account is directed to upload its factual source CV first. */
test('onboarding starts with a master CV', () => {
  const journey = onboardingJourney({ applicationCount: 0, masterCvCount: 0, tailoredPackCount: 0 });
  assert.equal(journey.nextAction.href, '/documents');
  assert.equal(journey.nextAction.actionLabel, 'Upload your CV');
  assert.deepEqual(journey.steps.map((step) => step.status), ['current', 'upcoming', 'upcoming', 'upcoming']);
});

/** Confirms an uploaded CV advances the user to pasting a job description for tailoring. */
test('onboarding advances to the job description', () => {
  const journey = onboardingJourney({ applicationCount: 0, masterCvCount: 1, tailoredPackCount: 0 });
  assert.equal(journey.nextAction.href, '/tailor');
  assert.equal(journey.nextAction.actionLabel, 'Create a tailored pack');
  assert.deepEqual(journey.steps.map((step) => step.status), ['complete', 'current', 'upcoming', 'upcoming']);
});

/** Confirms a completed pack prompts tracking before settling into normal application management. */
test('onboarding continues into application tracking', () => {
  const untracked = onboardingJourney({ applicationCount: 0, masterCvCount: 1, tailoredPackCount: 1 });
  const established = onboardingJourney({ applicationCount: 1, masterCvCount: 1, tailoredPackCount: 1 });
  assert.equal(untracked.nextAction.title, 'Review and download');
  assert.equal(established.nextAction.title, 'Track the application');
  assert.deepEqual(established.steps.map((step) => step.status), ['complete', 'complete', 'complete', 'current']);
});
