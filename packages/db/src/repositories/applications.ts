import type { Kysely } from "kysely";
import { asBigInt } from "../ids.js";
import type { DatabaseSchema } from "../types.js";

export interface JobApplicationRecord {
  appliedAt: Date | null;
  createdAt: Date;
  description: string;
  generationId: bigint | null;
  id: bigint;
  reasonCode: string | null;
  sourceUrl: string | null;
  status: string;
  title: string;
  updatedAt: Date;
  userId: bigint;
}

export interface ResearchRecord {
  generatedAt: Date;
  query: string;
  searchResults: unknown;
  summary: string;
}

/** Map a database row to a tenant-safe job application domain record. */
function mapApplication(row: {
  applied_at: Date | null;
  created_at: Date;
  description: string;
  generation_id: unknown;
  id: unknown;
  reason_code: string | null;
  source_url: string | null;
  status: string;
  title: string;
  updated_at: Date;
  user_id: unknown;
}): JobApplicationRecord {
  return {
    appliedAt: row.applied_at,
    createdAt: row.created_at,
    description: row.description,
    generationId: row.generation_id === null ? null : asBigInt(row.generation_id, "linked generation id"),
    id: asBigInt(row.id, "job application id"),
    reasonCode: row.reason_code,
    sourceUrl: row.source_url,
    status: row.status,
    title: row.title,
    updatedAt: row.updated_at,
    userId: asBigInt(row.user_id, "job application user id"),
  };
}

export class ApplicationsRepository {
  public constructor(private readonly database: Kysely<DatabaseSchema>) {}

  /** Create an outstanding application owned by one authenticated user. */
  public async create(userId: bigint, input: { description: string; sourceUrl: string | null; title: string }): Promise<bigint> {
    const result = await this.database
      .insertInto("job_applications")
      .values({
        applied_at: null,
        description: input.description,
        generation_id: null,
        reason_code: null,
        source_url: input.sourceUrl,
        status: "outstanding",
        title: input.title,
        user_id: userId,
      })
      .executeTakeFirstOrThrow();
    return asBigInt(result.insertId, "new job application id");
  }

  /** List one user's applications, optionally narrowed to a status. */
  public async listForUser(userId: bigint, status?: string, limit = 500): Promise<JobApplicationRecord[]> {
    let query = this.database.selectFrom("job_applications").selectAll().where("user_id", "=", userId);
    if (status !== undefined) {
      query = query.where("status", "=", status);
    }

    const rows = await query.orderBy("created_at", "desc").limit(limit).execute();
    return rows.map(mapApplication);
  }

  /** Count one user's applications by status for dashboard cards. */
  public async countForUser(userId: bigint, status: string): Promise<number> {
    const row = await this.database
      .selectFrom("job_applications")
      .select((expression) => expression.fn.countAll<number>().as("count"))
      .where("user_id", "=", userId)
      .where("status", "=", status)
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }

  /** Load an application only when it belongs to the authenticated user. */
  public async findOwned(id: bigint, userId: bigint): Promise<JobApplicationRecord | null> {
    const row = await this.database
      .selectFrom("job_applications")
      .selectAll()
      .where("id", "=", id)
      .where("user_id", "=", userId)
      .executeTakeFirst();
    return row === undefined ? null : mapApplication(row);
  }

  /** Update editable application fields with ownership enforced in SQL. */
  public async updateOwned(
    id: bigint,
    userId: bigint,
    input: { description: string; reasonCode: string | null; sourceUrl: string | null; status: string; title: string },
  ): Promise<boolean> {
    const existing = await this.findOwned(id, userId);
    if (existing === null) {
      return false;
    }

    const appliedAt = input.status === "applied"
      ? (existing.appliedAt ?? new Date())
      : input.status === "outstanding" ? null : existing.appliedAt;
    const result = await this.database
      .updateTable("job_applications")
      .set({
        applied_at: appliedAt,
        description: input.description,
        reason_code: input.status === "failed" ? input.reasonCode : null,
        source_url: input.sourceUrl,
        status: input.status,
        title: input.title,
        updated_at: new Date(),
      })
      .where("id", "=", id)
      .where("user_id", "=", userId)
      .executeTakeFirst();
    return Number(result.numUpdatedRows) === 1;
  }

