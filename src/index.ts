import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { generateAuthenticationOptions, generateRegistrationOptions, verifyAuthenticationResponse, verifyRegistrationResponse, type AuthenticationResponseJSON, type AuthenticatorTransportFuture, type RegistrationResponseJSON } from '@simplewebauthn/server';
import { Document, Packer, Paragraph, TextRun } from 'docx';
import dotenv from 'dotenv';
import express, { NextFunction, Request, Response } from 'express';
import mammoth from 'mammoth';
import multer from 'multer';
import nodemailer from 'nodemailer';
import PDFDocument from 'pdfkit';
import { ResultSetHeader, RowDataPacket } from 'mysql2/promise';
import { createDatabasePool } from './db';

dotenv.config();
const app = express();
const pool = createDatabasePool();
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 8 * 1024 * 1024 }, fileFilter: (_req, file, callback) => callback(null, file.originalname.toLowerCase().endsWith('.docx')) });
app.set('trust proxy', 'loopback');
app.use(express.urlencoded({ extended: false }));
app.use(express.json());

type User = { id: number; email: string };
type Cv = RowDataPacket & { id: number; original_filename: string; extracted_text: string; version_number: number };
type Tailored = RowDataPacket & { id: number; tailored_text: string; change_summary: string; cover_letter_text: string | null; company_name: string | null; job_title: string | null; model_name: string | null; generation_mode: string; job_description: string; source_cv_id: number; original_filename: string; created_at: Date };
type JobApplication = RowDataPacket & { id: number; company_name: string; job_title: string; location: string | null; source_url: string | null; status: ApplicationStatus; application_date: string | Date | null; cv_document_id: number | null; tailored_cv_id: number | null; notes: string | null; updated_at: Date; original_filename?: string | null; tailored_job_title?: string | null };
type ApplicationFormData = { id?: number; company_name?: string | null; job_title?: string | null; location?: string | null; source_url?: string | null; status?: ApplicationStatus; application_date?: string | Date | null; cv_document_id?: number | null; tailored_cv_id?: number | null; notes?: string | null };
type UserProfile = RowDataPacket & { full_name: string | null; phone: string | null; address_line_1: string | null; address_line_2: string | null; city: string | null; region: string | null; postal_code: string | null; country: string | null; linkedin_url: string | null; portfolio_url: string | null };
type ApplicationStatus = 'interested' | 'preparing' | 'applied' | 'interview' | 'offer' | 'accepted' | 'rejected' | 'withdrawn';
type WebAuthnChallenge = RowDataPacket & { id: number; ceremony: 'registration' | 'authentication'; challenge: string; email: string | null; user_id: number | null; user_handle: Buffer | null };
type PasskeyCredential = RowDataPacket & { credential_id: string; user_id: number; credential_public_key: Buffer; counter: number; transports: string | null };

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

/** Returns the configured, operator-approved model catalogue in display order. */
function availableModels(): string[] { const defaults = ['gpt-5-mini', 'gpt-5-nano', 'gpt-4.1-mini']; const configured = (process.env.OPENAI_MODELS || defaults.join(',')).split(',').map((model) => model.trim()).filter(Boolean); return configured.length ? [...new Set(configured)] : defaults; }

/** Returns the user's selected model or the deployment default when no preference exists. */
async function preferredModel(userId: number): Promise<string> { const [rows] = await pool.query<RowDataPacket[]>('SELECT ai_model FROM user_preferences WHERE user_id=?', [userId]); const models = availableModels(); const selected = String(rows[0]?.ai_model || ''); const configuredDefault = process.env.OPENAI_MODEL || ''; if (models.includes(selected)) return selected; return models.includes(configuredDefault) ? configuredDefault : models[0]; }

/** Returns a safely formatted date for inputs and human-readable lists. */
function formatDate(value: string | Date | null | undefined, input = false): string { if (!value) return ''; const date = value instanceof Date ? value : new Date(value); if (Number.isNaN(date.getTime())) return ''; return input ? date.toISOString().slice(0, 10) : new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }).format(date); }

/** Returns the label used for an application workflow state. */
function statusLabel(status: ApplicationStatus): string { return ({ interested: 'Interested', preparing: 'Preparing', applied: 'Applied', interview: 'Interview', offer: 'Offer', accepted: 'Accepted', rejected: 'Rejected', withdrawn: 'Withdrawn' })[status]; }

