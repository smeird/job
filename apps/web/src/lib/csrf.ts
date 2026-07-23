import { verifyCsrfToken } from "@job/core";
import { cookies } from "next/headers";
import { appConfig } from "./services.js";

export const CSRF_COOKIE_NAME = "job_csrf";

/** Read the signed token injected by Proxy for inclusion in traditional forms. */
export async function csrfToken(): Promise<string> {
  return (await cookies()).get(CSRF_COOKIE_NAME)?.value ?? "";
}

/** Validate a form or JSON request against the signed double-submit cookie. */
export function assertCsrf(request: Request, submittedToken: string | undefined): void {
  const cookieToken = request.headers.get("cookie")?.match(/(?:^|;\s*)job_csrf=([^;]+)/)?.[1];
  const decodedCookie = cookieToken === undefined ? undefined : decodeURIComponent(cookieToken);
  const headerToken = request.headers.get("x-csrf-token") ?? undefined;
  if (!verifyCsrfToken(decodedCookie, submittedToken ?? headerToken, appConfig().app.csrfSecret)) {
    throw new CsrfError();
  }
}

export class CsrfError extends Error {
  public constructor() {
    super("The form expired. Refresh the page and try again.");
    this.name = "CsrfError";
  }
}
