import { describe, expect, it } from "vitest";
import { reasoningEffort, safetyIdentifier } from "./openai.js";

describe("OpenAI orchestration boundaries", () => {
  it("maps legacy thinking time to supported Responses reasoning effort", () => {
    expect(reasoningEffort({ thinking_time: 15 })).toBe("low");
    expect(reasoningEffort({ thinking_time: 30 })).toBe("medium");
    expect(reasoningEffort({ thinking_time: 50 })).toBe("high");
    expect(reasoningEffort({ analysis_depth: "xhigh", thinking_time: 1 })).toBe("xhigh");
  });

  it("creates stable, secret-dependent safety identifiers", () => {
    expect(safetyIdentifier("42", "secret-a")).toBe(safetyIdentifier("42", "secret-a"));
    expect(safetyIdentifier("42", "secret-a")).not.toBe(safetyIdentifier("42", "secret-b"));
  });
});
