# Job Tune

Fresh TypeScript/MySQL rebuild foundation. Git history and the existing `origin` remote are intentionally retained.

## MVP

An authenticated user uploads a Word (`.docx`) CV and pastes a job description copied from a website. Job Tune produces an AI-assisted tailored CV that may reorganise or rephrase source material but must not invent experience. The user reviews a clear change summary and downloads a submission-ready PDF and editable Word file.

## What works now

- Email/password account registration and server-side MySQL sessions.
- User-isolated, versioned `.docx` CV uploads; the original file and extracted text are retained separately.
- Job-description paste, factual AI tailoring, a clear review summary, and separate stored tailored outputs.
- Editable DOCX and PDF downloads generated from each output.
- A safe no-key local fallback: it preserves the original CV verbatim, identifies matching terms already in it, and labels the output as `local_fallback`.

The AI prompt explicitly treats the uploaded CV as the only factual authority. Review the generated change summary before use; automated text generation cannot replace user review.

## Local verification

```bash
cp .env.example .env
# Create the job_tune MySQL database and an account with access to it first.
npm install
npm run db:migrate
npm run dev
```

Open `http://127.0.0.1:3000`, create an account, and upload a `.docx` CV. `npm run check` runs the TypeScript type checker and `npm run build` emits `dist/`.

## AI configuration

`OPENAI_API_KEY` is optional. When it is set, the app calls the OpenAI Chat Completions API using `OPENAI_MODEL` (default `gpt-4.1-mini`). When it is absent, every local feature works and no external request is made; tailoring is intentionally non-transforming so it cannot introduce claims.

## Apache deployment

Use [deploy/apache/job-tune.conf.example](deploy/apache/job-tune.conf.example) as a starting point. It assumes Apache reverse-proxies to a separately supervised Node process running `npm run start`. Set real database and session values in protected Apache configuration; never commit them. Enable Apache's `proxy`, `proxy_http`, and `headers` modules, terminate TLS in the production virtual host, and set `X-Forwarded-Proto` to `https` there.

## Post-deploy update

After `git pull --ff-only`, run this from the repository checkout:

```bash
npm run deploy:production
```

It uses `npm ci` to install exactly the dependency versions in `package-lock.json`, type-checks and compiles the TypeScript application, then runs the idempotent SQL migration files. It does not print `.env` or Apache environment values.

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
