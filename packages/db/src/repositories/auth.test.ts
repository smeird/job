import { describe, expect, it } from "vitest";
import { hashSessionToken } from "./auth.js";

describe("PHP-compatible session hashing", () => {
  it("returns the raw SHA-256 bytes expected by the existing sessions table", () => {
    expect(hashSessionToken("legacy-session-token")).toBeInstanceOf(Buffer);
    expect(hashSessionToken("legacy-session-token").toString("hex")).toBe("2643f7be36cdfb283688b1a18c0b923661046fee4f997c75b8003ddec10d8c97");
  });
});
