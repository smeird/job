# Job Tune

Fresh TypeScript/MySQL rebuild foundation. Git history and the existing `origin` remote are intentionally retained.

## MVP

An authenticated user uploads a Word (`.docx`) CV and pastes a job description copied from a website. Job Tune produces an AI-assisted tailored CV that may reorganise or rephrase source material but must not invent experience. The user reviews a clear change summary and downloads a submission-ready PDF and editable Word file.

## What works now

- Phishing-resistant WebAuthn passkeys and server-side MySQL sessions.
- User-isolated, versioned `.docx` CV uploads; the original file and extracted text are retained separately.
- Job-description paste, factual AI tailoring, a clear review summary, and separate stored tailored outputs.
- Immutable re-tailoring revisions with selectable models, balanced/management/technical/impact emphasis, professional tone controls, optional presentation guidance, and side-by-side comparison.
- Editable DOCX and PDF downloads generated from each output using one shared, printer-friendly professional layout with matching hierarchy, typography, spacing, bullets, contact details, and restrained page footers.
- A mobile-ready job application tracker with status, submission age, explicit follow-up dates, job links, notes, and linked source/tailored documents.
- A concise, status-coloured application timeline whose entries open dedicated detail pages, plus cautious response guidance and a dashboard attention queue.
- A user-scoped career evidence database with roles, categorised facts, CV provenance, missing-detail questions, and manual confirmation controls.
- A whole-career CV builder that selects pertinent facts across roles and retains the exact evidence snapshot used for every generated pack.
- A reusable document library, generated cover letters, contact profile, selectable AI models with account-aware catalogue refresh, and optional Word-document email delivery.
- Searchable document management with custom names, recoverable trash, restore, and guarded permanent deletion.
- A safe no-key local fallback: it preserves the original CV verbatim, identifies matching terms already in it, and labels the output as `local_fallback`.

The AI prompt explicitly treats the uploaded CV as the only factual authority. Review the generated change summary before use; automated text generation cannot replace user review.

An existing application pack can be re-tailored from its review page, the document library, or a linked tracked application. Every rerun starts again from the original uploaded CV and stored job description—not from an AI-edited draft—then creates a separate retained revision. The selected model, emphasis, tone, optional guidance, change summary, CV, and cover letter are stored with that revision. Starting from a tracked application also offers to update only that application's document link after the new revision succeeds. Revision history supports side-by-side model and presentation comparison without overwriting earlier files.

The application timeline calculates whole calendar days from the recorded submission date. An optional next follow-up date creates a due or overdue reminder; when no date is set, an unanswered application in the `Applied` state joins the attention queue after 14 days. Longer-wait guidance is deliberately phrased as a decision prompt, not a prediction about an employer. Closed applications remain visible but do not generate reminders.

Career evidence is segregated by user and organised into roles, factual excerpts, user-confirmed additions, and optional gap questions. CV extraction uses a strict structured response, but the server still independently rejects any imported role block or fact excerpt that cannot be found in the original stored CV text. Imported facts retain their source document name and excerpt. Editing an imported fact deliberately converts it into a user-confirmed statement. Questions are prompts only and never become evidence until the user supplies and saves an answer.

The whole-career builder requires an active master CV as a document-lineage anchor, then builds its factual source from all active career evidence rather than that one file. It saves an immutable `career_snapshot` with the resulting application pack. Re-tailoring a career-built pack uses that same stored snapshot, so later career edits do not silently change an existing revision or undermine model comparisons.

Generated CVs and cover letters use structured Word and PDF renderers rather than plain-text export. Both formats share the same semantic layout rules. Profile and account contact details appear only when already stored for that user; the renderer does not invent missing details. Run `npm run documents:samples` to create non-personal QA fixtures under ignored `tmp/docs/`.

Document deletion is recoverable by default: **Move to trash** hides an item without removing its stored file or links. Permanent deletion is available only from Trash and requires typing `DELETE`. A master CV cannot be permanently deleted while any tailored application pack still depends on it.

## Local verification

```bash
cp .env.example .env
# Create the job_tune MySQL database and an account with access to it first.
npm install
npm run db:migrate
npm run dev
```

Open `http://localhost:3000`, create an account, and upload a `.docx` CV. Use the same hostname configured in `WEBAUTHN_RP_ID`; `127.0.0.1` and `localhost` are different passkey identities. `npm run check` runs the TypeScript type checker and `npm run build` emits `dist/`.

## Passkey sign-in

