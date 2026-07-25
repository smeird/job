import assert from 'node:assert/strict';
import test from 'node:test';
import { buildProfessionalDocumentLayout, professionalDocxBuffer, professionalPdfBuffer, type ProfessionalDocumentContext } from '../src/documents';

const profile = { fullName: 'Alex Morgan', email: 'alex.morgan@example.test', phone: '020 7946 0100', city: 'London', country: 'United Kingdom', linkedinUrl: 'https://www.linkedin.com/in/alex-morgan-test' };

/** Verifies that the shared CV model creates hierarchy while retaining every synthetic factual statement. */
test('professional CV layout', () => {
  const layout = buildProfessionalDocumentLayout('Alex Morgan\nalex.morgan@example.test | 020 7946 0100\n\n## Professional Summary\nSynthetic summary text.\n\nEXPERIENCE\nSenior Engineer | Example Systems | 2022–Present\n- Delivered the documented migration.\n- Reduced the synthetic test duration.\n\nLEADERSHIP AND DELIVERY\n- Recorded factual outcome.', { kind: 'cv', profile });
  assert.equal(layout.title, 'Alex Morgan');
  assert.equal(layout.contactLines[0], 'alex.morgan@example.test | 020 7946 0100');
  assert.deepEqual(layout.blocks.map((block) => block.kind), ['section', 'paragraph', 'section', 'subheading', 'bullet', 'bullet', 'section', 'bullet']);
  assert.ok(layout.blocks.some((block) => block.text === 'Senior Engineer | Example Systems | 2022-Present'));
  assert.ok(layout.blocks.some((block) => block.text === 'Reduced the synthetic test duration.'));
});

/** Verifies that formal letter metadata and a source-provided signature remain factual and structured. */
test('professional cover-letter layout', () => {
  const context: ProfessionalDocumentContext = { kind: 'cover_letter', profile, companyName: 'Example Industries', jobTitle: 'Platform Engineer', createdAt: new Date('2026-07-25T10:00:00Z') };
  const layout = buildProfessionalDocumentLayout('Dear Hiring Team,\n\nI am applying using only the synthetic experience described in my CV.\n\nKind regards,\nAlex Morgan', context);
  assert.deepEqual(layout.blocks.slice(0, 3), [{ kind: 'date', text: '25 July 2026' }, { kind: 'addressee', text: 'Example Industries' }, { kind: 'subject', text: 'Application for Platform Engineer' }]);
  assert.ok(layout.blocks.some((block) => block.kind === 'closing' && block.text === 'Kind regards,'));
  assert.ok(layout.blocks.some((block) => block.kind === 'signature' && block.text === 'Alex Morgan'));
});

/** Verifies that missing optional profile data produces a neutral, legible title without invented contact details. */
test('cover letter without optional contact data', () => {
  const layout = buildProfessionalDocumentLayout('Dear Hiring Team,\n\nSynthetic body.', { kind: 'cover_letter' });
  assert.equal(layout.title, 'Cover Letter');
  assert.deepEqual(layout.contactLines, []);
  assert.ok(!layout.blocks.some((block) => block.kind === 'addressee' || block.kind === 'subject' || block.kind === 'date'));
});

/** Verifies that both production renderers emit substantial files from one shared layout. */
test('DOCX and PDF rendering', async () => {
  const text = 'PROFESSIONAL SUMMARY\nSynthetic summary.\n\nSKILLS\n- TypeScript\n- MySQL';
  const context: ProfessionalDocumentContext = { kind: 'cv', profile };
  const [docx, pdf] = await Promise.all([professionalDocxBuffer(text, context), professionalPdfBuffer(text, context)]);
  assert.equal(docx.subarray(0, 2).toString('ascii'), 'PK');
  assert.equal(pdf.subarray(0, 4).toString('ascii'), '%PDF');
  assert.ok(docx.length > 5000);
  assert.ok(pdf.length > 3000);
});
