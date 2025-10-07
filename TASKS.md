# Remediation Tasks

## 1. Fix missing imports and DI registrations
- [x] Update `public/index.php` to import and register `UsageController`, `GenerationDownloadService`, and `GenerationTokenService` with fully-qualified class names.
- [x] Ensure any other controllers/services referenced in the container have the correct namespace imports.

## 2. Repair route definitions
- [x] Add the missing `use App\\Controllers\\UsageController;` statement (or fully-qualified reference) in the router so `/usage` routes resolve to the correct class.
- [x] Register routes for `GenerationDownloadController` and `RetentionController` so implemented features are reachable.

## 3. Align database migrations with runtime schema
- [x] Extend the runtime `Migrator` to create the `api_usage` table (and any other tables required by analytics/services).
- [x] Reconcile `database/migrations/20240326000000_initial.php` with the runtime schema so both migration paths create identical structures.

## 4. Restore cross-database compatibility
- [x] Replace MySQL-specific SQL (e.g., `DATE_FORMAT` in `UsageService::fetchMonthlySummary()`) with portable logic that works in both MySQL and SQLite.

## 5. Surface dormant features
- [x] Audit controllers and views for registration gaps (e.g., retention view) and add missing routes or remove dead code if obsolete.

## 6. Review documentation
- [x] Update installation/setup documentation to reflect the unified migration approach and any new dependencies introduced while fixing the above issues.

## 7. Implement user-driven account deletion
- [ ] Build an `AccountDeletionService` that purges user data (pending passcodes, audit logs, background jobs, and the user record) within a transaction and emits a non-PII audit trail entry.
- [ ] Add HTTP wiring for the delete flow, including a dedicated controller action that terminates the active session, calls the deletion service, expires authentication cookies, and redirects to a confirmation screen.
- [ ] Extend the dashboard UI with a confirmation dialog and form that posts to the new endpoint using existing CSRF protections and accessible destructive-action styling.
- [ ] Introduce automated verification that seeding test data and invoking the service wipes all related tables, and document manual QA steps for end-to-end validation.
