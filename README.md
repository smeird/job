# Job Tune

Job Tune turns a master CV and a job description into an evidence-led, ATS-readable tailored CV and optional cover letter. It is a Slim 4 application designed for PHP 7.4 and MySQL 8+, with queued OpenAI generation, authenticated downloads, usage reporting, and configurable retention.

## What it does

- Uploads and validates CVs and job descriptions in DOCX, PDF, Markdown, or text format.
- Builds a requirement-to-evidence plan before drafting, keeping the source CV as the factual authority.
- Produces Markdown, DOCX, and PDF outputs.
- Lets an authenticated user choose separate OpenAI models for analysis and drafting at `/settings/models`.
- Refreshes the selectable GPT model list from `GET /models` and retains a current fallback catalogue when the API is unavailable.
- Records prompt/completion tokens and precise estimated costs for `/usage`; models without a configured tariff are marked as unpriced instead of being reported as free.
- Purges retained documents, outputs, usage rows, and audit data according to the configured policy.

## Requirements

- PHP 7.4 with `pdo_mysql`, `mbstring`, `zip`, `fileinfo`, and `pcntl`
- MySQL 8+
- Composer
- An OpenAI API key for real generation and remote model refresh

SQLite is used only by the isolated smoke test. The web application and CLI migrations should be run against MySQL.

## Local setup

```bash
git clone https://github.com/smeird/job.git
cd job
composer install
cp .env.example .env
php bin/migrate.php
composer start-dev
```

The development server listens on `http://127.0.0.1:8080`. The built-in router serves static assets directly and sends application requests to Slim.

Run the queue worker in a second process:

```bash
php bin/worker.php
```

Production should point Apache or Nginx at `public/` and run the worker under a supervisor such as systemd.

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

The prompts live in `prompts/`; orchestration is in `src/Queue/Handler/TailorCvJobHandler.php` and `src/AI/OpenAIProvider.php`.

## Verification

```bash
composer test
php bin/smoke.php
composer audit --locked
```

`composer test` covers planning and drafting request construction, model catalogue behaviour, precise usage aggregation, and database schema verification. The smoke test uses an isolated SQLite database and fake AI provider to exercise authentication, ingestion, queued CV/cover-letter generation, downloads, and retention purge without spending API credits.

For a production release, also run one controlled generation with a real API key, download every format, and confirm the resulting API rows on `/usage`.

## Operations

- Apply migrations after each deployment with `php bin/migrate.php`.
- Run `php bin/worker.php` continuously for queued jobs.
- Run `php bin/purge.php` daily from cron.
- Restart PHP-FPM/Apache and queue workers after environment or code changes.
- Keep `.env`, generated documents, and API credentials out of version control.
