import { describe, expect, it } from "vitest";
import { ensureNoUnknownOrganisations } from "./draft-validator.js";

describe("draft factual guardrail", () => {
  it("accepts organisations present in the master CV and rejects invented ones", () => {
    const source = "# Alex Example\n\nSenior Engineer at Acme Systems Ltd.";
    expect(() => ensureNoUnknownOrganisations(source, "## Experience\n\nAcme Systems Ltd — Senior Engineer")).not.toThrow();
    expect(() => ensureNoUnknownOrganisations(source, "## Experience\n\nFabricated Holdings plc — Director")).toThrow(/Fabricated Holdings/i);
  });
});
