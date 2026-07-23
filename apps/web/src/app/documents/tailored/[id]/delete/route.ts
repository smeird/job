import { parsePublicId } from "@job/db";
import { requestUser } from "../../../../../lib/auth.js";
import { assertCsrf } from "../../../../../lib/csrf.js";
import { redirectAfterPost, withStatus } from "../../../../../lib/http.js";
import { repositories } from "../../../../../lib/services.js";

/** Delete one owned tailoring run and its cascading outputs. */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  const form = await request.formData();
  assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
  let deleted = false;
  try { deleted = await repositories().generations.deleteOwned(parsePublicId((await params).id), user.id); } catch { deleted = false; }
  return redirectAfterPost(request, withStatus("/documents", deleted ? "Tailored CV deleted successfully." : "The tailored CV could not be found."));
}
