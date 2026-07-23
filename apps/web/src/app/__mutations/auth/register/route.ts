import { clientIp } from "@job/core";
import type { NextResponse } from "next/server";
import { setAuthChallengeCookie } from "../../../../lib/auth-challenge.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { publicError, redirectAfterPost, requiredFormString, userAgent, withStatus } from "../../../../lib/http.js";
import { authenticationService } from "../../../../lib/services.js";

/** Start registration and bind its QR setup to this browser for ten minutes. */
export async function POST(request: Request): Promise<NextResponse> {
  const form = await request.formData();
  const email = requiredFormString(form, "email").toLowerCase();
  try {
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    await authenticationService().initiateRegistration(email, { ipAddress: clientIp(request.headers), userAgent: userAgent(request) });
    const response = redirectAfterPost(request, `/auth/register/verify?email=${encodeURIComponent(email)}`);
    setAuthChallengeCookie(response, "register", email);
    return response;
  } catch (error) {
    return redirectAfterPost(request, withStatus(`/auth/register?email=${encodeURIComponent(email)}`, publicError(error), "error"));
  }
}
