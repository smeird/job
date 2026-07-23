import { applicationInput, requireOwnedApplication, syncApplicationDocument } from "../../../lib/applications.js";
import { requestUser } from "../../../lib/auth.js";
import { assertCsrf } from "../../../lib/csrf.js";
import { publicError, redirectAfterPost, withStatus } from "../../../lib/http.js";
import { repositories } from "../../../lib/services.js";

/** Create a tracked application and its matching job-description document. */
export async function POST(request: Request): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const input = applicationInput(form);
    const repository = repositories();
    const applicationId = await repository.applications.create(user.id, input);
    const application = await requireOwnedApplication(repository.applications, applicationId, user.id);
    await syncApplicationDocument(repository.documents, application);
    return redirectAfterPost(request, withStatus("/applications", "Job application saved"));
  } catch (error) {
    return new Response(publicError(error), { headers: { "Content-Type": "text/plain; charset=utf-8" }, status: 422 });
  }
}
