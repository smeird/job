import crypto from 'node:crypto';
import bcrypt from 'bcryptjs';
import { Document, Packer, Paragraph, TextRun } from 'docx';
import dotenv from 'dotenv';
import express, { NextFunction, Request, Response } from 'express';
import mammoth from 'mammoth';
import multer from 'multer';
import PDFDocument from 'pdfkit';
import { ResultSetHeader, RowDataPacket } from 'mysql2/promise';
import { createDatabasePool } from './db';

dotenv.config();
const app = express();
const pool = createDatabasePool();
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 8 * 1024 * 1024 }, fileFilter: (_req, file, callback) => callback(null, file.originalname.toLowerCase().endsWith('.docx')) });
app.use(express.urlencoded({ extended: false }));
app.use(express.json());

type User = { id: number; email: string };
type Cv = RowDataPacket & { id: number; original_filename: string; extracted_text: string; version_number: number };
type Tailored = RowDataPacket & { id: number; tailored_text: string; change_summary: string; generation_mode: string; job_description: string; original_filename: string; created_at: Date };

/** Escapes untrusted strings before placing them in server-rendered HTML. */
function escapeHtml(value: string): string { return value.replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character] || character)); }

/** Reads a named cookie without bringing a client-session dependency into the application. */
function cookie(request: Request, name: string): string | undefined { return request.headers.cookie?.split(';').map((part) => part.trim()).find((part) => part.startsWith(`${name}=`))?.slice(name.length + 1); }

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

/** Displays login and registration forms for the required account boundary. */
app.get('/login', (_request, response) => response.send(page('Sign in', `<div class="mx-auto grid max-w-2xl gap-6 md:grid-cols-2"><form class="rounded-2xl bg-slate-900 p-6" method="post" action="/login"><h1 class="text-2xl font-bold">Welcome back</h1><input class="mt-4 w-full rounded p-2 text-slate-900" name="email" type="email" placeholder="Email" required><input class="mt-3 w-full rounded p-2 text-slate-900" name="password" type="password" placeholder="Password" required><button class="mt-4 rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">Sign in</button></form><form class="rounded-2xl bg-white p-6 text-slate-900" method="post" action="/register"><h2 class="text-2xl font-bold">Create account</h2><input class="mt-4 w-full rounded border p-2" name="email" type="email" placeholder="Email" required><input class="mt-3 w-full rounded border p-2" name="password" type="password" minlength="10" placeholder="Password (10+ chars)" required><button class="mt-4 rounded bg-slate-950 px-4 py-2 font-semibold text-white">Create account</button></form></div>`)));

/** Registers a user with a bcrypt password hash and immediately starts their session. */
app.post('/register', async (request, response) => { const email = String(request.body.email || '').trim().toLowerCase(); const password = String(request.body.password || ''); if (!/^\S+@\S+\.\S+$/.test(email) || password.length < 10) return response.status(400).send('Use a valid email and password of at least 10 characters.'); try { const [result] = await pool.query<ResultSetHeader>('INSERT INTO users (email,password_hash) VALUES (?,?)', [email, await bcrypt.hash(password, 12)]); await startSession(response, result.insertId); response.redirect('/'); } catch { response.status(409).send('An account with that email already exists.'); } });

/** Authenticates an existing account and begins a server-side session. */
app.post('/login', async (request, response) => { const [rows] = await pool.query<RowDataPacket[]>('SELECT id,email,password_hash FROM users WHERE email=?', [String(request.body.email || '').trim().toLowerCase()]); if (!rows.length || !await bcrypt.compare(String(request.body.password || ''), rows[0].password_hash)) return response.status(401).send('Incorrect email or password.'); await startSession(response, rows[0].id); response.redirect('/'); });

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
