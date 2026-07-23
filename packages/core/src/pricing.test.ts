import { describe, expect, it } from "vitest";
import { calculateUsageCost, parseTariffs } from "./pricing.js";

describe("usage pricing", () => {
  it("retains precise fractional pence and rounds only the database field", () => {
    const tariffs = parseTariffs('{"gpt-test":{"prompt":0.2,"completion":0.8}}');
    expect(calculateUsageCost("gpt-test", 1_250, 500, tariffs)).toEqual({ available: true, precisePence: 0.65, roundedPence: 1n });
  });

  it("marks unknown models unpriced rather than free", () => {
    expect(calculateUsageCost("gpt-future", 10, 10, {})).toEqual({ available: false, precisePence: null, roundedPence: 0n });
  });
});
