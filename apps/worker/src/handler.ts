import type { AppConfig } from "@job/core/config";
import { ModelCatalogService } from "@job/core/model-catalog";
import { OpenAITailoringService, type OpenAIUsageSample } from "@job/core/openai";
import { calculateUsageCost, parseTariffs } from "@job/core/pricing";
import { tailorJobPayloadV1Schema } from "@job/core/schemas";
import { asBigInt } from "@job/db/ids";
import { GenerationsRepository } from "@job/db/repositories/generations";
import { SettingsRepository } from "@job/db/repositories/settings";
import { UsageRepository } from "@job/db/repositories/usage";
import type { DatabaseSchema } from "@job/db/types";
import { documentPlainText, parseRestrictedMarkdown } from "@job/documents/markdown-tree";
import { renderDocx } from "@job/documents/render-docx";
import { renderRestrictedHtml } from "@job/documents/render-html";
import { renderPdf } from "@job/documents/render-pdf";
import type { Kysely } from "kysely";

export class TailorJobHandler {
  private readonly generations: GenerationsRepository;
  private readonly usage: UsageRepository;
  private readonly modelCatalog: ModelCatalogService;
  private readonly openai: OpenAITailoringService;
  private readonly tariffs: Record<string, { completion: number; prompt: number }>;

  public constructor(database: Kysely<DatabaseSchema>, private readonly config: AppConfig) {
    this.generations = new GenerationsRepository(database);
    this.usage = new UsageRepository(database);
    this.modelCatalog = new ModelCatalogService(new SettingsRepository(database), config.openai.apiKey, config.openai.baseUrl);
    this.openai = new OpenAITailoringService(config.openai.apiKey, {
      baseURL: config.openai.baseUrl,
      maxOutputTokens: config.openai.maxOutputTokens,
      safetySecret: config.app.csrfSecret,
    });
    this.tariffs = parseTariffs(config.openai.tariffJson);
  }

  /** Process one versioned tailoring payload and persist all safe output formats. */
  public async handle(payloadValue: unknown): Promise<void> {
    const payload = tailorJobPayloadV1Schema.parse(payloadValue);
    const generationId = asBigInt(payload.generation_id, "generation id");
    const userId = asBigInt(payload.user_id, "user id");
    if (await this.generations.findOwned(generationId, userId) === null) {
      throw new Error("The queued generation does not belong to the payload user.");
    }

    await this.generations.updateProgress(generationId, { errorMessage: null, progressPercent: 5, status: "running" });
    const planModel = await this.modelCatalog.planningModel(this.config.openai.planModel);
    const result = await this.openai.tailor(payload, planModel, {
      onProgress: async (event) => this.generations.updateProgress(generationId, { progressPercent: event.percent, status: "running" }),
      onUsage: async (sample) => this.recordUsage(userId, generationId, sample),
    });
    await this.generations.updateProgress(generationId, { progressPercent: 90, status: "rendering" });

    const cvTree = parseRestrictedMarkdown(result.cvMarkdown);
    const letterTree = parseRestrictedMarkdown(result.coverLetterMarkdown);
    const [cvDocx, cvPdf, letterDocx, letterPdf] = await Promise.all([
      renderDocx(cvTree),
      renderPdf(cvTree, { title: "Tailored CV" }),
      renderDocx(letterTree),
      renderPdf(letterTree, { title: "Cover letter" }),
    ]);
    await this.generations.replaceOutputs(generationId, [
      ...this.buildArtifactOutputs("cv", result.cvMarkdown, cvTree, cvDocx, cvPdf),
      ...this.buildArtifactOutputs("cover_letter", result.coverLetterMarkdown, letterTree, letterDocx, letterPdf),
    ]);
    await this.generations.updateProgress(generationId, { errorMessage: null, progressPercent: 100, status: "completed" });
  }

  /** Mark a generation failed or requeued after its worker-level error decision. */
  public async recordFailure(payloadValue: unknown, error: string, retrying: boolean): Promise<void> {
    const parsed = tailorJobPayloadV1Schema.safeParse(payloadValue);
    if (!parsed.success) {
      return;
    }
    await this.generations.updateProgress(asBigInt(parsed.data.generation_id), {
      errorMessage: error.slice(0, 2_000),
      status: retrying ? "queued" : "failed",
    });
  }

  /** Build Markdown, HTML, text, DOCX, and PDF rows from one restricted document tree. */
  private buildArtifactOutputs(
    artifact: string,
    markdown: string,
    tree: ReturnType<typeof parseRestrictedMarkdown>,
    docx: Buffer,
    pdf: Buffer,
  ): Array<{ artifact: string; content: Buffer | null; mimeType: string; outputText: string | null; tokensUsed: number | null }> {
    return [
      { artifact, content: null, mimeType: "text/markdown", outputText: markdown, tokensUsed: null },
      { artifact, content: null, mimeType: "text/html", outputText: renderRestrictedHtml(tree), tokensUsed: null },
      { artifact, content: null, mimeType: "text/plain", outputText: documentPlainText(tree), tokensUsed: null },
      { artifact, content: docx, mimeType: "application/vnd.openxmlformats-officedocument.wordprocessingml.document", outputText: null, tokensUsed: null },
      { artifact, content: pdf, mimeType: "application/pdf", outputText: null, tokensUsed: null },
    ];
  }

  /** Persist each completed provider call immediately so retries and rendering failures remain billable and visible. */
  private async recordUsage(userId: bigint, generationId: bigint, sample: OpenAIUsageSample): Promise<void> {
    const cost = calculateUsageCost(sample.modelRequested, sample.promptTokens, sample.completionTokens, this.tariffs);
    await this.usage.recordForGeneration({
      costPenceRounded: cost.roundedPence,
      endpoint: sample.endpoint,
      generationId,
      metadata: {
        completion_tokens: sample.completionTokens,
        cost_available: cost.available,
        ...(cost.precisePence === null ? {} : { cost_pence_precise: cost.precisePence }),
        model_reported: sample.modelReported,
        model_requested: sample.modelRequested,
        prompt_tokens: sample.promptTokens,
        total_tokens: sample.totalTokens,
      },
      provider: "openai",
      tokensUsed: sample.totalTokens,
      userId,
    });
  }
}
