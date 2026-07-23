import { createHmac, randomBytes, timingSafeEqual } from "node:crypto";

/** Sign the random portion of a CSRF token with the application secret. */
function signCsrfNonce(nonce: string, secret: string): string {
  return createHmac("sha256", secret).update(nonce, "utf8").digest("base64url");
}

/** Create a signed double-submit token suitable for the job_csrf cookie and matching form field. */
export function createCsrfToken(secret: string): string {
  const nonce = randomBytes(32).toString("base64url");
  return `${nonce}.${signCsrfNonce(nonce, secret)}`;
}

/** Validate the signed cookie and require an exact constant-time match with the submitted token. */
export function verifyCsrfToken(cookieToken: string | undefined, submittedToken: string | undefined, secret: string): boolean {
  if (cookieToken === undefined || submittedToken === undefined) {
    return false;
  }

  const separator = cookieToken.lastIndexOf(".");
  if (separator <= 0) {
    return false;
  }

  const nonce = cookieToken.slice(0, separator);
  const signature = cookieToken.slice(separator + 1);
  const expectedSignature = signCsrfNonce(nonce, secret);
  const cookieBuffer = Buffer.from(cookieToken, "utf8");
  const submittedBuffer = Buffer.from(submittedToken, "utf8");
  const signatureBuffer = Buffer.from(signature, "utf8");
  const expectedBuffer = Buffer.from(expectedSignature, "utf8");

  return cookieBuffer.length === submittedBuffer.length
    && signatureBuffer.length === expectedBuffer.length
    && timingSafeEqual(cookieBuffer, submittedBuffer)
    && timingSafeEqual(signatureBuffer, expectedBuffer);
}
