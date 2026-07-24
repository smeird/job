# Job Tune

Job Tune turns a master CV and a job description into an evidence-led, ATS-readable tailored CV and cover letter. The replacement runtime is a strict-TypeScript npm workspace: a self-hosted Next.js 16 web app, a separate Node queue worker, shared Kysely repositories, and native document renderers. The PHP application remains temporarily as a route-by-route rollback target during cutover.

## What it does

- Uploads and validates CVs and job descriptions in DOCX, PDF, Markdown, or text format.
- Builds a requirement-to-evidence plan before drafting, keeping the source CV as the factual authority.
- Produces Markdown, DOCX, and PDF outputs.
- Lets an authenticated user choose separate OpenAI models for analysis and drafting at `/settings/models`.
- Refreshes the selectable GPT model list from `GET /models` and retains a current fallback catalogue when the API is unavailable.
- Records prompt/completion tokens and precise estimated costs for `/usage`; models without a configured tariff are marked as unpriced instead of being reported as free.
- Purges retained documents, outputs, usage rows, and audit data according to the configured policy.

## Requirements

- Node.js 24 LTS and npm 11+
- MySQL 8+
- An OpenAI API key for real generation and remote model refresh

PHP 7.4 and Composer are required only during the coexistence and seven-day observation stages.

SQLite is used only by the isolated smoke test. The web application and CLI migrations should be run against MySQL.

## Local setup

```bash
git clone https://github.com/smeird/job.git
cd job
nvm use
npm ci
cp .env.example .env
# For local work, set APP_ENV=development, APP_URL=http://127.0.0.1:3000,
# and leave APP_COOKIE_DOMAIN empty. Development supplies a local-only signing key.
npm run db:migrate
npm run dev:web
```

The Next.js development server listens on `http://127.0.0.1:3000`.

Run the queue worker in a second process:

```bash
npm run dev:worker
```

Production keeps Apache as the public TLS reverse proxy and runs the web and worker processes under systemd. See [the cutover runbook](docs/typescript-cutover.md).

After merging a production release, deploy it from the Ubuntu checkout with:

```bash
git pull --ff-only origin main # First run only, to obtain the deployment script.
./bin/deploy-production.sh --cutover
```

After that bootstrap pull, subsequent releases need only the script command; the script performs its own pull, verification, build, database backup and migration, systemd installation, Apache cutover, queue gate, and health checks. Run it as the normal deployment user; it requests `sudo` for the individual privileged operations.

## Important configuration

| Variable | Purpose |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Runtime environment and canonical application URL |
| `APP_COOKIE_DOMAIN` | Optional production cookie domain; leave empty for local development |
| `DB_DSN` or `DB_*` variables | MySQL connection details, including optional socket and port |
| `OPENAI_API_KEY` | OpenAI API credential |
| `OPENAI_BASE_URL` | API base URL, normally `https://api.openai.com/v1` |
| `OPENAI_MODEL_PLAN` | Environment fallback for the analysis model |
| `OPENAI_MODEL_DRAFT` | Environment fallback for the drafting model |
| `OPENAI_TARIFF_JSON` | Price map in pence per 1,000 prompt and completion tokens |
| `OPENAI_MAX_TOKENS` | Maximum generated tokens per request |

Model choices saved in `/settings/models` take precedence over the environment model fallbacks. A model selected for an individual tailoring run overrides the saved drafting default for that run only.

Example tariff configuration:

```dotenv
OPENAI_TARIFF_JSON='{"gpt-5.6-sol":{"prompt":0.20,"completion":0.80}}'
```

Keep tariff values current when models or pricing change. Unknown models remain visible in analytics with `Not configured` pricing.

## Generation pipeline

1. A validated job description and master CV are stored as documents.
2. The tailor form queues a `tailor_cv` job with the selected draft model and analysis depth.
3. The worker asks the analysis model for a structured evidence plan.
4. The drafting model receives the full source CV, full job description, and evidence plan.
5. Outputs are stored and exposed as authenticated Markdown, DOCX, and PDF downloads.
6. Each API request records model and token usage for analytics.

The prompt contracts and OpenAI Responses orchestration live in `packages/core`; the queue handler lives in `apps/worker`. Generated Markdown is parsed into a restricted document tree before native DOCX and PDF rendering in `packages/documents`.

## Verification

```bash
npm run lint
npm run typecheck
npm test
npm run build
npm run audit:dependencies
```

MySQL integration tests are deliberately guarded by `RUN_MYSQL_INTEGRATION=true` so they cannot run against a production database accidentally. Playwright tests use `npm run test:e2e` after a disposable test schema is configured.

For a production release, also run one controlled generation with a real API key, download every format, and confirm the resulting API rows on `/usage`.

## Operations

- Apply migrations after each deployment with `npm run db:migrate`.
- Run `npm run db:verify` after migration and `npm run db:queue-status` before final cutover.
- Run the compiled TypeScript worker continuously using the supplied systemd unit.
- Run `npm run retention:purge` daily from a systemd timer or cron.
- Restart the Node web/worker services after environment or code changes; reload Apache only when proxy groups change.
- Keep `.env`, generated documents, and API credentials out of version control.
