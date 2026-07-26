export type ApplicationStatus = 'interested' | 'preparing' | 'applied' | 'interview' | 'offer' | 'accepted' | 'rejected' | 'withdrawn';

export type ApplicationTiming = {
  status: ApplicationStatus;
  application_date: string | Date | null;
  follow_up_date: string | Date | null;
};

const CLOSED_STATUSES = new Set<ApplicationStatus>(['accepted', 'rejected', 'withdrawn']);

/** Converts a database date value into a timezone-stable ISO date key. */
function dateKey(value: string | Date | null | undefined): string | null {
  if (!value) return null;
  if (typeof value === 'string') {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (match) return `${match[1]}-${match[2]}-${match[3]}`;
  }
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/** Returns the whole number of calendar days since submission, or null when no date is recorded. */
export function daysSinceSubmitted(value: string | Date | null | undefined, now = new Date()): number | null {
  const submittedKey = dateKey(value);
  const todayKey = dateKey(now);
  if (!submittedKey || !todayKey) return null;
  const submitted = Date.parse(`${submittedKey}T00:00:00Z`);
  const today = Date.parse(`${todayKey}T00:00:00Z`);
  return Math.max(0, Math.floor((today - submitted) / 86400000));
}

/** Formats the submission age for compact cards and timeline labels. */
export function submissionAgeLabel(value: string | Date | null | undefined, now = new Date()): string {
  const days = daysSinceSubmitted(value, now);
  if (days === null) return 'Not submitted';
  if (days === 0) return 'Submitted today';
  return `${days} ${days === 1 ? 'day' : 'days'} since submitted`;
}

/** Returns the whole number of calendar days until a follow-up date, with negative values representing overdue days. */
export function daysUntilFollowUp(value: string | Date | null | undefined, now = new Date()): number | null {
  const followUpKey = dateKey(value);
  const todayKey = dateKey(now);
  if (!followUpKey || !todayKey) return null;
  return Math.floor((Date.parse(`${followUpKey}T00:00:00Z`) - Date.parse(`${todayKey}T00:00:00Z`)) / 86400000);
}

/** Formats a concise next-action label from an explicit follow-up date. */
export function followUpLabel(value: string | Date | null | undefined, now = new Date()): string | null {
  const days = daysUntilFollowUp(value, now);
  if (days === null) return null;
  if (days < 0) return `Follow-up overdue by ${Math.abs(days)} ${Math.abs(days) === 1 ? 'day' : 'days'}`;
  if (days === 0) return 'Follow up today';
  if (days === 1) return 'Follow up tomorrow';
  return `Follow up in ${days} days`;
}

/** Flags an application that has reached either an explicit or sensible default follow-up point. */
export function applicationNeedsAttention(application: ApplicationTiming, now = new Date()): boolean {
  if (CLOSED_STATUSES.has(application.status)) return false;
  const followUpDays = daysUntilFollowUp(application.follow_up_date, now);
  if (followUpDays !== null) return followUpDays <= 0;
  const submittedDays = daysSinceSubmitted(application.application_date, now);
  return application.status === 'applied' && submittedDays !== null && submittedDays >= 14;
}

/** Provides cautious, explicit guidance for interpreting time without claiming to know an employer's intent. */
export function responseGuidance(application: ApplicationTiming, now = new Date()): string {
  const followUp = followUpLabel(application.follow_up_date, now);
  if (followUp && !CLOSED_STATUSES.has(application.status)) return followUp;
  if (application.status === 'interested') return 'Not yet in preparation';
  if (application.status === 'preparing') return 'Preparing to submit';
  if (application.status === 'interview') return 'Interview process active';
  if (application.status === 'offer') return 'Offer received';
  if (application.status === 'accepted') return 'Application accepted';
  if (application.status === 'rejected') return 'Application closed by employer';
  if (application.status === 'withdrawn') return 'Application withdrawn';
  const days = daysSinceSubmitted(application.application_date, now);
  if (days === null) return 'Add the submission date to track response time';
  if (days < 7) return 'Recently submitted';
  if (days < 14) return 'Still within an early response window';
  if (days < 30) return 'Consider a polite follow-up';
  if (days < 45) return 'Response is becoming less likely';
  return 'Consider closing or deprioritising this application';
}
