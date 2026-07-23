import { describe, expect, it } from "vitest";
import { loadAppConfig } from "./config.js";

describe("shared runtime configuration", () => {
  it("maps the legacy PHP local environment to the TypeScript development runtime", () => {
    const config = loadAppConfig({ APP_ENV: "local", APP_URL: "http://127.0.0.1:3000" });
    expect(config.app.environment).toBe("development");
    expect(config.app.csrfSecret.length).toBeGreaterThanOrEqual(32);
  });

  it("requires an explicit signing key in production", () => {
    expect(() => loadAppConfig({ APP_ENV: "production", APP_KEY: "", APP_URL: "https://job.smeird.com" })).toThrow(/APP_KEY/);
  });
});
