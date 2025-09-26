<?php

declare(strict_types=1);

namespace App\Validation;

use InvalidArgumentException;

final class DraftValidator
{
    private const STOPWORDS = [
        'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December',
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
        'University', 'College', 'School', 'Optional', 'Evidence', 'Add',
        'Curriculum', 'Vitae', 'Professional', 'Summary', 'Education', 'Experience',
        'Skills', 'References', 'Interests', 'Profile', 'Overview', 'Employment',
    ];

    private const ROLE_WORDS = [
        'Assistant', 'Associate', 'Consultant', 'Coordinator', 'Designer', 'Developer', 'Director', 'Engineer',
        'Executive', 'Lead', 'Leader', 'Manager', 'Officer', 'Partner', 'Principal', 'Specialist', 'Supervisor', 'Technician',
        'Analyst', 'Architect', 'Administrator', 'Advisor', 'Strategist', 'Scientist', 'Researcher', 'Teacher', 'Lecturer',
        'Tutor', 'Intern', 'Contractor', 'Volunteer', 'Mentor', 'Coach', 'Trainer', 'Consultant', 'Head', 'Chief',
        'Project', 'Product', 'Programme', 'Program', 'Service', 'Support', 'Operations', 'Operational', 'Customer', 'Client',
    ];

    /**
     * Handle the ensure no unknown organisations workflow.
     *
     * This helper keeps the ensure no unknown organisations logic centralised for clarity and reuse.
     * @throws InvalidArgumentException when the draft contains organisations not present in the source CV.
     */
    public function ensureNoUnknownOrganisations(string $sourceCv, string $draftMarkdown): void
    {
        $sourceOrganisations = $this->extractOrganisations($sourceCv);
        $draftOrganisations = $this->extractOrganisations($draftMarkdown);

        $unknown = array_values(array_diff($draftOrganisations, $sourceOrganisations));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Draft introduces unrecognised organisations: ' . implode(', ', $unknown));
        }
    }

    /**
     * Handle the extract organisations workflow.
     *
     * This helper keeps the extract organisations logic centralised for clarity and reuse.
     * @return list<string>
     */
    private function extractOrganisations(string $text): array
    {
        $matches = [];
        preg_match_all('/\b([A-Z][A-Za-z&\'"-]*(?:\s+(?:of|and|&|the|for|in|on|de|da|di|la|le|von|van)?\s*[A-Z][A-Za-z&\'"-]*)*)\b/u', $text, $matches);

        if (!isset($matches[1])) {
            return [];
        }

        $organisations = [];

        foreach ($matches[1] as $match) {
            $cleaned = trim(preg_replace('/\s+/', ' ', (string) $match));

            if ($cleaned === '' || mb_strlen($cleaned) < 3) {
                continue;
            }

            $words = preg_split('/\s+/', $cleaned);

            if ($words === false || $words === []) {
                continue;
            }

            if ($this->shouldSkip($words)) {
                continue;
            }

            $organisations[] = mb_strtolower($cleaned);
        }

        $organisations = array_values(array_unique($organisations));

        return $organisations;
    }

    /**
     * Evaluate whether the skip should occur.
     *
     * Providing a single decision point keeps policy logic together.
     * @param list<string> $words
     */
    private function shouldSkip(array $words): bool
    {
        $upperWords = array_map(
            static fn (string $word): string => preg_replace('/[^A-Za-z]/', '', $word) ?? '',
            $words
        );

        $allStopwords = true;
        $allRoleWords = true;

        foreach ($upperWords as $word) {
            if ($word === '') {
                continue;
            }

            if (!in_array($word, self::STOPWORDS, true)) {
                $allStopwords = false;
            }

            if (!in_array($word, self::ROLE_WORDS, true)) {
                $allRoleWords = false;
            }
        }

        if ($allStopwords || $allRoleWords) {
            return true;
        }

        if (count($words) === 1) {
            $word = $upperWords[0];

            if (in_array($word, self::STOPWORDS, true) || in_array($word, self::ROLE_WORDS, true)) {
                return true;
            }
        }

        return false;
    }
}
