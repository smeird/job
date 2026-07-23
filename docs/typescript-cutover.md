# TypeScript migration and cutover runbook

The Next.js web process and TypeScript worker are designed to run alongside PHP until the final observation gate. MySQL remains the system of record throughout.

## Build and deploy dormant services

1. Install Node.js 24 LTS and run `npm ci` followed by `npm run build`.
2. Run `npm run db:characterize > before-cutover.json`, then `npm run db:migrate` and `npm run db:verify`.
3. Install `deploy/systemd/job-web.service` and `deploy/systemd/job-worker.service`; keep both bound to localhost.
4. Include `deploy/apache/job-typescript-phased.conf` and confirm `/__ts/healthz` through Apache.
5. Verify a PHP-created `job_session` cookie authenticates against a TypeScript page before enabling authenticated routes.

`npm run audit:dependencies` gates critical production findings. At the time of this migration, the latest stable Next.js 16 release still pins PostCSS and Sharp versions reported by npm as high severity, and npm's proposed automatic fix is an invalid downgrade to Next.js 9. Recheck these upstream advisories before every release and upgrade Next.js as soon as a patched stable 16.x release is available.

## Phased route enablement

Uncomment proxy groups in this order: low-risk settings and analytics, documents, applications, tailoring/generations, then authentication. After each group, run the matching Playwright checks and compare status codes, JSON fields, cookies, headers, and downloads against the captured baseline.

New TypeScript generations enter `runtime_queue='typescript'`; the PHP worker is restricted to `runtime_queue='php'`. Both workers use `FOR UPDATE SKIP LOCKED`.

## Final cutover gate

1. Run `npm run db:queue-status`. The `php` count must be zero.
2. Repeat `npm run db:characterize > pre-final-cutover.json` and compare row counts plus every document checksum with the earlier snapshot.
3. Replace the phased Apache rules with `deploy/apache/job-typescript-cutover.conf`, reload Apache, and stop the PHP worker.
4. Monitor authentication, error rates, queue age, SSE completions, OpenAI usage, and DOCX/PDF downloads for seven days.
5. Tag the release before removal. Only after the observation window should PHP, Composer, `vendor/`, PHP service units, and Apache PHP handling be removed.

Rollback during observation means restoring the prior Apache config and restarting the PHP worker. Do not roll back the additive `runtime_queue` column while either worker may still run.
