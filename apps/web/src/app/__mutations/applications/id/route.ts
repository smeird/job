import { parsePublicId } from "@job/db";
import { applicationInput, requireOwnedApplication, syncApplicationDocument } from "../../../../lib/applications.js";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { publicError, redirectAfterPost, withStatus } from "../../../../lib/http.js";
import { repositories } from "../../../../lib/services.js";

/** Update one owned application while keeping its source document in sync. */
export async function POST(request: Request): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  const publicPath = request.headers.get("x-middleware-rewrite") ?? request.url;
  let applicationId: bigint;
  try {
    applicationId = parsePublicId(new URL(publicPath).searchParams.get("id") ?? new URL(request.url).searchParams.get("id") ?? "", "application id");
  } catch {
    return redirectAfterPost(request, withStatus("/applications", "The requested job application could not be found."));
  }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const repository = repositories();
    const current = await requireOwnedApplication(repository.applications, applicationId, user.id);
    const input = applicationInput(form, current.status, current.reasonCode);
    if (!(await repository.applications.updateOwned(applicationId, user.id, input))) {
      throw new Error("The requested job application could not be found.");
    }
    const updated = await requireOwnedApplication(repository.applications, applicationId, user.id);
    await syncApplicationDocument(repository.documents, updated);
    return redirectAfterPost(request, withStatus(`/applications/${applicationId.toString()}`, "Job application updated"));
  } catch (error) {
    return new Response(publicError(error), { headers: { "Content-Type": "text/plain; charset=utf-8" }, status: 422 });
  }
}
