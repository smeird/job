import { createHash } from "node:crypto";
import { applicationStatusSchema } from "@job/core";
import type { ApplicationsRepository, DocumentsRepository, JobApplicationRecord } from "@job/db";

export const applicationStatuses = [
  { description: "Roles waiting on materials or next steps.", label: "Queued", value: "outstanding" },
  { description: "Applications sent to the employer.", label: "Submitted", value: "applied" },
  { description: "Conversations and interviews in progress.", label: "Interviewing", value: "interviewing" },
  { description: "Offer review or contract discussions.", label: "Contracting", value: "contracting" },
  { description: "Unsuccessful outcomes retained for learning.", label: "Learning", value: "failed" },
] as const;

export const failureReasons = {
  no_response: "No response received",
  position_filled: "Position filled by employer",
  salary_misaligned: "Salary expectations misaligned",
  skills_gap: "Skills or experience gap",
  other: "Other or unspecified reason",
} as const;

/** Validate and normalize the editable application fields shared by create and update routes. */
export function applicationInput(form: FormData, currentStatus = "outstanding", currentReason: string | null = null): {
  description: string;
  reasonCode: string | null;
  sourceUrl: string | null;
  status: string;
  title: string;
} {
  const description = typeof form.get("description") === "string" ? String(form.get("description")).trim() : "";
  if (description === "") {
    throw new Error("Paste the job description text before saving the record.");
  }
  const sourceValue = typeof form.get("source_url") === "string" ? String(form.get("source_url")).trim() : "";
  let sourceUrl: string | null = null;
  if (sourceValue !== "") {
    const parsed = new URL(sourceValue);
    if (!["http:", "https:"].includes(parsed.protocol)) {
      throw new Error("Provide a valid URL so the job posting can be revisited later.");
    }
    sourceUrl = parsed.toString();
  }
  const statusValue = typeof form.get("status") === "string" ? String(form.get("status")) : currentStatus;
  const status = applicationStatusSchema.parse(statusValue);
  const reasonValue = typeof form.get("reason_code") === "string" ? String(form.get("reason_code")).trim() : currentReason ?? "";
  const reasonCode = status === "failed" && reasonValue in failureReasons ? reasonValue : null;
  if (status === "failed" && reasonCode === null) {
    throw new Error("Select a valid rejection reason before marking the application as failed.");
  }
  const titleValue = typeof form.get("title") === "string" ? String(form.get("title")).trim() : "";
  return { description, reasonCode, sourceUrl, status, title: titleValue || "Untitled application" };
}

/** Store the current application description as a legacy-compatible source document and return its id. */
export async function syncApplicationDocument(
  documents: DocumentsRepository,
  application: Pick<JobApplicationRecord, "createdAt" | "description" | "id" | "title" | "userId">,
): Promise<bigint> {
  const sha256 = createHash("sha256").update(`${application.description}|${application.id.toString()}`, "utf8").digest("hex");
  const existing = (await documents.listForUser(application.userId, "job_description")).find((document) => document.sha256 === sha256);
  if (existing !== undefined) {
    return existing.id;
  }
  const slug = application.title.replace(/[^A-Za-z0-9]+/g, "-").replace(/^-|-$/g, "").toLowerCase() || "job-description";
  const stamp = application.createdAt.toISOString().replace(/[-:]/g, "").replace("T", "_").slice(0, 15);
  const content = Buffer.from(application.description, "utf8");
  return documents.create({
    content,
    documentType: "job_description",
    filename: `${slug}-${stamp}.txt`.slice(0, 255),
    mimeType: "text/plain",
    sha256,
    sizeBytes: BigInt(content.byteLength),
    userId: application.userId,
  });
}

/** Resolve an owned application and fail with the public not-found wording used by PHP. */
export async function requireOwnedApplication(repository: ApplicationsRepository, id: bigint, userId: bigint): Promise<JobApplicationRecord> {
  const application = await repository.findOwned(id, userId);
  if (application === null) {
    throw new Error("The requested job application could not be found.");
  }
  return application;
}
