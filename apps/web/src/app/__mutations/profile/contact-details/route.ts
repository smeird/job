import { contactDetailsSchema } from "@job/core";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { optionalFormString, publicError, redirectAfterPost, requiredFormString, withStatus } from "../../../../lib/http.js";
import { repositories } from "../../../../lib/services.js";

/** Validate and save only the authenticated user's reusable contact details. */
export async function POST(request: Request): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  const form = await request.formData();
  try {
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const details = contactDetailsSchema.parse({ address: requiredFormString(form, "address"), email: optionalFormString(form, "email"), phone: optionalFormString(form, "phone") });
    await repositories().contactDetails.saveForUser(user.id, { address: details.address, email: details.email ?? null, phone: details.phone ?? null });
    return redirectAfterPost(request, withStatus("/profile/contact-details", "Saved your contact details for cover letters."));
  } catch (error) {
    return redirectAfterPost(request, withStatus("/profile/contact-details", publicError(error), "error"));
  }
}
