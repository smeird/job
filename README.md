# Job Tune

Fresh TypeScript/MySQL rebuild foundation. Git history and the existing `origin` remote are intentionally retained.

## MVP

An authenticated user uploads a Word (`.docx`) CV and pastes a job description copied from a website. Job Tune produces an AI-assisted tailored CV that may reorganise or rephrase source material but must not invent experience. The user reviews a clear change summary and downloads a submission-ready PDF and editable Word file.

## Foundation included

- TypeScript compiler configuration and an intentionally empty application entry point.
- MySQL initial schema with `users` and a separate `site_settings` table.
- Environment-variable template for Apache-provided MySQL connection details.
- Apache reverse-proxy example for the future TypeScript service.

Tailwind CSS, authentication flows, CV processing, AI integration, and document export are deliberately not implemented yet. They need an agreed application architecture before code is introduced.

## Local verification

```bash
npm install
npm run check
```

## Rebuild guardrails

- Keep every user-owned resource scoped by `user_id` and enforce that scope in queries.
- Require authentication before upload, tailoring, review, or download.
- Treat the source CV as the factual authority; surface every material change for review.
- Keep credentials in Apache/environment configuration, never in Git.
