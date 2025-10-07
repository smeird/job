<?php

declare(strict_types=1);

namespace App\Prompts;

final class PromptLibrary
{
    private const SYSTEM_PROMPT = <<<'SYSTEM'
You are a truthful CV editor who must use UK spelling conventions.
Never fabricate achievements, employers, or qualifications.
Remove any meta-notes such as "Note: Modern engineering practices..." and replace them with a concise "Selected Highlights" or "Value Proposition" section that showcases two or three authentic differentiators (e.g. cloud platform recovery, architecture governance, cost optimisation) drawn from the existing record.
When you detect employment or education gaps, prepare them as interview talking points instead of adding them to the written CV—do not create "Optional evidence to add" sections or similar gap call-outs.
SYSTEM;

    private const TAILOR_PROMPT = <<<'TAILOR'
Inputs you receive:
- Job title: {{title}}
- Hiring company: {{company}}
- Priority competencies: {{competencies}}
- Candidate CV sections (Markdown):
{{cv_sections}}

Tasks:
1. Draft a role-specific summary that links the candidate's experience to the job title and company.
2. Reorder or trim the supplied CV sections so the most relevant accomplishments for the listed competencies appear first.
3. Only quantify achievements when the original CV already provides the numbers.
4. Remove any meta-notes such as "Note: Modern engineering practices..." and replace them with a concise "Selected Highlights" or "Value Proposition" section featuring two or three genuine differentiators (e.g. cloud platform recovery, architecture governance, cost optimisation) grounded in the source material.
5. Treat employment or education gaps as interview talking points instead of written sections; omit "Optional evidence to add" or similar gap call-outs from the CV.
6. Never introduce employers or qualifications that are absent from the source CV.
7. Do not append a standalone suggestions section at the end of the tailored CV.

Output:
- Return the tailored CV as valid Markdown.
- Use British English throughout.
- Preserve factual accuracy while keeping gap context out of the document itself.
- Include the "Selected Highlights" or "Value Proposition" section directly after the summary unless the source CV lacks material to support it.
- Do not include meta-notes, "Optional evidence to add" sections, or interview talking point lists in the written CV.
- Do not add closing statements inviting further customisation or referencing the tailoring process.
- Present the document as the final CV ready for submission without suggesting further edits or mentioning AI involvement.
TAILOR;

    private const COVER_LETTER_PROMPT = <<<'LETTER'
You are preparing a professional UK-English cover letter that must remain factually accurate.

Role context:
- Job title: {{title}}
- Hiring company: {{company}}
- Priority competencies: {{competencies}}

Job description excerpt:
{{job_description}}

Candidate CV excerpt:
{{cv_sections}}

Tailoring plan JSON:
{{plan}}

Candidate contact details JSON:
{{contact_details}}

Instructions:
1. If the contact details JSON includes an address, place it above the greeting and add any provided email or phone on separate lines. Skip the header when the JSON is empty.
2. Write a concise cover letter in Markdown with a greeting, three to four short paragraphs, and a polite closing with a signature placeholder.
3. Reference achievements already present in the CV excerpt and align them with the listed competencies and plan guidance.
4. Keep the letter under 350 words, using a confident and warm tone.
5. Avoid inventing employers, responsibilities, or achievements that the CV does not mention.
6. Present the letter as the final submission-ready draft without suggesting future tweaks or referencing AI assistance.

Return only the cover letter Markdown without commentary.
LETTER;

    /**
     * Handle the system prompt operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public static function systemPrompt(): string
    {
        return self::SYSTEM_PROMPT;
    }

    /**
     * Handle the tailor prompt operation.
     *
     * Documenting this helper clarifies its role within the wider workflow.
     */
    public static function tailorPrompt(): string
    {
        return self::TAILOR_PROMPT;
    }

    /**
     * Provide the reusable cover letter drafting instructions.
     *
     * Supplying the template through a dedicated accessor keeps the job handler implementation succinct.
     */
    public static function coverLetterPrompt(): string
    {
        return self::COVER_LETTER_PROMPT;
    }
}
