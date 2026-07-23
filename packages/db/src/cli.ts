import { resolve } from "node:path";
import { sql } from "kysely";
import { createDatabase } from "./database.js";
import { loadDatabaseConfig } from "./config.js";
import { migrateToLatest, rollbackLatest } from "./migrations/index.js";
import { JobsRepository } from "./repositories/jobs.js";
import { SettingsRepository } from "./repositories/settings.js";
import { verifyDatabaseSchema } from "./schema-verifier.js";

/** Load the repository environment for local CLI use while allowing service variables to take precedence. */
function loadLocalEnvironment(): void {
  try {
    process.loadEnvFile(resolve(import.meta.dirname, "../../..", ".env"));
  } catch {
    // CI and production provide variables through the process environment.
  }
}

/** Capture non-mutating schema, row-count, and stored-file evidence for cutover comparison. */
async function characterize(database: ReturnType<typeof createDatabase>): Promise<unknown> {
  const schema = await sql<{ column_name: string; column_type: string; is_nullable: string; table_name: string }>`
    SELECT table_name AS table_name, column_name AS column_name,
           column_type AS column_type, is_nullable AS is_nullable
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    ORDER BY table_name, ordinal_position
  `.execute(database);
  const tables = [...new Set(schema.rows.map((row) => row.table_name))];
  const rowCounts: Record<string, number> = {};
  for (const table of tables) {
    if (!/^[a-z0-9_]+$/.test(table)) { continue; }
    const count = await sql<{ count: number }>`SELECT COUNT(*) AS count FROM ${sql.table(table)}`.execute(database);
    rowCounts[table] = Number(count.rows[0]?.count ?? 0);
  }
  const documents = await sql<{ actual_sha256: string; id: string; size_bytes: string; stored_sha256: string }>`
    SELECT CAST(id AS CHAR) AS id, CAST(size_bytes AS CHAR) AS size_bytes, sha256 AS stored_sha256,
           LOWER(SHA2(content, 256)) AS actual_sha256
    FROM documents ORDER BY id
  `.execute(database);
  return {
    captured_at: new Date().toISOString(),
    document_checksums: documents.rows,
    row_counts: rowCounts,
    schema: schema.rows,
  };
}

/** Apply the configured retention policy through a fixed table allowlist. */
async function purge(database: ReturnType<typeof createDatabase>): Promise<Record<string, number>> {
  const policy = await new SettingsRepository(database).getRetentionSettings();
  if (policy === null) {
    return {};
  }
  if (policy.purgeAfterDays < 1 || policy.applyTo.length === 0) {
    return {};
  }
  const cutoff = new Date(Date.now() - policy.purgeAfterDays * 86_400_000);
  const removed: Record<string, number> = {};
  for (const resource of policy.applyTo) {
    let count = 0;
    if (resource === "documents") { count = Number((await database.deleteFrom("documents").where("created_at", "<", cutoff).executeTakeFirst()).numDeletedRows); }
    if (resource === "generation_outputs") { count = Number((await database.deleteFrom("generation_outputs").where("created_at", "<", cutoff).executeTakeFirst()).numDeletedRows); }
    if (resource === "api_usage") { count = Number((await database.deleteFrom("api_usage").where("created_at", "<", cutoff).executeTakeFirst()).numDeletedRows); }
    if (resource === "audit_logs") { count = Number((await database.deleteFrom("audit_logs").where("created_at", "<", cutoff).executeTakeFirst()).numDeletedRows); }
    if (["documents", "generation_outputs", "api_usage", "audit_logs"].includes(resource)) { removed[resource] = count; }
  }
  return removed;
}

/** Run the explicit migration command without creating or changing schema during web requests. */
async function main(): Promise<void> {
  loadLocalEnvironment();
  const command = process.argv[2];
  const database = createDatabase(loadDatabaseConfig());

  try {
    if (command === "migrate") {
      const applied = await migrateToLatest(database);
      process.stdout.write(applied.length === 0 ? "Migrations already current.\n" : `Applied: ${applied.join(", ")}\n`);
      return;
    }

    if (command === "rollback") {
      const rolledBack = await rollbackLatest(database);
      process.stdout.write(rolledBack === null ? "No migration to roll back.\n" : `Rolled back: ${rolledBack}\n`);
      return;
    }

    if (command === "verify") {
      const verification = await verifyDatabaseSchema(database);
      process.stdout.write(`${JSON.stringify(verification, null, 2)}\n`);
      if (!verification.ok) { process.exitCode = 1; }
      return;
    }

    if (command === "characterize") {
      process.stdout.write(`${JSON.stringify(await characterize(database), null, 2)}\n`);
      return;
    }

    if (command === "queue-status") {
      const jobs = new JobsRepository(database);
      process.stdout.write(`${JSON.stringify({ php: await jobs.countPending("php"), typescript: await jobs.countPending("typescript") }, null, 2)}\n`);
      return;
    }

    if (command === "purge") {
      process.stdout.write(`${JSON.stringify({ purged: await purge(database) }, null, 2)}\n`);
      return;
    }

    throw new Error("Usage: db CLI command must be migrate, rollback, verify, characterize, queue-status, or purge.");
  } finally {
    await database.destroy();
  }
}

await main();
