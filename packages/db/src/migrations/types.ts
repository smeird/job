import type { Kysely } from "kysely";
import type { DatabaseSchema } from "../types.js";

export interface SharedMigration {
  id: string;
  up(database: Kysely<DatabaseSchema>): Promise<void>;
  down(database: Kysely<DatabaseSchema>): Promise<void>;
}
