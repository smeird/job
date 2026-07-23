import type { SettingsRepository } from "@job/db";
import { describe, expect, it } from "vitest";
import { fallbackModels, isSelectableTextModel, ModelCatalogService } from "./model-catalog.js";

/** Build a minimal in-memory settings repository for catalogue fallback tests. */
function settingsStub(): SettingsRepository {
  const values = new Map<string, string>();
  return {
    get: (name: string) => Promise.resolve(values.get(name) ?? null),
    set: (name: string, value: string) => {
      values.set(name, value);
      return Promise.resolve();
    },
  } as unknown as SettingsRepository;
}

describe("OpenAI model discovery", () => {
  it("accepts stable GPT text identifiers and excludes dated or non-GPT models", () => {
    expect(isSelectableTextModel("gpt-5.6-sol")).toBe(true);
    expect(isSelectableTextModel("gpt-5.6-sol-2026-07-01")).toBe(false);
    expect(isSelectableTextModel("text-embedding-4-large")).toBe(false);
    expect(isSelectableTextModel("gpt-image-2")).toBe(false);
  });

  it("retains the current built-in catalogue when no API credential is configured", async () => {
    const catalogue = new ModelCatalogService(settingsStub(), "");
    await expect(catalogue.models()).resolves.toEqual(fallbackModels);
  });
});