/** Renders application-state options while preserving the current selection. */
function statusOptions(selected: ApplicationStatus = 'interested'): string { const statuses: ApplicationStatus[] = ['interested', 'preparing', 'applied', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn']; return statuses.map((status) => `<option value="${status}"${status === selected ? ' selected' : ''}>${statusLabel(status)}</option>`).join(''); }

/** Validates an application state received from an untrusted form. */
function validStatus(value: string): ApplicationStatus { const statuses: ApplicationStatus[] = ['interested', 'preparing', 'applied', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn']; return statuses.includes(value as ApplicationStatus) ? value as ApplicationStatus : 'interested'; }

/** Accepts only absolute HTTP(S) URLs for saved external links. */
function safeUrl(value: string): string | null { if (!value.trim()) return null; try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url.toString() : null; } catch { return null; } }

/** Renders the shared responsive light/dark application shell and navigation. */
function page(title: string, body: string, user?: User): string {
  const navigation = user ? `<div class="hidden items-center gap-1 md:flex"><a class="nav-link" href="/">Dashboard</a><a class="nav-link" href="/applications">Applications</a><a class="nav-link" href="/documents">Documents</a><a class="nav-link" href="/tailor">Tailor</a><a class="nav-link" href="/profile">Profile</a><a class="nav-link" href="/settings">Settings</a></div>` : '';
  const mobileNavigation = user ? `<nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-slate-200 bg-white/95 px-1 py-2 text-center text-[11px] font-medium backdrop-blur dark:border-slate-800 dark:bg-slate-950/95 md:hidden"><a href="/">Home</a><a href="/applications">Jobs</a><a href="/documents">Docs</a><a href="/tailor">Tailor</a><a href="/settings">More</a></nav>` : '';
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#f8fafc"><script>if(localStorage.getItem('job-tune-theme')==='dark')document.documentElement.classList.add('dark');</script><script>tailwind={config:{darkMode:'class'}}</script><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="/assets/app.css"><title>${escapeHtml(title)} · Job Tune</title></head><body class="min-h-screen bg-slate-50 text-slate-950 antialiased dark:bg-slate-950 dark:text-slate-100"><header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/90"><div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6"><a href="/" class="flex items-center gap-2 font-bold tracking-tight"><span class="grid h-8 w-8 place-items-center rounded-xl bg-cyan-600 text-sm text-white">JT</span>Job Tune</a>${navigation}<div class="flex items-center gap-2"><button id="theme-toggle" class="icon-button" aria-label="Toggle colour theme">◐</button>${user ? `<span class="hidden max-w-40 truncate text-xs text-slate-500 lg:block">${escapeHtml(user.email)}</span><form method="post" action="/logout" class="inline"><button class="icon-button" title="Log out" aria-label="Log out">↗</button></form>` : ''}</div></div></header><main class="mx-auto max-w-7xl px-4 py-6 pb-24 sm:px-6 sm:py-10 md:pb-10">${body}</main>${mobileNavigation}<script src="/assets/security.js"></script><script src="/assets/theme.js"></script></body></html>`;
}

/** Preserves the input CV exactly when AI is unavailable while plainly identifying the limitation. */
function localFallback(cvText: string, jobDescription: string, profile: UserProfile | null, companyName: string, jobTitle: string, model: string): { tailoredText: string; summary: string; coverLetter: string; mode: string; model: string } {
  const keywords = [...new Set((jobDescription.toLowerCase().match(/[a-z][a-z-]{3,}/g) || []).filter((word) => cvText.toLowerCase().includes(word)))].slice(0, 12);
  const name = profile?.full_name || 'Applicant';
  return { tailoredText: cvText, summary: `Local factual fallback: the source CV is unchanged because no AI API key is configured. Matching terms already present in the CV: ${keywords.length ? keywords.join(', ') : 'none detected'}. Configure OPENAI_API_KEY to enable drafting.`, coverLetter: `Dear Hiring Team,\n\nPlease accept my application for the ${jobTitle || 'advertised role'}${companyName ? ` at ${companyName}` : ''}. My attached CV provides a factual account of my relevant experience and qualifications.\n\nI would welcome the opportunity to discuss my application further.\n\nKind regards,\n${name}`, mode: 'local_fallback', model };
}

/** Requests a constrained AI response that can only use source-CV facts. */
async function tailorWithAi(cvText: string, jobDescription: string, profile: UserProfile | null, companyName: string, jobTitle: string, model: string): Promise<{ tailoredText: string; summary: string; coverLetter: string; mode: string; model: string }> {
  if (!process.env.OPENAI_API_KEY) return localFallback(cvText, jobDescription, profile, companyName, jobTitle, model);
  const contact = profile ? JSON.stringify({ fullName: profile.full_name, phone: profile.phone, address: [profile.address_line_1, profile.address_line_2, profile.city, profile.region, profile.postal_code, profile.country].filter(Boolean).join(', '), linkedin: profile.linkedin_url, portfolio: profile.portfolio_url }) : '{}';
  const prompt = `Create a targeted CV and professional cover letter. The source CV is the sole authority for experience, employers, dates, qualifications, achievements, metrics, responsibilities, and skills. Never invent, infer, or exaggerate facts. Contact details may only come from CONTACT DETAILS. Return JSON with tailoredText, changeSummary, and coverLetter. The change summary must list meaningful reordering or wording changes and explicitly state that no new facts were added.\n\nCOMPANY: ${companyName}\nROLE: ${jobTitle}\nCONTACT DETAILS: ${contact}\n\nSOURCE CV:\n${cvText}\n\nJOB DESCRIPTION:\n${jobDescription}`;
  const result = await fetch('https://api.openai.com/v1/chat/completions', { method: 'POST', headers: { Authorization: `Bearer ${process.env.OPENAI_API_KEY}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ model, response_format: { type: 'json_object' }, messages: [{ role: 'system', content: 'You are a meticulous CV editor. Never invent candidate facts. Return valid JSON only.' }, { role: 'user', content: prompt }] }) });
  if (!result.ok) throw new Error('The AI provider did not accept the tailoring request.');
  const payload = await result.json() as { choices: Array<{ message: { content: string } }> };
  const parsed = JSON.parse(payload.choices[0].message.content) as { tailoredText?: string; changeSummary?: string; coverLetter?: string };
  if (!parsed.tailoredText || !parsed.changeSummary || !parsed.coverLetter) throw new Error('The AI provider returned an incomplete tailoring response.');
  return { tailoredText: parsed.tailoredText, summary: parsed.changeSummary, coverLetter: parsed.coverLetter, mode: 'openai', model };
}

/** Converts plain CV text into a downloadable editable DOCX document. */
async function wordBuffer(text: string): Promise<Buffer> { return Packer.toBuffer(new Document({ sections: [{ children: text.split(/\r?\n/).map((line) => new Paragraph({ children: [new TextRun(line || ' ')] })) }] })); }

/** Converts plain CV text into a simple submission-ready PDF document. */
async function pdfBuffer(text: string): Promise<Buffer> { const pdf = new PDFDocument({ margin: 54 }); const chunks: Buffer[] = []; return new Promise((resolve, reject) => { pdf.on('data', (chunk: Buffer) => chunks.push(chunk)); pdf.on('end', () => resolve(Buffer.concat(chunks))); pdf.on('error', reject); pdf.fontSize(11).text(text, { lineGap: 4 }); pdf.end(); }); }

/** Retrieves optional contact details for cover-letter generation and profile editing. */
async function profileForUser(userId: number): Promise<UserProfile | null> { const [rows] = await pool.query<UserProfile[]>('SELECT * FROM user_profiles WHERE user_id=?', [userId]); return rows[0] || null; }

/** Renders model options and keeps costs controllable through the configured allow-list. */
function modelOptions(selected: string): string { return availableModels().map((model) => `<option value="${escapeHtml(model)}"${model === selected ? ' selected' : ''}>${escapeHtml(model)}</option>`).join(''); }

/** Ensures a selected source document belongs to the current user before linking it. */
async function ownedCvId(id: number, userId: number): Promise<number | null> { if (!Number.isInteger(id) || id <= 0) return null; const [rows] = await pool.query<RowDataPacket[]>('SELECT id FROM cv_documents WHERE id=? AND user_id=?', [id, userId]); return rows.length ? id : null; }

/** Ensures a selected tailored document belongs to the current user before linking it. */
async function ownedTailoredId(id: number, userId: number): Promise<number | null> { if (!Number.isInteger(id) || id <= 0) return null; const [rows] = await pool.query<RowDataPacket[]>('SELECT id FROM tailored_cvs WHERE id=? AND user_id=?', [id, userId]); return rows.length ? id : null; }

/** Renders the reusable create/edit form for a tracked job application. */
function applicationForm(application: ApplicationFormData, cvs: Cv[], outputs: Tailored[]): string {
  const cvOptions = cvs.map((cv) => `<option value="${cv.id}"${Number(application.cv_document_id) === cv.id ? ' selected' : ''}>Version ${cv.version_number} · ${escapeHtml(cv.original_filename)}</option>`).join('');
  const outputOptions = outputs.map((output) => `<option value="${output.id}"${Number(application.tailored_cv_id) === output.id ? ' selected' : ''}>${escapeHtml(output.job_title || output.original_filename)}${output.company_name ? ` · ${escapeHtml(output.company_name)}` : ''}</option>`).join('');
  return `<form method="post" action="${application.id ? `/applications/${application.id}` : '/applications'}" class="panel mx-auto max-w-4xl p-5 sm:p-8"><div class="grid gap-5 sm:grid-cols-2"><label class="text-sm font-semibold">Company<input class="field mt-2" name="companyName" maxlength="255" required value="${escapeHtml(application.company_name || '')}"></label><label class="text-sm font-semibold">Role title<input class="field mt-2" name="jobTitle" maxlength="255" required value="${escapeHtml(application.job_title || '')}"></label><label class="text-sm font-semibold">Location<input class="field mt-2" name="location" maxlength="255" value="${escapeHtml(application.location || '')}"></label><label class="text-sm font-semibold">Job advert URL<input class="field mt-2" name="sourceUrl" type="url" value="${escapeHtml(application.source_url || '')}"></label><label class="text-sm font-semibold">Status<select class="field mt-2" name="status">${statusOptions(application.status || 'interested')}</select></label><label class="text-sm font-semibold">Application date<input class="field mt-2" name="applicationDate" type="date" value="${formatDate(application.application_date, true)}"></label><label class="text-sm font-semibold">Master CV<select class="field mt-2" name="cvDocumentId"><option value="">Not linked</option>${cvOptions}</select></label><label class="text-sm font-semibold">Tailored application pack<select class="field mt-2" name="tailoredCvId"><option value="">Not linked</option>${outputOptions}</select></label></div><label class="mt-5 block text-sm font-semibold">Notes<textarea class="field mt-2 min-h-32" name="notes" maxlength="10000" placeholder="Interview contacts, deadlines, follow-up notes…">${escapeHtml(application.notes || '')}</textarea></label><div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a class="button-secondary" href="/applications">Cancel</a><button class="button-primary">${application.id ? 'Save changes' : 'Add application'}</button></div></form>`;
}

/** Reports whether direct document email delivery is configured. */
function emailDeliveryConfigured(): boolean { return Boolean(process.env.SMTP_HOST && process.env.SMTP_FROM); }

/** Sends a tailored application pack through the configured SMTP relay. */
async function sendApplicationPack(recipient: string, subject: string, message: string, output: Tailored, includeCv: boolean, includeCoverLetter: boolean): Promise<void> {
  if (!emailDeliveryConfigured()) throw new Error('SMTP delivery is not configured.');
  const transport = nodemailer.createTransport({ host: process.env.SMTP_HOST, port: Number(process.env.SMTP_PORT || 587), secure: process.env.SMTP_SECURE === 'true', auth: process.env.SMTP_USER ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASSWORD } : undefined });
  const attachments: Array<{ filename: string; content: Buffer; contentType: string }> = [];
  if (includeCv) attachments.push({ filename: 'tailored-cv.docx', content: await wordBuffer(output.tailored_text), contentType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
  if (includeCoverLetter && output.cover_letter_text) attachments.push({ filename: 'cover-letter.docx', content: await wordBuffer(output.cover_letter_text), contentType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
  await transport.sendMail({ from: process.env.SMTP_FROM, to: recipient, subject, text: message, attachments });
}

/** Shows an actionable overview of applications, documents, and next steps. */
app.get('/', requireUser, async (_request, response) => {
  const user = response.locals.user as User;
  const [[applicationCount], [documentCount], [tailoredCount], [statusRows], [recentApplications]] = await Promise.all([
    pool.query<RowDataPacket[]>('SELECT COUNT(*) AS total FROM job_applications WHERE user_id=?', [user.id]),
    pool.query<RowDataPacket[]>('SELECT COUNT(*) AS total FROM cv_documents WHERE user_id=?', [user.id]),
    pool.query<RowDataPacket[]>('SELECT COUNT(*) AS total FROM tailored_cvs WHERE user_id=?', [user.id]),
    pool.query<RowDataPacket[]>('SELECT status,COUNT(*) AS total FROM job_applications WHERE user_id=? GROUP BY status', [user.id]),
    pool.query<JobApplication[]>('SELECT * FROM job_applications WHERE user_id=? ORDER BY updated_at DESC LIMIT 5', [user.id]),
  ]);
  const activeCount = statusRows.filter((row) => ['applied', 'interview', 'offer'].includes(row.status)).reduce((total, row) => total + Number(row.total), 0);
  const recent = recentApplications.length ? recentApplications.map((application) => `<a href="/applications/${application.id}/edit" class="flex items-center justify-between gap-3 border-b border-slate-100 py-4 last:border-0 dark:border-slate-800"><div class="min-w-0"><p class="truncate font-semibold">${escapeHtml(application.job_title)}</p><p class="truncate text-sm text-slate-500">${escapeHtml(application.company_name)}${application.location ? ` · ${escapeHtml(application.location)}` : ''}</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">${statusLabel(application.status)}</span></a>`).join('') : '<div class="py-10 text-center text-sm text-slate-500">No applications yet. Add a role you are interested in.</div>';
  response.send(page('Dashboard', `<section class="overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-600 to-blue-700 p-6 text-white shadow-xl shadow-cyan-900/10 sm:p-10"><div class="max-w-3xl"><p class="text-sm font-semibold uppercase tracking-[.18em] text-cyan-100">Your application workspace</p><h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-5xl">Turn a strong CV into a focused job search.</h1><p class="mt-4 max-w-2xl text-cyan-50/90">Track every opportunity, keep each tailored document together, and move the right applications forward.</p><div class="mt-7 flex flex-wrap gap-3"><a class="button-secondary border-0" href="/tailor">Tailor a CV</a><a class="inline-flex min-h-11 items-center rounded-xl border border-white/30 px-4 font-semibold" href="/applications/new">Add application</a></div></div></section><section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4"><div class="panel p-4 sm:p-5"><p class="text-sm text-slate-500">Applications</p><p class="mt-2 text-3xl font-bold">${Number(applicationCount[0].total)}</p></div><div class="panel p-4 sm:p-5"><p class="text-sm text-slate-500">Active pipeline</p><p class="mt-2 text-3xl font-bold text-cyan-600">${activeCount}</p></div><div class="panel p-4 sm:p-5"><p class="text-sm text-slate-500">Master CVs</p><p class="mt-2 text-3xl font-bold">${Number(documentCount[0].total)}</p></div><div class="panel p-4 sm:p-5"><p class="text-sm text-slate-500">Tailored packs</p><p class="mt-2 text-3xl font-bold">${Number(tailoredCount[0].total)}</p></div></section><section class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_.8fr]"><div class="panel p-5 sm:p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Pipeline</p><h2 class="mt-1 text-xl font-bold">Recent applications</h2></div><a class="text-sm font-semibold text-cyan-700 dark:text-cyan-300" href="/applications">View all</a></div><div class="mt-3">${recent}</div></div><div class="panel p-5 sm:p-6"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Quick start</p><h2 class="mt-1 text-xl font-bold">Build your next application pack</h2><ol class="mt-5 space-y-4 text-sm"><li class="flex gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-cyan-100 font-bold text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200">1</span><span><a class="font-semibold" href="/profile">Complete contact details</a><br><span class="text-slate-500">Used in your cover letters.</span></span></li><li class="flex gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-cyan-100 font-bold text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200">2</span><span><a class="font-semibold" href="/documents">Upload a master CV</a><br><span class="text-slate-500">The factual source for tailoring.</span></span></li><li class="flex gap-3"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-cyan-100 font-bold text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200">3</span><span><a class="font-semibold" href="/tailor">Generate and review</a><br><span class="text-slate-500">CV, summary, and cover letter.</span></span></li></ol></div></section>`, user));
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
app.get('/documents', requireUser, async (_request, response) => {
  const user = response.locals.user as User;
  const [[cvs], [outputs]] = await Promise.all([pool.query<Cv[]>('SELECT id,original_filename,version_number,created_at FROM cv_documents WHERE user_id=? ORDER BY created_at DESC', [user.id]), pool.query<Tailored[]>('SELECT t.*,c.original_filename FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id WHERE t.user_id=? ORDER BY t.created_at DESC', [user.id])]);
  const cvCards = cvs.length ? cvs.map((cv) => `<div class="panel flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">Master CV · Version ${cv.version_number}</p><h3 class="mt-1 font-bold">${escapeHtml(cv.original_filename)}</h3><p class="mt-1 text-sm text-slate-500">Uploaded ${formatDate(cv.created_at)}</p></div><div class="flex gap-2"><a class="button-secondary" href="/cvs/${cv.id}/download">Download original</a><a class="button-primary" href="/tailor?cv=${cv.id}">Tailor</a></div></div>`).join('') : '<div class="panel p-8 text-center text-slate-500">Upload your first Word CV to establish a factual source document.</div>';
  const outputCards = outputs.length ? outputs.map((output) => `<article class="panel p-5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">Application pack</p><h3 class="mt-1 truncate text-lg font-bold">${escapeHtml(output.job_title || 'Tailored CV')}</h3><p class="truncate text-sm text-slate-500">${escapeHtml(output.company_name || output.original_filename)} · ${escapeHtml(output.model_name || output.generation_mode)}</p></div><span class="rounded-full bg-slate-100 px-2 py-1 text-xs dark:bg-slate-800">${formatDate(output.created_at)}</span></div><div class="mt-5 grid grid-cols-2 gap-2"><a class="button-secondary" href="/tailored/${output.id}">Review pack</a><a class="button-secondary" href="/tailored/${output.id}/download.docx">CV Word</a><a class="button-secondary" href="/tailored/${output.id}/download.pdf">CV PDF</a>${output.cover_letter_text ? `<a class="button-secondary" href="/tailored/${output.id}/cover-letter.docx">Letter Word</a>` : ''}</div></article>`).join('') : '<div class="panel p-8 text-center text-slate-500 sm:col-span-2">Tailored CVs and cover letters will appear here for reuse.</div>';
  response.send(page('Documents', `<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Document library</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Everything you have created</h1><p class="mt-2 text-slate-500">Keep original CV versions and role-specific application packs organised.</p></div><a class="button-primary" href="/tailor">Create application pack</a></div><section class="mt-8"><div class="flex items-center justify-between"><h2 class="text-xl font-bold">Master CVs</h2><form action="/cvs" method="post" enctype="multipart/form-data" class="flex max-w-xs gap-2"><input required accept=".docx" name="cv" type="file" class="field min-w-0 p-2 text-xs"><button class="button-primary whitespace-nowrap">Upload</button></form></div><div class="mt-4 space-y-3">${cvCards}</div></section><section class="mt-10"><h2 class="text-xl font-bold">Tailored application packs</h2><div class="mt-4 grid gap-4 lg:grid-cols-2">${outputCards}</div></section>`, user));
});

/** Extracts and stores a DOCX CV as a new user-scoped version. */
app.post('/cvs', requireUser, upload.single('cv'), requireCsrf, async (request, response) => { if (!request.file) return response.status(400).send('Please upload a .docx CV.'); const text = (await mammoth.extractRawText({ buffer: request.file.buffer })).value.trim(); if (!text) return response.status(400).send('This Word file did not contain readable text.'); const user = response.locals.user as User; const [versions] = await pool.query<RowDataPacket[]>('SELECT COALESCE(MAX(version_number),0)+1 AS nextVersion FROM cv_documents WHERE user_id=?', [user.id]); await pool.query('INSERT INTO cv_documents (user_id,original_filename,mime_type,original_docx,extracted_text,version_number) VALUES (?,?,?,?,?,?)', [user.id, request.file.originalname, request.file.mimetype || 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', request.file.buffer, text, versions[0].nextVersion]); response.redirect('/documents'); });

/** Downloads the exact original DOCX only for its owning user. */
app.get('/cvs/:id/download', requireUser, async (request, response) => { const user = response.locals.user as User; const [rows] = await pool.query<(RowDataPacket & { original_filename: string; mime_type: string; original_docx: Buffer })[]>('SELECT original_filename,mime_type,original_docx FROM cv_documents WHERE id=? AND user_id=?', [Number(request.params.id), user.id]); if (!rows.length) return response.sendStatus(404); response.type(rows[0].mime_type).attachment(rows[0].original_filename).send(rows[0].original_docx); });

/** Displays the mobile-friendly tailoring form with role, company, CV, and model controls. */
app.get('/tailor', requireUser, async (request, response) => { const user = response.locals.user as User; const [cvs] = await pool.query<Cv[]>('SELECT id,original_filename,version_number FROM cv_documents WHERE user_id=? ORDER BY version_number DESC', [user.id]); const model = await preferredModel(user.id); const selectedCv = Number(request.query.cv || 0); const cvOptions = cvs.map((cv) => `<option value="${cv.id}"${selectedCv === cv.id ? ' selected' : ''}>Version ${cv.version_number} · ${escapeHtml(cv.original_filename)}</option>`).join(''); response.send(page('Tailor', `<div class="mx-auto max-w-4xl"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Application pack builder</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Tailor your evidence to the role</h1><p class="mt-2 text-slate-500">Job Tune creates a CV, change summary, and cover letter while treating your source CV as the factual authority.</p>${cvs.length ? `<form class="panel mt-7 p-5 sm:p-8" action="/tailor" method="post"><div class="grid gap-5 sm:grid-cols-2"><label class="text-sm font-semibold">Company<input class="field mt-2" name="companyName" maxlength="255" required></label><label class="text-sm font-semibold">Role title<input class="field mt-2" name="jobTitle" maxlength="255" required></label><label class="text-sm font-semibold">Source CV<select class="field mt-2" name="cvId" required><option value="">Choose CV</option>${cvOptions}</select></label><label class="text-sm font-semibold">AI model<select class="field mt-2" name="model">${modelOptions(model)}</select><span class="mt-1 block font-normal text-slate-500">Smaller models generally reduce cost.</span></label></div><label class="mt-5 block text-sm font-semibold">Job description<textarea required name="jobDescription" class="field mt-2 min-h-64" minlength="30" placeholder="Paste the complete description from the employer's website"></textarea></label><div class="mt-6 flex justify-end"><button class="button-primary w-full sm:w-auto">Create CV and cover letter</button></div></form>` : '<div class="panel mt-7 p-8 text-center"><p class="text-slate-500">Upload a Word CV before creating an application pack.</p><a class="button-primary mt-4" href="/documents">Open documents</a></div>'}</div>`, user)); });

/** Creates and stores a factual tailored CV, change summary, and cover letter. */
app.post('/tailor', requireUser, async (request, response) => { const user = response.locals.user as User; const [cvs] = await pool.query<Cv[]>('SELECT * FROM cv_documents WHERE id=? AND user_id=?', [Number(request.body.cvId), user.id]); const jobDescription = String(request.body.jobDescription || '').trim(); const companyName = String(request.body.companyName || '').trim().slice(0, 255); const jobTitle = String(request.body.jobTitle || '').trim().slice(0, 255); const requestedModel = String(request.body.model || ''); const model = availableModels().includes(requestedModel) ? requestedModel : await preferredModel(user.id); if (!cvs.length || jobDescription.length < 30 || !companyName || !jobTitle) return response.status(400).send('Choose your CV and provide the company, role, and full job description.'); try { const profile = await profileForUser(user.id); const draft = await tailorWithAi(cvs[0].extracted_text, jobDescription, profile, companyName, jobTitle, model); const [result] = await pool.query<ResultSetHeader>('INSERT INTO tailored_cvs (user_id,source_cv_id,job_description,tailored_text,change_summary,generation_mode,company_name,job_title,model_name,cover_letter_text) VALUES (?,?,?,?,?,?,?,?,?,?)', [user.id, cvs[0].id, jobDescription, draft.tailoredText, draft.summary, draft.mode, companyName, jobTitle, draft.model, draft.coverLetter]); response.redirect(`/tailored/${result.insertId}`); } catch (error) { response.status(502).send(`Tailoring failed: ${escapeHtml((error as Error).message)}`); } });

/** Lists the user's tracked opportunities and their current workflow state. */
app.get('/applications', requireUser, async (_request, response) => { const user = response.locals.user as User; const [applications] = await pool.query<JobApplication[]>('SELECT a.*,c.original_filename,t.job_title AS tailored_job_title FROM job_applications a LEFT JOIN cv_documents c ON c.id=a.cv_document_id LEFT JOIN tailored_cvs t ON t.id=a.tailored_cv_id WHERE a.user_id=? ORDER BY COALESCE(a.application_date,a.created_at) DESC,a.updated_at DESC', [user.id]); const cards = applications.length ? applications.map((application) => `<article class="panel p-5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-lg font-bold">${escapeHtml(application.job_title)}</p><p class="truncate text-sm text-slate-500">${escapeHtml(application.company_name)}${application.location ? ` · ${escapeHtml(application.location)}` : ''}</p></div><span class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200">${statusLabel(application.status)}</span></div><dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">Applied</dt><dd class="font-medium">${application.application_date ? formatDate(application.application_date) : 'Not yet'}</dd></div><div><dt class="text-slate-500">Documents</dt><dd class="truncate font-medium">${application.tailored_cv_id ? 'Tailored pack linked' : application.cv_document_id ? 'Master CV linked' : 'Not linked'}</dd></div></dl><div class="mt-5 flex gap-2"><a class="button-secondary flex-1" href="/applications/${application.id}/edit">View / edit</a>${application.source_url ? `<a class="button-secondary" href="${escapeHtml(application.source_url)}" target="_blank" rel="noopener">Advert ↗</a>` : ''}</div></article>`).join('') : '<div class="panel p-10 text-center text-slate-500 sm:col-span-2 lg:col-span-3">No applications tracked yet.</div>'; response.send(page('Applications', `<div class="flex items-end justify-between gap-4"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Application tracker</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Your job search pipeline</h1><p class="mt-2 text-slate-500">Keep dates, status, documents, links, and notes together.</p></div><a class="button-primary shrink-0" href="/applications/new">Add role</a></div><div class="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">${cards}</div>`, user)); });

/** Displays the new-application form with user-owned document choices. */
app.get('/applications/new', requireUser, async (_request, response) => { const user = response.locals.user as User; const [[cvs], [outputs]] = await Promise.all([pool.query<Cv[]>('SELECT id,original_filename,version_number FROM cv_documents WHERE user_id=? ORDER BY version_number DESC', [user.id]), pool.query<Tailored[]>('SELECT t.*,c.original_filename FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id WHERE t.user_id=? ORDER BY t.created_at DESC', [user.id])]); response.send(page('Add application', `<div class="mb-7"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">New opportunity</p><h1 class="mt-1 text-3xl font-bold">Track a job application</h1></div>${applicationForm({}, cvs, outputs)}`, user)); });

/** Prefills a tracked application from a completed tailored application pack. */
app.get('/applications/from/:tailoredId', requireUser, async (request, response) => { const user = response.locals.user as User; const [[outputs], [cvs], [allOutputs]] = await Promise.all([pool.query<Tailored[]>('SELECT t.*,c.original_filename FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id WHERE t.id=? AND t.user_id=?', [Number(request.params.tailoredId), user.id]), pool.query<Cv[]>('SELECT id,original_filename,version_number FROM cv_documents WHERE user_id=? ORDER BY version_number DESC', [user.id]), pool.query<Tailored[]>('SELECT t.*,c.original_filename FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id WHERE t.user_id=? ORDER BY t.created_at DESC', [user.id])]); if (!outputs.length) return response.sendStatus(404); const output = outputs[0]; const initial: ApplicationFormData = { company_name: output.company_name || '', job_title: output.job_title || '', status: 'preparing', cv_document_id: output.source_cv_id, tailored_cv_id: output.id }; response.send(page('Track application', `<div class="mb-7"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">From application pack</p><h1 class="mt-1 text-3xl font-bold">Track ${escapeHtml(output.job_title || 'this role')}</h1></div>${applicationForm(initial, cvs, allOutputs)}`, user)); });

/** Creates a user-scoped job application with validated document relationships. */
app.post('/applications', requireUser, async (request, response) => { const user = response.locals.user as User; const companyName = String(request.body.companyName || '').trim().slice(0, 255); const jobTitle = String(request.body.jobTitle || '').trim().slice(0, 255); if (!companyName || !jobTitle) return response.status(400).send('Company and role title are required.'); const cvId = await ownedCvId(Number(request.body.cvDocumentId), user.id); const tailoredId = await ownedTailoredId(Number(request.body.tailoredCvId), user.id); await pool.query('INSERT INTO job_applications (user_id,company_name,job_title,location,source_url,status,application_date,cv_document_id,tailored_cv_id,notes) VALUES (?,?,?,?,?,?,?,?,?,?)', [user.id, companyName, jobTitle, String(request.body.location || '').trim().slice(0, 255) || null, safeUrl(String(request.body.sourceUrl || '')), validStatus(String(request.body.status || '')), String(request.body.applicationDate || '') || null, cvId, tailoredId, String(request.body.notes || '').trim().slice(0, 10000) || null]); response.redirect('/applications'); });

/** Displays one application for safe editing by its owner. */
app.get('/applications/:id/edit', requireUser, async (request, response) => { const user = response.locals.user as User; const [[applications], [cvs], [outputs]] = await Promise.all([pool.query<JobApplication[]>('SELECT * FROM job_applications WHERE id=? AND user_id=?', [Number(request.params.id), user.id]), pool.query<Cv[]>('SELECT id,original_filename,version_number FROM cv_documents WHERE user_id=? ORDER BY version_number DESC', [user.id]), pool.query<Tailored[]>('SELECT t.*,c.original_filename FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id WHERE t.user_id=? ORDER BY t.created_at DESC', [user.id])]); if (!applications.length) return response.sendStatus(404); response.send(page('Edit application', `<div class="mb-7"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Application record</p><h1 class="mt-1 text-3xl font-bold">${escapeHtml(applications[0].job_title)}</h1></div>${applicationForm(applications[0], cvs, outputs)}`, user)); });

/** Updates one application while maintaining strict user and document ownership. */
app.post('/applications/:id', requireUser, async (request, response) => { const user = response.locals.user as User; const companyName = String(request.body.companyName || '').trim().slice(0, 255); const jobTitle = String(request.body.jobTitle || '').trim().slice(0, 255); if (!companyName || !jobTitle) return response.status(400).send('Company and role title are required.'); const cvId = await ownedCvId(Number(request.body.cvDocumentId), user.id); const tailoredId = await ownedTailoredId(Number(request.body.tailoredCvId), user.id); await pool.query('UPDATE job_applications SET company_name=?,job_title=?,location=?,source_url=?,status=?,application_date=?,cv_document_id=?,tailored_cv_id=?,notes=? WHERE id=? AND user_id=?', [companyName, jobTitle, String(request.body.location || '').trim().slice(0, 255) || null, safeUrl(String(request.body.sourceUrl || '')), validStatus(String(request.body.status || '')), String(request.body.applicationDate || '') || null, cvId, tailoredId, String(request.body.notes || '').trim().slice(0, 10000) || null, Number(request.params.id), user.id]); response.redirect('/applications'); });

/** Displays and edits the contact information used in generated cover letters. */
app.get('/profile', requireUser, async (_request, response) => { const user = response.locals.user as User; const profile = await profileForUser(user.id); const value = (field: keyof UserProfile): string => escapeHtml(String(profile?.[field] || '')); response.send(page('Profile', `<div class="mx-auto max-w-3xl"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Cover-letter identity</p><h1 class="mt-1 text-3xl font-bold">Contact details</h1><p class="mt-2 text-slate-500">These details are available to the cover-letter generator and remain private to your account.</p><form method="post" action="/profile" class="panel mt-7 p-5 sm:p-8"><div class="grid gap-5 sm:grid-cols-2"><label class="text-sm font-semibold sm:col-span-2">Full name<input class="field mt-2" name="fullName" value="${value('full_name')}"></label><label class="text-sm font-semibold">Phone<input class="field mt-2" name="phone" value="${value('phone')}"></label><label class="text-sm font-semibold">Address line 1<input class="field mt-2" name="addressLine1" value="${value('address_line_1')}"></label><label class="text-sm font-semibold">Address line 2<input class="field mt-2" name="addressLine2" value="${value('address_line_2')}"></label><label class="text-sm font-semibold">City<input class="field mt-2" name="city" value="${value('city')}"></label><label class="text-sm font-semibold">County / region<input class="field mt-2" name="region" value="${value('region')}"></label><label class="text-sm font-semibold">Postcode<input class="field mt-2" name="postalCode" value="${value('postal_code')}"></label><label class="text-sm font-semibold">Country<input class="field mt-2" name="country" value="${value('country')}"></label><label class="text-sm font-semibold">LinkedIn URL<input class="field mt-2" type="url" name="linkedinUrl" value="${value('linkedin_url')}"></label><label class="text-sm font-semibold">Portfolio URL<input class="field mt-2" type="url" name="portfolioUrl" value="${value('portfolio_url')}"></label></div><div class="mt-6 flex justify-end"><button class="button-primary w-full sm:w-auto">Save contact details</button></div></form></div>`, user)); });

/** Saves user-scoped contact data without exposing it to other accounts. */
app.post('/profile', requireUser, async (request, response) => { const user = response.locals.user as User; const values = [String(request.body.fullName || '').trim().slice(0, 255) || null, String(request.body.phone || '').trim().slice(0, 80) || null, String(request.body.addressLine1 || '').trim().slice(0, 255) || null, String(request.body.addressLine2 || '').trim().slice(0, 255) || null, String(request.body.city || '').trim().slice(0, 120) || null, String(request.body.region || '').trim().slice(0, 120) || null, String(request.body.postalCode || '').trim().slice(0, 40) || null, String(request.body.country || '').trim().slice(0, 120) || null, safeUrl(String(request.body.linkedinUrl || '')), safeUrl(String(request.body.portfolioUrl || ''))]; await pool.query('INSERT INTO user_profiles (user_id,full_name,phone,address_line_1,address_line_2,city,region,postal_code,country,linkedin_url,portfolio_url) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),phone=VALUES(phone),address_line_1=VALUES(address_line_1),address_line_2=VALUES(address_line_2),city=VALUES(city),region=VALUES(region),postal_code=VALUES(postal_code),country=VALUES(country),linkedin_url=VALUES(linkedin_url),portfolio_url=VALUES(portfolio_url)', [user.id, ...values]); response.redirect('/profile'); });

/** Displays cost-control model preferences, email readiness, and passkey access. */
app.get('/settings', requireUser, async (_request, response) => { const user = response.locals.user as User; const model = await preferredModel(user.id); response.send(page('Settings', `<div class="mx-auto max-w-3xl"><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Preferences</p><h1 class="mt-1 text-3xl font-bold">Settings</h1><div class="mt-7 grid gap-5"><form method="post" action="/settings/model" class="panel p-5 sm:p-7"><h2 class="text-xl font-bold">AI cost and quality</h2><p class="mt-2 text-sm text-slate-500">Choose the default model used for new application packs. The available list is controlled by the server.</p><label class="mt-5 block text-sm font-semibold">Default model<select class="field mt-2" name="model">${modelOptions(model)}</select></label><button class="button-primary mt-5">Save model</button></form><div class="panel p-5 sm:p-7"><h2 class="text-xl font-bold">Document email</h2><p class="mt-2 text-sm text-slate-500">${emailDeliveryConfigured() ? 'SMTP is configured. You can email Word application packs from each tailored document.' : 'SMTP is not configured. Downloads remain available; add SMTP settings to enable direct email.'}</p></div><div class="panel p-5 sm:p-7"><h2 class="text-xl font-bold">Account security</h2><p class="mt-2 text-sm text-slate-500">Add a backup passkey so losing one device does not lock you out.</p><a class="button-secondary mt-5" href="/passkeys">Manage passkeys</a></div></div></div>`, user)); });

/** Saves a model preference only when it belongs to the deployment allow-list. */
app.post('/settings/model', requireUser, async (request, response) => { const user = response.locals.user as User; const model = String(request.body.model || ''); if (!availableModels().includes(model)) return response.status(400).send('Choose an available model.'); await pool.query('INSERT INTO user_preferences (user_id,ai_model) VALUES (?,?) ON DUPLICATE KEY UPDATE ai_model=VALUES(ai_model)', [user.id, model]); response.redirect('/settings'); });

/** Reads a tailored CV only when its owner is the signed-in user. */
async function outputForUser(id: number, userId: number): Promise<Tailored | null> { const [rows] = await pool.query<Tailored[]>('SELECT t.*,c.original_filename FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id WHERE t.id=? AND t.user_id=?', [id, userId]); return rows[0] || null; }

/** Displays the full review, download, tracking, and email controls for an application pack. */
app.get('/tailored/:id', requireUser, async (request, response) => { const user = response.locals.user as User; const output = await outputForUser(Number(request.params.id), user.id); if (!output) return response.sendStatus(404); const emailPanel = emailDeliveryConfigured() ? `<form method="post" action="/tailored/${output.id}/email" class="panel p-5 sm:p-6"><h2 class="text-xl font-bold">Email application documents</h2><p class="mt-1 text-sm text-slate-500">Send editable Word files through the configured mail relay.</p><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">Recipient<input class="field mt-2" type="email" name="recipient" required></label><label class="text-sm font-semibold">Subject<input class="field mt-2" name="subject" value="Application for ${escapeHtml(output.job_title || 'the role')}"></label></div><label class="mt-4 block text-sm font-semibold">Message<textarea class="field mt-2 min-h-24" name="message">Please find my CV and cover letter attached for your consideration.</textarea></label><div class="mt-4 flex flex-wrap gap-4 text-sm"><label><input type="checkbox" name="includeCv" value="1" checked> Tailored CV (.docx)</label><label><input type="checkbox" name="includeCoverLetter" value="1" checked> Cover letter (.docx)</label></div><button class="button-primary mt-5">Send documents</button></form>` : '';
  response.send(page('Review application pack', `<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">Application pack · ${escapeHtml(output.model_name || output.generation_mode)}</p><h1 class="mt-1 text-3xl font-bold">${escapeHtml(output.job_title || 'Tailored CV')}</h1><p class="mt-1 text-slate-500">${escapeHtml(output.company_name || output.original_filename)} · created ${formatDate(output.created_at)}</p></div><a class="button-primary" href="/applications/from/${output.id}">Track this application</a></div><section class="mt-7 grid gap-5 lg:grid-cols-2"><article class="panel p-5 sm:p-6"><h2 class="text-xl font-bold">Change summary</h2><p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-600 dark:text-slate-300">${escapeHtml(output.change_summary)}</p></article><article class="panel p-5 sm:p-6"><h2 class="text-xl font-bold">Downloads</h2><div class="mt-4 grid grid-cols-2 gap-2"><a class="button-secondary" href="/tailored/${output.id}/download.docx">CV · Word</a><a class="button-secondary" href="/tailored/${output.id}/download.pdf">CV · PDF</a>${output.cover_letter_text ? `<a class="button-secondary" href="/tailored/${output.id}/cover-letter.docx">Letter · Word</a><a class="button-secondary" href="/tailored/${output.id}/cover-letter.pdf">Letter · PDF</a>` : ''}</div></article></section><section class="mt-5 grid gap-5 xl:grid-cols-2"><article class="panel p-5 sm:p-7"><h2 class="text-xl font-bold">Tailored CV preview</h2><pre class="mt-4 whitespace-pre-wrap font-sans text-sm leading-6">${escapeHtml(output.tailored_text)}</pre></article><article class="panel p-5 sm:p-7"><h2 class="text-xl font-bold">Cover letter preview</h2><pre class="mt-4 whitespace-pre-wrap font-sans text-sm leading-6">${escapeHtml(output.cover_letter_text || 'No cover letter was generated for this older document.')}</pre></article></section>${emailPanel ? `<section class="mt-5">${emailPanel}</section>` : ''}`, user)); });

/** Generates a requested DOCX or PDF only for the output's owning user. */
app.get('/tailored/:id/download.:format', requireUser, async (request, response) => { const format = String(request.params.format); const output = await outputForUser(Number(request.params.id), (response.locals.user as User).id); if (!output || !['docx', 'pdf'].includes(format)) return response.sendStatus(404); const safeName = 'job-tune-tailored-cv'; if (format === 'docx') { response.type('application/vnd.openxmlformats-officedocument.wordprocessingml.document').attachment(`${safeName}.docx`).send(await wordBuffer(output.tailored_text)); return; } response.type('application/pdf').attachment(`${safeName}.pdf`).send(await pdfBuffer(output.tailored_text)); });

/** Generates a cover letter as editable Word or submission-ready PDF. */
app.get('/tailored/:id/cover-letter.:format', requireUser, async (request, response) => { const format = String(request.params.format); const output = await outputForUser(Number(request.params.id), (response.locals.user as User).id); if (!output?.cover_letter_text || !['docx', 'pdf'].includes(format)) return response.sendStatus(404); const safeName = 'job-tune-cover-letter'; if (format === 'docx') { response.type('application/vnd.openxmlformats-officedocument.wordprocessingml.document').attachment(`${safeName}.docx`).send(await wordBuffer(output.cover_letter_text)); return; } response.type('application/pdf').attachment(`${safeName}.pdf`).send(await pdfBuffer(output.cover_letter_text)); });

/** Emails selected Word documents with rate limiting and a minimal delivery audit record. */
app.post('/tailored/:id/email', requireUser, async (request, response) => { const user = response.locals.user as User; const output = await outputForUser(Number(request.params.id), user.id); const recipient = String(request.body.recipient || '').trim().toLowerCase(); const includeCv = request.body.includeCv === '1'; const includeCoverLetter = request.body.includeCoverLetter === '1'; if (!output || !/^\S+@\S+\.\S+$/.test(recipient) || (!includeCv && !includeCoverLetter) || (includeCoverLetter && !output.cover_letter_text)) return response.status(400).send('Choose an available document and enter a valid recipient.'); const [usage] = await pool.query<RowDataPacket[]>('SELECT COUNT(*) AS total FROM sent_document_emails WHERE user_id=? AND created_at>DATE_SUB(NOW(), INTERVAL 1 DAY)', [user.id]); if (Number(usage[0].total) >= 20) return response.status(429).send('Daily document email limit reached.'); await sendApplicationPack(recipient, String(request.body.subject || '').trim().slice(0, 255) || `Application for ${output.job_title || 'the role'}`, String(request.body.message || '').trim().slice(0, 5000), output, includeCv, includeCoverLetter); await pool.query('INSERT INTO sent_document_emails (user_id,tailored_cv_id,recipient_email,included_cv,included_cover_letter) VALUES (?,?,?,?,?)', [user.id, output.id, recipient, includeCv ? 1 : 0, includeCoverLetter ? 1 : 0]); response.redirect(`/tailored/${output.id}`); });

/** Sends concise operational errors without exposing internal details or personal input. */
app.use((error: Error, _request: Request, response: Response, _next: NextFunction) => { console.error(error.message); response.status(400).send('The request could not be processed. Check the file and try again.'); });

/** Starts the HTTP service behind Apache once this module is executed directly. */
function start(): void { webAuthnConfig(); app.listen(Number(process.env.PORT || 3000), () => console.log(`Job Tune listening on port ${process.env.PORT || 3000}`)); }

start();
