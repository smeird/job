import { sql, type Kysely } from "kysely";
import type { DatabaseSchema } from "./types.js";

const requiredSchema: Record<string, readonly string[]> = {
  api_usage: ["id", "user_id", "provider", "endpoint", "tokens_used", "cost_pence", "metadata", "created_at"],
  audit_logs: ["id", "user_id", "ip_address", "action", "created_at"],
  documents: ["id", "user_id", "document_type", "filename", "mime_type", "size_bytes", "sha256", "content"],
  generation_outputs: ["id", "generation_id", "artifact", "mime_type", "content", "output_text"],
  generations: ["id", "user_id", "job_document_id", "cv_document_id", "model", "status", "progress_percent"],
  job_applications: ["id", "user_id", "description", "status", "reason_code", "generation_id"],
  jobs: ["id", "type", "payload_json", "runtime_queue", "run_after", "attempts", "status"],
  sessions: ["id", "user_id", "token_hash", "expires_at"],
  site_settings: ["name", "value"],
  users: ["id", "email", "totp_secret"],
};

export interface SchemaVerification {
  missingColumns: string[];
  missingTables: string[];
  ok: boolean;
}

/** Verify required production tables and columns without mutating schema during a web request. */
export async function verifyDatabaseSchema(database: Kysely<DatabaseSchema>): Promise<SchemaVerification> {
  const rows = await sql<{ column_name: string; table_name: string }>`
    SELECT table_name AS table_name, column_name AS column_name
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
  `.execute(database);
  const columns = new Map<string, Set<string>>();
  for (const row of rows.rows) {
    const set = columns.get(row.table_name) ?? new Set<string>();
    set.add(row.column_name);
    columns.set(row.table_name, set);
  }
  const missingTables: string[] = [];
  const missingColumns: string[] = [];
  for (const [table, requiredColumns] of Object.entries(requiredSchema)) {
    const actual = columns.get(table);
    if (actual === undefined) {
      missingTables.push(table);
      continue;
    }
    for (const column of requiredColumns) {
      if (!actual.has(column)) { missingColumns.push(`${table}.${column}`); }
    }
  }
  return { missingColumns, missingTables, ok: missingTables.length === 0 && missingColumns.length === 0 };
}
