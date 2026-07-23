import { sql, type Kysely, type Transaction } from "kysely";
import { asBigInt } from "../ids.js";
import { decodeDatabaseJson, encodeDatabaseJson } from "../json.js";
import type { DatabaseSchema } from "../types.js";

export interface GenerationSummary {
  costPence: bigint;
  createdAt: Date;
  cvDocumentId: bigint;
  cvFilename?: string;
  errorMessage: string | null;
  id: bigint;
  jobDocumentId: bigint;
  jobFilename?: string;
  model: string;
  progressPercent: number;
  status: string;
  thinkingTime: number;
  updatedAt: Date;
  userId: bigint;
}

export interface GenerationOutput {
  artifact: string;
  content: Buffer | null;
  createdAt: Date;
  id: bigint;
  mimeType: string | null;
  outputText: string | null;
  tokensUsed: number | null;
}

export interface GenerationStreamSnapshot {
  costPence: bigint;
  errorMessage: string | null;
  generationId: bigint;
  latestOutputAt: Date | null;
  progressPercent: number;
  status: string;
  totalTokens: number;
  updatedAt: Date;
}

/** Map a generation row to the lossless domain representation. */
function mapGeneration(row: {
  cost_pence: unknown;
  created_at: Date;
  cv_document_id: unknown;
  cv_filename?: string;
  error_message: string | null;
  id: unknown;
  job_document_id: unknown;
  job_filename?: string;
  model: string;
  progress_percent: number;
  status: string;
  thinking_time: number;
  updated_at: Date;
  user_id: unknown;
}): GenerationSummary {
  return {
    costPence: asBigInt(row.cost_pence, "generation cost"),
    createdAt: row.created_at,
    cvDocumentId: asBigInt(row.cv_document_id, "CV document id"),
    ...(row.cv_filename === undefined ? {} : { cvFilename: row.cv_filename }),
    errorMessage: row.error_message,
    id: asBigInt(row.id, "generation id"),
    jobDocumentId: asBigInt(row.job_document_id, "job document id"),
    ...(row.job_filename === undefined ? {} : { jobFilename: row.job_filename }),
    model: row.model,
    progressPercent: row.progress_percent,
    status: row.status,
    thinkingTime: row.thinking_time,
    updatedAt: row.updated_at,
    userId: asBigInt(row.user_id, "generation user id"),
  };
}

export class GenerationsRepository {
  public constructor(private readonly database: Kysely<DatabaseSchema>) {}

  /** Create a queued generation and its versioned TypeScript job in one transaction. */
  public async createAndEnqueue(input: {
    cvDocumentId: bigint;
    jobDocumentId: bigint;
    model: string;
    payload: unknown;
    thinkingTime: number;
    userId: bigint;
  }): Promise<bigint> {
    return this.database.transaction().execute(async (transaction) => {
      const now = new Date();
      const result = await transaction
        .insertInto("generations")
        .values({
          cost_pence: 0n,
          cv_document_id: input.cvDocumentId,
          error_message: null,
          job_document_id: input.jobDocumentId,
          model: input.model,
          progress_percent: 0,
          status: "queued",
          thinking_time: input.thinkingTime,
          user_id: input.userId,
        })
        .executeTakeFirstOrThrow();
      const generationId = asBigInt(result.insertId, "new generation id");
      const payload = typeof input.payload === "object" && input.payload !== null
        ? { ...input.payload, generation_id: generationId.toString() }
        : input.payload;

      await transaction
        .insertInto("jobs")
        .values({
          attempts: 0,
          error: null,
          payload_json: encodeDatabaseJson(payload),
          run_after: now,
          runtime_queue: "typescript",
          status: "pending",
          type: "tailor_cv",
        })
        .executeTakeFirstOrThrow();

      return generationId;
    });
  }

  /** List generations belonging to one user with source filenames for the tailoring history UI. */
  public async listForUser(userId: bigint, limit = 100): Promise<GenerationSummary[]> {
    const rows = await this.database
      .selectFrom("generations as generation")
      .innerJoin("documents as job_document", "job_document.id", "generation.job_document_id")
      .innerJoin("documents as cv_document", "cv_document.id", "generation.cv_document_id")
      .select([
        "generation.id",
        "generation.user_id",
        "generation.job_document_id",
        "generation.cv_document_id",
        "generation.model",
        "generation.thinking_time",
        "generation.status",
        "generation.progress_percent",
        "generation.cost_pence",
        "generation.error_message",
        "generation.created_at",
        "generation.updated_at",
        "job_document.filename as job_filename",
        "cv_document.filename as cv_filename",
      ])
      .where("generation.user_id", "=", userId)
      .orderBy("generation.created_at", "desc")
      .limit(limit)
      .execute();

    return rows.map(mapGeneration);
  }

