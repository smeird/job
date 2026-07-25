import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { generateAuthenticationOptions, generateRegistrationOptions, verifyAuthenticationResponse, verifyRegistrationResponse, type AuthenticationResponseJSON, type AuthenticatorTransportFuture, type RegistrationResponseJSON } from '@simplewebauthn/server';
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
app.set('trust proxy', 'loopback');
app.use(express.urlencoded({ extended: false }));
app.use(express.json());

type User = { id: number; email: string };
type Cv = RowDataPacket & { id: number; original_filename: string; extracted_text: string; version_number: number };
type Tailored = RowDataPacket & { id: number; tailored_text: string; change_summary: string; generation_mode: string; job_description: string; original_filename: string; created_at: Date };
type WebAuthnChallenge = RowDataPacket & { id: number; ceremony: 'registration' | 'authentication'; challenge: string; email: string | null; user_id: number | null; user_handle: Buffer | null };
type PasskeyCredential = RowDataPacket & { credential_id: string; user_id: number; credential_public_key: Buffer; counter: number; transports: string | null };

/** Escapes untrusted strings before placing them in server-rendered HTML. */
function escapeHtml(value: string): string { return value.replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character] || character)); }

/** Reads a named cookie without bringing a client-session dependency into the application. */
function cookie(request: Request, name: string): string | undefined { return request.headers.cookie?.split(';').map((part) => part.trim()).find((part) => part.startsWith(`${name}=`))?.slice(name.length + 1); }

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
}

/** Renders the shared Tailwind-based page shell. */
function page(title: string, body: string, user?: User): string { return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><title>${escapeHtml(title)} · Job Tune</title></head><body class="min-h-screen bg-slate-950 text-slate-100"><main class="mx-auto max-w-6xl px-5 py-8"><nav class="mb-12 flex items-center justify-between"><a href="/" class="text-xl font-bold tracking-tight text-cyan-300">Job Tune</a>${user ? `<span class="text-sm text-slate-300">${escapeHtml(user.email)} · <a class="text-cyan-300" href="/passkeys">Passkeys</a> · <a class="text-cyan-300" href="/logout">Log out</a></span>` : ''}</nav>${body}</main></body></html>`; }

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

/** Serves the pinned browser helper locally instead of loading authentication code from a CDN. */
app.get('/assets/simplewebauthn.js', (_request, response) => response.type('application/javascript').send(fs.readFileSync(path.resolve(__dirname, '..', 'node_modules', '@simplewebauthn', 'browser', 'dist', 'bundle', 'index.umd.min.js'))));

/** Serves the application-owned browser orchestration for registration and sign-in. */
app.get('/assets/passkeys.js', (_request, response) => response.type('application/javascript').send(fs.readFileSync(path.resolve(__dirname, '..', 'public', 'passkeys.js'))));

/** Displays passkey sign-in and secure first-account registration controls. */
app.get('/login', (_request, response) => response.send(page('Sign in', `<div class="mx-auto grid max-w-3xl gap-6 md:grid-cols-2"><section class="rounded-2xl bg-slate-900 p-7"><p class="text-sm font-medium text-cyan-300">Returning user</p><h1 class="mt-2 text-3xl font-bold">Sign in with a passkey</h1><p class="mt-3 text-sm leading-6 text-slate-300">Use Face ID, Touch ID, Windows Hello, your device PIN, or a security key.</p><button id="passkey-sign-in" class="mt-6 w-full rounded bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Use my passkey</button></section><section class="rounded-2xl bg-white p-7 text-slate-900"><p class="text-sm font-medium text-cyan-700">New user</p><h2 class="mt-2 text-2xl font-bold">Create your first passkey</h2><label class="mt-4 block text-sm font-medium">Email address<input id="passkey-email" class="mt-2 w-full rounded border p-3" type="email" autocomplete="email" required></label><button id="passkey-register" class="mt-5 w-full rounded bg-slate-950 px-4 py-3 font-semibold text-white">Create passkey</button></section></div><p id="passkey-status" class="mx-auto mt-4 max-w-3xl text-sm text-slate-400">Passkeys require a supported browser and HTTPS on the public site.</p>${passkeyBrowserScript()}`)));

/** Displays a signed-in page for adding another passkey without risking account takeover. */
app.get('/passkeys', requireUser, (_request, response) => response.send(page('Passkeys', `<div class="mx-auto max-w-xl rounded-2xl bg-slate-900 p-7"><p class="text-sm font-medium text-cyan-300">Account security</p><h1 class="mt-2 text-3xl font-bold">Add another passkey</h1><p class="mt-3 text-slate-300">A second device or hardware security key can prevent account loss.</p><button id="passkey-register" class="mt-6 rounded bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Add passkey</button><p id="passkey-status" class="mt-4 text-sm text-slate-400"></p></div>${passkeyBrowserScript()}`, response.locals.user)));

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
function start(): void { webAuthnConfig(); app.listen(Number(process.env.PORT || 3000), () => console.log(`Job Tune listening on port ${process.env.PORT || 3000}`)); }

start();
