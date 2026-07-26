import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { generateAuthenticationOptions, generateRegistrationOptions, verifyAuthenticationResponse, verifyRegistrationResponse, type AuthenticationResponseJSON, type AuthenticatorTransportFuture, type RegistrationResponseJSON } from '@simplewebauthn/server';
import dotenv from 'dotenv';
import express, { NextFunction, Request, Response } from 'express';
import mammoth from 'mammoth';
import multer from 'multer';
import nodemailer from 'nodemailer';
import { ResultSetHeader, RowDataPacket } from 'mysql2/promise';
import { applicationNeedsAttention, daysSinceSubmitted, followUpLabel, responseGuidance, submissionAgeLabel, type ApplicationStatus } from './applications';
import { createDatabasePool } from './db';
import { professionalDocxBuffer, professionalPdfBuffer, type ProfessionalDocumentContext, type ProfessionalDocumentProfile } from './documents';
import { discoverOpenAiModels, openAiResponseText, type OpenAiModel } from './openai';
import { buildTailoringPrompt, revisionGroupKey, tailoringControlsFromInput, TAILORING_FOCUS_LABELS, TAILORING_TONE_LABELS, type TailoringControls, type TailoringFocus, type TailoringTone } from './tailoring';

dotenv.config();
const app = express();
const pool = createDatabasePool();
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 8 * 1024 * 1024 }, fileFilter: (_req, file, callback) => callback(null, file.originalname.toLowerCase().endsWith('.docx')) });
app.set('trust proxy', 'loopback');
app.use(express.urlencoded({ extended: false }));
app.use(express.json());

type User = { id: number; email: string };
type Cv = RowDataPacket & { id: number; original_filename: string; extracted_text: string; version_number: number; document_name?: string | null; archived_at?: Date | null; created_at: Date };
type Tailored = RowDataPacket & { id: number; tailored_text: string; change_summary: string; cover_letter_text: string | null; company_name: string | null; job_title: string | null; model_name: string | null; generation_mode: string; job_description: string; source_cv_id: number; parent_tailored_cv_id: number | null; revision_group_key: string | null; revision_number: number; tailoring_focus: TailoringFocus; tailoring_tone: TailoringTone; tailoring_notes: string | null; original_filename: string; document_name?: string | null; archived_at?: Date | null; created_at: Date };
type JobApplication = RowDataPacket & { id: number; company_name: string; job_title: string; location: string | null; source_url: string | null; status: ApplicationStatus; application_date: string | Date | null; follow_up_date: string | Date | null; cv_document_id: number | null; tailored_cv_id: number | null; notes: string | null; created_at: Date; updated_at: Date; original_filename?: string | null; tailored_job_title?: string | null };
type ApplicationFormData = { id?: number; company_name?: string | null; job_title?: string | null; location?: string | null; source_url?: string | null; status?: ApplicationStatus; application_date?: string | Date | null; follow_up_date?: string | Date | null; cv_document_id?: number | null; tailored_cv_id?: number | null; notes?: string | null };
type UserProfile = RowDataPacket & { full_name: string | null; phone: string | null; address_line_1: string | null; address_line_2: string | null; city: string | null; region: string | null; postal_code: string | null; country: string | null; linkedin_url: string | null; portfolio_url: string | null };
type DocumentType = 'master_cv' | 'tailored_cv';
type TimelineFilter = 'all' | 'attention' | ApplicationStatus;
type WebAuthnChallenge = RowDataPacket & { id: number; ceremony: 'registration' | 'authentication'; challenge: string; email: string | null; user_id: number | null; user_handle: Buffer | null };
type PasskeyCredential = RowDataPacket & { credential_id: string; user_id: number; credential_public_key: Buffer; counter: number; transports: string | null };
type ModelCatalogue = { models: OpenAiModel[]; checkedAt: string; newModelIds: string[] };

/** Escapes untrusted strings before placing them in server-rendered HTML. */
function escapeHtml(value: string): string { return value.replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character] || character)); }

/** Reads a named cookie without bringing a client-session dependency into the application. */
function cookie(request: Request, name: string): string | undefined { return request.headers.cookie?.split(';').map((part) => part.trim()).find((part) => part.startsWith(`${name}=`))?.slice(name.length + 1); }

/** Compares two CSRF values without leaking useful timing information. */
function csrfTokensMatch(first: string, second: string): boolean { const left = Buffer.from(first); const right = Buffer.from(second); return left.length === right.length && left.length >= 32 && crypto.timingSafeEqual(left, right); }

/** Enforces a double-submit CSRF token for authenticated state-changing forms. */
function requireCsrf(request: Request, response: Response, next: NextFunction): void { const expected = cookie(request, 'job_tune_csrf') || ''; const supplied = String(request.body.csrfToken || request.headers['x-csrf-token'] || ''); if (!csrfTokensMatch(expected, supplied)) { response.status(403).send('This form expired. Refresh the page and try again.'); return; } next(); }

/** Issues CSRF cookies for active sessions and protects regular form posts. */
function csrfMiddleware(request: Request, response: Response, next: NextFunction): void { const sessionId = cookie(request, 'job_tune_session'); if (sessionId && !cookie(request, 'job_tune_csrf')) response.cookie('job_tune_csrf', crypto.randomBytes(32).toString('hex'), { sameSite: 'strict', secure: process.env.NODE_ENV === 'production', maxAge: 14 * 86400 * 1000 }); const formPost = request.method === 'POST' && request.is('application/x-www-form-urlencoded'); if (sessionId && formPost && !request.path.startsWith('/auth/')) { requireCsrf(request, response, next); return; } next(); }

app.use(csrfMiddleware);

/** Returns the server-only secret used to hash opaque WebAuthn ceremony tokens. */
function authSecret(): string {
  const secret = process.env.AUTH_SECRET || process.env.SESSION_SECRET || '';
  if (process.env.NODE_ENV === 'production' && secret.length < 32) throw new Error('AUTH_SECRET must contain at least 32 characters in production.');
  return secret || 'development-only-job-tune-auth-secret';
}

/** Produces a one-way HMAC so client ceremony tokens are never stored in plaintext. */
function authenticationHash(purpose: string, value: string): string { return crypto.createHmac('sha256', authSecret()).update(`${purpose}:${value}`).digest('hex'); }

/** Returns and validates the WebAuthn relying-party settings for this deployment. */
function webAuthnConfig(): { rpID: string; origin: string; rpName: string } {
  const rpID = process.env.WEBAUTHN_RP_ID || 'localhost';
  const origin = process.env.WEBAUTHN_ORIGIN || `http://${rpID}:${process.env.PORT || 3000}`;
  if (process.env.NODE_ENV === 'production' && (!process.env.WEBAUTHN_RP_ID || !process.env.WEBAUTHN_ORIGIN || !origin.startsWith('https://'))) throw new Error('Production passkeys require WEBAUTHN_RP_ID and an HTTPS WEBAUTHN_ORIGIN.');
  return { rpID, origin: origin.replace(/\/$/, ''), rpName: process.env.WEBAUTHN_RP_NAME || 'Job Tune' };
}

/** Creates an opaque, single-use database record for a WebAuthn ceremony. */
async function storeWebAuthnChallenge(ceremony: 'registration' | 'authentication', challenge: string, details: { email?: string; userId?: number; userHandle?: Buffer; requestIpHash: string }): Promise<string> {
  const token = crypto.randomBytes(32).toString('hex');
  await pool.query('DELETE FROM webauthn_challenges WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
  await pool.query('INSERT INTO webauthn_challenges (token_hash,ceremony,challenge,email,user_id,user_handle,request_ip_hash,expires_at) VALUES (?,?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 5 MINUTE))', [authenticationHash('webauthn-token', token), ceremony, challenge, details.email || null, details.userId || null, details.userHandle || null, details.requestIpHash]);
  return token;
}

/** Applies a coarse IP throttle to anonymous challenge creation without storing raw addresses. */
async function passkeyRequestIsRateLimited(request: Request): Promise<boolean> { const requestIpHash = authenticationHash('webauthn-ip', request.ip || 'unknown'); const [rows] = await pool.query<RowDataPacket[]>('SELECT COUNT(*) AS requests FROM webauthn_challenges WHERE request_ip_hash=? AND created_at>DATE_SUB(NOW(), INTERVAL 15 MINUTE)', [requestIpHash]); return Number(rows[0].requests) >= 30; }

/** Safely decodes the optional JSON list of transports stored with a credential. */
function credentialTransports(value: string | null): AuthenticatorTransportFuture[] | undefined { if (!value) return undefined; try { return JSON.parse(value) as AuthenticatorTransportFuture[]; } catch { return undefined; } }

/** Returns local browser scripts that run passkey ceremonies from native button clicks. */
function passkeyBrowserScript(): string { return '<script src="/assets/simplewebauthn.js"></script><script src="/assets/passkeys.js"></script>'; }

/** Looks up the authenticated user from the server-side MySQL session. */
async function currentUser(request: Request): Promise<User | null> {
  const sessionId = cookie(request, 'job_tune_session');
  if (!sessionId) return null;
  const [rows] = await pool.query<RowDataPacket[]>('SELECT u.id, u.email FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.id=? AND s.expires_at > NOW()', [sessionId]);
  return rows.length ? rows[0] as User : null;
}

/** Redirects anonymous visitors to login while retaining a small, explicit request boundary. */
async function requireUser(request: Request, response: Response, next: NextFunction): Promise<void> { const user = await currentUser(request); if (!user) { response.redirect('/login'); return; } response.locals.user = user; next(); }

/** Creates a durable, HTTP-only session token whose authority remains in MySQL. */
async function startSession(response: Response, userId: number): Promise<void> {
  const id = crypto.randomBytes(32).toString('hex');
  await pool.query('INSERT INTO sessions (id,user_id,expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 14 DAY))', [id, userId]);
  response.cookie('job_tune_session', id, { httpOnly: true, sameSite: 'lax', secure: process.env.NODE_ENV === 'production', maxAge: 14 * 86400 * 1000 });
  response.cookie('job_tune_csrf', crypto.randomBytes(32).toString('hex'), { sameSite: 'strict', secure: process.env.NODE_ENV === 'production', maxAge: 14 * 86400 * 1000 });
}

/** Returns the deployment's manual model fallbacks in operator-controlled display order. */
function configuredModels(): string[] { const defaults = ['gpt-5-mini', 'gpt-5-nano', 'gpt-4.1-mini']; const configured = (process.env.OPENAI_MODELS || defaults.join(',')).split(',').map((model) => model.trim()).filter(Boolean); return configured.length ? [...new Set(configured)] : defaults; }

/** Reads the last valid provider catalogue from the site-wide settings store. */
async function cachedModelCatalogue(): Promise<ModelCatalogue | null> {
  const [rows] = await pool.query<RowDataPacket[]>('SELECT setting_value FROM site_settings WHERE setting_key=?', ['openai_model_catalogue']);
  if (!rows.length) return null;
  try {
    const value = JSON.parse(String(rows[0].setting_value)) as Partial<ModelCatalogue>;
    if (!Array.isArray(value.models) || typeof value.checkedAt !== 'string') return null;
    const models = value.models.filter((model): model is OpenAiModel => Boolean(model && typeof model.id === 'string' && typeof model.created === 'number'));
    return { models, checkedAt: value.checkedAt, newModelIds: Array.isArray(value.newModelIds) ? value.newModelIds.filter((id): id is string => typeof id === 'string') : [] };
  } catch { return null; }
}

/** Combines operator fallbacks with the most recently discovered account models. */
async function availableModels(catalogue?: ModelCatalogue | null): Promise<string[]> { const cached = catalogue === undefined ? await cachedModelCatalogue() : catalogue; return [...new Set([...configuredModels(), ...(cached?.models.map((model) => model.id) || [])])]; }

/** Returns whether signed-in users may ask the server to refresh the provider catalogue. */
function modelDiscoveryEnabled(): boolean { const setting = String(process.env.OPENAI_MODEL_DISCOVERY || 'true').toLowerCase(); return Boolean(process.env.OPENAI_API_KEY) && !['false', '0', 'off'].includes(setting); }

/** Returns the minimum number of minutes between site-wide model catalogue refreshes. */
function modelRefreshMinutes(): number { const configured = Number(process.env.OPENAI_MODEL_REFRESH_MINUTES || 5); return Number.isFinite(configured) ? Math.min(1440, Math.max(1, Math.floor(configured))) : 5; }

/** Returns whether the cached catalogue is still inside the refresh cooldown. */
function modelCatalogueIsRecent(catalogue: ModelCatalogue | null): boolean { if (!catalogue) return false; const checkedAt = new Date(catalogue.checkedAt).getTime(); return Number.isFinite(checkedAt) && Date.now() - checkedAt < modelRefreshMinutes() * 60000; }

/** Persists a validated account catalogue without storing API credentials or provider responses. */
async function saveModelCatalogue(models: OpenAiModel[], previous: ModelCatalogue | null): Promise<ModelCatalogue> {
  const previousIds = new Set(previous?.models.map((model) => model.id) || configuredModels());
  const catalogue = { models, checkedAt: new Date().toISOString(), newModelIds: models.map((model) => model.id).filter((id) => !previousIds.has(id)) };
  await pool.query('INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', ['openai_model_catalogue', JSON.stringify(catalogue)]);
  return catalogue;
}

/** Returns the user's selected model or the deployment default when no preference exists. */
async function preferredModel(userId: number, selectableModels?: string[]): Promise<string> { const [rows] = await pool.query<RowDataPacket[]>('SELECT ai_model FROM user_preferences WHERE user_id=?', [userId]); const models = selectableModels || await availableModels(); const selected = String(rows[0]?.ai_model || ''); const configuredDefault = process.env.OPENAI_MODEL || ''; if (models.includes(selected)) return selected; return models.includes(configuredDefault) ? configuredDefault : models[0]; }

/** Converts a database date into its calendar-day key without shifting it across time zones. */
function calendarDateKey(value: string | Date | null | undefined): string | null { if (!value) return null; if (typeof value === 'string') { const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/); if (match) return `${match[1]}-${match[2]}-${match[3]}`; } const date = value instanceof Date ? value : new Date(value); if (Number.isNaN(date.getTime())) return null; const year = date.getFullYear(); const month = String(date.getMonth() + 1).padStart(2, '0'); const day = String(date.getDate()).padStart(2, '0'); return `${year}-${month}-${day}`; }

