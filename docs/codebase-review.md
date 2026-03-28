# Codebase Review — Job Hunting Management Goal

_Date:_ 2026-03-28

## Executive summary

The codebase **substantially delivers** on the core goal of a multi-user job hunting management website. It supports account creation/login, per-user job tracking, document ingestion, AI-assisted tailoring, and usage visibility. The architecture is coherent and mostly aligned with the stated product requirements.

The largest gaps are around schema portability and strict requirement conformance (notably Composer platform pinning and SQL migration parity for `site_settings`).

## Requirement-by-requirement assessment

### 1) Local MySQL database via environment variables

**Status:** ✅ Mostly met

- Database config is resolved from environment variables (`DB_DSN` or `DB_DRIVER`/`DB_HOST`/`DB_PORT`/etc.).
- MySQL is the default driver path.

### 2) Dedicated site-wide settings storage in schema

**Status:** ⚠️ Partially met

- Runtime migrator creates a `site_settings` table.
- `SiteSettingsRepository` reads from `site_settings`.
- **Gap:** SQL migration files under `database/migrations/` do not currently create `site_settings`, so deployments relying exclusively on file migrations can drift from runtime expectations.

### 3) Multi-user segregation from outset

**Status:** ✅ Met

- Core domain tables include `user_id` relations (`documents`, `generations`, `job_applications`, `api_usage`, `sessions`, etc.).
- Repositories use user-scoped queries for reads/writes in important paths.

### 4) User authentication required

**Status:** ✅ Mostly met

- Authentication is implemented with passcode/TOTP workflows, sessions, and backup codes.
- Most protected controllers redirect unauthenticated users to `/auth/login`.
- Public routes remain intentionally available (landing page/auth flow/health), which is reasonable for this class of application.

### 5) Tailwind CSS used for look-and-feel

**Status:** ✅ Met

- Layout includes Tailwind CDN and custom Tailwind configuration.
- Authenticated UI is clearly styled with Tailwind utility classes.

### 6) Tabulator for interactive/data tables

**Status:** ✅ Met (with caveat)

- Usage analytics page includes Tabulator assets and instantiates a Tabulator table.
- Caveat: `theme.js` contains a lightweight `Tabulator` class shim. This is workable, but could confuse maintenance when mixed with CDN Tabulator usage.

### 7) Highcharts for graphs/charts

**Status:** ✅ Met

- Usage page loads Highcharts and renders token and cost charts.

### 8) Modern aesthetic with welcoming hero after login screen

**Status:** ⚠️ Partially met

- There is a polished, modern hero section on the public landing page.
- After login, the dashboard has a welcome panel and modern styling, but not a distinct post-login hero component in the same sense as the landing page hero.

### 9) Inline documentation comments for every function/method in new/modified code

**Status:** ⚠️ Mostly met, inconsistent

- A large proportion of PHP functions/methods are documented.
- Some JavaScript functions are documented thoroughly, but not universally across all JS files.
- Comment quality is also repetitive and sometimes generic (“Handle the X operation”), which meets form but not always intent.

### 10) PHP 7.4 compatibility and Composer platform requirement

**Status:** ⚠️ Partially met

- Code targets PHP 7.4 and includes polyfills for newer string helpers.
- **Gap:** `composer.json` requires `"php": "^7.4"` but does not set `config.platform.php` to lock dependency resolution to 7.4.

## Product-goal fit (job hunting management website)

## What is strong

- Job application lifecycle is well represented (`outstanding`, `applied`, `interviewing`, `contracting`, `failed`).
- Multi-user ownership boundaries are visible across repositories and tables.
- Dashboard and tracker UX are modern and coherent.
- Supporting capabilities (document ingestion, AI tailoring, usage telemetry, retention controls) are integrated rather than bolt-ons.

## What is missing or weak relative to the goal

- SQL migration parity issue (`site_settings`) can break predictable deployment behavior.
- No obvious authorization middleware centralization; controller-level auth checks are repetitive and increase maintenance risk.
- Requirement compliance around “post-login hero” is arguable rather than explicit.

## Priority recommendations

1. **Add a SQL migration for `site_settings` immediately** to align CLI migrations with runtime migrator behavior.
2. **Set Composer platform pin** (`config.platform.php: 7.4.x`) to prevent accidental dependency upgrades incompatible with production PHP.
3. **Centralize route authorization** (middleware or route groups) to reduce repeated controller checks.
4. **Decide and codify hero requirement interpretation** (public-only hero vs post-login hero) and adjust dashboard UI if needed.
5. **Improve inline docs quality standards** so comments explain intent/constraints, not just restate method names.

## Overall verdict

The application is **close to the intended job hunting management product** and already production-oriented in many areas. With the schema/migration and PHP platform pinning fixes, it would be materially stronger and more predictable to deploy and maintain.
