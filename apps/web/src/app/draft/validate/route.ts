import { ensureNoUnknownOrganisations } from "@job/core";
import { assertCsrf } from "../../../lib/csrf.js";

/** Validate that a draft has not introduced organizations absent from the source CV. */
export async function POST(request: Request): Promise<Response> {
  try {
    const contentType = request.headers.get("content-type") ?? "";
    let sourceCv = "";
    let draftMarkdown = "";
    if (contentType.includes("application/json")) {
      assertCsrf(request, undefined);
      const value = await request.json() as { draft_markdown?: unknown; source_cv?: unknown };
      sourceCv = typeof value.source_cv === "string" ? value.source_cv.trim() : "";
      draftMarkdown = typeof value.draft_markdown === "string" ? value.draft_markdown.trim() : "";
    } else {
      const form = await request.formData();
      assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
      sourceCv = typeof form.get("source_cv") === "string" ? String(form.get("source_cv")).trim() : "";
      draftMarkdown = typeof form.get("draft_markdown") === "string" ? String(form.get("draft_markdown")).trim() : "";
    }
    if (sourceCv === "" || draftMarkdown === "") { return Response.json({ error: "Both source_cv and draft_markdown are required." }, { status: 400 }); }
    ensureNoUnknownOrganisations(sourceCv, draftMarkdown);
    return Response.json({ status: "accepted" });
  } catch (error) {
    return Response.json({ reason: error instanceof Error ? error.message : "Draft validation failed.", status: "rejected" }, { status: 422 });
  }
}
