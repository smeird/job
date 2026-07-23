import { describe, expect, it } from "vitest";
import { summarizeUsage } from "./usage.js";

describe("usage analytics", () => {
  it("aggregates tokens while exposing incomplete pricing", () => {
    const createdAt = new Date("2026-07-05T12:00:00Z");
    const summary = summarizeUsage([
      { completionTokens: 20, costAvailable: true, costPence: 1.25, createdAt, endpoint: "responses.cv", id: 1n, model: "gpt-a", promptTokens: 30, provider: "openai", totalTokens: 50 },
      { completionTokens: 4, costAvailable: false, costPence: null, createdAt, endpoint: "responses.plan", id: 2n, model: "gpt-b", promptTokens: 6, provider: "openai", totalTokens: 10 },
    ], new Date("2026-07-23T00:00:00Z"));
    expect(summary.currentMonth.totalTokens).toBe(60);
    expect(summary.currentMonth.costPence).toBe(1.25);
    expect(summary.currentMonth.costComplete).toBe(false);
  });
});
