import { NextResponse } from "next/server";

/** Read a required form value as a trimmed string. */
export function requiredFormString(form: FormData, name: string): string {
  const value = form.get(name);
  if (typeof value !== "string" || value.trim() === "") {
    throw new Error(`${name.replace(/_/g, " ")} is required.`);
  }
  return value.trim();
}

/** Read an optional form value and normalize blanks to null. */
export function optionalFormString(form: FormData, name: string): string | null {
  const value = form.get(name);
  return typeof value !== "string" || value.trim() === "" ? null : value.trim();
}

/** Return the legacy-compatible 302 redirect used by existing PHP form handlers. */
export function redirectAfterPost(request: Request, pathname: string): NextResponse {
  return NextResponse.redirect(new URL(pathname, request.url), 302);
}

/** Add a safely encoded status message to a relative redirect URL. */
export function withStatus(pathname: string, message: string, kind: "status" | "error" = "status"): string {
  const separator = pathname.includes("?") ? "&" : "?";
  return `${pathname}${separator}${kind}=${encodeURIComponent(message)}`;
}

/** Return a bounded public error message without exposing stack traces or credentials. */
export function publicError(error: unknown, fallback = "The request could not be completed."): string {
  return error instanceof Error && error.message.trim() !== "" ? error.message.slice(0, 500) : fallback;
}

/** Return the request user agent in the database's existing maximum width. */
export function userAgent(request: Request): string | undefined {
  const value = request.headers.get("user-agent")?.slice(0, 255);
  return value === undefined || value === "" ? undefined : value;
}
