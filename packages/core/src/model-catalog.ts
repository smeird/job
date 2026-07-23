import OpenAI from "openai";
import type { SettingsRepository } from "@job/db";
import { modelIdentifierSchema } from "./schemas.js";

const CACHE_KEY = "openai_model_catalog";
const CACHE_TIME_KEY = "openai_model_catalog_refreshed_at";
const CACHE_TTL_MILLISECONDS = 6 * 60 * 60_000;

export interface ModelOption {
  description: string;
  label: string;
  value: string;
}

export const fallbackModels: readonly ModelOption[] = [
  { description: "Highest quality for demanding CV analysis and drafting.", label: "GPT-5.6 Sol", value: "gpt-5.6-sol" },
  { description: "Balanced quality, speed, and cost.", label: "GPT-5.6 Terra", value: "gpt-5.6-terra" },
  { description: "Economical drafting for simpler roles and high-volume use.", label: "GPT-5.6 Luna", value: "gpt-5.6-luna" },
  { description: "Previous flagship retained for compatibility.", label: "GPT-5.5", value: "gpt-5.5" },
  { description: "Previous model retained for compatibility.", label: "GPT-5.4", value: "gpt-5.4" },
  { description: "Lower-cost previous model retained for compatibility.", label: "GPT-5.4 Mini", value: "gpt-5.4-mini" },
];

/** Decide whether a model-list identifier is a general Responses-compatible GPT text model. */
export function isSelectableTextModel(model: string): boolean {
  if (!modelIdentifierSchema.safeParse(model).success || /-\d{4}-\d{2}-\d{2}$/.test(model)) {
    return false;
  }
  return !["audio", "realtime", "transcribe", "tts", "image", "search", "codex", "instruct", "chat-latest", "moderation"]
    .some((fragment) => model.toLowerCase().includes(fragment));
}

/** Convert an identifier to restrained settings-page copy. */
function describeModel(model: string): ModelOption {
  const known = fallbackModels.find((candidate) => candidate.value === model);
  if (known !== undefined) {
    return known;
  }
  const label = model.split(/[-_]/).map((part) => part.length <= 3 ? part.toUpperCase() : `${part[0]?.toUpperCase() ?? ""}${part.slice(1)}`).join(" ").replace(/^GPT /, "GPT-");
  return { description: "Available to the configured OpenAI project.", label, value: model };
}

/** Parse cached model options while discarding malformed records. */
function parseCachedCatalog(value: string | null): ModelOption[] {
  if (value === null) {
    return [];
  }
  try {
    const parsed = JSON.parse(value) as unknown;
    if (!Array.isArray(parsed)) {
      return [];
    }
    return parsed.flatMap((item) => {
      if (typeof item !== "object" || item === null) {
        return [];
      }
      const candidate = item as Partial<ModelOption>;
      return typeof candidate.value === "string" && typeof candidate.label === "string" && typeof candidate.description === "string"
        ? [{ description: candidate.description, label: candidate.label, value: candidate.value }]
        : [];
    });
  } catch {
    return [];
  }
}

export class ModelCatalogService {
  private readonly client: OpenAI | null;
  private lastRemoteRefresh = false;

  public constructor(
    private readonly settingsRepository: SettingsRepository,
    apiKey: string,
    baseURL = "https://api.openai.com/v1",
  ) {
    this.client = apiKey === "" ? null : new OpenAI({ apiKey, baseURL });
  }

  /** Load a fresh or cached catalogue, falling back to current built-in models when OpenAI is unavailable. */
  public async models(forceRefresh = false): Promise<ModelOption[]> {
    const [cachedValue, refreshedValue] = await Promise.all([
      this.settingsRepository.get(CACHE_KEY),
      this.settingsRepository.get(CACHE_TIME_KEY),
    ]);
    const cached = parseCachedCatalog(cachedValue);
    const refreshedAt = refreshedValue === null ? Number.NaN : Date.parse(refreshedValue);
    if (!forceRefresh && cached.length > 0 && Number.isFinite(refreshedAt) && refreshedAt >= Date.now() - CACHE_TTL_MILLISECONDS) {
      return cached;
    }

    const remote = await this.fetchRemoteModels();
    if (remote.length > 0) {
      const refreshed = new Date().toISOString();
      await Promise.all([
        this.settingsRepository.set(CACHE_KEY, JSON.stringify(remote)),
        this.settingsRepository.set(CACHE_TIME_KEY, refreshed),
      ]);
      return remote;
    }
    return cached.length > 0 ? cached : [...fallbackModels];
  }

  /** Resolve the saved planning model while retaining an environment fallback. */
  public async planningModel(environmentFallback: string): Promise<string> {
    return (await this.settingsRepository.get("openai_model_plan"))?.trim() || environmentFallback;
  }

  /** Resolve the saved drafting model while retaining an environment fallback. */
  public async draftingModel(environmentFallback: string): Promise<string> {
    return (await this.settingsRepository.get("openai_model_draft"))?.trim() || environmentFallback;
  }

  /** Validate that a posted model is present in the current account catalogue. */
  public async isSelectable(model: string): Promise<boolean> {
    return (await this.models()).some((candidate) => candidate.value === model);
  }

  /** Save independently configurable analysis and drafting defaults. */
  public async saveDefaults(planModel: string, draftModel: string): Promise<void> {
    const models = await this.models();
    const allowed = new Set(models.map((model) => model.value));
    if (!allowed.has(planModel) || !allowed.has(draftModel)) {
      throw new Error("Choose models from the currently available catalogue.");
    }
    await Promise.all([
      this.settingsRepository.set("openai_model_plan", planModel),
      this.settingsRepository.set("openai_model_draft", draftModel),
    ]);
  }

  /** Return the last successful catalogue refresh timestamp. */
  public async refreshedAt(): Promise<string | null> {
    return this.settingsRepository.get(CACHE_TIME_KEY);
  }

  /** Indicate whether the most recent refresh call reached OpenAI successfully. */
  public lastRefreshSucceeded(): boolean {
    return this.lastRemoteRefresh;
  }

  /** Fetch, filter, and order account models from the official SDK. */
  private async fetchRemoteModels(): Promise<ModelOption[]> {
    this.lastRemoteRefresh = false;
    if (this.client === null) {
      return [];
    }
    try {
      const identifiers: string[] = [];
      for await (const model of this.client.models.list()) {
        if (isSelectableTextModel(model.id) && !identifiers.includes(model.id)) {
          identifiers.push(model.id);
        }
      }
      if (identifiers.length === 0) {
        return [];
      }
      this.lastRemoteRefresh = true;
      const preferred = fallbackModels.map((model) => model.value).filter((model) => identifiers.includes(model));
      const remainder = identifiers.filter((model) => !preferred.includes(model)).sort().reverse();
      return [...preferred, ...remainder].map(describeModel);
    } catch {
      return [];
    }
  }
}
