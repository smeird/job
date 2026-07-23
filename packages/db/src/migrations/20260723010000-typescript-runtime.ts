import { sql, type Kysely } from "kysely";
import type { DatabaseSchema } from "../types.js";
import type { SharedMigration } from "./types.js";

/** Check whether a column already exists so the migration is safe on databases upgraded by legacy request-time code. */
async function columnExists(database: Kysely<DatabaseSchema>, table: string, column: string): Promise<boolean> {
  const result = await sql<{ count: string | number }>`
    SELECT COUNT(*) AS count
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = ${table} AND column_name = ${column}
  `.execute(database);

  return Number(result.rows[0]?.count ?? 0) > 0;
}

/** Check whether a named index is already present. */
async function indexExists(database: Kysely<DatabaseSchema>, table: string, index: string): Promise<boolean> {
  const result = await sql<{ count: string | number }>`
    SELECT COUNT(*) AS count
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = ${table} AND index_name = ${index}
  `.execute(database);

  return Number(result.rows[0]?.count ?? 0) > 0;
}

/** Check whether a named foreign-key constraint is already present. */
async function constraintExists(database: Kysely<DatabaseSchema>, table: string, constraint: string): Promise<boolean> {
  const result = await sql<{ count: string | number }>`
    SELECT COUNT(*) AS count
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND table_name = ${table} AND constraint_name = ${constraint}
  `.execute(database);

  return Number(result.rows[0]?.count ?? 0) > 0;
}

export const typescriptRuntimeMigration: SharedMigration = {
  id: "20260723010000_typescript_runtime",

  /** Add ledger-backed application fields and isolate PHP jobs from TypeScript jobs. */
  async up(database): Promise<void> {
    if (!(await columnExists(database, "job_applications", "reason_code"))) {
      await sql`ALTER TABLE job_applications ADD COLUMN reason_code VARCHAR(64) NULL AFTER applied_at`.execute(database);
    }

    if (!(await columnExists(database, "job_applications", "generation_id"))) {
      await sql`ALTER TABLE job_applications ADD COLUMN generation_id BIGINT UNSIGNED NULL AFTER reason_code`.execute(database);
    }

    if (!(await indexExists(database, "job_applications", "idx_job_applications_generation"))) {
      await sql`ALTER TABLE job_applications ADD INDEX idx_job_applications_generation (generation_id)`.execute(database);
    }

    if (!(await constraintExists(database, "job_applications", "fk_job_applications_generation"))) {
      await sql`
        ALTER TABLE job_applications
        ADD CONSTRAINT fk_job_applications_generation
        FOREIGN KEY (generation_id) REFERENCES generations(id) ON DELETE SET NULL
      `.execute(database);
    }

    if (!(await columnExists(database, "jobs", "runtime_queue"))) {
      await sql`ALTER TABLE jobs ADD COLUMN runtime_queue VARCHAR(32) NOT NULL DEFAULT 'php' AFTER payload_json`.execute(database);
    }

    await sql`UPDATE jobs SET runtime_queue = 'php' WHERE runtime_queue IS NULL OR runtime_queue = ''`.execute(database);

    if (!(await indexExists(database, "jobs", "idx_jobs_runtime_status_run_after"))) {
      await sql`ALTER TABLE jobs ADD INDEX idx_jobs_runtime_status_run_after (runtime_queue, status, run_after)`.execute(database);
    }
  },

  /** Remove only the runtime-queue additions; legacy application fields remain because earlier PHP code may depend on them. */
  async down(database): Promise<void> {
    if (await indexExists(database, "jobs", "idx_jobs_runtime_status_run_after")) {
      await sql`ALTER TABLE jobs DROP INDEX idx_jobs_runtime_status_run_after`.execute(database);
    }

    if (await columnExists(database, "jobs", "runtime_queue")) {
      await sql`ALTER TABLE jobs DROP COLUMN runtime_queue`.execute(database);
    }
  },
};
