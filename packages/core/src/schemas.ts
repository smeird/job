import { z } from "zod";

const publicIdSchema = z.string().regex(/^[1-9]\d*$/, "Expected a positive decimal identifier.");

export const contactDetailsSchema = z.object({
  address: z.string().trim().min(1).max(2_000),
  email: z.email().max(255).nullable().optional(),
  phone: z.string().trim().max(64).nullable().optional(),
});

export const tailorJobPayloadV1Schema = z.object({
  analysis_depth: z.enum(["low", "medium", "high", "xhigh"]).optional(),
  contact_details: contactDetailsSchema.optional(),
  cv_document_id: publicIdSchema,
  cv_markdown: z.string().min(1).max(1_500_000),
  generation_id: publicIdSchema,
  job_description: z.string().min(1).max(1_500_000),
  job_document_id: publicIdSchema,
  model: z.string().trim().min(1).max(128),
  prompt: z.string().min(1).max(100_000),
  thinking_time: z.number().int().min(0).max(120),
  user_id: publicIdSchema,
  version: z.literal(1),
});

export type TailorJobPayloadV1 = z.infer<typeof tailorJobPayloadV1Schema>;

export const evidencePlanSchema = z.object({
  requirements: z.array(z.object({
    evidence: z.array(z.string().min(1)).min(1),
    gap: z.boolean(),
    priority: z.enum(["high", "medium", "low"]),
    requirement: z.string().min(1),
  })).min(1),
  strategy: z.array(z.string().min(1)).min(1),
  truthful_positioning: z.string().min(1),
});

export type EvidencePlan = z.infer<typeof evidencePlanSchema>;

export const applicationStatusSchema = z.enum(["outstanding", "applied", "interviewing", "contracting", "failed"]);

export const modelIdentifierSchema = z.string().trim().regex(/^gpt-[a-z0-9][a-z0-9._-]*$/i).max(128);

export const documentTypeSchema = z.enum(["cv", "job_description"]);

export const retentionSettingsSchema = z.object({
  applyTo: z.array(z.enum(["documents", "generation_outputs", "api_usage", "audit_logs"]))
    .min(1, "Select at least one data type to apply retention.")
    .transform((resources) => [...new Set(resources)]),
  purgeAfterDays: z.coerce.number().int().min(1).max(3_650),
});
