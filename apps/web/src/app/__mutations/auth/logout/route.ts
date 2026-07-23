import { clientIp } from "@job/core";
import type { NextResponse } from "next/server";
import { clearSessionCookie, requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { redirectAfterPost, userAgent } from "../../../../lib/http.js";
import { authenticationService } from "../../../../lib/services.js";

/** Revoke the current server-side session and expire its cookie. */
export async function POST(request: Request): Promise<NextResponse> {
  const form = await request.formData();
  assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
  const token = request.headers.get("cookie")?.match(/(?:^|;\s*)job_session=([^;]+)/)?.[1];
  const user = await requestUser(request);
  if (token !== undefined) {
    await authenticationService().logout(decodeURIComponent(token), user, { ipAddress: clientIp(request.headers), userAgent: userAgent(request) });
  }
  const response = redirectAfterPost(request, "/");
  clearSessionCookie(response);
  return response;
}
