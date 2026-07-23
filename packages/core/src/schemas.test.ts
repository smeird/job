import { describe, expect, it } from "vitest";
import { applicationStatusSchema, retentionSettingsSchema, tailorJobPayloadV1Schema } from "./schemas.js";

describe("versioned queue contract", () => {
  it("accepts the exact V1 tailoring fields and decimal-string identifiers", () => {
    expect(tailorJobPayloadV1Schema.parse({ cv_document_id: "2", cv_markdown: "CV", generation_id: "3", job_description: "Role", job_document_id: "1", model: "gpt-5.6-sol", prompt: "Tailor", thinking_time: 30, user_id: "4", version: 1 }).version).toBe(1);
    expect(() => tailorJobPayloadV1Schema.parse({ cv_document_id: 2 })).toThrow();
  });

  it("preserves the existing application pipeline values", () => {
    expect(applicationStatusSchema.options).toEqual(["outstanding", "applied", "interviewing", "contracting", "failed"]);
  });

  it("validates and de-duplicates the fixed retention allowlist", () => {
    expect(retentionSettingsSchema.parse({ applyTo: ["documents", "documents", "api_usage"], purgeAfterDays: "30" })).toEqual({ applyTo: ["documents", "api_usage"], purgeAfterDays: 30 });
    expect(() => retentionSettingsSchema.parse({ applyTo: ["users"], purgeAfterDays: 30 })).toThrow();
    expect(() => retentionSettingsSchema.parse({ applyTo: [], purgeAfterDays: 0 })).toThrow();
  });
});
