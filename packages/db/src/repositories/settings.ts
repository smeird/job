import type { Kysely } from "kysely";
import { decodeDatabaseJson, encodeDatabaseJson } from "../json.js";
import type { DatabaseSchema } from "../types.js";

export interface RetentionSettings {
  applyTo: string[];
  purgeAfterDays: number;
}

export class SettingsRepository {
  public constructor(private readonly database: Kysely<DatabaseSchema>) {}

  /** Read one site-wide setting from the dedicated settings table. */
  public async get(name: string): Promise<string | null> {
    const row = await this.database.selectFrom("site_settings").select("value").where("name", "=", name).executeTakeFirst();
    return row?.value ?? null;
  }

  /** Read several site-wide settings while retaining missing keys as absent entries. */
  public async getMany(names: readonly string[]): Promise<Record<string, string | null>> {
    if (names.length === 0) {
      return {};
    }

    const rows = await this.database.selectFrom("site_settings").select(["name", "value"]).where("name", "in", [...names]).execute();
    return Object.fromEntries(rows.map((row) => [row.name, row.value]));
  }

  /** Upsert one site-wide setting without changing unrelated values. */
  public async set(name: string, value: string | null): Promise<void> {
    await this.database
      .insertInto("site_settings")
      .values({ name, value })
      .onDuplicateKeyUpdate({ value })
      .executeTakeFirstOrThrow();
  }

  /** Load the singleton retention policy using the same row id as PHP. */
  public async getRetentionSettings(): Promise<RetentionSettings | null> {
    const row = await this.database
      .selectFrom("retention_settings")
      .select(["purge_after_days", "apply_to"])
      .where("id", "=", 1)
      .executeTakeFirst();

    if (row === undefined) {
      return null;
    }

    const decoded = decodeDatabaseJson(row.apply_to);
    const applyTo = Array.isArray(decoded) ? decoded.filter((item): item is string => typeof item === "string") : [];
    return { applyTo, purgeAfterDays: row.purge_after_days };
  }

  /** Upsert the singleton retention policy. */
  public async setRetentionSettings(settings: RetentionSettings): Promise<void> {
    await this.database
      .insertInto("retention_settings")
      .values({ apply_to: encodeDatabaseJson(settings.applyTo), id: 1, purge_after_days: settings.purgeAfterDays })
      .onDuplicateKeyUpdate({ apply_to: encodeDatabaseJson(settings.applyTo), purge_after_days: settings.purgeAfterDays })
      .executeTakeFirstOrThrow();
  }
}
