import { describe, expect, it } from "vitest";
import { createCsrfToken, verifyCsrfToken } from "./csrf.js";

describe("signed double-submit CSRF tokens", () => {
  const secret = "a-production-length-secret-that-is-only-for-tests";

  it("accepts only a matching cookie/form token with a valid signature", () => {
    const token = createCsrfToken(secret);
    expect(verifyCsrfToken(token, token, secret)).toBe(true);
    expect(verifyCsrfToken(token, `${token}x`, secret)).toBe(false);
    expect(verifyCsrfToken(token.replace(/.$/, "x"), token.replace(/.$/, "x"), secret)).toBe(false);
  });
});
