import { describe, expect, it } from "vitest";
import { isTransientJobError, retryDelaySeconds } from "./retry.js";

describe("worker retry policy", () => {
  it("backs off exponentially with a five-minute cap", () => {
    expect([1, 2, 3, 20].map(retryDelaySeconds)).toEqual([5, 10, 20, 300]);
  });

  it("retries network, throttling, and upstream failures only", () => {
    expect(isTransientJobError({ status: 429 })).toBe(true);
    expect(isTransientJobError({ status: 503 })).toBe(true);
    expect(isTransientJobError({ code: "ETIMEDOUT" })).toBe(true);
    expect(isTransientJobError({ status: 422 })).toBe(false);
  });
});
