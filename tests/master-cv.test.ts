import assert from 'node:assert/strict';
import test from 'node:test';
import { buildMasterCvText, masterCvGenerationSummary } from '../src/master-cv';
import type { CareerKnowledgeRole } from '../src/career';

const roles: CareerKnowledgeRole[] = [{ employerName: 'Northwind Advisory', jobTitle: 'Technology Lead', location: 'London', startDateText: '2018', endDateText: '2024', facts: [
  { category: 'technical', factText: 'Designed the documented platform migration.', sourceLabel: 'source.docx', userConfirmed: false },
  { category: 'leadership', factText: 'Led the stated delivery team.', sourceLabel: 'user confirmed', userConfirmed: true },
] }];

/** Confirms the compiler includes every fact verbatim and never needs a job advert. */
test('buildMasterCvText compiles a factual master CV without a job advert', () => {
  const text = buildMasterCvText(roles, 'balanced');
  assert.match(text, /PROFESSIONAL EXPERIENCE/);
  assert.match(text, /Technology Lead \| Northwind Advisory \| 2018 - 2024/);
  assert.match(text, /Designed the documented platform migration\./);
  assert.match(text, /Led the stated delivery team\./);
  assert.doesNotMatch(text, /job description|ideal candidate/i);
});

/** Confirms emphasis changes presentation order while retaining exactly the same facts. */
test('buildMasterCvText applies emphasis only to fact ordering', () => {
  const technical = buildMasterCvText(roles, 'technical');
  const management = buildMasterCvText(roles, 'management');
  assert.ok(technical.indexOf('Designed') < technical.indexOf('Led'));
  assert.ok(management.indexOf('Led') < management.indexOf('Designed'));
  for (const fact of roles[0].facts) {
    assert.equal(technical.split(fact.factText).length, 2);
    assert.equal(management.split(fact.factText).length, 2);
  }
});

/** Confirms the audit summary plainly identifies the no-AI factual build. */
test('masterCvGenerationSummary records the grounded generation method', () => {
  const summary = masterCvGenerationSummary(roles, 'management');
  assert.match(summary, /2 stored facts across 1 role/);
  assert.match(summary, /No job advert or AI generation was used/);
});
