import crypto from 'node:crypto';
import { openAiResponseText } from './openai';

export type CareerFactCategory = 'achievement' | 'responsibility' | 'project' | 'leadership' | 'commercial' | 'technical' | 'other';

export type ExtractedCareerFact = { category: CareerFactCategory; evidenceQuote: string };
export type ExtractedCareerQuestion = { question: string; rationale: string };
export type ExtractedCareerRole = { employerName: string; jobTitle: string; location: string | null; startDateText: string | null; endDateText: string | null; isCurrent: boolean; roleEvidenceQuote: string; facts: ExtractedCareerFact[]; questions: ExtractedCareerQuestion[] };
export type CareerKnowledgeRole = { employerName: string; jobTitle: string; location?: string | null; startDateText?: string | null; endDateText?: string | null; isCurrent?: boolean; facts: Array<{ category: CareerFactCategory; factText: string; sourceLabel: string; userConfirmed: boolean }> };

export const CAREER_FACT_LABELS: Record<CareerFactCategory, string> = {
  achievement: 'Achievement',
  responsibility: 'Responsibility',
  project: 'Project or engagement',
  leadership: 'Leadership',
  commercial: 'Commercial and solutioning',
  technical: 'Technical',
  other: 'Other evidence',
};

/** Converts arbitrary text into a stable comparison form without changing stored evidence. */
export function normaliseCareerText(value: string): string { return value.normalize('NFKC').replace(/\s+/g, ' ').trim().toLocaleLowerCase(); }

/** Produces a stable user/role-scoped deduplication key for one factual statement or question. */
export function careerTextHash(value: string): string { return crypto.createHash('sha256').update(normaliseCareerText(value)).digest('hex'); }

/** Converts untrusted category input into the supported factual taxonomy. */
export function careerFactCategory(value: unknown): CareerFactCategory { const candidate = String(value || ''); return Object.hasOwn(CAREER_FACT_LABELS, candidate) ? candidate as CareerFactCategory : 'other'; }

/** Accepts an evidence quote only when its normalised words occur in the uploaded CV. */
export function groundedEvidenceQuote(cvText: string, quote: unknown): string | null { const candidate = String(quote || '').trim().slice(0, 4000); if (candidate.length < 3) return null; return normaliseCareerText(cvText).includes(normaliseCareerText(candidate)) ? candidate : null; }

/** Keeps a date label only when that exact label appears in the role's grounded excerpt. */
function groundedDateText(roleEvidence: string, value: unknown): string | null { const candidate = String(value || '').trim().slice(0, 80); return candidate && normaliseCareerText(roleEvidence).includes(normaliseCareerText(candidate)) ? candidate : null; }

/** Validates provider extraction and discards every role or fact that lacks direct CV evidence. */
export function validateExtractedCareerProfile(payload: unknown, cvText: string): ExtractedCareerRole[] {
  if (!payload || typeof payload !== 'object' || !Array.isArray((payload as { roles?: unknown }).roles)) return [];
  const cv = normaliseCareerText(cvText);
  return (payload as { roles: unknown[] }).roles.slice(0, 30).flatMap((raw) => {
    if (!raw || typeof raw !== 'object') return [];
    const role = raw as Record<string, unknown>;
    const employerName = String(role.employerName || '').trim().slice(0, 255);
    const jobTitle = String(role.jobTitle || '').trim().slice(0, 255);
    const roleEvidenceQuote = groundedEvidenceQuote(cvText, role.roleEvidenceQuote);
    const roleEvidence = normaliseCareerText(roleEvidenceQuote || '');
    if (!employerName || !jobTitle || !roleEvidenceQuote || !cv.includes(normaliseCareerText(employerName)) || !cv.includes(normaliseCareerText(jobTitle)) || !roleEvidence.includes(normaliseCareerText(employerName)) || !roleEvidence.includes(normaliseCareerText(jobTitle))) return [];
    const facts = (Array.isArray(role.facts) ? role.facts : []).slice(0, 30).flatMap((rawFact) => {
      if (!rawFact || typeof rawFact !== 'object') return [];
      const fact = rawFact as Record<string, unknown>;
      const evidenceQuote = groundedEvidenceQuote(cvText, fact.evidenceQuote);
      return evidenceQuote && roleEvidence.includes(normaliseCareerText(evidenceQuote)) ? [{ category: careerFactCategory(fact.category), evidenceQuote }] : [];
    });
    const questions = (Array.isArray(role.questions) ? role.questions : []).slice(0, 6).flatMap((rawQuestion) => {
      if (!rawQuestion || typeof rawQuestion !== 'object') return [];
      const item = rawQuestion as Record<string, unknown>;
      const question = String(item.question || '').trim().slice(0, 1000);
      const rationale = String(item.rationale || '').trim().slice(0, 500);
      return question.length >= 10 ? [{ question, rationale }] : [];
    });
    const location = String(role.location || '').trim().slice(0, 255);
    return [{ employerName, jobTitle, location: location && roleEvidence.includes(normaliseCareerText(location)) ? location : null, startDateText: groundedDateText(roleEvidenceQuote, role.startDateText), endDateText: groundedDateText(roleEvidenceQuote, role.endDateText), isCurrent: role.isCurrent === true && /\b(present|current|now)\b/i.test(roleEvidenceQuote), roleEvidenceQuote, facts, questions }];
  });
}

