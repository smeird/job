import { Kysely, MysqlDialect } from "kysely";
import mysql from "mysql2";
import type { Pool } from "mysql2";
import type { DatabaseConfig } from "./config.js";
import type { DatabaseSchema } from "./types.js";

let sharedDatabase: Kysely<DatabaseSchema> | undefined;
let sharedPool: Pool | undefined;

/** Create a Kysely database backed by a mysql2 pool with lossless BIGINT transport. */
export function createDatabase(config: DatabaseConfig): Kysely<DatabaseSchema> {
  const pool = mysql.createPool({
    charset: "utf8mb4",
    connectionLimit: 10,
    database: config.database,
    ...(config.host === undefined ? {} : { host: config.host }),
    password: config.password,
    ...(config.port === undefined ? {} : { port: config.port }),
    ...(config.socketPath === undefined ? {} : { socketPath: config.socketPath }),
    supportBigNumbers: true,
    bigNumberStrings: true,
    user: config.user,
  });

  return new Kysely<DatabaseSchema>({ dialect: new MysqlDialect({ pool }) });
}

/** Return a process-wide database pool so Next.js development reloads do not create unbounded connections. */
export function getDatabase(config: DatabaseConfig): Kysely<DatabaseSchema> {
  if (sharedDatabase !== undefined) {
    return sharedDatabase;
  }

  sharedPool = mysql.createPool({
    charset: "utf8mb4",
    connectionLimit: 10,
    database: config.database,
    ...(config.host === undefined ? {} : { host: config.host }),
    password: config.password,
    ...(config.port === undefined ? {} : { port: config.port }),
    ...(config.socketPath === undefined ? {} : { socketPath: config.socketPath }),
    supportBigNumbers: true,
    bigNumberStrings: true,
    user: config.user,
  });
  sharedDatabase = new Kysely<DatabaseSchema>({ dialect: new MysqlDialect({ pool: sharedPool }) });

  return sharedDatabase;
}

/** Close the process-wide database pool during worker shutdown and integration-test teardown. */
export async function destroyDatabase(): Promise<void> {
  if (sharedDatabase !== undefined) {
    await sharedDatabase.destroy();
  }

  sharedDatabase = undefined;
  sharedPool = undefined;
}
