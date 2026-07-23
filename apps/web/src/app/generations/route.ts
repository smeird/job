import { ModelCatalogService, TAILOR_PROMPT } from "@job/core";
import { parsePublicId } from "@job/db";
import { requestUser } from "../../lib/auth.js";
import { assertCsrf } from "../../lib/csrf.js";
import { extractStoredDocument } from "../../lib/documents.js";
import { publicError, requiredFormString } from "../../lib/http.js";
import { appConfig, repositories } from "../../lib/services.js";

/** Create a versioned TypeScript tailoring job while preserving the existing JSON endpoint. */
export async function POST(request: Request): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.json({ error: "Authentication required." }, { status: 401 }); }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const jobDocumentId = parsePublicId(requiredFormString(form, "job_document_id"), "job document id");
    const cvDocumentId = parsePublicId(requiredFormString(form, "cv_document_id"), "CV document id");
    const model = requiredFormString(form, "model");
    const thinkingTime = Number(requiredFormString(form, "thinking_time"));
    if (![10, 30, 60].includes(thinkingTime)) { throw new Error("Choose a valid analysis depth."); }
    const repository = repositories();
    const config = appConfig();
    const catalog = new ModelCatalogService(repository.settings, config.openai.apiKey, config.openai.baseUrl);
    if (!(await catalog.isSelectable(model))) { throw new Error("Unknown model selection."); }
    const [jobDocument, cvDocument, contactDetails] = await Promise.all([
      repository.documents.findOwned(jobDocumentId, user.id),
      repository.documents.findOwned(cvDocumentId, user.id),
      repository.contactDetails.findForUser(user.id),
    ]);
    if (jobDocument?.documentType !== "job_description" || cvDocument?.documentType !== "cv") { return Response.json({ error: "Document selection is invalid." }, { status: 422 }); }
    const [jobDescription, cvMarkdown] = await Promise.all([extractStoredDocument(jobDocument), extractStoredDocument(cvDocument)]);
    if (jobDescription === "" || cvMarkdown === "") { throw new Error("A selected document did not contain extractable text."); }
    const prompt = typeof form.get("prompt") === "string" && String(form.get("prompt")).trim() !== "" ? String(form.get("prompt")).trim() : TAILOR_PROMPT;
    const generationId = await repository.generations.createAndEnqueue({
      cvDocumentId,
      jobDocumentId,
      model,
      payload: {
        analysis_depth: thinkingTime === 10 ? "low" : thinkingTime === 30 ? "medium" : "high",
        ...(contactDetails === null ? {} : { contact_details: contactDetails }),
        cv_document_id: cvDocumentId.toString(),
        cv_markdown: cvMarkdown,
        job_description: jobDescription,
        job_document_id: jobDocumentId.toString(),
        model,
        prompt,
        thinking_time: thinkingTime,
        user_id: user.id.toString(),
        version: 1,
      },
      thinkingTime,
      userId: user.id,
    });
    const generation = await repository.generations.findOwned(generationId, user.id);
    return Response.json({
      created_at: generation?.createdAt.toISOString(),
      cv_document: { filename: cvDocument.filename, id: cvDocumentId.toString() },
      id: generationId.toString(),
      job_document: { filename: jobDocument.filename, id: jobDocumentId.toString() },
      model,
      status: "queued",
      thinking_time: thinkingTime,
    }, { status: 201 });
  } catch (error) {
    return Response.json({ error: publicError(error) }, { status: 422 });
  }
}
