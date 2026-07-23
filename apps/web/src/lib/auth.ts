import type { AuthenticatedUser, CreatedSession } from "@job/db";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import type { NextResponse } from "next/server";
import { appConfig, authenticationService } from "./services.js";

export const SESSION_COOKIE_NAME = "job_session";

/** Return the current user when a compatible session cookie is valid. */
export async function optionalUser(refresh = false): Promise<AuthenticatedUser | null> {
  const cookieStore = await cookies();
  return authenticationService().authenticate(cookieStore.get(SESSION_COOKIE_NAME)?.value, refresh);
}

/** Redirect unauthenticated page requests to the existing login URL. */
export async function requireUser(): Promise<AuthenticatedUser> {
  const user = await optionalUser(true);
  if (user === null) {
    redirect("/auth/login");
  }
  return user;
}

/** Resolve an authenticated API request without triggering an HTML redirect. */
export async function requestUser(request: Request): Promise<AuthenticatedUser | null> {
  const token = request.headers.get("cookie")?.match(/(?:^|;\s*)job_session=([^;]+)/)?.[1];
  return authenticationService().authenticate(token === undefined ? undefined : decodeURIComponent(token));
}

/** Attach a secure, legacy-compatible job_session cookie to a response. */
export function setSessionCookie(response: NextResponse, session: CreatedSession): void {
  const config = appConfig();
  response.cookies.set(SESSION_COOKIE_NAME, session.token, {
    ...(config.app.cookieDomain === undefined ? {} : { domain: config.app.cookieDomain }),
    expires: session.expiresAt,
    httpOnly: true,
    path: "/",
    sameSite: "lax",
    secure: config.app.url.protocol === "https:",
  });
}

/** Expire the public session cookie after the backing database row is removed. */
export function clearSessionCookie(response: NextResponse): void {
  const config = appConfig();
  response.cookies.set(SESSION_COOKIE_NAME, "", {
    ...(config.app.cookieDomain === undefined ? {} : { domain: config.app.cookieDomain }),
    expires: new Date(0),
    httpOnly: true,
    path: "/",
    sameSite: "lax",
    secure: config.app.url.protocol === "https:",
  });
}
