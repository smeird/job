import crypto from 'node:crypto';
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
type Tailored = RowDataPacket & { id: number; tailored_text: string; change_summary: string; generation_mode: string; job_description: string; original_filename: string; created_at: Date };
type PasscodeChallenge = RowDataPacket & { id: number; email: string; code_hash: string; attempts: number };

/** Escapes untrusted strings before placing them in server-rendered HTML. */
function escapeHtml(value: string): string { return value.replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character] || character)); }

/** Reads a named cookie without bringing a client-session dependency into the application. */
function cookie(request: Request, name: string): string | undefined { return request.headers.cookie?.split(';').map((part) => part.trim()).find((part) => part.startsWith(`${name}=`))?.slice(name.length + 1); }

/** Returns the server-only secret used to hash passcodes and opaque challenges. */
function authSecret(): string {
  const secret = process.env.AUTH_SECRET || process.env.SESSION_SECRET || '';
  if (process.env.NODE_ENV === 'production' && secret.length < 32) throw new Error('AUTH_SECRET must contain at least 32 characters in production.');
  return secret || 'development-only-job-tune-auth-secret';
}

/** Produces a one-way HMAC so passcodes and client challenges are never stored in plaintext. */
function authenticationHash(purpose: string, value: string): string { return crypto.createHmac('sha256', authSecret()).update(`${purpose}:${value}`).digest('hex'); }

/** Compares fixed-length hexadecimal hashes without leaking useful timing information. */
function hashesMatch(first: string, second: string): boolean { const left = Buffer.from(first, 'hex'); const right = Buffer.from(second, 'hex'); return left.length === right.length && crypto.timingSafeEqual(left, right); }

/** Reports whether a configured SMTP server can deliver production passcodes. */
function smtpIsConfigured(): boolean { return Boolean(process.env.SMTP_HOST && process.env.SMTP_FROM); }

/** Allows deliberately visible passcodes only for explicit, non-production local development. */
function developmentPasscodeIsEnabled(): boolean { return process.env.NODE_ENV !== 'production' && process.env.DEV_SHOW_PASSCODE === 'true'; }

/** Sends a short-lived login passcode without logging its value. */
async function sendPasscode(email: string, code: string): Promise<void> {
  if (!smtpIsConfigured()) throw new Error('SMTP delivery is not configured.');
  const transport = nodemailer.createTransport({ host: process.env.SMTP_HOST, port: Number(process.env.SMTP_PORT || 587), secure: process.env.SMTP_SECURE === 'true', auth: process.env.SMTP_USER ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASSWORD } : undefined });
  await transport.sendMail({ from: process.env.SMTP_FROM, to: email, subject: 'Your Job Tune sign-in code', text: `Your Job Tune sign-in code is ${code}. It expires in 10 minutes and can be used once. If you did not request it, ignore this email.` });
}

/** Renders the passcode-entry form for a newly created opaque challenge. */
function passcodeForm(challenge: string, developmentCode?: string): string {
  const notice = developmentCode ? `<div class="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"><b>Development only:</b> your passcode is ${escapeHtml(developmentCode)}.</div>` : '<p class="mb-5 text-sm text-slate-300">Check your email for a six-digit code. It expires in 10 minutes.</p>';
  return page('Enter passcode', `<div class="mx-auto max-w-md rounded-2xl bg-slate-900 p-7">${notice}<h1 class="text-2xl font-bold">Enter your passcode</h1><form class="mt-5" method="post" action="/auth/passcode/verify"><input type="hidden" name="challenge" value="${escapeHtml(challenge)}"><label class="block text-sm font-medium">Six-digit code<input class="mt-2 w-full rounded p-3 text-center font-mono text-2xl tracking-[.35em] text-slate-900" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label><button class="mt-5 w-full rounded bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Sign in securely</button></form><a class="mt-5 block text-center text-sm text-cyan-300" href="/login">Request another code</a></div>`);
}

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
}

