import { applicationStatusSchema } from "@job/core";
import { parsePublicId } from "@job/db";
import { failureReasons, requireOwnedApplication } from "../../../../lib/applications.js";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { redirectAfterPost, withStatus } from "../../../../lib/http.js";
import { repositories } from "../../../../lib/services.js";

/** Move one owned application through the existing pipeline states. */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const applicationId = parsePublicId((await params).id, "application id");
    const statusValue = typeof form.get("status") === "string" ? String(form.get("status")) : "applied";
    const parsedStatus = applicationStatusSchema.safeParse(statusValue);
    const status = parsedStatus.success ? parsedStatus.data : "outstanding";
    const reasonValue = typeof form.get("reason_code") === "string" ? String(form.get("reason_code")) : "";
    const reasonCode = status === "failed" && reasonValue in failureReasons ? reasonValue : null;
    if (status === "failed" && reasonCode === null) { throw new Error("Select a valid rejection reason before marking the application as failed."); }
    const repository = repositories();
    await requireOwnedApplication(repository.applications, applicationId, user.id);
    await repository.applications.updateStatusOwned(applicationId, user.id, status, reasonCode);
    const labels: Record<string, string> = { applied: "submitted", contracting: "contracting", failed: "failed", interviewing: "interviewing", outstanding: "outstanding" };
    return redirectAfterPost(request, withStatus("/applications", `Marked application as ${labels[status] ?? status}.`));
  } catch (error) {
    const message = error instanceof Error ? error.message : "The application could not be updated.";
    return redirectAfterPost(request, withStatus("/applications", message));
  }
}
