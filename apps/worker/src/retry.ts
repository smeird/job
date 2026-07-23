/** Return an exponential retry delay capped at five minutes. */
export function retryDelaySeconds(attempt: number): number {
  return Math.min(300, 5 * (2 ** Math.max(0, attempt - 1)));
}

/** Identify network, rate-limit, and upstream server failures that may succeed on retry. */
export function isTransientJobError(error: unknown): boolean {
  if (typeof error !== "object" || error === null) {
    return false;
  }
  const candidate = error as { code?: string; status?: number };
  return candidate.status === 408
    || candidate.status === 409
    || candidate.status === 429
    || (candidate.status !== undefined && candidate.status >= 500)
    || ["ECONNRESET", "ECONNREFUSED", "ETIMEDOUT", "ENOTFOUND"].includes(candidate.code ?? "");
}
