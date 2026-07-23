import { parsePublicId } from "@job/db";
import { validateUpload } from "@job/documents";
import { requestUser } from "../../../../../lib/auth.js";
import { assertCsrf } from "../../../../../lib/csrf.js";
import { publicError, redirectAfterPost, withStatus } from "../../../../../lib/http.js";
import { repositories } from "../../../../../lib/services.js";

/** Save a completed tailored CV Markdown output into the authenticated user's master-CV library. */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  const form = await request.formData();
  assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
  try {
    const id = parsePublicId((await params).id);
    const repo = repositories();
    const generation = await repo.generations.findOwned(id, user.id);
    if (generation === null || generation.status !== "completed") { throw new Error("Only completed tailored CVs can be saved."); }
    const output = await repo.generations.findOutputOwned(id, user.id, "cv", "text/markdown");
    if (output?.outputText === null || output === null) { throw new Error("The tailored CV output is not available."); }
    const jobName = (generation.jobFilename ?? "tailored-cv").replace(/\.[^.]+$/, "").replace(/[^A-Za-z0-9 _-]+/g, "").trim() || "tailored-cv";
    const filename = `${jobName} - tailored-${generation.createdAt.toISOString().replace(/[-:]/g, "").slice(0, 13)}.md`;
    const validated = await validateUpload(filename, Buffer.from(output.outputText, "utf8"));
    await repo.documents.create({ content: validated.content, documentType: "cv", filename: validated.filename, mimeType: validated.mimeType, sha256: validated.sha256, sizeBytes: validated.sizeBytes, userId: user.id });
    return redirectAfterPost(request, withStatus("/documents", `Saved tailored CV as “${filename}”.`));
  } catch (error) {
    return redirectAfterPost(request, withStatus("/documents", publicError(error, "The tailored CV could not be saved."), "error"));
  }
}
