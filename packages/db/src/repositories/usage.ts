import { sql, type Kysely, type Transaction } from "kysely";
import { asBigInt } from "../ids.js";
import { decodeDatabaseJson, encodeDatabaseJson } from "../json.js";
import type { DatabaseSchema } from "../types.js";

export interface UsageMetadata {
  cost_available?: boolean;
  cost_pence_precise?: number;
  model?: string;
  model_reported?: string;
  model_requested?: string;
  prompt_tokens?: number;
  completion_tokens?: number;
  total_tokens?: number;
  [key: string]: unknown;
}

export interface UsageRecord {
  completionTokens: number;
  costAvailable: boolean;
  costPence: number | null;
  createdAt: Date;
  endpoint: string;
  id: bigint;
  model: string;
  promptTokens: number;
  provider: string;
  totalTokens: number;
}

/** Normalize legacy model aliases so historical rows group with current settings. */
function normalizeModel(model: string): string {
  const aliases: Record<string, string> = {
    "gpt-5": "gpt-5.4",
    "gpt-5-main": "gpt-5.4",
    "gpt-5-mini": "gpt-5.4-mini",
    "gpt-5-nano": "gpt-5.4-nano",
    "gpt-5-strategist": "gpt-5.4",
    gpt5: "gpt-5.4",
    "gpt5-main": "gpt-5.4",
  };
  return aliases[model.toLowerCase()] ?? model;
}

/** Safely coerce an unknown metadata value to a finite number. */
function numericMetadata(value: unknown, fallback = 0): number {
  return typeof value === "number" && Number.isFinite(value) ? value : fallback;
}

export class UsageRepository {
  public constructor(private readonly database: Kysely<DatabaseSchema>) {}

  /** Persist one precisely metered provider call. */
  public async record(input: {
    costPenceRounded: bigint;
    endpoint: string;
    metadata: UsageMetadata;
    provider: string;
    tokensUsed: number;
    userId: bigint;
  }, executor: Kysely<DatabaseSchema> | Transaction<DatabaseSchema> = this.database): Promise<bigint> {
    const result = await executor
      .insertInto("api_usage")
      .values({
        cost_pence: input.costPenceRounded,
        endpoint: input.endpoint,
        metadata: encodeDatabaseJson(input.metadata),
        provider: input.provider,
        tokens_used: input.tokensUsed,
        user_id: input.userId,
      })
      .executeTakeFirstOrThrow();
    return asBigInt(result.insertId, "usage id");
  }

  /** Record one real provider call and atomically add its rounded cost to the owning generation. */
  public async recordForGeneration(input: {
    costPenceRounded: bigint;
    endpoint: string;
    generationId: bigint;
    metadata: UsageMetadata;
    provider: string;
    tokensUsed: number;
    userId: bigint;
  }): Promise<bigint> {
    return this.database.transaction().execute(async (transaction) => {
      const usageId = await this.record({
        costPenceRounded: input.costPenceRounded,
        endpoint: input.endpoint,
        metadata: { ...input.metadata, generation_id: input.generationId.toString() },
        provider: input.provider,
        tokensUsed: input.tokensUsed,
        userId: input.userId,
      }, transaction);
      const updated = await transaction
        .updateTable("generations")
        .set({ cost_pence: sql<bigint>`cost_pence + ${input.costPenceRounded}`, updated_at: new Date() })
        .where("id", "=", input.generationId)
        .where("user_id", "=", input.userId)
        .executeTakeFirst();
      if (Number(updated.numUpdatedRows) !== 1) {
        throw new Error("The usage row does not belong to an accessible generation.");
      }
      return usageId;
    });
  }

  /** Load and normalize every usage row belonging to one user for analytics. */
  public async listForUser(userId: bigint): Promise<UsageRecord[]> {
    const rows = await this.database
      .selectFrom("api_usage")
      .select(["id", "provider", "endpoint", "tokens_used", "cost_pence", "metadata", "created_at"])
      .where("user_id", "=", userId)
      .orderBy("created_at", "desc")
      .orderBy("id", "desc")
      .execute();

    return rows.map((row) => {
      const decoded = decodeDatabaseJson(row.metadata);
      const metadata = typeof decoded === "object" && decoded !== null ? decoded as UsageMetadata : {};
      const promptTokens = numericMetadata(metadata.prompt_tokens);
      const completionTokens = numericMetadata(metadata.completion_tokens);
      const totalTokens = numericMetadata(metadata.total_tokens, row.tokens_used ?? 0);
      const costAvailable = metadata.cost_available === undefined ? true : metadata.cost_available === true;
      const preciseCost = numericMetadata(metadata.cost_pence_precise, Number(asBigInt(row.cost_pence)));
      const requestedModel = typeof metadata.model_requested === "string" ? metadata.model_requested : "";
      const primaryModel = typeof metadata.model === "string" ? metadata.model : "";
      const reportedModel = typeof metadata.model_reported === "string" ? metadata.model_reported : "";
      const model = normalizeModel(requestedModel || primaryModel || reportedModel || "unknown");

      return {
        completionTokens,
        costAvailable,
        costPence: costAvailable ? preciseCost : null,
        createdAt: row.created_at,
        endpoint: row.endpoint,
        id: asBigInt(row.id, "usage id"),
        model,
        promptTokens,
        provider: row.provider,
        totalTokens,
      };
    });
  }
}