/** Renders the shared Tailwind-based page shell. */
function page(title: string, body: string, user?: User): string { return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><title>${escapeHtml(title)} · Job Tune</title></head><body class="min-h-screen bg-slate-950 text-slate-100"><main class="mx-auto max-w-6xl px-5 py-8"><nav class="mb-12 flex items-center justify-between"><a href="/" class="text-xl font-bold tracking-tight text-cyan-300">Job Tune</a>${user ? `<span class="text-sm text-slate-300">${escapeHtml(user.email)} · <a class="text-cyan-300" href="/logout">Log out</a></span>` : ''}</nav>${body}</main></body></html>`; }

/** Preserves the input CV exactly when AI is unavailable while plainly identifying the limitation. */
function localFallback(cvText: string, jobDescription: string): { tailoredText: string; summary: string; mode: string } {
  const keywords = [...new Set((jobDescription.toLowerCase().match(/[a-z][a-z-]{3,}/g) || []).filter((word) => cvText.toLowerCase().includes(word)))].slice(0, 12);
  return { tailoredText: cvText, summary: `Local factual fallback: the source CV is unchanged because no AI API key is configured. Matching terms already present in the CV: ${keywords.length ? keywords.join(', ') : 'none detected'}. Configure OPENAI_API_KEY to enable drafting.`, mode: 'local_fallback' };
}

/** Requests a constrained AI response that can only use source-CV facts. */
async function tailorWithAi(cvText: string, jobDescription: string): Promise<{ tailoredText: string; summary: string; mode: string }> {
  if (!process.env.OPENAI_API_KEY) return localFallback(cvText, jobDescription);
  const prompt = `You tailor CVs without inventing facts. Source CV is the sole authority. Do not add employers, dates, qualifications, metrics, responsibilities, skills, or claims not explicitly present. Return JSON with tailoredText and changeSummary.\n\nSOURCE CV:\n${cvText}\n\nJOB DESCRIPTION:\n${jobDescription}`;
  const result = await fetch('https://api.openai.com/v1/chat/completions', { method: 'POST', headers: { Authorization: `Bearer ${process.env.OPENAI_API_KEY}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ model: process.env.OPENAI_MODEL || 'gpt-4.1-mini', response_format: { type: 'json_object' }, messages: [{ role: 'system', content: 'Never invent CV facts. Return valid JSON only.' }, { role: 'user', content: prompt }] }) });
  if (!result.ok) throw new Error('The AI provider did not accept the tailoring request.');
  const payload = await result.json() as { choices: Array<{ message: { content: string } }> };
  const parsed = JSON.parse(payload.choices[0].message.content) as { tailoredText?: string; changeSummary?: string };
  if (!parsed.tailoredText || !parsed.changeSummary) throw new Error('The AI provider returned an incomplete tailoring response.');
  return { tailoredText: parsed.tailoredText, summary: parsed.changeSummary, mode: 'openai' };
}

/** Converts plain CV text into a downloadable editable DOCX document. */
async function wordBuffer(text: string): Promise<Buffer> { return Packer.toBuffer(new Document({ sections: [{ children: text.split(/\r?\n/).map((line) => new Paragraph({ children: [new TextRun(line || ' ')] })) }] })); }

/** Converts plain CV text into a simple submission-ready PDF document. */
async function pdfBuffer(text: string): Promise<Buffer> { const pdf = new PDFDocument({ margin: 54 }); const chunks: Buffer[] = []; return new Promise((resolve, reject) => { pdf.on('data', (chunk: Buffer) => chunks.push(chunk)); pdf.on('end', () => resolve(Buffer.concat(chunks))); pdf.on('error', reject); pdf.fontSize(11).text(text, { lineGap: 4 }); pdf.end(); }); }

