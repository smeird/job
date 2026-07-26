import { CAREER_FACT_LABELS, type CareerFactCategory, type CareerKnowledgeRole } from './career';
import type { TailoringFocus } from './tailoring';

const CATEGORY_PRIORITY: Record<TailoringFocus, CareerFactCategory[]> = {
  balanced: ['achievement', 'responsibility', 'project', 'leadership', 'commercial', 'technical', 'other'],
  management: ['leadership', 'responsibility', 'commercial', 'achievement', 'project', 'technical', 'other'],
  technical: ['technical', 'project', 'achievement', 'responsibility', 'leadership', 'commercial', 'other'],
  impact: ['achievement', 'commercial', 'project', 'leadership', 'responsibility', 'technical', 'other'],
};

/** Returns a stable, presentation-only ordering for facts without altering or omitting their wording. */
function orderedFacts(role: CareerKnowledgeRole, focus: TailoringFocus): CareerKnowledgeRole['facts'] {
  const priorities = new Map(CATEGORY_PRIORITY[focus].map((category, index) => [category, index]));
  return role.facts.map((fact, index) => ({ fact, index })).sort((left, right) => (priorities.get(left.fact.category) ?? 0) - (priorities.get(right.fact.category) ?? 0) || left.index - right.index).map(({ fact }) => fact);
}

/** Compiles a comprehensive master CV solely from stored career facts, preserving every factual statement verbatim. */
export function buildMasterCvText(roles: CareerKnowledgeRole[], focus: TailoringFocus): string {
  const sections = roles.filter((role) => role.facts.length).map((role) => {
    const dates = [role.startDateText, role.isCurrent ? 'Present' : role.endDateText].filter(Boolean).join(' - ');
    const heading = [role.jobTitle, role.employerName, dates].filter(Boolean).join(' | ');
    const location = role.location ? `Location: ${role.location}` : '';
    const facts = orderedFacts(role, focus).map((fact) => `- ${fact.factText}`).join('\n');
    return [heading, location, facts].filter(Boolean).join('\n');
  });
  return `PROFESSIONAL EXPERIENCE\n\n${sections.join('\n\n')}`;
}

/** Describes the deterministic master-CV build for its immutable document record. */
export function masterCvGenerationSummary(roles: CareerKnowledgeRole[], focus: TailoringFocus): string {
  const facts = roles.reduce((total, role) => total + role.facts.length, 0);
  const roleCount = roles.filter((role) => role.facts.length).length;
  const categories = [...new Set(roles.flatMap((role) => role.facts.map((fact) => CAREER_FACT_LABELS[fact.category])))];
  return `Compiled ${facts} stored facts across ${roleCount} ${roleCount === 1 ? 'role' : 'roles'} with ${focus} emphasis. No job advert or AI generation was used. Facts were preserved verbatim${categories.length ? ` across: ${categories.join(', ')}` : ''}.`;
}
