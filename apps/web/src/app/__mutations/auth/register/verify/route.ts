import { clientIp } from "@job/core";
import type { NextResponse } from "next/server";
import { clearAuthChallengeCookie } from "../../../../../lib/auth-challenge.js";
import { setSessionCookie } from "../../../../../lib/auth.js";
import { assertCsrf } from "../../../../../lib/csrf.js";
import { publicError, redirectAfterPost, requiredFormString, userAgent, withStatus } from "../../../../../lib/http.js";
import { authenticationService } from "../../../../../lib/services.js";

/** Verify registration, preserve TOTP configuration, and issue the compatible session cookie. */
export async function POST(request: Request): Promise<NextResponse> {
  const form = await request.formData();
  const email = requiredFormString(form, "email").toLowerCase();
  try {
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const session = await authenticationService().verifyRegistration(email, requiredFormString(form, "code"), { ipAddress: clientIp(request.headers), userAgent: userAgent(request) });
    const response = redirectAfterPost(request, "/auth/backup-codes");
    setSessionCookie(response, session);
    clearAuthChallengeCookie(response);
    return response;
  } catch (error) {
    return redirectAfterPost(request, withStatus(`/auth/register/verify?email=${encodeURIComponent(email)}`, publicError(error), "error"));
  }
}