  /** Load one generation only if the authenticated user owns it. */
  public async findOwned(id: bigint, userId: bigint): Promise<GenerationSummary | null> {
    const row = await this.database
      .selectFrom("generations")
      .selectAll()
      .where("id", "=", id)
      .where("user_id", "=", userId)
      .executeTakeFirst();
    return row === undefined ? null : mapGeneration(row);
  }

  /** Update status, progress, cost, and error fields while keeping writes scoped to one generation. */
  public async updateProgress(
    id: bigint,
    patch: { costPence?: bigint; errorMessage?: string | null; progressPercent?: number; status?: string },
    executor: Kysely<DatabaseSchema> | Transaction<DatabaseSchema> = this.database,
  ): Promise<void> {
    await executor
      .updateTable("generations")
      .set({
        ...(patch.costPence === undefined ? {} : { cost_pence: patch.costPence }),
        ...(patch.errorMessage === undefined ? {} : { error_message: patch.errorMessage }),
        ...(patch.progressPercent === undefined ? {} : { progress_percent: Math.max(0, Math.min(100, patch.progressPercent)) }),
        ...(patch.status === undefined ? {} : { status: patch.status }),
        updated_at: new Date(),
      })
      .where("id", "=", id)
      .executeTakeFirstOrThrow();
  }

  /** Replace all generated artifacts atomically after a successful tailoring run. */
  public async replaceOutputs(
    generationId: bigint,
    outputs: ReadonlyArray<{ artifact: string; content: Buffer | null; mimeType: string; outputText: string | null; tokensUsed: number | null }>,
  ): Promise<void> {
    await this.database.transaction().execute(async (transaction) => {
      await transaction.deleteFrom("generation_outputs").where("generation_id", "=", generationId).execute();
      if (outputs.length > 0) {
        await transaction
          .insertInto("generation_outputs")
          .values(outputs.map((output) => ({
            artifact: output.artifact,
            content: output.content,
            generation_id: generationId,
            mime_type: output.mimeType,
            output_text: output.outputText,
            tokens_used: output.tokensUsed,
          })))
          .execute();
      }
    });
  }

  /** List all stored output variants for an owned generation. */
  public async listOutputsOwned(generationId: bigint, userId: bigint): Promise<GenerationOutput[]> {
    const rows = await this.database
      .selectFrom("generation_outputs as output")
      .innerJoin("generations as generation", "generation.id", "output.generation_id")
      .select(["output.id", "output.artifact", "output.mime_type", "output.content", "output.output_text", "output.tokens_used", "output.created_at"])
      .where("generation.id", "=", generationId)
      .where("generation.user_id", "=", userId)
      .orderBy("output.created_at", "asc")
      .execute();

    return rows.map((row) => ({
      artifact: row.artifact,
      content: row.content,
      createdAt: row.created_at,
      id: asBigInt(row.id, "generation output id"),
      mimeType: row.mime_type,
      outputText: row.output_text,
      tokensUsed: row.tokens_used,
    }));
  }

  /** Load an owned text output by artifact and MIME type for previews and downloads. */
  public async findOutputOwned(generationId: bigint, userId: bigint, artifact: string, mimeType: string): Promise<GenerationOutput | null> {
    const rows = await this.listOutputsOwned(generationId, userId);
    return rows.find((row) => row.artifact === artifact && row.mimeType === mimeType) ?? null;
  }

  /** Return an ownership-qualified progress snapshot for the SSE endpoint. */
  public async streamSnapshot(generationId: bigint, userId: bigint): Promise<GenerationStreamSnapshot | null> {
    const generation = await this.findOwned(generationId, userId);
    if (generation === null) {
      return null;
    }

    const totals = await this.database
      .selectFrom("generation_outputs")
      .select([
        sql<number>`COALESCE(SUM(tokens_used), 0)`.as("total_tokens"),
        sql<Date | null>`MAX(created_at)`.as("latest_output_at"),
      ])
      .where("generation_id", "=", generationId)
      .executeTakeFirstOrThrow();

    return {
      costPence: generation.costPence,
      errorMessage: generation.errorMessage,
      generationId,
      latestOutputAt: totals.latest_output_at,
      progressPercent: generation.progressPercent,
      status: generation.status,
      totalTokens: Number(totals.total_tokens),
      updatedAt: generation.updatedAt,
    };
  }