  /** Change status without allowing cross-user updates. */
  public async updateStatusOwned(id: bigint, userId: bigint, status: string, reasonCode: string | null): Promise<boolean> {
    const existing = await this.findOwned(id, userId);
    if (existing === null) {
      return false;
    }

    const appliedAt = status === "applied" ? (existing.appliedAt ?? new Date()) : status === "outstanding" ? null : existing.appliedAt;
    const result = await this.database
      .updateTable("job_applications")
      .set({ applied_at: appliedAt, reason_code: status === "failed" ? reasonCode : null, status, updated_at: new Date() })
      .where("id", "=", id)
      .where("user_id", "=", userId)
      .executeTakeFirst();
    return Number(result.numUpdatedRows) === 1;
  }

  /** Link or unlink a generation after verifying both resources belong to the same user. */
  public async updateGenerationOwned(id: bigint, userId: bigint, generationId: bigint | null): Promise<boolean> {
    if (generationId !== null) {
      const generation = await this.database
        .selectFrom("generations")
        .select("id")
        .where("id", "=", generationId)
        .where("user_id", "=", userId)
        .executeTakeFirst();
      if (generation === undefined) {
        return false;
      }
    }

    const result = await this.database
      .updateTable("job_applications")
      .set({ generation_id: generationId, updated_at: new Date() })
      .where("id", "=", id)
      .where("user_id", "=", userId)
      .executeTakeFirst();
    return Number(result.numUpdatedRows) === 1;
  }

  /** Load cached company research when it is still within the requested freshness window. */
  public async findRecentResearch(userId: bigint, applicationId: bigint, maxAgeMinutes: number): Promise<ResearchRecord | null> {
    const row = await this.database
      .selectFrom("job_application_research")
      .select(["query", "summary", "search_results", "generated_at"])
      .where("user_id", "=", userId)
      .where("job_application_id", "=", applicationId)
      .executeTakeFirst();
    if (row === undefined || (maxAgeMinutes > 0 && row.generated_at.getTime() < Date.now() - maxAgeMinutes * 60_000)) {
      return null;
    }

    let searchResults: unknown = row.search_results;
    try {
      searchResults = JSON.parse(row.search_results) as unknown;
    } catch {
      return null;
    }

    return { generatedAt: row.generated_at, query: row.query, searchResults, summary: row.summary };
  }

  /** Upsert one cached company-research artifact per user and application. */
  public async saveResearch(
    userId: bigint,
    applicationId: bigint,
    input: { generatedAt: Date; query: string; searchResults: unknown; summary: string },
  ): Promise<void> {
    const encodedResults = JSON.stringify(input.searchResults);
    await this.database
      .insertInto("job_application_research")
      .values({
        created_at: input.generatedAt,
        generated_at: input.generatedAt,
        job_application_id: applicationId,
        query: input.query,
        search_results: encodedResults,
        summary: input.summary,
        updated_at: input.generatedAt,
        user_id: userId,
      })
      .onDuplicateKeyUpdate({
        generated_at: input.generatedAt,
        query: input.query,
        search_results: encodedResults,
        summary: input.summary,
        updated_at: input.generatedAt,
      })
      .executeTakeFirstOrThrow();
  }

  /** Delete an application and its cached research through ownership-qualified predicates. */
  public async deleteOwned(id: bigint, userId: bigint): Promise<boolean> {
    return this.database.transaction().execute(async (transaction) => {
      await transaction
        .deleteFrom("job_application_research")
        .where("job_application_id", "=", id)
        .where("user_id", "=", userId)
        .execute();
      const result = await transaction
        .deleteFrom("job_applications")
        .where("id", "=", id)
        .where("user_id", "=", userId)
        .executeTakeFirst();
      return Number(result.numDeletedRows) === 1;
    });
  }
}
