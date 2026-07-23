import { clientIp } from "@job/core";
import { NextResponse } from "next/server";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { userAgent } from "../../../../lib/http.js";
import { authenticationService } from "../../../../lib/services.js";

/** Escape generated values before placing them in the one-time HTML response. */
function escapeHtml(value: string): string {
  return value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

/** Rotate recovery codes and reveal them once to the authenticated user. */
export async function POST(request: Request): Promise<NextResponse> {
  const form = await request.formData();
  assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
  const user = await requestUser(request);
  if (user === null) {
    return NextResponse.redirect(new URL("/auth/login", request.url), 303);
  }
  const codes = await authenticationService().generateBackupCodes(user.id, { ipAddress: clientIp(request.headers), userAgent: userAgent(request) });
  const items = codes.map((code) => `<li><code>${escapeHtml(code)}</code></li>`).join("");
  return new NextResponse(`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Backup codes · Job Tune</title></head><body><main><h1>Backup codes generated</h1><p>Save these codes now. They will not be shown again.</p><ol>${items}</ol><p><a href="/">Return to Job Tune</a></p></main></body></html>`, { headers: { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-store" } });
}
