import { documentTypeSchema } from "@job/core";
import { validateUpload } from "@job/documents";
import { requestUser } from "../../../lib/auth.js";
import { assertCsrf } from "../../../lib/csrf.js";
import { publicError, redirectAfterPost, withStatus } from "../../../lib/http.js";
import { repositories } from "../../../lib/services.js";

/** Validate and store a multipart source document without trusting the browser MIME type. */
export async function POST(request: Request): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const documentType = documentTypeSchema.parse(form.get("document_type"));
    const file = form.get("document");
    if (!(file instanceof File)) { throw new Error("Select a file to upload."); }
    const validated = await validateUpload(file.name, Buffer.from(await file.arrayBuffer()));
    await repositories().documents.create({ content: validated.content, documentType, filename: validated.filename, mimeType: validated.mimeType, sha256: validated.sha256, sizeBytes: validated.sizeBytes, userId: user.id });
    const label = documentType === "cv" ? "CV" : "job description";
    return redirectAfterPost(request, withStatus("/documents", `Uploaded “${validated.filename}” as your ${label}.`));
  } catch (error) {
    return redirectAfterPost(request, withStatus("/documents", publicError(error, "The document could not be stored."), "error"));
  }
}
