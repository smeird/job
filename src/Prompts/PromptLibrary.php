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
Goal: produce the strongest truthful version of the source CV for the supplied job description.

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
- return only the finished CV
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
2. Write a concise, factual cover letter in British English using only the provided material. Do not invent achievements or employers.
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
