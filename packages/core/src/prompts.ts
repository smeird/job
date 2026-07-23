export const SYSTEM_PROMPT = `You are a truthful CV editor who must use UK spelling conventions.
Never fabricate achievements, employers, or qualifications.
Remove meta-notes and replace them only with concise, authentic differentiators drawn from the source CV.
Treat employment or education gaps as interview preparation, never as content to add to the written CV.`;

export const TAILOR_PROMPT = `Goal: produce the strongest truthful version of the source CV for the supplied job description.

Success criteria:
- open with a concise role-specific profile grounded in the candidate's real experience
- place the most relevant evidenced achievements and skills first
- preserve employers, roles, dates, education, qualifications, and career chronology accurately
- use job-description terminology only where the source CV supports it
- retain meaningful evidence rather than reducing the CV to a short keyword summary
- return clean, ATS-readable Markdown in British English

Constraints:
- never add an employer, responsibility, achievement, qualification, tool, date, or metric that is not supported by the source CV
- do not present missing requirements, employment gaps, suggestions, or interview advice in the CV
- do not mention prompts, tailoring, or AI
- return only the finished CV`;

export const COVER_LETTER_PROMPT = `Prepare a concise, professional UK-English cover letter using only facts in the source CV, job description, and evidence plan.
Use supplied contact details as a simple header when present. Keep the letter below 350 words.
Do not invent achievements, employers, responsibilities, tools, dates, or metrics.
Return only submission-ready Markdown and never mention AI or tailoring.`;

export const EVIDENCE_PLAN_PROMPT = `Map every important job requirement to explicit evidence in the source CV.
Mark unsupported requirements as gaps. Do not infer or invent experience.
Return a concise strategy that helps a drafting model prioritise the strongest truthful material.`;

/** Interpolate the stable prompt contract used for fixture and golden-file tests. */
export function buildTailoringInput(input: { cvMarkdown: string; evidencePlan: string; jobDescription: string; tailoringPrompt: string }): string {
  return [
    "# Tailoring instructions",
    input.tailoringPrompt,
    "# Job description",
    input.jobDescription,
    "# Source CV (factual authority)",
    input.cvMarkdown,
    "# Evidence plan",
    input.evidencePlan,
  ].join("\n\n");
}

/** Build the cover-letter request without allowing external or unsanitized content. */
export function buildCoverLetterInput(input: { contactDetails?: unknown; cvMarkdown: string; evidencePlan: string; jobDescription: string }): string {
  return [
    COVER_LETTER_PROMPT,
    "# Contact details",
    JSON.stringify(input.contactDetails ?? {}),
    "# Job description",
    input.jobDescription,
    "# Source CV",
    input.cvMarkdown,
    "# Evidence plan",
    input.evidencePlan,
  ].join("\n\n");
}
