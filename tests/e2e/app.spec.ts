import { randomBytes } from "node:crypto";
import { test, expect } from "@playwright/test";
import * as OTPAuth from "otpauth";
import { AuthRepository, createDatabase, loadDatabaseConfig } from "@job/db";

test("serves the TypeScript health check and professional landing page", async ({ page, request }) => {
  const health = await request.get("/__ts/healthz");
  expect(health.status()).toBe(200);
  expect(await health.text()).toBe("ok");
  await page.goto("/");
  await expect(page.getByRole("heading", { name: /A stronger application/i })).toBeVisible();
  await expect(page.getByRole("link", { name: "Sign in" })).toBeVisible();
});

test.describe("authenticated migration journey", () => {
  test.skip(process.env.RUN_E2E_FULL !== "true", "Requires an explicitly configured disposable MySQL test database.");

  test("continues TOTP login, upload, tracking, tailoring, settings, analytics, and logout", async ({ page }) => {
    const config = loadDatabaseConfig();
    expect(config.database).toMatch(/test/i);
    const database = createDatabase(config);
    const auth = new AuthRepository(database);
    const email = `playwright-${Date.now()}@example.com`;
    const secret = new OTPAuth.Secret({ size: 20 }).base32;
    const userId = await auth.createUser(email);
    await auth.updateTotpConfiguration(userId, secret, 30, 6);

    try {
      await page.goto("/auth/login");
      await page.getByLabel("Email address").fill(email);
      await page.getByRole("button", { name: "Continue with authenticator" }).click();
      await expect(page).toHaveURL(/\/auth\/login\/verify/);
      const code = new OTPAuth.TOTP({ algorithm: "SHA1", digits: 6, issuer: "job.smeird.com", label: email, period: 30, secret: OTPAuth.Secret.fromBase32(secret) }).generate();
      await page.getByLabel("Six-digit code").fill(code);
      await page.getByRole("button", { name: "Sign in", exact: true }).click();
      await expect(page.getByText("Welcome back")).toBeVisible();

      await page.goto("/documents");
      await page.getByLabel("File").setInputFiles({ buffer: Buffer.from("# Alex Example\n\nSenior Engineer at Acme Systems."), mimeType: "text/markdown", name: `master-${randomBytes(4).toString("hex")}.md` });
      await page.getByLabel("Document type").selectOption("cv");
      await page.getByRole("button", { name: "Upload" }).click();
      await expect(page.getByText(/Uploaded/)).toBeVisible();

      await page.goto("/applications/create");
      await page.getByLabel("Role title").fill("Senior Platform Engineer");
      await page.getByLabel("Job description").fill("Build reliable platforms, improve incident response, and lead technical delivery.");
      await page.getByRole("button", { name: "Save posting" }).click();
      await expect(page.getByText("Senior Platform Engineer")).toBeVisible();
      await page.getByRole("link", { name: "Senior Platform Engineer" }).click();
      await page.getByLabel("Master CV").selectOption({ index: 1 });
      await page.getByRole("button", { name: "Queue tailored documents" }).click();
      await expect(page.getByText(/Tailored CV job queued/)).toBeVisible();

      await page.goto("/settings/models");
      await expect(page.getByRole("heading", { name: "OpenAI models" })).toBeVisible();
      await expect(page.getByLabel("Analysis model")).toBeVisible();
      await page.goto("/usage");
      await expect(page.getByText("This month")).toBeVisible();
      await page.getByRole("button", { name: "Sign out" }).click();
      await expect(page.getByRole("link", { name: "Sign in" })).toBeVisible();
    } finally {
      const rows = await database.selectFrom("jobs").select(["id", "payload_json"]).where("type", "=", "tailor_cv").execute();
      const ownedJobIds = rows.flatMap((row) => JSON.stringify(row.payload_json).includes(userId.toString()) ? [row.id] : []);
      if (ownedJobIds.length > 0) { await database.deleteFrom("jobs").where("id", "in", ownedJobIds).execute(); }
      await database.deleteFrom("users").where("id", "=", userId).execute();
      await database.destroy();
    }
  });
});
