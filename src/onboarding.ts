export interface OnboardingProgress {
  applicationCount: number;
  masterCvCount: number;
  tailoredPackCount: number;
}

export interface OnboardingStep {
  actionLabel: string;
  description: string;
  href: string;
  status: 'complete' | 'current' | 'upcoming';
  title: string;
}

export interface OnboardingJourney {
  nextAction: OnboardingStep;
  steps: OnboardingStep[];
}

/** Builds the ordered getting-started journey and identifies the user's next useful action. */
export function onboardingJourney(progress: OnboardingProgress): OnboardingJourney {
  const hasMasterCv = progress.masterCvCount > 0;
  const hasTailoredPack = progress.tailoredPackCount > 0;
  const hasApplication = progress.applicationCount > 0;
  const currentIndex = !hasMasterCv ? 0 : !hasTailoredPack ? 1 : !hasApplication ? 2 : 3;
  const definitions = [
    {
      actionLabel: 'Upload your CV',
      description: 'Start with your most complete Word CV. This becomes the factual source Job Tune uses and never shares with other users.',
      href: '/documents',
      title: 'Add your master CV',
    },
    {
      actionLabel: 'Create a tailored pack',
      description: 'Copy the full job advert or job description, then paste it into the tailor form with the company and role title.',
      href: '/tailor',
      title: 'Add the job description',
    },
    {
      actionLabel: 'Review your documents',
      description: 'Check the change summary and factual accuracy, then download the tailored CV and cover letter in Word or PDF.',
      href: '/documents',
      title: 'Review and download',
    },
    {
      actionLabel: 'Open your applications',
      description: 'Save the opportunity in your tracker, record when you apply, and set a follow-up date so the application stays moving.',
      href: '/applications',
      title: 'Track the application',
    },
  ] as const;
  const steps = definitions.map((definition, index): OnboardingStep => ({
    ...definition,
    status: index < currentIndex ? 'complete' : index === currentIndex ? 'current' : 'upcoming',
  }));
  return { nextAction: steps[currentIndex], steps };
}
