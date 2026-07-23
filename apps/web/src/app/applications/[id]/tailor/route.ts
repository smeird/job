import { ModelCatalogService, TAILOR_PROMPT } from "@job/core";
import { parsePublicId } from "@job/db";
import { requireOwnedApplication, syncApplicationDocument } from "../../../../lib/applications.js";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { extractStoredDocument } from "../../../../lib/documents.js";
import { redirectAfterPost, requiredFormString, withStatus } from "../../../../lib/http.js";
import { appConfig, repositories } from "../../../../lib/services.js";

/** Queue evidence-led tailoring directly from an owned tracked application. */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  const applicationPath = `/applications/${(await params).id}`;
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const applicationId = parsePublicId((await params).id, "application id");
    const cvDocumentId = parsePublicId(requiredFormString(form, "cv_document_id"), "CV document id");
    const model = requiredFormString(form, "model");
    const thinkingTime = Number(requiredFormString(form, "thinking_time"));
    if (![15, 30, 50].includes(thinkingTime)) { throw new Error("Choose a valid analysis depth."); }
    const repository = repositories();
    const config = appConfig();
    const catalog = new ModelCatalogService(repository.settings, config.openai.apiKey, config.openai.baseUrl);
    if (!(await catalog.isSelectable(model))) { throw new Error("Choose a model from the current catalogue."); }
    const application = await requireOwnedApplication(repository.applications, applicationId, user.id);
    const [cvDocument, contactDetails] = await Promise.all([
      repository.documents.findOwned(cvDocumentId, user.id),
      repository.contactDetails.findForUser(user.id),
    ]);
    if (cvDocument?.documentType !== "cv") { throw new Error("Select a master CV that belongs to your workspace."); }
    const jobDocumentId = await syncApplicationDocument(repository.documents, application);
    const cvMarkdown = await extractStoredDocument(cvDocument);
    if (cvMarkdown.trim() === "") { throw new Error("The selected CV did not contain extractable text."); }
    const promptValue = typeof form.get("prompt") === "string" ? String(form.get("prompt")).trim() : "";
    const generationId = await repository.generations.createAndEnqueue({
      cvDocumentId,
      jobDocumentId,
      model,
      payload: {
        analysis_depth: thinkingTime === 15 ? "low" : thinkingTime === 30 ? "medium" : "high",
        ...(contactDetails === null ? {} : { contact_details: contactDetails }),
        cv_document_id: cvDocumentId.toString(),
        cv_markdown: cvMarkdown,
        job_description: application.description,
        job_document_id: jobDocumentId.toString(),
        model,
        prompt: promptValue || TAILOR_PROMPT,
        thinking_time: thinkingTime,
        user_id: user.id.toString(),
        version: 1,
      },
      thinkingTime,
      userId: user.id,
    });
    await repository.applications.updateGenerationOwned(applicationId, user.id, generationId);
    return redirectAfterPost(request, withStatus(applicationPath, "Tailored CV job queued and linked to this application."));
  } catch (error) {
    return redirectAfterPost(request, withStatus(applicationPath, error instanceof Error ? error.message : "Unable to queue tailored documents."));
  }
}
