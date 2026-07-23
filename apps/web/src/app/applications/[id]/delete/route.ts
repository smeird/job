import { parsePublicId } from "@job/db";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { redirectAfterPost, withStatus } from "../../../../lib/http.js";
import { repositories } from "../../../../lib/services.js";

/** Delete one owned application and its cached research. */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const applicationId = parsePublicId((await params).id, "application id");
    if (!(await repositories().applications.deleteOwned(applicationId, user.id))) {
      throw new Error("The requested job application could not be found.");
    }
    return redirectAfterPost(request, withStatus("/applications", "Job application deleted."));
  } catch (error) {
    return redirectAfterPost(request, withStatus("/applications", error instanceof Error ? error.message : "Unable to delete the application."));
  }
}
