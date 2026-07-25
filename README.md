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

## Rebuild guardrails

- Keep every user-owned resource scoped by `user_id` and enforce that scope in queries.
- Require authentication before upload, tailoring, review, or download.
- Treat the source CV as the factual authority; surface every material change for review.
- Keep credentials in Apache/environment configuration, never in Git.
