import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/e2e",
  fullyParallel: false,
  retries: process.env.CI === "true" ? 2 : 0,
  reporter: [["html", { open: "never" }], ["list"]],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3000",
    screenshot: "only-on-failure",
    trace: "retain-on-failure",
  },
  projects: [
    { name: "chromium", use: { ...devices["Desktop Chrome"] } },
  ],
  webServer: process.env.PLAYWRIGHT_EXTERNAL_SERVER === "true"
    ? undefined
    : {
        command: "npm run dev:web",
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        url: "http://127.0.0.1:3000/__ts/healthz",
      },
});
