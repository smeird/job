import { createHmac, timingSafeEqual } from "node:crypto";
import { cookies } from "next/headers";
import type { NextResponse } from "next/server";
import { appConfig } from "./services.js";

const COOKIE_NAME = "job_auth_challenge";

/** Sign an action/email pair so a verification page cannot enumerate another pending secret. */
function signedValue(action: "login" | "register", email: string): string {
  const encodedEmail = Buffer.from(email, "utf8").toString("base64url");
  const payload = `${action}.${encodedEmail}`;
  const signature = createHmac("sha256", appConfig().app.csrfSecret).update(payload).digest("base64url");
  return `${payload}.${signature}`;
}

/** Attach the short-lived authentication challenge context after a successful request form. */
export function setAuthChallengeCookie(response: NextResponse, action: "login" | "register", email: string): void {
  response.cookies.set(COOKIE_NAME, signedValue(action, email), {
    httpOnly: true,
    maxAge: 10 * 60,
    path: "/auth",
    sameSite: "lax",
    secure: appConfig().app.url.protocol === "https:",
  });
}

/** Validate that the current browser initiated the pending challenge it is attempting to view. */
export async function hasAuthChallenge(action: "login" | "register", email: string): Promise<boolean> {
  const actual = (await cookies()).get(COOKIE_NAME)?.value;
  if (actual === undefined) {
    return false;
  }
  const expected = signedValue(action, email);
  const actualBuffer = Buffer.from(actual);
  const expectedBuffer = Buffer.from(expected);
  return actualBuffer.length === expectedBuffer.length && timingSafeEqual(actualBuffer, expectedBuffer);
}

/** Expire authentication challenge context after successful verification. */
export function clearAuthChallengeCookie(response: NextResponse): void {
  response.cookies.set(COOKIE_NAME, "", { expires: new Date(0), httpOnly: true, path: "/auth", sameSite: "lax" });
}
