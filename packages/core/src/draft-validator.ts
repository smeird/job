const stopwords = new Set([
  "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December",
  "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday", "University", "College", "School", "Optional",
  "Evidence", "Add", "Curriculum", "Vitae", "Professional", "Summary", "Education", "Experience", "Skills", "References", "Interests",
  "Profile", "Overview", "Employment",
]);
const roleWords = new Set([
  "Assistant", "Associate", "Consultant", "Coordinator", "Designer", "Developer", "Director", "Engineer", "Executive", "Lead", "Leader",
  "Manager", "Officer", "Partner", "Principal", "Specialist", "Supervisor", "Technician", "Analyst", "Architect", "Administrator", "Advisor",
  "Strategist", "Scientist", "Researcher", "Teacher", "Lecturer", "Tutor", "Intern", "Contractor", "Volunteer", "Mentor", "Coach", "Trainer",
  "Head", "Chief", "Project", "Product", "Programme", "Program", "Service", "Support", "Operations", "Operational", "Customer", "Client",
]);

/** Decide whether a capitalized phrase is a heading or role phrase rather than an organisation. */
function shouldSkipOrganisation(words: readonly string[]): boolean {
  const normalized = words.map((word) => word.replace(/[^A-Za-z]/g, "")).filter(Boolean);
  const allStopwords = normalized.every((word) => stopwords.has(word));
  const allRoleWords = normalized.every((word) => roleWords.has(word));
  return allStopwords || allRoleWords || (normalized.length === 1 && (stopwords.has(normalized[0] ?? "") || roleWords.has(normalized[0] ?? "")));
}

/** Extract candidate organisation phrases using the same conservative heuristic as the PHP validator. */
export function extractOrganisations(text: string): string[] {
  const pattern = /\b([A-Z][A-Za-z&'"-]*(?:[ \t]+(?:(?:of|and|&|the|for|in|on|de|da|di|la|le|von|van)[ \t]+)?[A-Z][A-Za-z&'"-]*)*)\b/gu;
  const organisations = new Set<string>();
  for (const match of text.matchAll(pattern)) {
    const candidate = (match[1] ?? "").replace(/\s+/g, " ").trim();
    const words = candidate.split(/\s+/);
    if (candidate.length >= 3 && !shouldSkipOrganisation(words)) {
      organisations.add(candidate.toLowerCase());
    }
  }
  return [...organisations];
}

/** Reject a draft that introduces organisation names absent from its source CV. */
export function ensureNoUnknownOrganisations(sourceCv: string, draftMarkdown: string): void {
  const source = new Set(extractOrganisations(sourceCv));
  const unknown = extractOrganisations(draftMarkdown).filter((organisation) => !source.has(organisation));
  if (unknown.length > 0) {
    throw new Error(`Draft introduces unrecognised organisations: ${unknown.join(", ")}`);
  }
}