Set `AUTH_SECRET` to a random value containing at least 32 characters. In production, set `WEBAUTHN_RP_ID` to the public hostname (for example `job.smeird.com`) and `WEBAUTHN_ORIGIN` to the exact HTTPS origin (for example `https://job.smeird.com`). These values bind every registration and authentication signature to this site.

New users create a discoverable passkey after entering their email. Returning users sign in without typing an email. User verification is required, so authenticators use Face ID, Touch ID, Windows Hello, a device PIN, or a security key. Signed-in users can add another passkey at `/passkeys`; keeping a second passkey is strongly recommended because there is no insecure password fallback.

Passkeys require HTTPS on public deployments. `localhost` is supported for local development; passkeys created for localhost are separate from those created for the production domain.

When upgrading an account created before passkeys, keep its existing session open, deploy the migration, visit `/passkeys`, and register a credential before signing out. An existing account without either an active session or a registered passkey requires an administrator-assisted recovery; the public registration endpoint will not allow someone to claim an existing email address.

## AI configuration

`OPENAI_API_KEY` is optional. `OPENAI_MODEL` is the default and `OPENAI_MODELS` is the comma-separated fallback list shown before a provider catalogue has been checked. The example starts with `gpt-5-mini`, `gpt-5-nano`, and `gpt-4.1-mini` so users can choose a cost/quality trade-off.

With a key configured, a signed-in user can open **Settings → Check for newer models**. Job Tune asks OpenAI which models are available to that API project, keeps compatible text-generation aliases, and caches only model identifiers and timestamps in the site-wide settings table. It never sends the key to the browser or stores it in MySQL. Newly discovered models are added to Settings, CV tailoring, career extraction, and whole-career tailoring selectors; audio, image, embedding, realtime, dated snapshot, and other incompatible model types are excluded. The previous valid catalogue stays active if a refresh fails.

`OPENAI_MODEL_DISCOVERY` defaults to `true`; set it to `false` to disable the check. `OPENAI_MODEL_REFRESH_MINUTES` controls the shared refresh cooldown and defaults to five minutes. Tailoring and career extraction use the OpenAI Responses API with strict structured output. Model availability and pricing can differ, so review OpenAI pricing before selecting a newly discovered model. When no API key is present, manual career editing and every other non-AI feature work; single-CV tailoring uses the clearly labelled unchanged-source fallback, while extraction and whole-career generation remain visibly disabled because a raw evidence snapshot would not be a submission-ready fallback.

## Document email

Set `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASSWORD`, and `SMTP_FROM` to allow a signed-in user to send a tailored CV and cover letter as Word attachments. Delivery is capped at 20 messages per user per day and the database records only the recipient, selected document flags, and time—not the email body or attachment content.

## Apache deployment

Use [deploy/apache/job-tune.conf.example](deploy/apache/job-tune.conf.example) as a starting point. It assumes Apache reverse-proxies to a separately supervised Node process running `npm run start`. Set real database and session values in protected Apache configuration; never commit them. Enable Apache's `proxy`, `proxy_http`, and `headers` modules, terminate TLS in the production virtual host, and set `X-Forwarded-Proto` to `https` there.

## Post-deploy update

After `git pull --ff-only`, run this from the repository checkout:

```bash
npm run deploy:production
```

It uses `npm ci` to install exactly the dependency versions in `package-lock.json`, builds the local Tailwind stylesheet, type-checks and compiles the TypeScript application, then runs the idempotent SQL migration files. It does not print `.env` or Apache environment values, and the deployed interface does not depend on Tailwind's browser CDN.

The repository deliberately does not assume a Node service or Apache unit name. After the script finishes, restart the Node process with the server's existing supervisor. To include known restart operations in the same command without adding their names to the repository, set commands in the shell running the deploy:

```bash
JOB_TUNE_RESTART_COMMAND='sudo systemctl restart your-node-service' \
JOB_TUNE_APACHE_RELOAD_COMMAND='sudo systemctl reload your-apache-service' \
npm run deploy:production
```

Only set the Apache command when the virtual-host configuration changed; ordinary application releases only need the Node process restarted. Preview project commands without changing dependencies, the database, or services with `npm run deploy:dry-run`.

## Rebuild guardrails

- Keep every user-owned resource scoped by `user_id` and enforce that scope in queries.
- Require authentication before upload, tailoring, review, or download.
- Treat the source CV as the factual authority; surface every material change for review.
- Keep credentials in Apache/environment configuration, never in Git.