/** Returns a safely formatted date for inputs and human-readable lists. */
function formatDate(value: string | Date | null | undefined, input = false): string { const key = calendarDateKey(value); if (!key) return ''; if (input) return key; return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${key}T00:00:00Z`)); }

/** Returns an unambiguous UTC timestamp for site-wide operational checks. */
function formatDateTime(value: string | Date): string { const date = value instanceof Date ? value : new Date(value); if (Number.isNaN(date.getTime())) return 'Unknown'; return `${new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'UTC' }).format(date)} UTC`; }

/** Returns the label used for an application workflow state. */
function statusLabel(status: ApplicationStatus): string { return ({ interested: 'Interested', preparing: 'Preparing', applied: 'Applied', interview: 'Interview', offer: 'Offer', accepted: 'Accepted', rejected: 'Rejected', withdrawn: 'Withdrawn' })[status]; }

/** Renders application-state options while preserving the current selection. */
function statusOptions(selected: ApplicationStatus = 'interested'): string { const statuses: ApplicationStatus[] = ['interested', 'preparing', 'applied', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn']; return statuses.map((status) => `<option value="${status}"${status === selected ? ' selected' : ''}>${statusLabel(status)}</option>`).join(''); }

/** Validates an application state received from an untrusted form. */
function validStatus(value: string): ApplicationStatus { const statuses: ApplicationStatus[] = ['interested', 'preparing', 'applied', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn']; return statuses.includes(value as ApplicationStatus) ? value as ApplicationStatus : 'interested'; }

/** Accepts only absolute HTTP(S) URLs for saved external links. */
function safeUrl(value: string): string | null { if (!value.trim()) return null; try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url.toString() : null; } catch { return null; } }

/** Accepts a real ISO calendar date from an untrusted form or returns no date. */
function safeDateInput(value: unknown): string | null { const text = String(value || '').trim(); if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) return null; const date = new Date(`${text}T00:00:00Z`); return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === text ? text : null; }

/** Returns a high-contrast status style that remains distinguishable by its text label. */
function statusBadgeClass(status: ApplicationStatus): string { if (['accepted', 'offer'].includes(status)) return 'status-badge status-positive'; if (['rejected', 'withdrawn'].includes(status)) return 'status-badge status-closed'; if (status === 'interview') return 'status-badge status-progress'; return 'status-badge status-neutral'; }

/** Validates the optional timeline filter without allowing arbitrary SQL values. */
function validTimelineFilter(value: unknown): TimelineFilter { const candidate = String(value || 'all'); const filters: TimelineFilter[] = ['all', 'attention', 'interested', 'preparing', 'applied', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn']; return filters.includes(candidate as TimelineFilter) ? candidate as TimelineFilter : 'all'; }

/** Renders timeline filter choices while preserving the selected safe value. */
function timelineFilterOptions(selected: TimelineFilter): string { const choices: Array<[TimelineFilter, string]> = [['all', 'All applications'], ['attention', 'Needs attention'], ['interested', 'Interested'], ['preparing', 'Preparing'], ['applied', 'Applied'], ['interview', 'Interview'], ['offer', 'Offer'], ['accepted', 'Accepted'], ['rejected', 'Rejected'], ['withdrawn', 'Withdrawn']]; return choices.map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${label}</option>`).join(''); }

/** Maps the current page title to a stable navigation section. */
function activeNavigationHref(title: string): string { if (/timeline/i.test(title)) return '/applications/timeline'; if (/tailor/i.test(title)) return '/tailor'; if (/application/i.test(title) && !/pack/i.test(title)) return '/applications'; if (/document|pack|version/i.test(title)) return '/documents'; if (/profile|contact/i.test(title)) return '/profile'; if (/setting|passkey/i.test(title)) return '/settings'; return '/'; }

/** Renders one accessible desktop or mobile navigation link with a current-page state. */
function navigationLink(href: string, label: string, activeHref: string, mobile = false): string { const active = href === activeHref; return `<a class="${mobile ? 'mobile-nav-link' : 'nav-link'}${active ? ' is-active' : ''}" href="${href}"${active ? ' aria-current="page"' : ''}>${label}</a>`; }

/** Renders the shared responsive light/dark application shell and navigation. */
function page(title: string, body: string, user?: User): string {
  const activeHref = activeNavigationHref(title);
  const navigation = user ? `<nav class="hidden items-center gap-1 lg:flex" aria-label="Primary navigation">${navigationLink('/', 'Dashboard', activeHref)}${navigationLink('/applications', 'Applications', activeHref)}${navigationLink('/applications/timeline', 'Timeline', activeHref)}${navigationLink('/documents', 'Documents', activeHref)}${navigationLink('/tailor', 'Tailor', activeHref)}${navigationLink('/profile', 'Profile', activeHref)}${navigationLink('/settings', 'Settings', activeHref)}</nav>` : '';
  const mobileNavigation = user ? `<nav class="site-mobile-nav fixed inset-x-0 bottom-0 z-40 grid grid-cols-6 px-1 py-2 text-center text-[10px] font-semibold backdrop-blur lg:hidden" aria-label="Mobile navigation">${navigationLink('/', 'Home', activeHref, true)}${navigationLink('/applications', 'Jobs', activeHref, true)}${navigationLink('/applications/timeline', 'Timeline', activeHref, true)}${navigationLink('/documents', 'Docs', activeHref, true)}${navigationLink('/tailor', 'Tailor', activeHref, true)}${navigationLink('/settings', 'More', activeHref, true)}</nav>` : '';
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#f8fafc"><script>(()=>{const saved=localStorage.getItem('job-tune-theme');if(saved==='dark'||(!saved&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark');})();</script><link rel="stylesheet" href="/assets/app.css"><title>${escapeHtml(title)} · Job Tune</title></head><body class="app-body min-h-screen bg-slate-50 text-slate-950 antialiased dark:bg-slate-950 dark:text-slate-100"><header class="site-header sticky top-0 z-30 backdrop-blur-xl"><div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6"><a href="/" class="flex items-center gap-2 font-bold tracking-tight"><span class="grid h-8 w-8 place-items-center rounded-xl bg-cyan-600 text-sm text-white">JT</span>Job Tune</a>${navigation}<div class="flex items-center gap-2"><button id="theme-toggle" class="icon-button" type="button" aria-label="Switch to dark mode" aria-pressed="false">◐</button>${user ? `<span class="hidden max-w-40 truncate text-xs text-slate-500 xl:block">${escapeHtml(user.email)}</span><form method="post" action="/logout" class="inline"><button class="icon-button" title="Log out" aria-label="Log out">↗</button></form>` : ''}</div></div></header><main class="site-main mx-auto max-w-7xl px-4 py-6 pb-24 sm:px-6 sm:py-10 lg:pb-10">${body}</main>${mobileNavigation}<script src="/assets/security.js"></script><script src="/assets/theme.js"></script></body></html>`;
}

/** Preserves the input CV exactly when AI is unavailable while plainly identifying the limitation. */
function localFallback(cvText: string, jobDescription: string, profile: UserProfile | null, companyName: string, jobTitle: string, model: string, controls: TailoringControls): { tailoredText: string; summary: string; coverLetter: string; mode: string; model: string } {
  const keywords = [...new Set((jobDescription.toLowerCase().match(/[a-z][a-z-]{3,}/g) || []).filter((word) => cvText.toLowerCase().includes(word)))].slice(0, 12);
  const name = profile?.full_name || 'Applicant';
  return { tailoredText: cvText, summary: `Local factual fallback: the source CV is unchanged because no AI API key is configured, so the requested ${TAILORING_FOCUS_LABELS[controls.focus].toLowerCase()} emphasis and ${TAILORING_TONE_LABELS[controls.tone].toLowerCase()} tone were not applied. Matching terms already present in the CV: ${keywords.length ? keywords.join(', ') : 'none detected'}. Configure OPENAI_API_KEY to enable drafting.`, coverLetter: `Dear Hiring Team,\n\nPlease accept my application for the ${jobTitle || 'advertised role'}${companyName ? ` at ${companyName}` : ''}. My attached CV provides a factual account of my relevant experience and qualifications.\n\nI would welcome the opportunity to discuss my application further.\n\nKind regards,\n${name}`, mode: 'local_fallback', model };
}

/** Requests a constrained AI response that can only use source-CV facts. */
async function tailorWithAi(cvText: string, jobDescription: string, profile: UserProfile | null, companyName: string, jobTitle: string, model: string, controls: TailoringControls): Promise<{ tailoredText: string; summary: string; coverLetter: string; mode: string; model: string }> {
  if (!process.env.OPENAI_API_KEY) return localFallback(cvText, jobDescription, profile, companyName, jobTitle, model, controls);
  const contact = profile ? JSON.stringify({ fullName: profile.full_name, phone: profile.phone, address: [profile.address_line_1, profile.address_line_2, profile.city, profile.region, profile.postal_code, profile.country].filter(Boolean).join(', '), linkedin: profile.linkedin_url, portfolio: profile.portfolio_url }) : '{}';
  const prompt = buildTailoringPrompt({ cvText, jobDescription, contact, companyName, jobTitle, controls });
  const schema = { type: 'object', properties: { tailoredText: { type: 'string' }, changeSummary: { type: 'string' }, coverLetter: { type: 'string' } }, required: ['tailoredText', 'changeSummary', 'coverLetter'], additionalProperties: false };
  const result = await fetch('https://api.openai.com/v1/responses', { method: 'POST', headers: { Authorization: `Bearer ${process.env.OPENAI_API_KEY}`, 'Content-Type': 'application/json' }, signal: AbortSignal.timeout(120000), body: JSON.stringify({ model, instructions: 'You are a meticulous CV editor. Never invent candidate facts.', input: prompt, text: { format: { type: 'json_schema', name: 'job_tune_application_pack', strict: true, schema } }, max_output_tokens: 12000 }) });
  if (!result.ok) throw new Error('The AI provider did not accept the tailoring request.');
  const responseText = openAiResponseText(await result.json());
  if (!responseText) throw new Error('The AI provider returned no tailoring response.');
  const parsed = JSON.parse(responseText) as { tailoredText?: string; changeSummary?: string; coverLetter?: string };
  if (!parsed.tailoredText || !parsed.changeSummary || !parsed.coverLetter) throw new Error('The AI provider returned an incomplete tailoring response.');
  return { tailoredText: parsed.tailoredText, summary: parsed.changeSummary, coverLetter: parsed.coverLetter, mode: 'openai', model };
}

/** Retrieves optional contact details for cover-letter generation and profile editing. */
async function profileForUser(userId: number): Promise<UserProfile | null> { const [rows] = await pool.query<UserProfile[]>('SELECT * FROM user_profiles WHERE user_id=?', [userId]); return rows[0] || null; }

/** Maps stored user identity and profile fields into the document renderer's factual contact model. */
function professionalDocumentProfile(user: User, profile: UserProfile | null): ProfessionalDocumentProfile { return { fullName: profile?.full_name, email: user.email, phone: profile?.phone, addressLine1: profile?.address_line_1, addressLine2: profile?.address_line_2, city: profile?.city, region: profile?.region, postalCode: profile?.postal_code, country: profile?.country, linkedinUrl: profile?.linkedin_url, portfolioUrl: profile?.portfolio_url }; }

/** Builds the complete render context shared by CV and cover-letter downloads and email attachments. */
function professionalDocumentContext(kind: 'cv' | 'cover_letter', user: User, profile: UserProfile | null, output: Tailored): ProfessionalDocumentContext { return { kind, profile: professionalDocumentProfile(user, profile), companyName: output.company_name, jobTitle: output.job_title, createdAt: output.created_at }; }

/** Renders selectable model options and marks models found by the latest refresh. */
function modelOptions(models: string[], selected: string, newModelIds: string[] = []): string { const newModels = new Set(newModelIds); return models.map((model) => `<option value="${escapeHtml(model)}"${model === selected ? ' selected' : ''}>${newModels.has(model) ? 'New · ' : ''}${escapeHtml(model)}</option>`).join(''); }

/** Renders the closed list of factual emphasis choices for initial and repeated tailoring. */
function focusOptions(selected: TailoringFocus): string { return (Object.entries(TAILORING_FOCUS_LABELS) as Array<[TailoringFocus, string]>).map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${escapeHtml(label)}</option>`).join(''); }

/** Renders the closed list of professional tone choices for initial and repeated tailoring. */
function toneOptions(selected: TailoringTone): string { return (Object.entries(TAILORING_TONE_LABELS) as Array<[TailoringTone, string]>).map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${escapeHtml(label)}</option>`).join(''); }

/** Renders shared emphasis, tone, and optional guidance fields without exposing free-form factual inputs. */
function tailoringControlFields(controls: TailoringControls): string { return `<label class="text-sm font-semibold">Emphasis<select class="field mt-2" name="focus">${focusOptions(controls.focus)}</select><span class="mt-1 block font-normal text-slate-500">Changes ordering and prominence only; it cannot add experience.</span></label><label class="text-sm font-semibold">Tone<select class="field mt-2" name="tone">${toneOptions(controls.tone)}</select><span class="mt-1 block font-normal text-slate-500">Applies to both the CV and cover letter.</span></label><label class="text-sm font-semibold sm:col-span-2">Additional emphasis <span class="font-normal text-slate-400">optional</span><textarea class="field mt-2 min-h-24" name="tailoringNotes" maxlength="500" placeholder="For example: give more prominence to documented stakeholder management.">${escapeHtml(controls.notes)}</textarea><span class="mt-1 block font-normal text-slate-500">Use this for presentation priorities, not new facts.</span></label>`; }

/** Converts stored revision controls into the same validated representation used by form submissions. */
function controlsForOutput(output: Tailored): TailoringControls { return tailoringControlsFromInput(output.tailoring_focus, output.tailoring_tone, output.tailoring_notes); }

/** Summarises one revision's model and presentation controls for comparison screens. */
function tailoringSummary(output: Tailored): string { const controls = controlsForOutput(output); return `${output.model_name || output.generation_mode} · ${TAILORING_FOCUS_LABELS[controls.focus]} · ${TAILORING_TONE_LABELS[controls.tone]}`; }

/** Returns active master CV summaries with their optional user-defined names. */
async function activeCvsForUser(userId: number): Promise<Cv[]> { const [rows] = await pool.query<Cv[]>("SELECT c.id,c.original_filename,c.version_number,c.created_at,dm.display_name AS document_name FROM cv_documents c LEFT JOIN document_metadata dm ON dm.user_id=c.user_id AND dm.document_type='master_cv' AND dm.document_id=c.id WHERE c.user_id=? AND dm.archived_at IS NULL ORDER BY c.created_at DESC", [userId]); return rows; }

/** Returns active tailored application-pack summaries with their optional user-defined names. */
async function activeTailoredForUser(userId: number): Promise<Tailored[]> { const [rows] = await pool.query<Tailored[]>("SELECT t.id,t.source_cv_id,t.cover_letter_text,t.company_name,t.job_title,t.model_name,t.generation_mode,t.revision_group_key,t.revision_number,t.tailoring_focus,t.tailoring_tone,t.tailoring_notes,t.created_at,c.original_filename,dm.display_name AS document_name FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id LEFT JOIN document_metadata dm ON dm.user_id=t.user_id AND dm.document_type='tailored_cv' AND dm.document_id=t.id WHERE t.user_id=? AND dm.archived_at IS NULL ORDER BY t.created_at DESC", [userId]); return rows; }

/** Returns the best user-facing name for a master CV while retaining its original filename. */
function cvName(cv: Cv): string { return cv.document_name || cv.original_filename; }

