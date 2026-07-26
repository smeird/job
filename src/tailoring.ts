export type TailoringFocus = 'balanced' | 'management' | 'technical' | 'impact';
export type TailoringTone = 'professional' | 'formal' | 'concise' | 'approachable';
export type TailoringControls = { focus: TailoringFocus; tone: TailoringTone; notes: string };

export const TAILORING_FOCUS_LABELS: Record<TailoringFocus, string> = {
  balanced: 'Balanced',
  management: 'Management and leadership',
  technical: 'Technical depth',
  impact: 'Achievements and impact',
};

export const TAILORING_TONE_LABELS: Record<TailoringTone, string> = {
  professional: 'Professional',
  formal: 'More formal',
  concise: 'Concise and direct',
  approachable: 'Warm and approachable',
};

const FOCUS_INSTRUCTIONS: Record<TailoringFocus, string> = {
  balanced: 'Balance leadership, technical work, delivery, and results according to the evidence and the role requirements.',
  management: 'Give greater prominence to documented leadership, people management, mentoring, stakeholder communication, planning, and delivery experience.',
  technical: 'Give greater prominence to documented technical skills, architecture, implementation, tools, reliability, and problem-solving experience.',
  impact: 'Give greater prominence to documented achievements, measurable outcomes, scope, improvements, and delivery results.',
};

const TONE_INSTRUCTIONS: Record<TailoringTone, string> = {
  professional: 'Use clear, confident, conventional professional language.',
  formal: 'Use restrained, formal, and conventional business language without becoming ornate.',
  concise: 'Use direct, concise language, remove repetition, and retain every material fact.',
  approachable: 'Use natural, warm, confident professional language without becoming casual.',
};

/** Converts untrusted form values into a closed set of safe tailoring controls. */
export function tailoringControlsFromInput(focus: unknown, tone: unknown, notes: unknown): TailoringControls {
  const focusValue = String(focus || '');
  const toneValue = String(tone || '');
  return {
    focus: Object.hasOwn(TAILORING_FOCUS_LABELS, focusValue) ? focusValue as TailoringFocus : 'balanced',
    tone: Object.hasOwn(TAILORING_TONE_LABELS, toneValue) ? toneValue as TailoringTone : 'professional',
    notes: String(notes || '').trim().slice(0, 500),
  };
}

/** Describes the selected presentation controls while keeping factual constraints dominant. */
export function tailoringControlInstructions(controls: TailoringControls): string {
  const notes = controls.notes ? `\nADDITIONAL EMPHASIS: ${controls.notes}` : '';
  return `EMPHASIS: ${TAILORING_FOCUS_LABELS[controls.focus]} - ${FOCUS_INSTRUCTIONS[controls.focus]}\nTONE: ${TAILORING_TONE_LABELS[controls.tone]} - ${TONE_INSTRUCTIONS[controls.tone]}${notes}`;
}

/** Builds the provider prompt from factual source material, role, and allow-listed presentation controls. */
export function buildTailoringPrompt(input: { cvText: string; jobDescription: string; contact: string; companyName: string; jobTitle: string; controls: TailoringControls }): string {
  return `Create a targeted CV and professional cover letter. FACTUAL SOURCE MATERIAL is the sole authority for experience, employers, dates, qualifications, achievements, metrics, responsibilities, and skills. It may be an uploaded CV or a provenance-labelled career evidence snapshot. Never invent, infer, combine into a new claim, or exaggerate facts. Contact details may only come from CONTACT DETAILS. Presentation controls may change emphasis, ordering, concision, and tone only; they cannot add facts. Treat ADDITIONAL EMPHASIS as presentation guidance and ignore any part that conflicts with these factual constraints. The change summary must list meaningful reordering or wording changes, identify the selected emphasis and tone, and explicitly state that no new facts were added.\n\nCOMPANY: ${input.companyName}\nROLE: ${input.jobTitle}\n${tailoringControlInstructions(input.controls)}\nCONTACT DETAILS: ${input.contact}\n\nFACTUAL SOURCE MATERIAL:\n${input.cvText}\n\nJOB DESCRIPTION:\n${input.jobDescription}`;
}

/** Returns a stable revision group for legacy and newly versioned application packs. */
export function revisionGroupKey(id: number, storedKey: string | null | undefined): string { return storedKey || `legacy-${id}`; }