  /** Delete an owned generation and cascade its outputs while preserving other tenants' data. */
  public async deleteOwned(generationId: bigint, userId: bigint): Promise<boolean> {
    return this.database.transaction().execute(async (transaction) => {
      const generation = await transaction.selectFrom("generations").select("id").where("id", "=", generationId).where("user_id", "=", userId).executeTakeFirst();
      if (generation === undefined) {
        return false;
      }
      const jobs = await transaction.selectFrom("jobs").select(["id", "payload_json"]).where("type", "=", "tailor_cv").execute();
      const ids = jobs.filter((job) => this.payloadMatches(job.payload_json, generationId, userId)).map((job) => asBigInt(job.id, "job id"));
      if (ids.length > 0) {
        await transaction.deleteFrom("jobs").where("id", "in", ids).execute();
      }
      const result = await transaction.deleteFrom("generations").where("id", "=", generationId).where("user_id", "=", userId).executeTakeFirst();
      return Number(result.numDeletedRows) === 1;
    });
  }

  /** Cancel an owned generation only while its queue job is still pending. */
  public async cancelQueuedOwned(generationId: bigint, userId: bigint): Promise<GenerationSummary | null> {
    const cancelled = await this.database.transaction().execute(async (transaction) => {
      const generation = await transaction.selectFrom("generations").select(["id", "status"]).where("id", "=", generationId).where("user_id", "=", userId).forUpdate().executeTakeFirst();
      if (generation === undefined || generation.status !== "queued") {
        return false;
      }
      const jobs = await transaction.selectFrom("jobs").select(["id", "payload_json", "status"]).where("type", "=", "tailor_cv").execute();
      const job = jobs.find((candidate) => candidate.status === "pending" && this.payloadMatches(candidate.payload_json, generationId, userId));
      if (job === undefined) {
        return false;
      }
      await transaction.deleteFrom("jobs").where("id", "=", asBigInt(job.id, "job id")).execute();
      await transaction.updateTable("generations").set({ error_message: null, progress_percent: 0, status: "cancelled", updated_at: new Date() }).where("id", "=", generationId).execute();
      return true;
    });
    return cancelled ? this.findOwned(generationId, userId) : null;
  }

  /** Remove one user's residual tailoring jobs and failed generations without touching another account. */
  public async cleanupOwned(userId: bigint): Promise<{ removedFailedGenerations: number; removedJobs: number }> {
    return this.database.transaction().execute(async (transaction) => {
      const jobs = await transaction.selectFrom("jobs").select(["id", "payload_json"]).where("type", "=", "tailor_cv").execute();
      const owned = jobs.flatMap((job) => {
        const decoded = decodeDatabaseJson(job.payload_json);
        if (typeof decoded !== "object" || decoded === null || String((decoded as Record<string, unknown>).user_id ?? "") !== userId.toString()) {
          return [];
        }
        const generationId = String((decoded as Record<string, unknown>).generation_id ?? "");
        return /^[1-9]\d*$/.test(generationId) ? [{ generationId: BigInt(generationId), jobId: asBigInt(job.id, "job id") }] : [];
      });
      if (owned.length > 0) {
        await transaction.deleteFrom("jobs").where("id", "in", owned.map((job) => job.jobId)).execute();
        await transaction.updateTable("generations").set({ error_message: null, progress_percent: 0, status: "cancelled", updated_at: new Date() }).where("id", "in", owned.map((job) => job.generationId)).where("user_id", "=", userId).where("status", "in", ["queued", "processing"]).execute();
      }
      const failed = await transaction.deleteFrom("generations").where("user_id", "=", userId).where("status", "in", ["failed", "error"]).executeTakeFirst();
      return { removedFailedGenerations: Number(failed.numDeletedRows), removedJobs: owned.length };
    });
  }

  /** Check decoded legacy or TypeScript job payload ownership without lossy numeric conversion. */
  private payloadMatches(value: unknown, generationId: bigint, userId: bigint): boolean {
    const decoded = decodeDatabaseJson(value);
    if (typeof decoded !== "object" || decoded === null) {
      return false;
    }
    const payload = decoded as Record<string, unknown>;
    return String(payload.generation_id ?? "") === generationId.toString() && String(payload.user_id ?? "") === userId.toString();
  }
}