/** Returns the best user-facing name for a tailored application pack. */
function tailoredName(output: Tailored): string { return output.document_name || output.job_title || `Application pack ${output.id}`; }

/** Converts a user-facing name into a safe, compact download filename stem. */
function downloadBaseName(value: string, fallback: string): string { const cleaned = value.replace(/\.(docx|pdf)$/i, '').replace(/[\u0000-\u001f/\\:*?"<>|]+/g, '-').replace(/\s+/g, ' ').trim().slice(0, 120); return cleaned || fallback; }

/** Resolves the physical table behind a validated document type. */
function documentTable(documentType: DocumentType): 'cv_documents' | 'tailored_cvs' { return documentType === 'master_cv' ? 'cv_documents' : 'tailored_cvs'; }

/** Converts a public route segment into a closed document-type value. */
function routeDocumentType(value: string): DocumentType | null { if (value === 'master') return 'master_cv'; if (value === 'tailored') return 'tailored_cv'; return null; }

/** Confirms a document exists and belongs to the current user before metadata changes. */
async function documentBelongsToUser(documentType: DocumentType, documentId: number, userId: number): Promise<boolean> { if (!Number.isInteger(documentId) || documentId <= 0) return false; const [rows] = await pool.query<RowDataPacket[]>(`SELECT id FROM ${documentTable(documentType)} WHERE id=? AND user_id=?`, [documentId, userId]); return rows.length > 0; }

/** Reports whether an owned document is currently in recoverable trash. */
async function documentIsArchived(documentType: DocumentType, documentId: number, userId: number): Promise<boolean> { const [rows] = await pool.query<RowDataPacket[]>('SELECT document_id FROM document_metadata WHERE user_id=? AND document_type=? AND document_id=? AND archived_at IS NOT NULL', [userId, documentType, documentId]); return rows.length > 0; }

/** Creates or updates one user-scoped document's recoverable archive state. */
async function setDocumentArchived(documentType: DocumentType, documentId: number, userId: number, archived: boolean): Promise<boolean> { if (!await documentBelongsToUser(documentType, documentId, userId)) return false; await pool.query('INSERT INTO document_metadata (user_id,document_type,document_id,archived_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE archived_at=VALUES(archived_at)', [userId, documentType, documentId, archived ? new Date() : null]); return true; }

/** Saves a custom document name without altering the original uploaded file. */
async function renameDocument(documentType: DocumentType, documentId: number, userId: number, displayName: string): Promise<boolean> { if (!await documentBelongsToUser(documentType, documentId, userId)) return false; await pool.query('INSERT INTO document_metadata (user_id,document_type,document_id,display_name) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name)', [userId, documentType, documentId, displayName]); return true; }

/** Ensures a selected source document belongs to the current user before linking it. */
async function ownedCvId(id: number, userId: number): Promise<number | null> { if (!Number.isInteger(id) || id <= 0) return null; const [rows] = await pool.query<RowDataPacket[]>("SELECT c.id FROM cv_documents c LEFT JOIN document_metadata dm ON dm.user_id=c.user_id AND dm.document_type='master_cv' AND dm.document_id=c.id WHERE c.id=? AND c.user_id=? AND dm.archived_at IS NULL", [id, userId]); return rows.length ? id : null; }

/** Ensures a selected tailored document belongs to the current user before linking it. */
async function ownedTailoredId(id: number, userId: number): Promise<number | null> { if (!Number.isInteger(id) || id <= 0) return null; const [rows] = await pool.query<RowDataPacket[]>("SELECT t.id FROM tailored_cvs t LEFT JOIN document_metadata dm ON dm.user_id=t.user_id AND dm.document_type='tailored_cv' AND dm.document_id=t.id WHERE t.id=? AND t.user_id=? AND dm.archived_at IS NULL", [id, userId]); return rows.length ? id : null; }

/** Renders the reusable create/edit form for a tracked job application. */
function applicationForm(application: ApplicationFormData, cvs: Cv[], outputs: Tailored[]): string {
  const linkedCvIsArchived = Boolean(application.cv_document_id && !cvs.some((cv) => cv.id === Number(application.cv_document_id)));
  const linkedTailoredIsArchived = Boolean(application.tailored_cv_id && !outputs.some((output) => output.id === Number(application.tailored_cv_id)));
  const cvOptions = `${linkedCvIsArchived ? `<option value="${Number(application.cv_document_id)}" selected>Linked master CV · in Trash</option>` : ''}${cvs.map((cv) => `<option value="${cv.id}"${Number(application.cv_document_id) === cv.id ? ' selected' : ''}>Version ${cv.version_number} · ${escapeHtml(cvName(cv))}</option>`).join('')}`;
  const outputOptions = `${linkedTailoredIsArchived ? `<option value="${Number(application.tailored_cv_id)}" selected>Linked application pack · in Trash</option>` : ''}${outputs.map((output) => `<option value="${output.id}"${Number(application.tailored_cv_id) === output.id ? ' selected' : ''}>${escapeHtml(tailoredName(output))} · v${output.revision_number}${output.company_name ? ` · ${escapeHtml(output.company_name)}` : ''}</option>`).join('')}`;
  const revisionActions = application.id && application.tailored_cv_id && !linkedTailoredIsArchived ? `<div class="mt-5 flex flex-col gap-2 rounded-2xl border border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-900 dark:bg-cyan-950/40 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-semibold">Try another tailored version</p><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Change the model, emphasis, or tone while keeping the original version.</p></div><div class="flex shrink-0 gap-2"><a class="button-secondary" href="/tailored/${Number(application.tailored_cv_id)}">Review pack</a><a class="button-primary" href="/tailored/${Number(application.tailored_cv_id)}/retailor?application=${Number(application.id)}">Re-tailor</a></div></div>` : '';
  return `<form method="post" action="${application.id ? `/applications/${application.id}` : '/applications'}" class="panel mx-auto max-w-4xl p-5 sm:p-8"><div class="grid gap-5 sm:grid-cols-2"><label class="text-sm font-semibold">Company<input class="field mt-2" name="companyName" maxlength="255" required value="${escapeHtml(application.company_name || '')}"></label><label class="text-sm font-semibold">Role title<input class="field mt-2" name="jobTitle" maxlength="255" required value="${escapeHtml(application.job_title || '')}"></label><label class="text-sm font-semibold">Location<input class="field mt-2" name="location" maxlength="255" value="${escapeHtml(application.location || '')}"></label><label class="text-sm font-semibold">Job advert URL<input class="field mt-2" name="sourceUrl" type="url" value="${escapeHtml(application.source_url || '')}"></label><label class="text-sm font-semibold">Status<select class="field mt-2" name="status">${statusOptions(application.status || 'interested')}</select></label><label class="text-sm font-semibold">Date submitted<input class="field mt-2" name="applicationDate" type="date" value="${formatDate(application.application_date, true)}"><span class="mt-1 block font-normal text-slate-500">Used to calculate days without a response.</span></label><label class="text-sm font-semibold">Next follow-up <span class="font-normal text-slate-400">optional</span><input class="field mt-2" name="followUpDate" type="date" value="${formatDate(application.follow_up_date, true)}"><span class="mt-1 block font-normal text-slate-500">Appears as an attention reminder when due.</span></label><label class="text-sm font-semibold">Master CV<select class="field mt-2" name="cvDocumentId"><option value="">Not linked</option>${cvOptions}</select></label><label class="text-sm font-semibold">Tailored application pack<select class="field mt-2" name="tailoredCvId"><option value="">Not linked</option>${outputOptions}</select></label></div><label class="mt-5 block text-sm font-semibold">Notes<textarea class="field mt-2 min-h-32" name="notes" maxlength="10000" placeholder="Interview contacts, deadlines, follow-up notes…">${escapeHtml(application.notes || '')}</textarea></label>${revisionActions}<div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a class="button-secondary" href="/applications">Cancel</a><button class="button-primary">${application.id ? 'Save changes' : 'Add application'}</button></div></form>`;
}

/** Reports whether direct document email delivery is configured. */
function emailDeliveryConfigured(): boolean { return Boolean(process.env.SMTP_HOST && process.env.SMTP_FROM); }

/** Sends a tailored application pack through the configured SMTP relay. */
async function sendApplicationPack(recipient: string, subject: string, message: string, user: User, profile: UserProfile | null, output: Tailored, includeCv: boolean, includeCoverLetter: boolean): Promise<void> {
  if (!emailDeliveryConfigured()) throw new Error('SMTP delivery is not configured.');
  const transport = nodemailer.createTransport({ host: process.env.SMTP_HOST, port: Number(process.env.SMTP_PORT || 587), secure: process.env.SMTP_SECURE === 'true', auth: process.env.SMTP_USER ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASSWORD } : undefined });
  const attachments: Array<{ filename: string; content: Buffer; contentType: string }> = [];
  const baseName = downloadBaseName(tailoredName(output), 'job-tune');
  if (includeCv) attachments.push({ filename: `${baseName}-cv.docx`, content: await professionalDocxBuffer(output.tailored_text, professionalDocumentContext('cv', user, profile, output)), contentType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
  if (includeCoverLetter && output.cover_letter_text) attachments.push({ filename: `${baseName}-cover-letter.docx`, content: await professionalDocxBuffer(output.cover_letter_text, professionalDocumentContext('cover_letter', user, profile, output)), contentType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
  await transport.sendMail({ from: process.env.SMTP_FROM, to: recipient, subject, text: message, attachments });
}

/** Shows an actionable overview of applications, documents, and next steps. */
app.get('/', requireUser, async (_request, response) => {
  const user = response.locals.user as User;
  const [[applicationCount], [documentCount], [tailoredCount], [attentionCount], [statusRows], [recentApplications]] = await Promise.all([
    pool.query<RowDataPacket[]>('SELECT COUNT(*) AS total FROM job_applications WHERE user_id=?', [user.id]),
    pool.query<RowDataPacket[]>("SELECT COUNT(*) AS total FROM cv_documents c LEFT JOIN document_metadata dm ON dm.user_id=c.user_id AND dm.document_type='master_cv' AND dm.document_id=c.id WHERE c.user_id=? AND dm.archived_at IS NULL", [user.id]),
    pool.query<RowDataPacket[]>("SELECT COUNT(*) AS total FROM tailored_cvs t LEFT JOIN document_metadata dm ON dm.user_id=t.user_id AND dm.document_type='tailored_cv' AND dm.document_id=t.id WHERE t.user_id=? AND dm.archived_at IS NULL", [user.id]),
    pool.query<RowDataPacket[]>("SELECT COUNT(*) AS total FROM job_applications WHERE user_id=? AND status NOT IN ('accepted','rejected','withdrawn') AND ((follow_up_date IS NOT NULL AND follow_up_date<=CURDATE()) OR (follow_up_date IS NULL AND status='applied' AND application_date<=DATE_SUB(CURDATE(),INTERVAL 14 DAY)))", [user.id]),
    pool.query<RowDataPacket[]>('SELECT status,COUNT(*) AS total FROM job_applications WHERE user_id=? GROUP BY status', [user.id]),
    pool.query<JobApplication[]>('SELECT * FROM job_applications WHERE user_id=? ORDER BY updated_at DESC LIMIT 5', [user.id]),
  ]);
  const activeCount = statusRows.filter((row) => ['applied', 'interview', 'offer'].includes(row.status)).reduce((total, row) => total + Number(row.total), 0);
  const recent = recentApplications.length ? recentApplications.map((application) => `<a href="/applications/${application.id}/edit" class="flex items-center justify-between gap-3 border-b border-slate-100 py-4 last:border-0 dark:border-slate-800"><div class="min-w-0"><p class="truncate font-semibold">${escapeHtml(application.job_title)}</p><p class="truncate text-sm text-slate-500">${escapeHtml(application.company_name)}${application.application_date ? ` · ${escapeHtml(submissionAgeLabel(application.application_date))}` : ''}</p></div><span class="${statusBadgeClass(application.status)}">${statusLabel(application.status)}</span></a>`).join('') : '<div class="py-10 text-center text-sm text-slate-500">No applications yet. Add a role you are interested in.</div>';
  response.send(page('Dashboard', `<section class="overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-600 to-blue-700 p-6 text-white shadow-xl shadow-cyan-900/10 sm:p-10"><div class="max-w-3xl"><p class="text-sm font-semibold uppercase tracking-[.18em] text-cyan-100">Your application workspace</p><h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-5xl">Turn a strong CV into a focused job search.</h1><p class="mt-4 max-w-2xl text-cyan-50/90">Track every opportunity, keep each tailored document together, and know when an application needs attention.</p><div class="mt-7 flex flex-wrap gap-3"><a class="hero-secondary" href="/applications/timeline">View timeline</a><a class="inline-flex min-h-11 items-center rounded-xl border border-white/40 px-4 font-semibold text-white hover:bg-white/10" href="/applications/new">Add application</a></div></div></section><section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-5"><div class="panel p-4 sm:p-5"><p class="text-sm text-slate-500">Applications</p><p class="mt-2 text-3xl font-bold">${Number(applicationCount[0].total)}</p></div><div class="panel p-4 sm:p-5"><p class="text-sm text-slate-500">Active pipeline</p><p class="mt-2 text-3xl font-bold text-cyan-600">${activeCount}</p></div><a class="panel p-4 sm:p-5" href="/applications/timeline?filter=attention"><p class="text-sm text-slate-500">Needs attention</p><p class="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-300">${Number(attentionCount[0].total)}</p></a><div class="panel p-4 sm:p-5"><p class="text-sm text-slate-500">Master CVs</p><p class="mt-2 text-3xl font-bold">${Number(documentCount[0].total)}</p></div><div class="panel p-4 sm:p-5"><p class="text-sm text-slate-500">Tailored packs</p><p class="mt-2 text-3xl font-bold">${Number(tailoredCount[0].total)}</p></div></section><section class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_.8fr]"><div class="panel p-5 sm:p-6"><div class="flex items-center justify-between gap-3"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Pipeline</p><h2 class="mt-1 text-xl font-bold">Recent applications</h2></div><a class="text-sm font-semibold text-cyan-700 dark:text-cyan-300" href="/applications/timeline">Open timeline</a></div><div class="mt-3">${recent}</div></div><div class="panel p-5 sm:p-6"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Follow-up rhythm</p><h2 class="mt-1 text-xl font-bold">Keep the pipeline current</h2><ol class="mt-5 space-y-4 text-sm"><li class="flex gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-cyan-100 font-bold text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200">1</span><span><a class="font-semibold" href="/applications/new">Record the submission date</a><br><span class="text-slate-500">Starts the elapsed-time counter.</span></span></li><li class="flex gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-cyan-100 font-bold text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200">2</span><span><a class="font-semibold" href="/applications">Set a follow-up date</a><br><span class="text-slate-500">Creates a clear next-action reminder.</span></span></li><li class="flex gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-cyan-100 font-bold text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200">3</span><span><a class="font-semibold" href="/applications/timeline?filter=attention">Review quiet applications</a><br><span class="text-slate-500">Decide whether to follow up, close, or deprioritise.</span></span></li></ol></div></section>`, user));
});

/** Serves the pinned browser helper locally instead of loading authentication code from a CDN. */
app.get('/assets/simplewebauthn.js', (_request, response) => response.type('application/javascript').send(fs.readFileSync(path.resolve(__dirname, '..', 'node_modules', '@simplewebauthn', 'browser', 'dist', 'bundle', 'index.umd.min.js'))));

/** Serves the application-owned browser orchestration for registration and sign-in. */
app.get('/assets/passkeys.js', (_request, response) => response.type('application/javascript').send(fs.readFileSync(path.resolve(__dirname, '..', 'public', 'passkeys.js'))));

/** Serves the application-owned responsive visual system. */
app.get('/assets/app.css', (_request, response) => response.type('text/css').send(fs.readFileSync(path.resolve(__dirname, '..', 'public', 'app.css'))));

/** Serves the local light/dark theme controller. */
app.get('/assets/theme.js', (_request, response) => response.type('application/javascript').send(fs.readFileSync(path.resolve(__dirname, '..', 'public', 'theme.js'))));

/** Serves the small client-side helper that adds CSRF tokens to authenticated forms. */
app.get('/assets/security.js', (_request, response) => response.type('application/javascript').send(fs.readFileSync(path.resolve(__dirname, '..', 'public', 'security.js'))));

/** Displays passkey sign-in and secure first-account registration controls. */
app.get('/login', (_request, response) => response.send(page('Sign in', `<div class="mx-auto max-w-4xl"><div class="mb-8 text-center"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Secure, passwordless access</p><h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Welcome to Job Tune</h1><p class="mx-auto mt-3 max-w-xl text-slate-500">Build focused application packs and keep your entire job search organised.</p></div><div class="grid gap-5 md:grid-cols-2"><section class="panel p-6 sm:p-8"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Returning user</p><h2 class="mt-2 text-2xl font-bold">Sign in with a passkey</h2><p class="mt-3 text-sm leading-6 text-slate-500">Use Face ID, Touch ID, Windows Hello, your device PIN, or a security key.</p><button id="passkey-sign-in" class="button-primary mt-6 w-full">Use my passkey</button></section><section class="panel p-6 sm:p-8"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">New user</p><h2 class="mt-2 text-2xl font-bold">Create your first passkey</h2><label class="mt-4 block text-sm font-semibold">Email address<input id="passkey-email" class="field mt-2" type="email" autocomplete="email" required></label><button id="passkey-register" class="button-secondary mt-5 w-full">Create passkey</button></section></div><p id="passkey-status" class="mx-auto mt-4 max-w-3xl text-center text-sm text-slate-500">Passkeys require a supported browser and HTTPS on the public site.</p>${passkeyBrowserScript()}</div>`)));

/** Displays a signed-in page for adding another passkey without risking account takeover. */
app.get('/passkeys', requireUser, (_request, response) => response.send(page('Passkeys', `<div class="panel mx-auto max-w-xl p-6 sm:p-8"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Account security</p><h1 class="mt-2 text-3xl font-bold">Add another passkey</h1><p class="mt-3 text-slate-500">A second device or hardware security key can prevent account loss.</p><button id="passkey-register" class="button-primary mt-6">Add passkey</button><p id="passkey-status" class="mt-4 text-sm text-slate-500"></p></div>${passkeyBrowserScript()}`, response.locals.user)));

/** Generates registration options for a new user or the currently authenticated user. */
app.post('/auth/passkeys/register/options', async (request, response) => {
  if (await passkeyRequestIsRateLimited(request)) { response.status(429).json({ error: 'Too many passkey requests. Wait 15 minutes and try again.' }); return; }
  const signedInUser = await currentUser(request);
  let email = signedInUser?.email || String(request.body.email || '').trim().toLowerCase();
  let userId = signedInUser?.id;
  let userHandle: Buffer;
  let excludeCredentials: Array<{ id: string; transports?: AuthenticatorTransportFuture[] }> = [];
  if (!signedInUser && !/^\S+@\S+\.\S+$/.test(email)) { response.status(400).json({ error: 'Enter a valid email address.' }); return; }
  if (signedInUser) {
    const [users] = await pool.query<RowDataPacket[]>('SELECT webauthn_user_id FROM users WHERE id=?', [userId]);
    userHandle = users[0].webauthn_user_id ? Buffer.from(users[0].webauthn_user_id) : crypto.randomBytes(32);
    if (!users[0].webauthn_user_id) {
      await pool.query('UPDATE users SET webauthn_user_id=? WHERE id=? AND webauthn_user_id IS NULL', [userHandle, userId]);
      const [updatedUsers] = await pool.query<RowDataPacket[]>('SELECT webauthn_user_id FROM users WHERE id=?', [userId]);
      userHandle = Buffer.from(updatedUsers[0].webauthn_user_id);
    }
    const [credentials] = await pool.query<PasskeyCredential[]>('SELECT credential_id,transports FROM webauthn_credentials WHERE user_id=?', [userId]);
    excludeCredentials = credentials.map((credential) => ({ id: credential.credential_id, transports: credentialTransports(credential.transports) }));
  } else {
    const [existing] = await pool.query<RowDataPacket[]>('SELECT id FROM users WHERE email=?', [email]);
    if (existing.length) { response.status(409).json({ error: 'This account already exists. Sign in with its passkey.' }); return; }
    userHandle = crypto.randomBytes(32);
  }
  const config = webAuthnConfig();
  const options = await generateRegistrationOptions({ rpName: config.rpName, rpID: config.rpID, userName: email, userDisplayName: email, userID: new Uint8Array(userHandle), attestationType: 'none', excludeCredentials, authenticatorSelection: { residentKey: 'required', userVerification: 'required' }, supportedAlgorithmIDs: [-7, -257] });
  const token = await storeWebAuthnChallenge('registration', options.challenge, { email, userId, userHandle, requestIpHash: authenticationHash('webauthn-ip', request.ip || 'unknown') });
  response.json({ token, options });
});

/** Verifies a registration ceremony and stores only the public credential material. */
app.post('/auth/passkeys/register/verify', async (request, response) => {
  const token = String(request.body.token || '');
  if (!/^[a-f0-9]{64}$/.test(token) || !request.body.credential) { response.status(400).json({ error: 'Invalid registration response.' }); return; }
  const tokenHash = authenticationHash('webauthn-token', token);
  const [challenges] = await pool.query<WebAuthnChallenge[]>('SELECT * FROM webauthn_challenges WHERE token_hash=? AND ceremony=? AND consumed_at IS NULL AND expires_at>NOW()', [tokenHash, 'registration']);
  const challenge = challenges[0];
  if (!challenge || !challenge.email || !challenge.user_handle) { response.status(401).json({ error: 'Registration expired. Start again.' }); return; }
  const config = webAuthnConfig();
  const verification = await verifyRegistrationResponse({ response: request.body.credential as RegistrationResponseJSON, expectedChallenge: challenge.challenge, expectedOrigin: config.origin, expectedRPID: config.rpID, requireUserVerification: true });
  if (!verification.verified || !verification.registrationInfo) { response.status(401).json({ error: 'Passkey registration could not be verified.' }); return; }
  const signedInUser = await currentUser(request);
  if (challenge.user_id && signedInUser?.id !== challenge.user_id) { response.status(403).json({ error: 'Sign in again before adding a passkey.' }); return; }
  const connection = await pool.getConnection();
  let userId = challenge.user_id;
  try {
    await connection.beginTransaction();
    const [consumed] = await connection.query<ResultSetHeader>('UPDATE webauthn_challenges SET consumed_at=NOW() WHERE id=? AND consumed_at IS NULL', [challenge.id]);
    if (consumed.affectedRows !== 1) throw new Error('Registration challenge was already used.');
    if (!userId) {
      const [created] = await connection.query<ResultSetHeader>('INSERT INTO users (email,password_hash,webauthn_user_id) VALUES (?,NULL,?)', [challenge.email, challenge.user_handle]);
      userId = created.insertId;
    }
    const credential = verification.registrationInfo.credential;
    await connection.query('INSERT INTO webauthn_credentials (credential_id,user_id,credential_public_key,signature_counter,transports,device_type,backed_up) VALUES (?,?,?,?,?,?,?)', [credential.id, userId, Buffer.from(credential.publicKey), credential.counter, credential.transports ? JSON.stringify(credential.transports) : null, verification.registrationInfo.credentialDeviceType, verification.registrationInfo.credentialBackedUp ? 1 : 0]);
    await connection.commit();
  } catch (error) { await connection.rollback(); throw error; } finally { connection.release(); }
  await startSession(response, Number(userId));
  response.json({ verified: true });
});

/** Generates username-free authentication options for discoverable passkeys. */
app.post('/auth/passkeys/authenticate/options', async (request, response) => {
  if (await passkeyRequestIsRateLimited(request)) { response.status(429).json({ error: 'Too many passkey requests. Wait 15 minutes and try again.' }); return; }
  const config = webAuthnConfig();
  const options = await generateAuthenticationOptions({ rpID: config.rpID, userVerification: 'required' });
  const token = await storeWebAuthnChallenge('authentication', options.challenge, { requestIpHash: authenticationHash('webauthn-ip', request.ip || 'unknown') });
  response.json({ token, options });
});

/** Verifies a passkey signature, advances its replay counter, and starts a session. */
app.post('/auth/passkeys/authenticate/verify', async (request, response) => {
  const token = String(request.body.token || '');
  const credentialResponse = request.body.credential as AuthenticationResponseJSON | undefined;
  if (!/^[a-f0-9]{64}$/.test(token) || !credentialResponse?.id) { response.status(400).json({ error: 'Invalid authentication response.' }); return; }
  const tokenHash = authenticationHash('webauthn-token', token);
  const [challenges] = await pool.query<WebAuthnChallenge[]>('SELECT * FROM webauthn_challenges WHERE token_hash=? AND ceremony=? AND consumed_at IS NULL AND expires_at>NOW()', [tokenHash, 'authentication']);
  const [credentials] = await pool.query<PasskeyCredential[]>('SELECT credential_id,user_id,credential_public_key,signature_counter AS counter,transports FROM webauthn_credentials WHERE credential_id=?', [credentialResponse.id]);
  const challenge = challenges[0]; const credential = credentials[0];
  if (!challenge || !credential) { response.status(401).json({ error: 'Passkey authentication failed.' }); return; }
  const config = webAuthnConfig();
  const verification = await verifyAuthenticationResponse({ response: credentialResponse, expectedChallenge: challenge.challenge, expectedOrigin: config.origin, expectedRPID: config.rpID, credential: { id: credential.credential_id, publicKey: new Uint8Array(credential.credential_public_key), counter: Number(credential.counter), transports: credentialTransports(credential.transports) }, requireUserVerification: true });
  if (!verification.verified) { response.status(401).json({ error: 'Passkey authentication failed.' }); return; }
  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();
    const [consumed] = await connection.query<ResultSetHeader>('UPDATE webauthn_challenges SET consumed_at=NOW() WHERE id=? AND consumed_at IS NULL', [challenge.id]);
    if (consumed.affectedRows !== 1) throw new Error('Authentication challenge was already used.');
    const [updated] = await connection.query<ResultSetHeader>('UPDATE webauthn_credentials SET signature_counter=?,last_used_at=NOW() WHERE credential_id=? AND signature_counter=?', [verification.authenticationInfo.newCounter, credential.credential_id, credential.counter]);
    if (updated.affectedRows !== 1) throw new Error('Credential counter changed during authentication.');
    await connection.commit();
  } catch (error) { await connection.rollback(); throw error; } finally { connection.release(); }
  await startSession(response, credential.user_id);
  response.json({ verified: true });
});

/** Redirects obsolete password and passcode forms to the passkey page. */
app.post(['/login', '/register', '/auth/passcode/request', '/auth/passcode/verify'], (_request, response) => response.redirect(303, '/login'));

/** Deletes the active server-side session after a CSRF-protected form submission. */
app.post('/logout', async (request, response) => { const id = cookie(request, 'job_tune_session'); if (id) await pool.query('DELETE FROM sessions WHERE id=?', [id]); response.clearCookie('job_tune_session'); response.clearCookie('job_tune_csrf'); response.redirect('/login'); });

/** Keeps old logout bookmarks harmless by redirecting without changing session state. */
app.get('/logout', (_request, response) => response.redirect('/'));

/** Lists master CVs, tailored CVs, and cover letters as a reusable document library. */
app.get('/documents', requireUser, async (request, response) => {
  const user = response.locals.user as User;
  const query = String(request.query.q || '').trim().slice(0, 100);
  const [allCvs, allOutputs, [trashRows]] = await Promise.all([activeCvsForUser(user.id), activeTailoredForUser(user.id), pool.query<RowDataPacket[]>('SELECT COUNT(*) AS total FROM document_metadata WHERE user_id=? AND archived_at IS NOT NULL', [user.id])]);
  const search = query.toLocaleLowerCase();
  const cvs = search ? allCvs.filter((cv) => [cvName(cv), cv.original_filename].some((value) => value.toLocaleLowerCase().includes(search))) : allCvs;
  const outputs = search ? allOutputs.filter((output) => [tailoredName(output), output.company_name || '', output.original_filename].some((value) => value.toLocaleLowerCase().includes(search))) : allOutputs;
  const cvCards = cvs.length ? cvs.map((cv) => `<article class="panel p-5"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">Master CV · Version ${cv.version_number}</p><h3 class="mt-1 break-words text-lg font-bold">${escapeHtml(cvName(cv))}</h3>${cv.document_name ? `<p class="mt-1 truncate text-xs text-slate-400">Original: ${escapeHtml(cv.original_filename)}</p>` : ''}<p class="mt-1 text-sm text-slate-500">Uploaded ${formatDate(cv.created_at)}</p></div><div class="flex shrink-0 flex-wrap gap-2"><a class="button-secondary" href="/cvs/${cv.id}/download">Download</a><a class="button-primary" href="/tailor?cv=${cv.id}">Tailor</a></div></div><div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between"><details class="group flex-1"><summary class="cursor-pointer text-sm font-semibold text-cyan-700 dark:text-cyan-300">Rename</summary><form method="post" action="/documents/master/${cv.id}/rename" class="mt-3 flex flex-col gap-2 sm:flex-row"><input class="field" name="displayName" maxlength="255" required value="${escapeHtml(cvName(cv))}" aria-label="New document name"><button class="button-secondary whitespace-nowrap">Save name</button></form></details><form method="post" action="/documents/master/${cv.id}/archive"><button class="text-sm font-semibold text-rose-700 dark:text-rose-300">Move to trash</button></form></div></article>`).join('') : `<div class="panel p-8 text-center text-slate-500">${query ? 'No master CVs match this search.' : 'Upload your first Word CV to establish a factual source document.'}</div>`;
  const outputCards = outputs.length ? outputs.map((output) => `<article class="panel p-5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">Application pack · Version ${output.revision_number}</p><h3 class="mt-1 break-words text-lg font-bold">${escapeHtml(tailoredName(output))}</h3><p class="truncate text-sm text-slate-500">${escapeHtml(output.company_name || output.original_filename)} · ${escapeHtml(tailoringSummary(output))}</p></div><span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-xs dark:bg-slate-800">${formatDate(output.created_at)}</span></div><div class="mt-5 grid grid-cols-2 gap-2"><a class="button-secondary" href="/tailored/${output.id}">Review pack</a><a class="button-primary" href="/tailored/${output.id}/retailor">Re-tailor</a><a class="button-secondary" href="/tailored/${output.id}/download.docx">CV Word</a><a class="button-secondary" href="/tailored/${output.id}/download.pdf">CV PDF</a>${output.cover_letter_text ? `<a class="button-secondary" href="/tailored/${output.id}/cover-letter.docx">Letter Word</a>` : ''}</div><div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between"><details class="group flex-1"><summary class="cursor-pointer text-sm font-semibold text-cyan-700 dark:text-cyan-300">Rename</summary><form method="post" action="/documents/tailored/${output.id}/rename" class="mt-3 flex flex-col gap-2 sm:flex-row"><input class="field" name="displayName" maxlength="255" required value="${escapeHtml(tailoredName(output))}" aria-label="New document name"><button class="button-secondary whitespace-nowrap">Save name</button></form></details><form method="post" action="/documents/tailored/${output.id}/archive"><button class="text-sm font-semibold text-rose-700 dark:text-rose-300">Move to trash</button></form></div></article>`).join('') : `<div class="panel p-8 text-center text-slate-500 sm:col-span-2">${query ? 'No application packs match this search.' : 'Tailored CVs and cover letters will appear here for reuse.'}</div>`;
  const controls = `<div class="panel mt-7 p-4"><form method="get" action="/documents" class="flex flex-col gap-3 sm:flex-row"><label class="sr-only" for="document-search">Search documents</label><input id="document-search" class="field" type="search" name="q" value="${escapeHtml(query)}" placeholder="Search names, companies, or original files"><button class="button-secondary sm:w-auto">Search</button>${query ? '<a class="button-secondary" href="/documents">Clear</a>' : ''}<a class="button-secondary sm:ml-auto" href="/documents/trash">Trash · ${Number(trashRows[0].total)}</a></form></div>`;
  response.send(page('Documents', `<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Document library</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Everything you have created</h1><p class="mt-2 text-slate-500">Search, rename, download, reuse, or safely remove your documents.</p></div><a class="button-primary" href="/tailor">Create application pack</a></div>${controls}<section class="mt-8"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><h2 class="text-xl font-bold">Master CVs <span class="text-sm font-normal text-slate-400">${cvs.length}</span></h2><form action="/cvs" method="post" enctype="multipart/form-data" class="flex w-full gap-2 sm:max-w-md"><input required accept=".docx" name="cv" type="file" class="field min-w-0 p-2 text-xs"><button class="button-primary whitespace-nowrap">Upload</button></form></div><div class="mt-4 space-y-3">${cvCards}</div></section><section class="mt-10"><h2 class="text-xl font-bold">Tailored application packs <span class="text-sm font-normal text-slate-400">${outputs.length}</span></h2><div class="mt-4 grid gap-4 lg:grid-cols-2">${outputCards}</div></section>`, user));
});

/** Displays recoverable documents separately from the active library. */
app.get('/documents/trash', requireUser, async (_request, response) => { const user = response.locals.user as User; const [[cvs], [outputs]] = await Promise.all([pool.query<Cv[]>("SELECT c.id,c.original_filename,c.version_number,c.created_at,dm.display_name AS document_name,dm.archived_at FROM document_metadata dm JOIN cv_documents c ON c.id=dm.document_id AND c.user_id=dm.user_id WHERE dm.user_id=? AND dm.document_type='master_cv' AND dm.archived_at IS NOT NULL ORDER BY dm.archived_at DESC", [user.id]), pool.query<Tailored[]>("SELECT t.id,t.source_cv_id,t.company_name,t.job_title,t.model_name,t.generation_mode,t.created_at,c.original_filename,dm.display_name AS document_name,dm.archived_at FROM document_metadata dm JOIN tailored_cvs t ON t.id=dm.document_id AND t.user_id=dm.user_id JOIN cv_documents c ON c.id=t.source_cv_id WHERE dm.user_id=? AND dm.document_type='tailored_cv' AND dm.archived_at IS NOT NULL ORDER BY dm.archived_at DESC", [user.id])]); const cards = [...cvs.map((cv) => ({ type: 'master', id: cv.id, name: cvName(cv), description: `Master CV · Version ${cv.version_number}`, archivedAt: cv.archived_at })), ...outputs.map((output) => ({ type: 'tailored', id: output.id, name: tailoredName(output), description: `Application pack${output.company_name ? ` · ${output.company_name}` : ''}`, archivedAt: output.archived_at }))].sort((left, right) => new Date(right.archivedAt || 0).getTime() - new Date(left.archivedAt || 0).getTime()).map((document) => `<article class="panel p-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">${escapeHtml(document.description)}</p><h2 class="mt-1 break-words text-lg font-bold">${escapeHtml(document.name)}</h2><p class="mt-1 text-sm text-slate-500">Moved to trash ${formatDate(document.archivedAt)}</p></div><div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between"><form method="post" action="/documents/${document.type}/${document.id}/restore"><button class="button-secondary w-full">Restore</button></form><form method="post" action="/documents/${document.type}/${document.id}/delete" class="flex flex-1 flex-col gap-2 sm:flex-row sm:justify-end"><input class="field sm:max-w-36" name="confirmation" required pattern="DELETE" placeholder="Type DELETE" aria-label="Type DELETE to permanently delete"><button class="button-danger whitespace-nowrap">Delete forever</button></form></div></article>`).join(''); response.send(page('Document trash', `<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Document management</p><h1 class="mt-1 text-3xl font-bold">Trash</h1><p class="mt-2 max-w-2xl text-slate-500">Restore documents at any time. Permanent deletion removes stored files and cannot be undone; linked application records retain their other details.</p></div><a class="button-secondary" href="/documents">Back to documents</a></div><div class="mt-7 grid gap-4">${cards || '<div class="panel p-10 text-center text-slate-500">Trash is empty.</div>'}</div>`, user)); });

/** Updates a user-defined name for either kind of owned document. */
app.post('/documents/:type/:id/rename', requireUser, async (request, response) => { const user = response.locals.user as User; const documentType = routeDocumentType(String(request.params.type)); const displayName = String(request.body.displayName || '').trim().slice(0, 255); if (!documentType || !displayName) return response.status(400).send('Enter a document name.'); if (!await renameDocument(documentType, Number(request.params.id), user.id, displayName)) return response.sendStatus(404); response.redirect('/documents'); });

/** Moves an owned document into recoverable trash without breaking application links. */
app.post('/documents/:type/:id/archive', requireUser, async (request, response) => { const user = response.locals.user as User; const documentType = routeDocumentType(String(request.params.type)); if (!documentType || !await setDocumentArchived(documentType, Number(request.params.id), user.id, true)) return response.sendStatus(404); response.redirect('/documents'); });

/** Restores an owned document from trash to the active library. */
app.post('/documents/:type/:id/restore', requireUser, async (request, response) => { const user = response.locals.user as User; const documentType = routeDocumentType(String(request.params.type)); const documentId = Number(request.params.id); if (!documentType || !await documentIsArchived(documentType, documentId, user.id) || !await setDocumentArchived(documentType, documentId, user.id, false)) return response.sendStatus(404); response.redirect('/documents/trash'); });

/** Permanently removes an explicitly confirmed trashed document with dependency safeguards. */
app.post('/documents/:type/:id/delete', requireUser, async (request, response) => { const user = response.locals.user as User; const documentType = routeDocumentType(String(request.params.type)); const documentId = Number(request.params.id); if (!documentType || String(request.body.confirmation || '') !== 'DELETE') return response.status(400).send('Type DELETE to confirm permanent deletion.'); if (!await documentIsArchived(documentType, documentId, user.id) || !await documentBelongsToUser(documentType, documentId, user.id)) return response.sendStatus(404); if (documentType === 'master_cv') { const [dependencies] = await pool.query<RowDataPacket[]>('SELECT COUNT(*) AS total FROM tailored_cvs WHERE source_cv_id=? AND user_id=?', [documentId, user.id]); if (Number(dependencies[0].total) > 0) { response.status(409).send(page('Document still in use', `<div class="panel mx-auto max-w-xl p-6 sm:p-8"><p class="text-sm font-semibold text-rose-700 dark:text-rose-300">Deletion blocked</p><h1 class="mt-2 text-2xl font-bold">This master CV still has tailored packs</h1><p class="mt-3 text-slate-500">Permanently delete its dependent tailored application packs first. The master CV remains safely in Trash.</p><a class="button-secondary mt-6" href="/documents/trash">Return to Trash</a></div>`, user)); return; } } const connection = await pool.getConnection(); try { await connection.beginTransaction(); const [deleted] = await connection.query<ResultSetHeader>(`DELETE FROM ${documentTable(documentType)} WHERE id=? AND user_id=? AND EXISTS (SELECT document_id FROM document_metadata dm WHERE dm.user_id=? AND dm.document_type=? AND dm.document_id=? AND dm.archived_at IS NOT NULL)`, [documentId, user.id, user.id, documentType, documentId]); if (deleted.affectedRows !== 1) throw new Error('Document archive state changed before deletion.'); await connection.query('DELETE FROM document_metadata WHERE user_id=? AND document_type=? AND document_id=?', [user.id, documentType, documentId]); await connection.commit(); } catch (error) { await connection.rollback(); throw error; } finally { connection.release(); } response.redirect('/documents/trash'); });

/** Extracts and stores a DOCX CV as a new user-scoped version. */
app.post('/cvs', requireUser, upload.single('cv'), requireCsrf, async (request, response) => { if (!request.file) return response.status(400).send('Please upload a .docx CV.'); const text = (await mammoth.extractRawText({ buffer: request.file.buffer })).value.trim(); if (!text) return response.status(400).send('This Word file did not contain readable text.'); const user = response.locals.user as User; const [versions] = await pool.query<RowDataPacket[]>('SELECT COALESCE(MAX(version_number),0)+1 AS nextVersion FROM cv_documents WHERE user_id=?', [user.id]); await pool.query('INSERT INTO cv_documents (user_id,original_filename,mime_type,original_docx,extracted_text,version_number) VALUES (?,?,?,?,?,?)', [user.id, request.file.originalname, request.file.mimetype || 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', request.file.buffer, text, versions[0].nextVersion]); response.redirect('/documents'); });

/** Downloads the exact original DOCX only for its owning user. */
app.get('/cvs/:id/download', requireUser, async (request, response) => { const user = response.locals.user as User; const [rows] = await pool.query<(RowDataPacket & { original_filename: string; mime_type: string; original_docx: Buffer; document_name: string | null })[]>("SELECT c.original_filename,c.mime_type,c.original_docx,dm.display_name AS document_name FROM cv_documents c LEFT JOIN document_metadata dm ON dm.user_id=c.user_id AND dm.document_type='master_cv' AND dm.document_id=c.id WHERE c.id=? AND c.user_id=?", [Number(request.params.id), user.id]); if (!rows.length) return response.sendStatus(404); const filename = rows[0].document_name ? `${downloadBaseName(rows[0].document_name, 'master-cv')}.docx` : rows[0].original_filename; response.type(rows[0].mime_type).attachment(filename).send(rows[0].original_docx); });

/** Displays the mobile-friendly tailoring form with role, company, CV, and model controls. */
app.get('/tailor', requireUser, async (request, response) => { const user = response.locals.user as User; const [cvs, catalogue] = await Promise.all([activeCvsForUser(user.id), cachedModelCatalogue()]); const models = await availableModels(catalogue); const model = await preferredModel(user.id, models); const selectedCv = Number(request.query.cv || 0); const controls = tailoringControlsFromInput('balanced', 'professional', ''); const cvOptions = cvs.map((cv) => `<option value="${cv.id}"${selectedCv === cv.id ? ' selected' : ''}>Version ${cv.version_number} · ${escapeHtml(cvName(cv))}</option>`).join(''); response.send(page('Tailor', `<div class="mx-auto max-w-4xl"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Application pack builder</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Tailor your evidence to the role</h1><p class="mt-2 text-slate-500">Job Tune creates a CV, change summary, and cover letter while treating your source CV as the factual authority.</p>${cvs.length ? `<form class="panel mt-7 p-5 sm:p-8" action="/tailor" method="post"><div class="grid gap-5 sm:grid-cols-2"><label class="text-sm font-semibold">Company<input class="field mt-2" name="companyName" maxlength="255" required></label><label class="text-sm font-semibold">Role title<input class="field mt-2" name="jobTitle" maxlength="255" required></label><label class="text-sm font-semibold">Source CV<select class="field mt-2" name="cvId" required><option value="">Choose CV</option>${cvOptions}</select></label><label class="text-sm font-semibold">AI model<select class="field mt-2" name="model">${modelOptions(models, model, catalogue?.newModelIds)}</select><span class="mt-1 block font-normal text-slate-500">Smaller models generally reduce cost. Refresh the catalogue in Settings.</span></label>${tailoringControlFields(controls)}</div><label class="mt-5 block text-sm font-semibold">Job description<textarea required name="jobDescription" class="field mt-2 min-h-64" minlength="30" placeholder="Paste the complete description from the employer's website"></textarea></label><div class="mt-6 flex justify-end"><button class="button-primary w-full sm:w-auto">Create CV and cover letter</button></div></form>` : '<div class="panel mt-7 p-8 text-center"><p class="text-slate-500">Upload or restore a Word CV before creating an application pack.</p><a class="button-primary mt-4" href="/documents">Open documents</a></div>'}</div>`, user)); });

/** Creates and stores a factual tailored CV, change summary, and cover letter. */
app.post('/tailor', requireUser, async (request, response) => { const user = response.locals.user as User; const [cvs] = await pool.query<Cv[]>("SELECT c.* FROM cv_documents c LEFT JOIN document_metadata dm ON dm.user_id=c.user_id AND dm.document_type='master_cv' AND dm.document_id=c.id WHERE c.id=? AND c.user_id=? AND dm.archived_at IS NULL", [Number(request.body.cvId), user.id]); const jobDescription = String(request.body.jobDescription || '').trim(); const companyName = String(request.body.companyName || '').trim().slice(0, 255); const jobTitle = String(request.body.jobTitle || '').trim().slice(0, 255); const controls = tailoringControlsFromInput(request.body.focus, request.body.tone, request.body.tailoringNotes); const requestedModel = String(request.body.model || ''); const models = await availableModels(); const model = models.includes(requestedModel) ? requestedModel : await preferredModel(user.id, models); if (!cvs.length || jobDescription.length < 30 || !companyName || !jobTitle) return response.status(400).send('Choose an active CV and provide the company, role, and full job description.'); try { const profile = await profileForUser(user.id); const draft = await tailorWithAi(cvs[0].extracted_text, jobDescription, profile, companyName, jobTitle, model, controls); const [result] = await pool.query<ResultSetHeader>('INSERT INTO tailored_cvs (user_id,source_cv_id,job_description,tailored_text,change_summary,generation_mode,company_name,job_title,model_name,cover_letter_text,revision_group_key,revision_number,tailoring_focus,tailoring_tone,tailoring_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [user.id, cvs[0].id, jobDescription, draft.tailoredText, draft.summary, draft.mode, companyName, jobTitle, draft.model, draft.coverLetter, crypto.randomUUID(), 1, controls.focus, controls.tone, controls.notes || null]); response.redirect(`/tailored/${result.insertId}`); } catch (error) { response.status(502).send(`Tailoring failed: ${escapeHtml((error as Error).message)}`); } });

/** Lists the user's tracked opportunities and their current workflow state. */
app.get('/applications', requireUser, async (_request, response) => {
  const user = response.locals.user as User;
  const [applications] = await pool.query<JobApplication[]>('SELECT a.*,c.original_filename,t.job_title AS tailored_job_title FROM job_applications a LEFT JOIN cv_documents c ON c.id=a.cv_document_id LEFT JOIN tailored_cvs t ON t.id=a.tailored_cv_id WHERE a.user_id=? ORDER BY COALESCE(a.application_date,a.created_at) DESC,a.updated_at DESC', [user.id]);
  const cards = applications.length ? applications.map((application) => {
    const attention = applicationNeedsAttention(application);
    const followUp = followUpLabel(application.follow_up_date);
    return `<article class="panel p-5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-lg font-bold">${escapeHtml(application.job_title)}</p><p class="truncate text-sm text-slate-500">${escapeHtml(application.company_name)}${application.location ? ` · ${escapeHtml(application.location)}` : ''}</p></div><span class="${statusBadgeClass(application.status)}">${statusLabel(application.status)}</span></div><dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">Submitted</dt><dd class="font-medium">${application.application_date ? formatDate(application.application_date) : 'Not recorded'}</dd></div><div><dt class="text-slate-500">Elapsed</dt><dd class="font-medium">${escapeHtml(submissionAgeLabel(application.application_date))}</dd></div><div><dt class="text-slate-500">Next action</dt><dd class="font-medium">${escapeHtml(followUp || responseGuidance(application))}</dd></div><div><dt class="text-slate-500">Documents</dt><dd class="truncate font-medium">${application.tailored_cv_id ? 'Tailored pack linked' : application.cv_document_id ? 'Master CV linked' : 'Not linked'}</dd></div></dl>${attention ? `<div class="attention-note mt-4 px-3 py-2 text-sm font-semibold">Needs attention · ${escapeHtml(responseGuidance(application))}</div>` : ''}<div class="mt-5 flex flex-wrap gap-2"><a class="button-secondary flex-1" href="/applications/${application.id}/edit">View / edit</a>${application.tailored_cv_id ? `<a class="button-primary" href="/tailored/${application.tailored_cv_id}/retailor?application=${application.id}">Re-tailor</a>` : ''}${application.source_url ? `<a class="button-secondary" href="${escapeHtml(application.source_url)}" target="_blank" rel="noopener">Advert ↗</a>` : ''}</div></article>`;
  }).join('') : '<div class="panel p-10 text-center text-slate-500 sm:col-span-2 lg:col-span-3">No applications tracked yet.</div>';
  response.send(page('Applications', `<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Application tracker</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Your job search pipeline</h1><p class="mt-2 text-slate-500">See elapsed time, next actions, documents, and progress in one place.</p></div><div class="flex flex-wrap gap-2"><a class="button-secondary" href="/applications/timeline">View timeline</a><a class="button-primary" href="/applications/new">Add role</a></div></div><div class="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">${cards}</div>`, user));
});

/** Displays applications chronologically with elapsed-time and follow-up guidance. */
app.get('/applications/timeline', requireUser, async (request, response) => {
  const user = response.locals.user as User;
  const filter = validTimelineFilter(request.query.filter);
  const [applications] = await pool.query<JobApplication[]>('SELECT * FROM job_applications WHERE user_id=? ORDER BY application_date DESC,created_at DESC', [user.id]);
  const visible = filter === 'all' ? applications : filter === 'attention' ? applications.filter((application) => applicationNeedsAttention(application)) : applications.filter((application) => application.status === filter);
  const submitted = visible.filter((application) => application.application_date);
  const notSubmitted = visible.filter((application) => !application.application_date);
  const submittedTotal = applications.filter((application) => application.application_date).length;
  const attentionTotal = applications.filter((application) => applicationNeedsAttention(application)).length;
  const quietTotal = applications.filter((application) => application.status === 'applied' && (daysSinceSubmitted(application.application_date) || 0) >= 30).length;
  const timelineItems = submitted.map((application) => {
    const attention = applicationNeedsAttention(application);
    const followUp = followUpLabel(application.follow_up_date);
    return `<li class="timeline-item${attention ? ' needs-attention' : ''}"><span class="timeline-marker" aria-hidden="true"></span><time class="mb-2 block text-sm font-semibold text-slate-500 md:absolute md:left-0 md:top-5 md:w-28 md:text-right" datetime="${formatDate(application.application_date, true)}">${formatDate(application.application_date)}</time><article class="panel p-5 sm:p-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><h2 class="text-lg font-bold">${escapeHtml(application.job_title)}</h2><p class="mt-1 text-sm text-slate-500">${escapeHtml(application.company_name)}${application.location ? ` · ${escapeHtml(application.location)}` : ''}</p></div><div class="flex flex-wrap items-center gap-2"><span class="age-badge">${escapeHtml(submissionAgeLabel(application.application_date))}</span><span class="${statusBadgeClass(application.status)}">${statusLabel(application.status)}</span></div></div><div class="mt-4 ${attention ? 'attention-note' : 'rounded-xl bg-slate-50 text-slate-700 dark:bg-slate-900 dark:text-slate-300'} px-3 py-2 text-sm"><strong>${attention ? 'Needs attention' : 'Current signal'}:</strong> ${escapeHtml(responseGuidance(application))}</div><dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">Next follow-up</dt><dd class="mt-1 font-medium">${application.follow_up_date ? `${formatDate(application.follow_up_date)} · ${escapeHtml(followUp || '')}` : 'No date set'}</dd></div><div><dt class="text-slate-500">Document</dt><dd class="mt-1 font-medium">${application.tailored_cv_id ? 'Tailored application pack linked' : application.cv_document_id ? 'Master CV linked' : 'No document linked'}</dd></div></dl><div class="mt-5 flex flex-wrap gap-2"><a class="button-secondary" href="/applications/${application.id}/edit">View / edit</a>${application.tailored_cv_id ? `<a class="button-secondary" href="/tailored/${application.tailored_cv_id}">Review pack</a>` : ''}${application.source_url ? `<a class="button-secondary" href="${escapeHtml(application.source_url)}" target="_blank" rel="noopener">Advert ↗</a>` : ''}</div></article></li>`;
  }).join('');
  const pendingItems = notSubmitted.map((application) => `<article class="panel p-4"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-bold">${escapeHtml(application.job_title)}</p><p class="text-sm text-slate-500">${escapeHtml(application.company_name)} · ${escapeHtml(responseGuidance(application))}</p></div><div class="flex items-center gap-2"><span class="${statusBadgeClass(application.status)}">${statusLabel(application.status)}</span><a class="button-secondary" href="/applications/${application.id}/edit">Edit</a></div></div></article>`).join('');
  const emptyTimeline = `<li class="panel list-none p-8 text-center text-slate-500">${filter === 'all' ? 'Add a submission date to place an application on the timeline.' : 'No applications match this filter.'}</li>`;
  response.send(page('Application timeline', `<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Chronological view</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Application timeline</h1><p class="mt-2 max-w-2xl text-slate-500">See how long each employer has had your application and use the guidance as a prompt—not a prediction—to decide the next action.</p></div><div class="flex flex-wrap gap-2"><a class="button-secondary" href="/applications">Card view</a><a class="button-primary" href="/applications/new">Add role</a></div></div><section class="mt-6 grid grid-cols-3 gap-3"><div class="panel p-4"><p class="text-xs text-slate-500 sm:text-sm">Submitted</p><p class="mt-2 text-2xl font-bold sm:text-3xl">${submittedTotal}</p></div><div class="panel p-4"><p class="text-xs text-slate-500 sm:text-sm">Needs attention</p><p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-300 sm:text-3xl">${attentionTotal}</p></div><div class="panel p-4"><p class="text-xs text-slate-500 sm:text-sm">30+ days quiet</p><p class="mt-2 text-2xl font-bold sm:text-3xl">${quietTotal}</p></div></section><form class="panel mt-5 flex flex-col gap-3 p-4 sm:flex-row sm:items-end" method="get" action="/applications/timeline"><label class="flex-1 text-sm font-semibold">Show<select class="field mt-2" name="filter">${timelineFilterOptions(filter)}</select></label><button class="button-secondary sm:w-auto">Apply filter</button>${filter !== 'all' ? '<a class="button-secondary" href="/applications/timeline">Clear</a>' : ''}</form><ol class="application-timeline mt-7 space-y-5">${timelineItems || emptyTimeline}</ol>${notSubmitted.length ? `<section class="mt-10"><h2 class="text-xl font-bold">Not yet submitted</h2><p class="mt-1 text-sm text-slate-500">These roles stay outside the dated timeline until a submission date is recorded.</p><div class="mt-4 grid gap-3">${pendingItems}</div></section>` : ''}`, user));
});

/** Displays the new-application form with user-owned document choices. */
app.get('/applications/new', requireUser, async (_request, response) => { const user = response.locals.user as User; const [cvs, outputs] = await Promise.all([activeCvsForUser(user.id), activeTailoredForUser(user.id)]); response.send(page('Add application', `<div class="mb-7"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">New opportunity</p><h1 class="mt-1 text-3xl font-bold">Track a job application</h1></div>${applicationForm({}, cvs, outputs)}`, user)); });

/** Prefills a tracked application from a completed tailored application pack. */
app.get('/applications/from/:tailoredId', requireUser, async (request, response) => { const user = response.locals.user as User; const [cvs, allOutputs] = await Promise.all([activeCvsForUser(user.id), activeTailoredForUser(user.id)]); const output = allOutputs.find((item) => item.id === Number(request.params.tailoredId)); if (!output) return response.sendStatus(404); const initial: ApplicationFormData = { company_name: output.company_name || '', job_title: output.job_title || '', status: 'preparing', cv_document_id: output.source_cv_id, tailored_cv_id: output.id }; response.send(page('Track application', `<div class="mb-7"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">From application pack</p><h1 class="mt-1 text-3xl font-bold">Track ${escapeHtml(output.job_title || 'this role')}</h1></div>${applicationForm(initial, cvs, allOutputs)}`, user)); });

/** Creates a user-scoped job application with validated document relationships. */
app.post('/applications', requireUser, async (request, response) => { const user = response.locals.user as User; const companyName = String(request.body.companyName || '').trim().slice(0, 255); const jobTitle = String(request.body.jobTitle || '').trim().slice(0, 255); if (!companyName || !jobTitle) return response.status(400).send('Company and role title are required.'); const cvId = await ownedCvId(Number(request.body.cvDocumentId), user.id); const tailoredId = await ownedTailoredId(Number(request.body.tailoredCvId), user.id); await pool.query('INSERT INTO job_applications (user_id,company_name,job_title,location,source_url,status,application_date,follow_up_date,cv_document_id,tailored_cv_id,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [user.id, companyName, jobTitle, String(request.body.location || '').trim().slice(0, 255) || null, safeUrl(String(request.body.sourceUrl || '')), validStatus(String(request.body.status || '')), safeDateInput(request.body.applicationDate), safeDateInput(request.body.followUpDate), cvId, tailoredId, String(request.body.notes || '').trim().slice(0, 10000) || null]); response.redirect('/applications'); });

/** Displays one application for safe editing by its owner. */
app.get('/applications/:id/edit', requireUser, async (request, response) => { const user = response.locals.user as User; const [[applications], cvs, outputs] = await Promise.all([pool.query<JobApplication[]>('SELECT * FROM job_applications WHERE id=? AND user_id=?', [Number(request.params.id), user.id]), activeCvsForUser(user.id), activeTailoredForUser(user.id)]); if (!applications.length) return response.sendStatus(404); response.send(page('Edit application', `<div class="mb-7"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Application record</p><h1 class="mt-1 text-3xl font-bold">${escapeHtml(applications[0].job_title)}</h1></div>${applicationForm(applications[0], cvs, outputs)}`, user)); });

/** Updates one application while maintaining strict user and document ownership. */
app.post('/applications/:id', requireUser, async (request, response) => { const user = response.locals.user as User; const applicationId = Number(request.params.id); const companyName = String(request.body.companyName || '').trim().slice(0, 255); const jobTitle = String(request.body.jobTitle || '').trim().slice(0, 255); if (!companyName || !jobTitle) return response.status(400).send('Company and role title are required.'); const [applications] = await pool.query<JobApplication[]>('SELECT cv_document_id,tailored_cv_id FROM job_applications WHERE id=? AND user_id=?', [applicationId, user.id]); if (!applications.length) return response.sendStatus(404); const requestedCvId = Number(request.body.cvDocumentId); const requestedTailoredId = Number(request.body.tailoredCvId); const preserveArchivedCv = requestedCvId === Number(applications[0].cv_document_id) && await documentBelongsToUser('master_cv', requestedCvId, user.id); const preserveArchivedTailored = requestedTailoredId === Number(applications[0].tailored_cv_id) && await documentBelongsToUser('tailored_cv', requestedTailoredId, user.id); const cvId = preserveArchivedCv ? requestedCvId : await ownedCvId(requestedCvId, user.id); const tailoredId = preserveArchivedTailored ? requestedTailoredId : await ownedTailoredId(requestedTailoredId, user.id); await pool.query('UPDATE job_applications SET company_name=?,job_title=?,location=?,source_url=?,status=?,application_date=?,follow_up_date=?,cv_document_id=?,tailored_cv_id=?,notes=? WHERE id=? AND user_id=?', [companyName, jobTitle, String(request.body.location || '').trim().slice(0, 255) || null, safeUrl(String(request.body.sourceUrl || '')), validStatus(String(request.body.status || '')), safeDateInput(request.body.applicationDate), safeDateInput(request.body.followUpDate), cvId, tailoredId, String(request.body.notes || '').trim().slice(0, 10000) || null, applicationId, user.id]); response.redirect('/applications'); });

/** Displays and edits the contact information used in generated cover letters. */
app.get('/profile', requireUser, async (_request, response) => { const user = response.locals.user as User; const profile = await profileForUser(user.id); const value = (field: keyof UserProfile): string => escapeHtml(String(profile?.[field] || '')); response.send(page('Profile', `<div class="mx-auto max-w-3xl"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Cover-letter identity</p><h1 class="mt-1 text-3xl font-bold">Contact details</h1><p class="mt-2 text-slate-500">These details are available to the cover-letter generator and remain private to your account.</p><form method="post" action="/profile" class="panel mt-7 p-5 sm:p-8"><div class="grid gap-5 sm:grid-cols-2"><label class="text-sm font-semibold sm:col-span-2">Full name<input class="field mt-2" name="fullName" value="${value('full_name')}"></label><label class="text-sm font-semibold">Phone<input class="field mt-2" name="phone" value="${value('phone')}"></label><label class="text-sm font-semibold">Address line 1<input class="field mt-2" name="addressLine1" value="${value('address_line_1')}"></label><label class="text-sm font-semibold">Address line 2<input class="field mt-2" name="addressLine2" value="${value('address_line_2')}"></label><label class="text-sm font-semibold">City<input class="field mt-2" name="city" value="${value('city')}"></label><label class="text-sm font-semibold">County / region<input class="field mt-2" name="region" value="${value('region')}"></label><label class="text-sm font-semibold">Postcode<input class="field mt-2" name="postalCode" value="${value('postal_code')}"></label><label class="text-sm font-semibold">Country<input class="field mt-2" name="country" value="${value('country')}"></label><label class="text-sm font-semibold">LinkedIn URL<input class="field mt-2" type="url" name="linkedinUrl" value="${value('linkedin_url')}"></label><label class="text-sm font-semibold">Portfolio URL<input class="field mt-2" type="url" name="portfolioUrl" value="${value('portfolio_url')}"></label></div><div class="mt-6 flex justify-end"><button class="button-primary w-full sm:w-auto">Save contact details</button></div></form></div>`, user)); });

/** Saves user-scoped contact data without exposing it to other accounts. */
app.post('/profile', requireUser, async (request, response) => { const user = response.locals.user as User; const values = [String(request.body.fullName || '').trim().slice(0, 255) || null, String(request.body.phone || '').trim().slice(0, 80) || null, String(request.body.addressLine1 || '').trim().slice(0, 255) || null, String(request.body.addressLine2 || '').trim().slice(0, 255) || null, String(request.body.city || '').trim().slice(0, 120) || null, String(request.body.region || '').trim().slice(0, 120) || null, String(request.body.postalCode || '').trim().slice(0, 40) || null, String(request.body.country || '').trim().slice(0, 120) || null, safeUrl(String(request.body.linkedinUrl || '')), safeUrl(String(request.body.portfolioUrl || ''))]; await pool.query('INSERT INTO user_profiles (user_id,full_name,phone,address_line_1,address_line_2,city,region,postal_code,country,linkedin_url,portfolio_url) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),phone=VALUES(phone),address_line_1=VALUES(address_line_1),address_line_2=VALUES(address_line_2),city=VALUES(city),region=VALUES(region),postal_code=VALUES(postal_code),country=VALUES(country),linkedin_url=VALUES(linkedin_url),portfolio_url=VALUES(portfolio_url)', [user.id, ...values]); response.redirect('/profile'); });

/** Renders a fixed, safe status message for a completed catalogue refresh attempt. */
function modelRefreshMessage(request: Request): string {
  const status = String(request.query.modelCheck || '');
  const added = Math.min(1000, Math.max(0, Number.parseInt(String(request.query.added || '0'), 10) || 0));
  if (status === 'updated') return `<div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">Model catalogue updated.${added ? ` ${added} newly available compatible ${added === 1 ? 'model was' : 'models were'} added.` : ' No newly available compatible models were found.'}</div>`;
  if (status === 'recent') return '<div class="mt-6 rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-800 dark:border-cyan-900 dark:bg-cyan-950 dark:text-cyan-200">The shared catalogue was checked recently, so the cached result is still in use.</div>';
  if (status === 'unavailable') return '<div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">Model discovery is unavailable until the server has an OpenAI API key and discovery is enabled.</div>';
  if (status === 'error') return '<div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">OpenAI could not refresh the catalogue. The last known safe list remains available.</div>';
  return '';
}

/** Displays cost-control model preferences, catalogue discovery, email readiness, and passkey access. */
app.get('/settings', requireUser, async (request, response) => { const user = response.locals.user as User; const catalogue = await cachedModelCatalogue(); const models = await availableModels(catalogue); const model = await preferredModel(user.id, models); const checked = catalogue ? formatDateTime(catalogue.checkedAt) : 'Not checked yet'; const discoveryControl = modelDiscoveryEnabled() ? `<form method="post" action="/settings/models/refresh" class="mt-5"><button class="button-secondary w-full sm:w-auto">Check for newer models</button><p class="mt-2 text-xs text-slate-500">Checks your OpenAI project's available models. Refreshes are limited to once every ${modelRefreshMinutes()} minutes across the site.</p></form>` : '<p class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">Add OPENAI_API_KEY and leave OPENAI_MODEL_DISCOVERY enabled to check the provider catalogue.</p>'; response.send(page('Settings', `<div class="mx-auto max-w-3xl"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Preferences</p><h1 class="mt-1 text-3xl font-bold">Settings</h1>${modelRefreshMessage(request)}<div class="mt-7 grid gap-5"><section class="panel p-5 sm:p-7"><form method="post" action="/settings/model"><h2 class="text-xl font-bold">AI cost and quality</h2><p class="mt-2 text-sm text-slate-500">Choose the default model used for new application packs. Configured fallbacks and compatible models discovered for this API project are available.</p><label class="mt-5 block text-sm font-semibold">Default model<select class="field mt-2" name="model">${modelOptions(models, model, catalogue?.newModelIds)}</select></label><button class="button-primary mt-5">Save model</button></form><div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-bold">OpenAI model catalogue</h3><span class="text-xs text-slate-500">Last checked: ${escapeHtml(checked)}</span></div><p class="mt-2 text-sm text-slate-500">${catalogue ? `${catalogue.models.length} compatible account ${catalogue.models.length === 1 ? 'model' : 'models'} cached. ` : ''}Job Tune excludes audio, image, embedding, realtime, dated snapshot, and other incompatible models.</p>${discoveryControl}</div></section><div class="panel p-5 sm:p-7"><h2 class="text-xl font-bold">Document email</h2><p class="mt-2 text-sm text-slate-500">${emailDeliveryConfigured() ? 'SMTP is configured. You can email Word application packs from each tailored document.' : 'SMTP is not configured. Downloads remain available; add SMTP settings to enable direct email.'}</p></div><div class="panel p-5 sm:p-7"><h2 class="text-xl font-bold">Account security</h2><p class="mt-2 text-sm text-slate-500">Add a backup passkey so losing one device does not lock you out.</p><a class="button-secondary mt-5" href="/passkeys">Manage passkeys</a></div></div></div>`, user)); });

/** Saves a model preference only when it belongs to the current safe catalogue. */
app.post('/settings/model', requireUser, async (request, response) => { const user = response.locals.user as User; const model = String(request.body.model || ''); const models = await availableModels(); if (!models.includes(model)) return response.status(400).send('Choose an available model.'); await pool.query('INSERT INTO user_preferences (user_id,ai_model) VALUES (?,?) ON DUPLICATE KEY UPDATE ai_model=VALUES(ai_model)', [user.id, model]); response.redirect('/settings'); });

/** Refreshes the shared model catalogue while retaining the previous list on provider failure. */
app.post('/settings/models/refresh', requireUser, async (_request, response) => {
  if (!modelDiscoveryEnabled()) { response.redirect('/settings?modelCheck=unavailable'); return; }
  const previous = await cachedModelCatalogue();
  if (modelCatalogueIsRecent(previous)) { response.redirect('/settings?modelCheck=recent'); return; }
  try {
    const models = await discoverOpenAiModels(process.env.OPENAI_API_KEY || '');
    if (!models.length) throw new Error('OpenAI returned no compatible text models.');
    const catalogue = await saveModelCatalogue(models, previous);
    response.redirect(`/settings?modelCheck=updated&added=${catalogue.newModelIds.length}`);
  } catch (error) {
    console.error(`OpenAI model catalogue refresh failed: ${(error as Error).message}`);
    response.redirect('/settings?modelCheck=error');
  }
});

/** Reads a tailored CV only when its owner is the signed-in user. */
async function outputForUser(id: number, userId: number): Promise<Tailored | null> { const [rows] = await pool.query<Tailored[]>("SELECT t.*,c.original_filename,dm.display_name AS document_name,dm.archived_at FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id LEFT JOIN document_metadata dm ON dm.user_id=t.user_id AND dm.document_type='tailored_cv' AND dm.document_id=t.id WHERE t.id=? AND t.user_id=?", [id, userId]); return rows[0] || null; }

/** Returns every retained revision in the same user-owned comparison group. */
async function revisionHistoryForOutput(output: Tailored, userId: number): Promise<Tailored[]> {
  const groupKey = revisionGroupKey(output.id, output.revision_group_key);
  const legacyRootId = legacyRevisionRootId(groupKey);
  const [rows] = await pool.query<Tailored[]>("SELECT t.*,c.original_filename,dm.display_name AS document_name,dm.archived_at FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id LEFT JOIN document_metadata dm ON dm.user_id=t.user_id AND dm.document_type='tailored_cv' AND dm.document_id=t.id WHERE t.user_id=? AND (t.revision_group_key=? OR t.id=?) ORDER BY t.revision_number DESC,t.created_at DESC", [userId, groupKey, legacyRootId]);
  return rows;
}

/** Recovers an older unversioned pack's numeric root identifier from its internal group key. */
function legacyRevisionRootId(groupKey: string): number { const id = groupKey.startsWith('legacy-') ? Number(groupKey.slice(7)) : -1; return Number.isInteger(id) && id > 0 ? id : -1; }

/** Returns whether two owned outputs belong to the same re-tailoring comparison group. */
function outputsShareRevisionGroup(left: Tailored, right: Tailored): boolean { return revisionGroupKey(left.id, left.revision_group_key) === revisionGroupKey(right.id, right.revision_group_key); }

/** Displays the full review, download, tracking, and email controls for an application pack. */
app.get('/tailored/:id', requireUser, async (request, response) => {
  const user = response.locals.user as User;
  const output = await outputForUser(Number(request.params.id), user.id);
  if (!output) return response.sendStatus(404);
  const history = await revisionHistoryForOutput(output, user.id);
  const controls = controlsForOutput(output);
  const updateStatus = String(request.query.applicationUpdated || '');
  const updateBanner = updateStatus === '1' ? '<div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">This revision is now linked to the tracked application.</div>' : updateStatus === 'conflict' ? '<div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">The new revision was saved, but the application link changed while it was being generated. Review the application before selecting this version.</div>' : '';
  const historyItems = history.map((version) => `<article class="rounded-2xl border p-4 ${version.id === output.id ? 'border-cyan-300 bg-cyan-50/70 dark:border-cyan-800 dark:bg-cyan-950/30' : 'border-slate-200 dark:border-slate-800'}"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-bold">Version ${version.revision_number}${version.id === output.id ? ' · current' : ''}${version.archived_at ? ' · in Trash' : ''}</p><p class="mt-1 text-xs text-slate-500">${escapeHtml(tailoringSummary(version))} · ${formatDate(version.created_at)}</p></div><div class="flex flex-wrap gap-2">${version.id !== output.id ? `<a class="button-secondary" href="/tailored/${output.id}/compare/${version.id}">Compare</a><a class="button-secondary" href="/tailored/${version.id}">Review</a>` : ''}</div></div></article>`).join('');
  const emailPanel = emailDeliveryConfigured() && !output.archived_at ? `<form method="post" action="/tailored/${output.id}/email" class="panel p-5 sm:p-6"><h2 class="text-xl font-bold">Email application documents</h2><p class="mt-1 text-sm text-slate-500">Send editable Word files through the configured mail relay.</p><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">Recipient<input class="field mt-2" type="email" name="recipient" required></label><label class="text-sm font-semibold">Subject<input class="field mt-2" name="subject" value="Application for ${escapeHtml(output.job_title || 'the role')}"></label></div><label class="mt-4 block text-sm font-semibold">Message<textarea class="field mt-2 min-h-24" name="message">Please find my CV and cover letter attached for your consideration.</textarea></label><div class="mt-4 flex flex-wrap gap-4 text-sm"><label><input type="checkbox" name="includeCv" value="1" checked> Tailored CV (.docx)</label><label><input type="checkbox" name="includeCoverLetter" value="1" checked> Cover letter (.docx)</label></div><button class="button-primary mt-5">Send documents</button></form>` : '';
  const actions = output.archived_at ? '<a class="button-secondary" href="/documents/trash">Open Trash</a>' : `<a class="button-secondary" href="/applications/from/${output.id}">Track application</a><a class="button-primary" href="/tailored/${output.id}/retailor">Re-tailor</a>`;
  response.send(page('Review application pack', `<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Application pack · Version ${output.revision_number}${output.archived_at ? ' · in Trash' : ''}</p><h1 class="mt-1 text-3xl font-bold">${escapeHtml(tailoredName(output))}</h1><p class="mt-1 text-slate-500">${escapeHtml(output.company_name || output.original_filename)} · ${escapeHtml(tailoringSummary(output))} · created ${formatDate(output.created_at)}</p></div><div class="flex flex-wrap gap-2">${actions}</div></div>${updateBanner}<section class="mt-7 grid gap-5 lg:grid-cols-2"><article class="panel p-5 sm:p-6"><h2 class="text-xl font-bold">Change summary</h2><p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-400">${escapeHtml(TAILORING_FOCUS_LABELS[controls.focus])} · ${escapeHtml(TAILORING_TONE_LABELS[controls.tone])}</p><p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-600 dark:text-slate-300">${escapeHtml(output.change_summary)}</p>${controls.notes ? `<div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-900"><span class="font-semibold">Additional emphasis:</span> ${escapeHtml(controls.notes)}</div>` : ''}</article><article class="panel p-5 sm:p-6"><h2 class="text-xl font-bold">Downloads</h2><div class="mt-4 grid grid-cols-2 gap-2"><a class="button-secondary" href="/tailored/${output.id}/download.docx">CV · Word</a><a class="button-secondary" href="/tailored/${output.id}/download.pdf">CV · PDF</a>${output.cover_letter_text ? `<a class="button-secondary" href="/tailored/${output.id}/cover-letter.docx">Letter · Word</a><a class="button-secondary" href="/tailored/${output.id}/cover-letter.pdf">Letter · PDF</a>` : ''}</div></article></section><section class="panel mt-5 p-5 sm:p-6"><div class="flex items-center justify-between gap-3"><div><h2 class="text-xl font-bold">Revision history</h2><p class="mt-1 text-sm text-slate-500">Every rerun is retained so models and presentation choices can be compared.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold dark:bg-slate-800">${history.length} ${history.length === 1 ? 'version' : 'versions'}</span></div><div class="mt-4 grid gap-3">${historyItems}</div></section><section class="mt-5 grid gap-5 xl:grid-cols-2"><article class="panel p-5 sm:p-7"><h2 class="text-xl font-bold">Tailored CV preview</h2><pre class="mt-4 whitespace-pre-wrap font-sans text-sm leading-6">${escapeHtml(output.tailored_text)}</pre></article><article class="panel p-5 sm:p-7"><h2 class="text-xl font-bold">Cover letter preview</h2><pre class="mt-4 whitespace-pre-wrap font-sans text-sm leading-6">${escapeHtml(output.cover_letter_text || 'No cover letter was generated for this older document.')}</pre></article></section>${emailPanel ? `<section class="mt-5">${emailPanel}</section>` : ''}`, user));
});

/** Displays a repeatable tailoring form using the original CV and stored job description. */
app.get('/tailored/:id/retailor', requireUser, async (request, response) => {
  const user = response.locals.user as User;
  const output = await outputForUser(Number(request.params.id), user.id);
  if (!output) return response.sendStatus(404);
  if (output.archived_at) return response.status(409).send(page('Restore application pack', '<div class="panel mx-auto max-w-xl p-6 sm:p-8"><h1 class="text-2xl font-bold">Restore this pack before re-tailoring</h1><p class="mt-3 text-slate-500">Archived documents remain unchanged until they are restored.</p><a class="button-secondary mt-6" href="/documents/trash">Open Trash</a></div>', user));
  const [catalogue, history] = await Promise.all([cachedModelCatalogue(), revisionHistoryForOutput(output, user.id)]);
  const models = await availableModels(catalogue);
  const preferred = await preferredModel(user.id, models);
  const model = output.model_name && models.includes(output.model_name) ? output.model_name : preferred;
  const controls = controlsForOutput(output);
  const applicationId = Number(request.query.application || 0);
  let application: JobApplication | null = null;
  if (Number.isInteger(applicationId) && applicationId > 0) {
    const [applications] = await pool.query<JobApplication[]>('SELECT * FROM job_applications WHERE id=? AND user_id=? AND tailored_cv_id=?', [applicationId, user.id, output.id]);
    if (!applications.length) return response.sendStatus(404);
    application = applications[0];
  }
  const nextVersion = Math.max(...history.map((version) => Number(version.revision_number) || 1), 0) + 1;
  const applicationControl = application ? `<div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-900 dark:bg-cyan-950/40"><input type="hidden" name="applicationId" value="${application.id}"><label class="flex gap-3 text-sm"><input class="mt-1" type="checkbox" name="replaceApplication" value="1" checked><span><strong>Use version ${nextVersion} for ${escapeHtml(application.job_title)}</strong><br><span class="text-slate-600 dark:text-slate-300">After generation, update only this tracked application to the new pack.</span></span></label></div>` : '';
  response.send(page('Re-tailor application pack', `<div class="mx-auto max-w-4xl"><div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Application pack · Version ${output.revision_number}</p><h1 class="mt-1 text-3xl font-bold">Create another tailored version</h1><p class="mt-2 text-slate-500">Rerun the original CV and job description with a different model or presentation priorities. Existing versions stay unchanged.</p></div><a class="button-secondary" href="/tailored/${output.id}">Cancel</a></div><form class="panel mt-7 p-5 sm:p-8" action="/tailored/${output.id}/retailor" method="post"><div class="grid gap-4 rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-900 sm:grid-cols-3"><div><p class="text-slate-500">Company</p><p class="mt-1 font-semibold">${escapeHtml(output.company_name || 'Not specified')}</p></div><div><p class="text-slate-500">Role</p><p class="mt-1 font-semibold">${escapeHtml(output.job_title || 'Not specified')}</p></div><div><p class="text-slate-500">Factual source</p><p class="mt-1 font-semibold">${escapeHtml(output.original_filename)}</p></div></div><div class="mt-6 grid gap-5 sm:grid-cols-2"><label class="text-sm font-semibold sm:col-span-2">AI model<select class="field mt-2" name="model">${modelOptions(models, model, catalogue?.newModelIds)}</select><span class="mt-1 block font-normal text-slate-500">Current version: ${escapeHtml(output.model_name || output.generation_mode)}. Choose another model for a direct comparison.</span></label>${tailoringControlFields(controls)}</div>${applicationControl ? `<div class="mt-5">${applicationControl}</div>` : ''}<details class="mt-5 rounded-2xl border border-slate-200 p-4 dark:border-slate-800"><summary class="cursor-pointer font-semibold">Review stored job description</summary><pre class="mt-4 max-h-80 overflow-auto whitespace-pre-wrap font-sans text-sm leading-6 text-slate-600 dark:text-slate-300">${escapeHtml(output.job_description)}</pre></details><div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-500">A model request may incur provider charges. No previous version is overwritten.</p><button class="button-primary">Create version ${nextVersion}</button></div></form></div>`, user));
});

/** Generates an immutable re-tailored revision and optionally relinks one tracked application. */
app.post('/tailored/:id/retailor', requireUser, async (request, response) => {
  const user = response.locals.user as User;
  const output = await outputForUser(Number(request.params.id), user.id);
  if (!output) return response.sendStatus(404);
  if (output.archived_at) return response.status(409).send('Restore this application pack before re-tailoring it.');
  const models = await availableModels();
  const model = String(request.body.model || '');
  if (!models.includes(model)) return response.status(400).send('Choose an available AI model.');
  const controls = tailoringControlsFromInput(request.body.focus, request.body.tone, request.body.tailoringNotes);
  const applicationId = Number(request.body.applicationId || 0);
  const replaceApplication = request.body.replaceApplication === '1' && Number.isInteger(applicationId) && applicationId > 0;
  if (Number.isInteger(applicationId) && applicationId > 0) {
    const [applications] = await pool.query<JobApplication[]>('SELECT id FROM job_applications WHERE id=? AND user_id=? AND tailored_cv_id=?', [applicationId, user.id, output.id]);
    if (!applications.length) return response.status(409).send('This application is no longer linked to the selected pack.');
  }
  const [cvs] = await pool.query<Cv[]>('SELECT * FROM cv_documents WHERE id=? AND user_id=?', [output.source_cv_id, user.id]);
  if (!cvs.length) return response.status(409).send('The factual source CV is no longer available.');
  try {
    const profile = await profileForUser(user.id);
    const draft = await tailorWithAi(cvs[0].extracted_text, output.job_description, profile, output.company_name || '', output.job_title || '', model, controls);
    const connection = await pool.getConnection();
    let resultId = 0;
    let applicationUpdated = '';
    try {
      await connection.beginTransaction();
      const [activeParent] = await connection.query<RowDataPacket[]>("SELECT t.id FROM tailored_cvs t LEFT JOIN document_metadata dm ON dm.user_id=t.user_id AND dm.document_type='tailored_cv' AND dm.document_id=t.id WHERE t.id=? AND t.user_id=? AND dm.archived_at IS NULL FOR UPDATE", [output.id, user.id]);
      if (!activeParent.length) throw new Error('The source application pack changed while the new version was being generated.');
      const groupKey = revisionGroupKey(output.id, output.revision_group_key);
      const [lockedVersions] = await connection.query<RowDataPacket[]>('SELECT id,revision_number FROM tailored_cvs WHERE user_id=? AND (revision_group_key=? OR id=?) FOR UPDATE', [user.id, groupKey, legacyRevisionRootId(groupKey)]);
      const nextVersion = Math.max(...lockedVersions.map((version) => Number(version.revision_number) || 1), 0) + 1;
      const [created] = await connection.query<ResultSetHeader>('INSERT INTO tailored_cvs (user_id,source_cv_id,job_description,tailored_text,change_summary,generation_mode,company_name,job_title,model_name,cover_letter_text,parent_tailored_cv_id,revision_group_key,revision_number,tailoring_focus,tailoring_tone,tailoring_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [user.id, output.source_cv_id, output.job_description, draft.tailoredText, draft.summary, draft.mode, output.company_name, output.job_title, draft.model, draft.coverLetter, output.id, groupKey, nextVersion, controls.focus, controls.tone, controls.notes || null]);
      resultId = created.insertId;
      if (replaceApplication) {
        const [updated] = await connection.query<ResultSetHeader>('UPDATE job_applications SET tailored_cv_id=?,cv_document_id=? WHERE id=? AND user_id=? AND tailored_cv_id=?', [resultId, output.source_cv_id, applicationId, user.id, output.id]);
        applicationUpdated = updated.affectedRows === 1 ? '1' : 'conflict';
      }
      await connection.commit();
    } catch (error) { await connection.rollback(); throw error; } finally { connection.release(); }
    response.redirect(`/tailored/${resultId}${applicationUpdated ? `?applicationUpdated=${applicationUpdated}` : ''}`);
  } catch (error) { response.status(502).send(`Re-tailoring failed: ${escapeHtml((error as Error).message)}`); }
});

/** Shows two revisions side by side so model and presentation differences are easy to assess. */
app.get('/tailored/:id/compare/:otherId', requireUser, async (request, response) => {
  const user = response.locals.user as User;
  const [left, right] = await Promise.all([outputForUser(Number(request.params.id), user.id), outputForUser(Number(request.params.otherId), user.id)]);
  if (!left || !right || !outputsShareRevisionGroup(left, right) || left.id === right.id) return response.sendStatus(404);
  const versions = [left, right];
  const cards = versions.map((version) => { const controls = controlsForOutput(version); return `<article class="panel min-w-0 p-5 sm:p-6"><p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">Version ${version.revision_number}</p><h2 class="mt-1 text-xl font-bold">${escapeHtml(version.model_name || version.generation_mode)}</h2><p class="mt-1 text-sm text-slate-500">${escapeHtml(TAILORING_FOCUS_LABELS[controls.focus])} · ${escapeHtml(TAILORING_TONE_LABELS[controls.tone])} · ${formatDate(version.created_at)}</p>${controls.notes ? `<p class="mt-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-900"><strong>Additional emphasis:</strong> ${escapeHtml(controls.notes)}</p>` : ''}<h3 class="mt-6 font-bold">Change summary</h3><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600 dark:text-slate-300">${escapeHtml(version.change_summary)}</p><h3 class="mt-6 font-bold">Tailored CV</h3><pre class="mt-2 whitespace-pre-wrap font-sans text-sm leading-6">${escapeHtml(version.tailored_text)}</pre><details class="mt-6 border-t border-slate-200 pt-4 dark:border-slate-800"><summary class="cursor-pointer font-bold">Cover letter</summary><pre class="mt-3 whitespace-pre-wrap font-sans text-sm leading-6">${escapeHtml(version.cover_letter_text || 'No cover letter was generated.')}</pre></details><a class="button-secondary mt-6" href="/tailored/${version.id}">Review version ${version.revision_number}</a></article>`; }).join('');
  response.send(page('Compare tailored versions', `<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Model and presentation comparison</p><h1 class="mt-1 text-3xl font-bold">Compare versions ${left.revision_number} and ${right.revision_number}</h1><p class="mt-2 text-slate-500">Both versions use the same original CV and stored job description, making their model and control choices directly comparable.</p></div><a class="button-secondary" href="/tailored/${left.id}">Back to history</a></div><div class="mt-7 grid items-start gap-5 xl:grid-cols-2">${cards}</div>`, user));
});

/** Generates a requested DOCX or PDF only for the output's owning user. */
app.get('/tailored/:id/download.:format', requireUser, async (request, response) => { const format = String(request.params.format); const user = response.locals.user as User; const [output, profile] = await Promise.all([outputForUser(Number(request.params.id), user.id), profileForUser(user.id)]); if (!output || !['docx', 'pdf'].includes(format)) return response.sendStatus(404); const safeName = `${downloadBaseName(tailoredName(output), 'job-tune')}-cv`; const context = professionalDocumentContext('cv', user, profile, output); if (format === 'docx') { response.type('application/vnd.openxmlformats-officedocument.wordprocessingml.document').attachment(`${safeName}.docx`).send(await professionalDocxBuffer(output.tailored_text, context)); return; } response.type('application/pdf').attachment(`${safeName}.pdf`).send(await professionalPdfBuffer(output.tailored_text, context)); });

/** Generates a cover letter as editable Word or submission-ready PDF. */
app.get('/tailored/:id/cover-letter.:format', requireUser, async (request, response) => { const format = String(request.params.format); const user = response.locals.user as User; const [output, profile] = await Promise.all([outputForUser(Number(request.params.id), user.id), profileForUser(user.id)]); if (!output?.cover_letter_text || !['docx', 'pdf'].includes(format)) return response.sendStatus(404); const safeName = `${downloadBaseName(tailoredName(output), 'job-tune')}-cover-letter`; const context = professionalDocumentContext('cover_letter', user, profile, output); if (format === 'docx') { response.type('application/vnd.openxmlformats-officedocument.wordprocessingml.document').attachment(`${safeName}.docx`).send(await professionalDocxBuffer(output.cover_letter_text, context)); return; } response.type('application/pdf').attachment(`${safeName}.pdf`).send(await professionalPdfBuffer(output.cover_letter_text, context)); });

/** Emails selected Word documents with rate limiting and a minimal delivery audit record. */
app.post('/tailored/:id/email', requireUser, async (request, response) => { const user = response.locals.user as User; const output = await outputForUser(Number(request.params.id), user.id); const recipient = String(request.body.recipient || '').trim().toLowerCase(); const includeCv = request.body.includeCv === '1'; const includeCoverLetter = request.body.includeCoverLetter === '1'; if (!output || output.archived_at || !/^\S+@\S+\.\S+$/.test(recipient) || (!includeCv && !includeCoverLetter) || (includeCoverLetter && !output.cover_letter_text)) return response.status(400).send('Choose an active, available document and enter a valid recipient.'); const [usage] = await pool.query<RowDataPacket[]>('SELECT COUNT(*) AS total FROM sent_document_emails WHERE user_id=? AND created_at>DATE_SUB(NOW(), INTERVAL 1 DAY)', [user.id]); if (Number(usage[0].total) >= 20) return response.status(429).send('Daily document email limit reached.'); const profile = await profileForUser(user.id); await sendApplicationPack(recipient, String(request.body.subject || '').trim().slice(0, 255) || `Application for ${output.job_title || 'the role'}`, String(request.body.message || '').trim().slice(0, 5000), user, profile, output, includeCv, includeCoverLetter); await pool.query('INSERT INTO sent_document_emails (user_id,tailored_cv_id,recipient_email,included_cv,included_cover_letter) VALUES (?,?,?,?,?)', [user.id, output.id, recipient, includeCv ? 1 : 0, includeCoverLetter ? 1 : 0]); response.redirect(`/tailored/${output.id}`); });

/** Sends concise operational errors without exposing internal details or personal input. */
app.use((error: Error, _request: Request, response: Response, _next: NextFunction) => { console.error(error.message); response.status(400).send('The request could not be processed. Check the file and try again.'); });

/** Starts the HTTP service behind Apache once this module is executed directly. */
function start(): void { webAuthnConfig(); app.listen(Number(process.env.PORT || 3000), () => console.log(`Job Tune listening on port ${process.env.PORT || 3000}`)); }

start();
