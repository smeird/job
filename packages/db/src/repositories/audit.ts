import type { Kysely } from "kysely";
import type { DatabaseSchema } from "../types.js";

export interface AuditEvent {
  action: string;
  details?: unknown;
  email?: string | null;
  ipAddress: string;
  userAgent?: string | null;
  userId?: bigint | null;
}

export class AuditRepository {
  public constructor(private readonly database: Kysely<DatabaseSchema>) {}

  /** Store an append-only security or operational audit event. */
  public async record(event: AuditEvent): Promise<void> {
    await this.database
      .insertInto("audit_logs")
      .values({
        action: event.action,
        created_at: new Date(),
        details: event.details === undefined ? null : JSON.stringify(event.details),
        email: event.email ?? null,
        ip_address: event.ipAddress,
        user_agent: event.userAgent ?? null,
        user_id: event.userId ?? null,
      })
      .executeTakeFirstOrThrow();
  }

  /** Count recent matching audit rows for database-backed rate limiting. */
  public async countRecent(input: { action: string; email?: string; ipAddress: string; since: Date }): Promise<number> {
    let query = this.database
      .selectFrom("audit_logs")
      .select((expression) => expression.fn.countAll<number>().as("count"))
      .where("action", "=", input.action)
      .where("ip_address", "=", input.ipAddress)
      .where("created_at", ">=", input.since);

    if (input.email !== undefined) {
      query = query.where("email", "=", input.email);
    }

    const row = await query.executeTakeFirstOrThrow();
    return Number(row.count);
  }

  /** Clear generation-failure audit entries for one user during explicit workspace cleanup. */
  public async clearGenerationFailures(userId: bigint): Promise<number> {
    const result = await this.database.deleteFrom("audit_logs").where("user_id", "=", userId).where("action", "=", "generation_failed").executeTakeFirst();
    return Number(result.numDeletedRows);
  }
}
