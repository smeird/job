export type CvFramework = 'experience_led' | 'profile_led' | 'skills_led' | 'hybrid';

export type CvFrameworkDefinition = {
  label: string;
  shortLabel: string;
  description: string;
  bestFor: string;
  instructions: string;
};

export const DEFAULT_CV_FRAMEWORK: CvFramework = 'experience_led';

export const CV_FRAMEWORKS: Record<CvFramework, CvFrameworkDefinition> = {
  experience_led: {
    label: 'Experience-led',
    shortLabel: 'Experience-led',
    description: 'A clear reverse-chronological CV that lets relevant roles and evidence lead.',
    bestFor: 'A consistent career history where recent roles are strongly relevant.',
    instructions: 'Use this order: contact heading; relevant skills only when useful; professional experience in reverse chronology; education and qualifications; any other source-supported section. Do not add a generic profile or career objective.',
  },
  profile_led: {
    label: 'Profile-led',
    shortLabel: 'Profile-led',
    description: 'Opens with a concise, role-specific professional profile before career history.',
    bestFor: 'Experienced candidates who need their most pertinent value made clear quickly.',
    instructions: 'Use this order: contact heading; a short targeted professional profile; core strengths; professional experience in reverse chronology; education and qualifications; any other source-supported section. Every profile claim must be supported by the factual source. Do not invent ambitions, desired progression, or a career objective.',
  },
  skills_led: {
    label: 'Skills-led',
    shortLabel: 'Skills-led',
    description: 'Groups proven capabilities first, while retaining an honest employment timeline.',
    bestFor: 'Career changes, varied assignments, or roles where transferable skills matter most.',
    instructions: 'Use this order: contact heading; a short factual profile when supported; key skills or capability groups with evidence and context; concise professional experience in reverse chronology; education and qualifications; any other source-supported section. Never detach a claim from enough context to understand where it came from.',
  },
  hybrid: {
    label: 'Hybrid achievement-led',
    shortLabel: 'Hybrid',
    description: 'Combines an upfront profile and selected achievements with a full career timeline.',
    bestFor: 'Senior or broad careers where both standout impact and progression need visibility.',
    instructions: 'Use this order: contact heading; a concise factual profile; selected achievements that are directly supported by the source; core skills; professional experience in reverse chronology; education and qualifications; any other source-supported section. Do not repeat the same evidence merely to fill multiple sections.',
  },
};

/** Converts an untrusted value into one of Job Tune's researched CV structures. */
export function cvFrameworkFromInput(value: unknown): CvFramework {
  const candidate = String(value || '');
  return Object.hasOwn(CV_FRAMEWORKS, candidate) ? candidate as CvFramework : DEFAULT_CV_FRAMEWORK;
}

/** Produces strict structural guidance while preserving source evidence as the only factual authority. */
export function cvFrameworkInstructions(framework: CvFramework): string {
  const definition = CV_FRAMEWORKS[framework];
  return `CV FRAMEWORK: ${definition.label}\nPURPOSE: ${definition.description}\nSTRUCTURE: ${definition.instructions}\nOmit any optional section that cannot be populated from the factual source. Section structure may change presentation only and must never introduce candidate facts.`;
}
