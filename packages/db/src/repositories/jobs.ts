import { sql, type Kysely } from "kysely";
import { asBigInt } from "../ids.js";
import { decodeDatabaseJson, encodeDatabaseJson } from "../json.js";
import type { DatabaseSchema } from "../types.js";

export interface ReservedJob {
  attempts: number;
  createdAt: Date;
  id: bigint;
  payload: unknown;
  runAfter: Date;
  type: string;
}

export class JobsRepository {
  public constructor(private readonly database: Kysely<DatabaseSchema>) {}

  /** Enqueue work explicitly for the TypeScript runtime so the PHP worker cannot claim it. */
  public async enqueue(type: string, payload: unknown, runAfter = new Date()): Promise<bigint> {
    const result = await this.database
      .insertInto("jobs")
      .values({
        attempts: 0,
        error: null,
        payload_json: encodeDatabaseJson(payload),
        run_after: runAfter,
        runtime_queue: "typescript",
        status: "pending",
        type,
      })
      .executeTakeFirstOrThrow();

    return asBigInt(result.insertId, "new job id");
  }

  /** Reserve one due or lease-expired TypeScript job using row locking that safely supports concurrent workers. */
  public async reserveNext(now = new Date(), leaseMilliseconds = 60 * 60_000): Promise<ReservedJob | null> {
    return this.database.transaction().execute(async (transaction) => {
      const row = await transaction
        .selectFrom("jobs")
        .select(["id", "type", "payload_json", "run_after", "attempts", "created_at"])
        .where("runtime_queue", "=", "typescript")
        .where("status", "in", ["pending", "running"])
        .where("run_after", "<=", now)
        .orderBy("run_after", "asc")
        .orderBy("id", "asc")
        .limit(1)
        .forUpdate()
        .skipLocked()
        .executeTakeFirst();

      if (row === undefined) {
        return null;
      }

      await transaction
        .updateTable("jobs")
        .set({ attempts: sql<number>`attempts + 1`, error: null, run_after: new Date(now.getTime() + leaseMilliseconds), status: "running" })
        .where("id", "=", row.id)
        .where("runtime_queue", "=", "typescript")
        .where("status", "in", ["pending", "running"])
        .where("run_after", "<=", now)
        .executeTakeFirstOrThrow();

      return {
        attempts: row.attempts + 1,
        createdAt: row.created_at,
        id: asBigInt(row.id, "job id"),
        payload: decodeDatabaseJson(row.payload_json),
        runAfter: row.run_after,
        type: row.type,
      };
    });
  }

  /** Mark a reserved TypeScript job as complete. */
  public async markCompleted(id: bigint): Promise<void> {
    await this.database
      .updateTable("jobs")
      .set({ error: null, status: "completed" })
      .where("id", "=", id)
      .where("runtime_queue", "=", "typescript")
      .executeTakeFirstOrThrow();
  }

  /** Return a transiently failed job to the TypeScript queue after a bounded delay. */
  public async scheduleRetry(id: bigint, error: string, runAfter: Date): Promise<void> {
    await this.database
      .updateTable("jobs")
      .set({ error: error.slice(0, 1_000), run_after: runAfter, status: "pending" })
      .where("id", "=", id)
      .where("runtime_queue", "=", "typescript")
      .executeTakeFirstOrThrow();
  }

  /** Mark a permanently failed TypeScript job and retain a bounded diagnostic message. */
  public async markFailed(id: bigint, error: string): Promise<void> {
    await this.database
      .updateTable("jobs")
      .set({ error: error.slice(0, 1_000), status: "failed" })
      .where("id", "=", id)
      .where("runtime_queue", "=", "typescript")
      .executeTakeFirstOrThrow();
  }

  /** Count undrained jobs by runtime for cutover acceptance checks. */
  public async countPending(runtimeQueue: "php" | "typescript"): Promise<number> {
    const row = await this.database
      .selectFrom("jobs")
      .select((expression) => expression.fn.countAll<number>().as("count"))
      .where("runtime_queue", "=", runtimeQueue)
      .where("status", "in", ["pending", "running"])
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }
}
