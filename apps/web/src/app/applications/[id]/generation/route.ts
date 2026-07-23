import { parsePublicId } from "@job/db";
import { requireOwnedApplication } from "../../../../lib/applications.js";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { redirectAfterPost, withStatus } from "../../../../lib/http.js";
import { repositories } from "../../../../lib/services.js";

/** Link or unlink an owned generation from an owned application. */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const applicationId = parsePublicId((await params).id, "application id");
    const value = typeof form.get("generation_id") === "string" ? String(form.get("generation_id")).trim() : "";
    const generationId = value === "" ? null : parsePublicId(value, "generation id");
    const repository = repositories();
    await requireOwnedApplication(repository.applications, applicationId, user.id);
    if (!(await repository.applications.updateGenerationOwned(applicationId, user.id, generationId))) {
      throw new Error("Select a tailored CV that belongs to your workspace.");
    }
    return redirectAfterPost(request, withStatus("/applications", generationId === null ? "Cleared tailored CV link." : "Linked tailored CV to application."));
  } catch (error) {
    return redirectAfterPost(request, withStatus("/applications", error instanceof Error ? error.message : "Unable to update the tailored CV link."));
  }
}
