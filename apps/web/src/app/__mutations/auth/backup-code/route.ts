import { clientIp } from "@job/core";
import type { NextResponse } from "next/server";
import { setSessionCookie } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { publicError, redirectAfterPost, requiredFormString, userAgent, withStatus } from "../../../../lib/http.js";
import { authenticationService } from "../../../../lib/services.js";

/** Consume a recovery code and issue the same database-backed session as TOTP login. */
export async function POST(request: Request): Promise<NextResponse> {
  const form = await request.formData();
  const email = requiredFormString(form, "email").toLowerCase();
  try {
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const session = await authenticationService().verifyBackupCode(email, requiredFormString(form, "code"), { ipAddress: clientIp(request.headers), userAgent: userAgent(request) });
    const response = redirectAfterPost(request, "/");
    setSessionCookie(response, session);
    return response;
  } catch (error) {
    return redirectAfterPost(request, withStatus(`/auth/backup-code?email=${encodeURIComponent(email)}`, publicError(error), "error"));
  }
}
