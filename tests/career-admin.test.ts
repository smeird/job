import assert from 'node:assert/strict';
import test from 'node:test';
import { mergeCareerRoleMetadata, preferredCareerQuestionState, reorderedCareerRoleIds } from '../src/career-admin';

/** Verifies that merge metadata preserves deliberate destination values and fills only gaps. */
test('career role metadata merge', () => {
  const merged = mergeCareerRoleMetadata(
    { location: 'London', startDateText: null, endDateText: '2025', isCurrent: false, summary: null, displayOrder: 4 },
    { location: 'Manchester', startDateText: '2016', endDateText: null, isCurrent: true, summary: 'Source summary', displayOrder: 1 },
  );
  assert.deepEqual(merged, { location: 'London', startDateText: '2016', endDateText: '2025', isCurrent: true, summary: 'Source summary', displayOrder: 1 });
});

/** Verifies that answers and intentional dismissals are not lost when questions deduplicate. */
test('career question merge precedence', () => {
  assert.deepEqual(preferredCareerQuestionState({ status: 'open', answeredFactId: null }, { status: 'answered', answeredFactId: 42 }), { status: 'answered', answeredFactId: 42 });
  assert.deepEqual(preferredCareerQuestionState({ status: 'dismissed', answeredFactId: null }, { status: 'open', answeredFactId: null }), { status: 'dismissed', answeredFactId: null });
});

/** Verifies bounded role ordering for administrative up and down controls. */
test('career role ordering', () => {
  assert.deepEqual(reorderedCareerRoleIds([1, 2, 3], 2, 'up'), [2, 1, 3]);
  assert.deepEqual(reorderedCareerRoleIds([1, 2, 3], 2, 'down'), [1, 3, 2]);
  assert.deepEqual(reorderedCareerRoleIds([1, 2, 3], 1, 'up'), [1, 2, 3]);
  assert.deepEqual(reorderedCareerRoleIds([1, 2, 3], 3, 'down'), [1, 2, 3]);
});
