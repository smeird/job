import { createCsrfToken, securityHeaders, verifyCsrfToken } from "@job/core";
import { createHash } from "node:crypto";
import { type NextRequest, NextResponse } from "next/server";

const developmentSecret = "development-only-job-csrf-secret-change-me";

/** Resolve the CSRF signing key without requiring database or provider configuration in Proxy. */
function csrfSecret(): string {
  const production = process.env.APP_ENV === "production" || (process.env.APP_ENV === undefined && process.env.NODE_ENV === "production");
  const configuredSecret = process.env.APP_KEY || (production ? "" : developmentSecret);
  const secret = !production && configuredSecret.length > 0 && configuredSecret.length < 32
    ? createHash("sha256").update(`job-development:${configuredSecret}`, "utf8").digest("hex")
    : configuredSecret;
  if (secret.length < 32) {
    throw new Error("APP_KEY must contain at least 32 characters.");
  }
  return secret;
}

/** Rewrite traditional POST forms away from collocated App Router pages while preserving their public URLs. */
function mutationPath(pathname: string): string | null {
  const exact: Record<string, string> = {
    "/applications": "/__mutations/applications",
    "/auth/backup-code": "/__mutations/auth/backup-code",
    "/auth/backup-codes": "/__mutations/auth/backup-codes",
    "/auth/login": "/__mutations/auth/login",
    "/auth/login/verify": "/__mutations/auth/login/verify",
    "/auth/logout": "/__mutations/auth/logout",
    "/auth/register": "/__mutations/auth/register",
    "/auth/register/verify": "/__mutations/auth/register/verify",
    "/profile/contact-details": "/__mutations/profile/contact-details",
    "/retention": "/__mutations/retention",
    "/settings/models": "/__mutations/settings/models",
  };
  if (exact[pathname] !== undefined) {
    return exact[pathname];
  }
  const application = pathname.match(/^\/applications\/([1-9]\d*)$/);
  return application === null ? null : `/__mutations/applications/id?id=${application[1] ?? ""}`;
}

/** Attach signed CSRF state, security headers, and compatibility rewrites to every application request. */
export function proxy(request: NextRequest): NextResponse {
  const secret = csrfSecret();
  const existing = request.cookies.get("job_csrf")?.value;
  const token = existing !== undefined && verifyCsrfToken(existing, existing, secret) ? existing : createCsrfToken(secret);
  const requestHeaders = new Headers(request.headers);
  if (existing !== token) {
    const cookieHeader = requestHeaders.get("cookie");
    requestHeaders.set("cookie", `${cookieHeader === null || cookieHeader === "" ? "" : `${cookieHeader}; `}job_csrf=${token}`);
  }

  const internalPath = request.method === "POST" ? mutationPath(request.nextUrl.pathname) : null;
  const response = internalPath === null
    ? NextResponse.next({ request: { headers: requestHeaders } })
    : NextResponse.rewrite(new URL(internalPath, request.url), { request: { headers: requestHeaders } });
  if (existing !== token) {
    response.cookies.set("job_csrf", token, {
      httpOnly: true,
      maxAge: 8 * 60 * 60,
      path: "/",
      sameSite: "lax",
      secure: request.nextUrl.protocol === "https:",
    });
  }
  for (const [name, value] of Object.entries(securityHeaders)) {
    response.headers.set(name, value);
  }
  return response;
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
};
