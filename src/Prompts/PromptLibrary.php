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

    public static function systemPrompt(): string
    {
        return self::SYSTEM_PROMPT;
    }

    public static function tailorPrompt(): string
    {
        return self::TAILOR_PROMPT;
    }
}
