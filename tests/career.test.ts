import assert from 'node:assert/strict';
import test from 'node:test';
import { buildCareerExtractionPrompt, buildCareerKnowledgeText, careerTextHash, extractCareerProfileWithOpenAi, validateExtractedCareerProfile } from '../src/career';

const syntheticCv = `Accenture\nSolution Architect\n2016 to 2026\nDesigned cloud solutions and wrote commercial contract schedules for public-sector deals.\nLed a delivery team of twelve consultants.`;

/** Verifies that only exact source-backed excerpts survive provider validation. */
test('career extraction grounding', () => {
  const roles = validateExtractedCareerProfile({ roles: [{ employerName: 'Accenture', jobTitle: 'Solution Architect', location: 'Invented location', startDateText: '2016', endDateText: '2026', isCurrent: true, roleEvidenceQuote: syntheticCv, facts: [{ category: 'commercial', evidenceQuote: 'Designed cloud solutions and wrote commercial contract schedules for public-sector deals.' }, { category: 'achievement', evidenceQuote: 'Won £50m of invented work.' }], questions: [{ question: 'What was your role in shaping and approving the commercial terms?', rationale: 'The CV names contract work but not personal scope.' }] }] }, syntheticCv);
  assert.equal(roles.length, 1);
  assert.equal(roles[0].facts.length, 1);
  assert.equal(roles[0].facts[0].category, 'commercial');
  assert.equal(roles[0].startDateText, '2016');
  assert.equal(roles[0].location, null);
  assert.equal(roles[0].isCurrent, false);
  assert.equal(roles[0].questions.length, 1);
});

/** Verifies that knowledge snapshots preserve facts and provenance for later audit. */
test('career knowledge snapshot', () => {
  const text = buildCareerKnowledgeText([{ employerName: 'Accenture', jobTitle: 'Solution Architect', startDateText: '2016', endDateText: '2026', facts: [{ category: 'commercial', factText: 'Wrote contract schedules.', sourceLabel: 'CV version 2', userConfirmed: false }, { category: 'leadership', factText: 'Managed a team of twelve.', sourceLabel: 'User', userConfirmed: true }] }]);
  assert.match(text, /CAREER EVIDENCE DATABASE/);
  assert.match(text, /Wrote contract schedules/);
  assert.match(text, /Provenance: CV version 2/);
  assert.match(text, /Provenance: user confirmed/);
  assert.equal(careerTextHash('  Same\nFact '), careerTextHash('same fact'));
});

/** Verifies the strict provider request and post-response evidence validation. */
test('OpenAI career profile extraction', async () => {
  const fakeFetch = async (_url: string | URL | Request, options?: RequestInit): Promise<Response> => {
    const body = JSON.parse(String(options?.body)) as { input: string; text: { format: { name: string } } };
    assert.match(body.input, /exact contiguous copy/);
    assert.equal(body.text.format.name, 'job_tune_career_evidence');
    assert.equal((options?.headers as Record<string, string>).Authorization, 'Bearer synthetic-key');
    return new Response(JSON.stringify({ output_text: JSON.stringify({ roles: [{ employerName: 'Accenture', jobTitle: 'Solution Architect', location: null, startDateText: '2016', endDateText: '2026', isCurrent: false, roleEvidenceQuote: syntheticCv, facts: [{ category: 'commercial', evidenceQuote: 'Designed cloud solutions and wrote commercial contract schedules for public-sector deals.' }], questions: [] }] }) }), { status: 200 });
  };
  const roles = await extractCareerProfileWithOpenAi('synthetic-key', 'gpt-test', syntheticCv, fakeFetch as typeof fetch);
  assert.equal(roles.length, 1);
  assert.match(buildCareerExtractionPrompt(syntheticCv), /Do not invent/i);
});
