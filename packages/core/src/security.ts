export const securityHeaders = {
  "Content-Security-Policy": [
    "default-src 'self'",
    "base-uri 'self'",
    "connect-src 'self'",
    "font-src 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
    "img-src 'self' data:",
    "object-src 'none'",
    "script-src 'self'",
    "style-src 'self' 'unsafe-inline'",
  ].join("; "),
  "Cross-Origin-Opener-Policy": "same-origin",
  "Cross-Origin-Resource-Policy": "same-origin",
  "Permissions-Policy": "camera=(), geolocation=(), microphone=()",
  "Referrer-Policy": "strict-origin-when-cross-origin",
  "Strict-Transport-Security": "max-age=31536000; includeSubDomains",
  "X-Content-Type-Options": "nosniff",
  "X-Frame-Options": "DENY",
} as const;

/** Reject non-HTTP schemes, credentials, and local-network hosts before server-side fetching. */
export function validateRemoteHttpUrl(value: string): URL {
  const url = new URL(value);
  if (!["http:", "https:"].includes(url.protocol) || url.username !== "" || url.password !== "") {
    throw new Error("Only credential-free HTTP and HTTPS URLs are supported.");
  }

  const hostname = url.hostname.toLowerCase().replace(/^\[|\]$/g, "");
  const blockedNames = ["localhost", "localhost.localdomain", "metadata.google.internal"];
  const blockedIpv4 = /^(?:127\.|10\.|0\.|169\.254\.|192\.168\.|172\.(?:1[6-9]|2\d|3[01])\.)/;
  const blockedIpv6 = /^(?:::1|fe80:|fc|fd)/i;
  if (blockedNames.includes(hostname) || blockedIpv4.test(hostname) || blockedIpv6.test(hostname)) {
    throw new Error("Local and private network URLs are not allowed.");
  }

  return url;
}

/** Extract a bounded client IP value from Apache proxy headers. */
export function clientIp(headers: Headers): string {
  const forwarded = headers.get("x-forwarded-for")?.split(",")[0]?.trim();
  const candidate = forwarded || headers.get("x-real-ip") || "127.0.0.1";
  return candidate.slice(0, 45);
}
