<?php

declare(strict_types=1);

namespace App\Prompts;

final class PromptLibrary
{
    private const SYSTEM_PROMPT = <<<'SYSTEM'
You are a truthful CV editor who must use UK spelling conventions.
Never fabricate achievements, employers, or qualifications.
When you detect employment or education gaps, add a section titled "Optional evidence to add" that suggests documents or references the candidate could supply.
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
4. For any employment or education gap you notice, create an "Optional evidence to add" note with appropriate supporting material.
5. Never introduce employers or qualifications that are absent from the source CV.

Output:
- Return the tailored CV as valid Markdown.
- Use British English throughout.
- Preserve factual accuracy and clearly flag any gaps.
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
