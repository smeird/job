import assert from 'node:assert/strict';
import test from 'node:test';
import { applicationNeedsAttention, daysSinceSubmitted, followUpLabel, responseGuidance, submissionAgeLabel } from '../src/applications';

const today = new Date('2026-07-26T12:00:00Z');

/** Verifies calendar-day submission ages and concise user-facing labels. */
test('submission age calculation', () => {
  assert.equal(daysSinceSubmitted('2026-07-12', today), 14);
  assert.equal(submissionAgeLabel('2026-07-26', today), 'Submitted today');
  assert.equal(submissionAgeLabel('2026-07-25', today), '1 day since submitted');
  assert.equal(submissionAgeLabel(null, today), 'Not submitted');
});

/** Verifies explicit follow-up dates produce actionable overdue and upcoming labels. */
test('follow-up labels', () => {
  assert.equal(followUpLabel('2026-07-24', today), 'Follow-up overdue by 2 days');
  assert.equal(followUpLabel('2026-07-26', today), 'Follow up today');
  assert.equal(followUpLabel('2026-07-27', today), 'Follow up tomorrow');
  assert.equal(followUpLabel('2026-07-30', today), 'Follow up in 4 days');
});

/** Verifies inactivity guidance is cautious and closed applications are not flagged. */
test('application attention guidance', () => {
  const stale = { status: 'applied' as const, application_date: '2026-06-10', follow_up_date: null };
  assert.equal(applicationNeedsAttention(stale, today), true);
  assert.equal(responseGuidance(stale, today), 'Consider closing or deprioritising this application');
  assert.equal(applicationNeedsAttention({ ...stale, status: 'rejected' }, today), false);
  assert.equal(responseGuidance({ ...stale, status: 'interview' }, today), 'Interview process active');
});
