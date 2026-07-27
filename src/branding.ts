export const SITE_SHARE_TITLE = 'Job Tune — Tune your CV. Track every opportunity.';
export const SITE_DESCRIPTION = 'Create factual, tailored CVs and cover letters from your real career evidence, then manage documents and track every job application in one secure workspace.';

/** Resolves the canonical public origin used by link-preview crawlers without trusting arbitrary request headers. */
export function publicSiteOrigin(environment: NodeJS.ProcessEnv = process.env): string {
  const configured = environment.PUBLIC_ORIGIN || environment.WEBAUTHN_ORIGIN || '';
  try {
    const url = new URL(configured);
    if (url.protocol === 'https:' || url.protocol === 'http:') return url.origin;
  } catch { /* A missing or invalid operator value falls back to the local development origin. */ }
  return `http://localhost:${Number(environment.PORT || 3000) || 3000}`;
}