/** Shows the post-login hero and the user's stored CV versions and tailored documents. */
app.get('/', requireUser, async (request, response) => { const user = response.locals.user as User; const [cvs] = await pool.query<Cv[]>('SELECT id,original_filename,version_number,created_at FROM cv_documents WHERE user_id=? ORDER BY version_number DESC', [user.id]); const [outputs] = await pool.query<Tailored[]>('SELECT t.*, c.original_filename FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 10', [user.id]); response.send(page('Workspace', `<section class="grid gap-8 lg:grid-cols-[1.1fr_.9fr]"><div><p class="mb-3 font-medium text-cyan-300">Factual tailoring, under your control</p><h1 class="text-5xl font-bold tracking-tight">Make each application feel written for the role.</h1><p class="mt-5 max-w-xl text-lg text-slate-300">Upload your Word CV, add a job description, review every proposed change, then download editable Word and submission-ready PDF files.</p></div><form class="rounded-3xl bg-white p-6 text-slate-900 shadow-2xl" action="/cvs" method="post" enctype="multipart/form-data"><h2 class="text-xl font-bold">1. Upload your CV</h2><label class="mt-4 block text-sm font-medium">Word .docx file<input required accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" name="cv" type="file" class="mt-2 block w-full rounded border p-2"></label><button class="mt-5 rounded bg-slate-950 px-4 py-2 font-semibold text-white">Save CV version</button></form></section><section class="mt-12 grid gap-8 lg:grid-cols-2"><div><h2 class="text-2xl font-bold">Your CV versions</h2><div class="mt-4 space-y-3">${cvs.length ? cvs.map((cv) => `<form class="rounded-xl bg-slate-900 p-4" action="/tailor" method="post"><input type="hidden" name="cvId" value="${cv.id}"><b>${escapeHtml(cv.original_filename)}</b> <span class="text-sm text-slate-400">Version ${cv.version_number}</span><textarea required name="jobDescription" class="mt-3 h-28 w-full rounded border border-slate-700 bg-slate-800 p-2" placeholder="Paste the job description copied from the website"></textarea><button class="mt-3 rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">Create tailored CV</button></form>`).join('') : '<p class="text-slate-400">No CV uploaded yet.</p>'}</div></div><div><h2 class="text-2xl font-bold">Recent tailored CVs</h2><div class="mt-4 space-y-3">${outputs.length ? outputs.map((output) => `<div class="rounded-xl bg-slate-900 p-4"><b>${escapeHtml(output.original_filename)}</b><p class="mt-1 text-sm text-slate-400">${escapeHtml(output.generation_mode)}</p><a class="mt-3 inline-block text-cyan-300" href="/tailored/${output.id}">Review changes and download →</a></div>`).join('') : '<p class="text-slate-400">Your reviewed outputs will appear here.</p>'}</div></div></section>`, user)); });

/** Displays one passwordless form for both new and returning users. */
app.get('/login', (_request, response) => response.send(page('Sign in', `<div class="mx-auto max-w-md rounded-2xl bg-slate-900 p-7"><p class="text-sm font-medium text-cyan-300">Passwordless access</p><h1 class="mt-2 text-3xl font-bold">Sign in with a passcode</h1><p class="mt-3 text-sm leading-6 text-slate-300">Enter your email and we will send a single-use code. New users are created after their email is verified.</p><form class="mt-6" method="post" action="/auth/passcode/request"><label class="block text-sm font-medium">Email address<input class="mt-2 w-full rounded p-3 text-slate-900" name="email" type="email" autocomplete="email" required autofocus></label><button class="mt-5 w-full rounded bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Send passcode</button></form></div>`)));

