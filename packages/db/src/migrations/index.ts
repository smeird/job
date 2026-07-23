import { sql, type Kysely } from "kysely";
import type { DatabaseSchema } from "../types.js";
import { typescriptRuntimeMigration } from "./20260723010000-typescript-runtime.js";
import type { SharedMigration } from "./types.js";

export const migrations: readonly SharedMigration[] = [typescriptRuntimeMigration];

/** Apply unapplied TypeScript migrations through the existing PHP schema_migrations ledger. */
export async function migrateToLatest(database: Kysely<DatabaseSchema>): Promise<string[]> {
  const appliedRows = await database.selectFrom("schema_migrations").select("migration").execute();
  const applied = new Set(appliedRows.map((row) => row.migration));
  const completed: string[] = [];

  for (const migration of migrations) {
    if (applied.has(migration.id)) {
      continue;
    }

    await migration.up(database);
    await database.insertInto("schema_migrations").values({ migration: migration.id }).executeTakeFirstOrThrow();
    completed.push(migration.id);
  }

  return completed;
}

/** Roll back the most recent migration only when it belongs to the TypeScript migration catalogue. */
export async function rollbackLatest(database: Kysely<DatabaseSchema>): Promise<string | null> {
  const latest = await database
    .selectFrom("schema_migrations")
    .select("migration")
    .orderBy("applied_at", "desc")
    .orderBy("id", "desc")
    .executeTakeFirst();

  if (latest === undefined) {
    return null;
  }

  const migration = migrations.find((candidate) => candidate.id === latest.migration);
  if (migration === undefined) {
    throw new Error(`The latest migration ${latest.migration} is not managed by the TypeScript runtime.`);
  }

  await migration.down(database);
  await sql`DELETE FROM schema_migrations WHERE migration = ${migration.id}`.execute(database);

  return migration.id;
}

export type { SharedMigration } from "./types.js";