/** Builds the extraction request while requiring contiguous source excerpts for all stored facts. */
export function buildCareerExtractionPrompt(cvText: string): string {
  return `Extract the employment roles and reusable career evidence from this CV. Do not invent, infer, improve, or complete any candidate fact. employerName and jobTitle must occur in the CV. roleEvidenceQuote must be an exact contiguous copy of the complete source block for that role, including its heading and relevant bullet text. Every evidenceQuote must be copied exactly from within that roleEvidenceQuote; if evidence is absent, omit it. Keep date labels exactly as written rather than converting or guessing them. Categorise each factual excerpt. Suggest concise questions only for useful missing detail such as scope, actions, stakeholders, commercial contribution, technologies, or measurable outcomes; questions must not assume an answer.\n\nSOURCE CV:\n${cvText}`;
}

/** Calls the Responses API for a strict, evidence-bearing career profile extraction. */
export async function extractCareerProfileWithOpenAi(apiKey: string, model: string, cvText: string, fetcher: typeof fetch = fetch): Promise<ExtractedCareerRole[]> {
  const fact = { type: 'object', properties: { category: { type: 'string', enum: Object.keys(CAREER_FACT_LABELS) }, evidenceQuote: { type: 'string' } }, required: ['category', 'evidenceQuote'], additionalProperties: false };
  const question = { type: 'object', properties: { question: { type: 'string' }, rationale: { type: 'string' } }, required: ['question', 'rationale'], additionalProperties: false };
  const role = { type: 'object', properties: { employerName: { type: 'string' }, jobTitle: { type: 'string' }, location: { type: ['string', 'null'] }, startDateText: { type: ['string', 'null'] }, endDateText: { type: ['string', 'null'] }, isCurrent: { type: 'boolean' }, roleEvidenceQuote: { type: 'string' }, facts: { type: 'array', items: fact }, questions: { type: 'array', items: question } }, required: ['employerName', 'jobTitle', 'location', 'startDateText', 'endDateText', 'isCurrent', 'roleEvidenceQuote', 'facts', 'questions'], additionalProperties: false };
  const schema = { type: 'object', properties: { roles: { type: 'array', items: role } }, required: ['roles'], additionalProperties: false };
  const response = await fetcher('https://api.openai.com/v1/responses', { method: 'POST', headers: { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' }, signal: AbortSignal.timeout(120000), body: JSON.stringify({ model, instructions: 'You extract only directly evidenced career facts. Never invent or infer candidate experience.', input: buildCareerExtractionPrompt(cvText), text: { format: { type: 'json_schema', name: 'job_tune_career_evidence', strict: true, schema } }, max_output_tokens: 12000 }) });
  if (!response.ok) throw new Error('The AI provider did not accept the career extraction request.');
  const responseText = openAiResponseText(await response.json());
  if (!responseText) throw new Error('The AI provider returned no career extraction response.');
  return validateExtractedCareerProfile(JSON.parse(responseText), cvText);
}

/** Serialises active career facts into the immutable factual source used for one tailoring run. */
export function buildCareerKnowledgeText(roles: CareerKnowledgeRole[]): string {
  const sections = roles.filter((role) => role.facts.length).map((role) => {
    const dates = [role.startDateText, role.isCurrent ? 'Present' : role.endDateText].filter(Boolean).join(' – ');
    const heading = [`EMPLOYER: ${role.employerName}`, `ROLE: ${role.jobTitle}`, role.location ? `LOCATION: ${role.location}` : '', dates ? `DATES: ${dates}` : ''].filter(Boolean).join('\n');
    const facts = role.facts.map((fact) => `- [${CAREER_FACT_LABELS[fact.category]}] ${fact.factText}\n  Provenance: ${fact.userConfirmed ? 'user confirmed' : fact.sourceLabel}`).join('\n');
    return `${heading}\nFACTUAL EVIDENCE:\n${facts}`;
  });
  return `CAREER EVIDENCE DATABASE\nEvery statement below is stored factual evidence. Select only pertinent evidence and do not infer anything beyond it.\n\n${sections.join('\n\n')}`;
}
