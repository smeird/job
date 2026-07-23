import { retentionSettingsSchema } from "@job/core";
import { requestUser } from "../../../lib/auth.js";
import { assertCsrf } from "../../../lib/csrf.js";
import { publicError, redirectAfterPost, requiredFormString, withStatus } from "../../../lib/http.js";
import { repositories } from "../../../lib/services.js";

/** Validate and persist the singleton retention policy. */
export async function POST(request: Request): Promise<Response> {
  if (await requestUser(request) === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  const form = await request.formData();
  try {
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const policy = retentionSettingsSchema.parse({
      applyTo: form.getAll("apply_to").filter((value): value is string => typeof value === "string"),
      purgeAfterDays: requiredFormString(form, "purge_after_days"),
    });
    await repositories().settings.setRetentionSettings(policy);
    return redirectAfterPost(request, withStatus("/retention", "Retention policy saved."));
  } catch (error) {
    return redirectAfterPost(request, withStatus("/retention", publicError(error), "error"));
  }
}