/** Creates and delivers a rate-limited, short-lived passcode challenge. */
app.post('/auth/passcode/request', async (request, response) => {
  const email = String(request.body.email || '').trim().toLowerCase();
  if (!/^\S+@\S+\.\S+$/.test(email)) { response.status(400).send('Enter a valid email address.'); return; }
  if (!smtpIsConfigured() && !developmentPasscodeIsEnabled()) { response.status(503).send(page('Email unavailable', '<div class="mx-auto max-w-md rounded-2xl bg-slate-900 p-7"><h1 class="text-2xl font-bold">Email sign-in is not configured</h1><p class="mt-3 text-slate-300">Ask the administrator to configure SMTP delivery, then try again.</p></div>')); return; }
  const requestIpHash = authenticationHash('ip', request.ip || 'unknown');
  const [limits] = await pool.query<RowDataPacket[]>('SELECT COUNT(*) AS requests FROM login_passcodes WHERE created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (email=? OR request_ip_hash=?)', [email, requestIpHash]);
  if (Number(limits[0].requests) >= 5) { response.status(429).send('Too many passcode requests. Wait 15 minutes and try again.'); return; }
  const code = crypto.randomInt(0, 1000000).toString().padStart(6, '0');
  const challenge = crypto.randomBytes(32).toString('hex');
  const challengeHash = authenticationHash('challenge', challenge);
  await pool.query('DELETE FROM login_passcodes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
  await pool.query('INSERT INTO login_passcodes (email,challenge_hash,code_hash,request_ip_hash,expires_at) VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE))', [email, challengeHash, authenticationHash('code', `${challenge}:${code}`), requestIpHash]);
  try {
    if (smtpIsConfigured()) await sendPasscode(email, code);
  } catch (error) {
    await pool.query('DELETE FROM login_passcodes WHERE challenge_hash=?', [challengeHash]);
    console.error(`Passcode delivery failed: ${(error as Error).message}`);
    response.status(502).send('The sign-in email could not be sent. Try again later.');
    return;
  }
  response.send(passcodeForm(challenge, developmentPasscodeIsEnabled() && !smtpIsConfigured() ? code : undefined));
});

/** Consumes a valid passcode atomically, creates the user if needed, and starts a session. */
app.post('/auth/passcode/verify', async (request, response) => {
  const challenge = String(request.body.challenge || '');
  const code = String(request.body.code || '').trim();
  if (!/^[a-f0-9]{64}$/.test(challenge) || !/^\d{6}$/.test(code)) { response.status(400).send('Enter the six-digit code from your email.'); return; }
  const connection = await pool.getConnection();
  let userId: number | null = null;
  try {
    await connection.beginTransaction();
    const challengeHash = authenticationHash('challenge', challenge);
    const [rows] = await connection.query<PasscodeChallenge[]>('SELECT id,email,code_hash,attempts FROM login_passcodes WHERE challenge_hash=? AND consumed_at IS NULL AND expires_at > NOW() FOR UPDATE', [challengeHash]);
    const record = rows[0];
    if (!record || record.attempts >= 5) { await connection.rollback(); response.status(401).send('This passcode is invalid or expired. Request a new one.'); return; }
    const submittedHash = authenticationHash('code', `${challenge}:${code}`);
    if (!hashesMatch(record.code_hash, submittedHash)) {
      await connection.query('UPDATE login_passcodes SET attempts=attempts+1, consumed_at=IF(attempts+1>=5,NOW(),consumed_at) WHERE id=?', [record.id]);
      await connection.commit();
      response.status(401).send('This passcode is invalid or expired. Request a new one.');
      return;
    }
    await connection.query('INSERT INTO users (email,password_hash) VALUES (?,NULL) ON DUPLICATE KEY UPDATE email=VALUES(email)', [record.email]);
    const [users] = await connection.query<RowDataPacket[]>('SELECT id FROM users WHERE email=?', [record.email]);
    userId = Number(users[0].id);
    await connection.query('UPDATE login_passcodes SET consumed_at=NOW() WHERE id=?', [record.id]);
    await connection.commit();
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
  if (userId === null) { response.status(500).send('Sign-in could not be completed.'); return; }
  await startSession(response, userId);
  response.redirect('/');
});

/** Keeps old bookmarks and forms on the new passcode request route. */
app.post('/login', (request, response) => { response.redirect(307, '/auth/passcode/request'); });

/** Keeps the former registration route compatible with passwordless onboarding. */
app.post('/register', (request, response) => { response.redirect(307, '/auth/passcode/request'); });

/** Deletes the active server-side session and clears its browser cookie. */
app.get('/logout', async (request, response) => { const id = cookie(request, 'job_tune_session'); if (id) await pool.query('DELETE FROM sessions WHERE id=?', [id]); response.clearCookie('job_tune_session'); response.redirect('/login'); });

/** Extracts and stores a DOCX CV as a new user-scoped version. */
app.post('/cvs', requireUser, upload.single('cv'), async (request, response) => { if (!request.file) return response.status(400).send('Please upload a .docx CV.'); const text = (await mammoth.extractRawText({ buffer: request.file.buffer })).value.trim(); if (!text) return response.status(400).send('This Word file did not contain readable text.'); const user = response.locals.user as User; const [versions] = await pool.query<RowDataPacket[]>('SELECT COALESCE(MAX(version_number),0)+1 AS nextVersion FROM cv_documents WHERE user_id=?', [user.id]); await pool.query('INSERT INTO cv_documents (user_id,original_filename,mime_type,original_docx,extracted_text,version_number) VALUES (?,?,?,?,?,?)', [user.id, request.file.originalname, request.file.mimetype || 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', request.file.buffer, text, versions[0].nextVersion]); response.redirect('/'); });

/** Creates a separately stored tailored output from the user's selected original CV. */
app.post('/tailor', requireUser, async (request, response) => { const user = response.locals.user as User; const [cvs] = await pool.query<Cv[]>('SELECT * FROM cv_documents WHERE id=? AND user_id=?', [Number(request.body.cvId), user.id]); const jobDescription = String(request.body.jobDescription || '').trim(); if (!cvs.length || jobDescription.length < 30) return response.status(400).send('Choose your CV and provide a fuller job description.'); try { const draft = await tailorWithAi(cvs[0].extracted_text, jobDescription); const [result] = await pool.query<ResultSetHeader>('INSERT INTO tailored_cvs (user_id,source_cv_id,job_description,tailored_text,change_summary,generation_mode) VALUES (?,?,?,?,?,?)', [user.id, cvs[0].id, jobDescription, draft.tailoredText, draft.summary, draft.mode]); response.redirect(`/tailored/${result.insertId}`); } catch (error) { response.status(502).send(`Tailoring failed: ${escapeHtml((error as Error).message)}`); } });

/** Reads a tailored CV only when its owner is the signed-in user. */
async function outputForUser(id: number, userId: number): Promise<Tailored | null> { const [rows] = await pool.query<Tailored[]>('SELECT t.*,c.original_filename FROM tailored_cvs t JOIN cv_documents c ON c.id=t.source_cv_id WHERE t.id=? AND t.user_id=?', [id, userId]); return rows[0] || null; }

/** Displays the factual-review summary and download actions for one tailored output. */
app.get('/tailored/:id', requireUser, async (request, response) => { const output = await outputForUser(Number(request.params.id), (response.locals.user as User).id); if (!output) return response.sendStatus(404); response.send(page('Review tailored CV', `<a class="text-cyan-300" href="/">← Workspace</a><div class="mt-6 grid gap-7 lg:grid-cols-2"><section class="rounded-2xl bg-slate-900 p-6"><p class="text-sm text-cyan-300">Change summary · ${escapeHtml(output.generation_mode)}</p><h1 class="mt-2 text-3xl font-bold">Review before you submit</h1><p class="mt-5 whitespace-pre-wrap text-slate-200">${escapeHtml(output.change_summary)}</p><div class="mt-6 flex gap-3"><a class="rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950" href="/tailored/${output.id}/download.docx">Download Word</a><a class="rounded border border-slate-600 px-4 py-2" href="/tailored/${output.id}/download.pdf">Download PDF</a></div></section><section class="rounded-2xl bg-white p-6 text-slate-900"><h2 class="text-xl font-bold">Tailored CV preview</h2><pre class="mt-4 whitespace-pre-wrap font-sans text-sm leading-6">${escapeHtml(output.tailored_text)}</pre></section></div>`, response.locals.user)); });

/** Generates a requested DOCX or PDF only for the output's owning user. */
app.get('/tailored/:id/download.:format', requireUser, async (request, response) => { const format = String(request.params.format); const output = await outputForUser(Number(request.params.id), (response.locals.user as User).id); if (!output || !['docx', 'pdf'].includes(format)) return response.sendStatus(404); const safeName = 'job-tune-tailored-cv'; if (format === 'docx') { response.type('application/vnd.openxmlformats-officedocument.wordprocessingml.document').attachment(`${safeName}.docx`).send(await wordBuffer(output.tailored_text)); return; } response.type('application/pdf').attachment(`${safeName}.pdf`).send(await pdfBuffer(output.tailored_text)); });

/** Sends concise operational errors without exposing internal details or personal input. */
app.use((error: Error, _request: Request, response: Response, _next: NextFunction) => { console.error(error.message); response.status(400).send('The request could not be processed. Check the file and try again.'); });

/** Starts the HTTP service behind Apache once this module is executed directly. */
function start(): void { app.listen(Number(process.env.PORT || 3000), () => console.log(`Job Tune listening on port ${process.env.PORT || 3000}`)); }

start();
